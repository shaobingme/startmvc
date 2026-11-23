<?php
/**
 * Db - 实用的查询构建器和PDO类
 *
 * @class    Cache
 */

namespace startmvc\core\db;

/**
 * 数据库查询缓存（文件实现）
 *
 * 设计要点（2026-11 重构）：
 *  1. 缓存条目原子写：先写 `.tmp` 再 rename，读者永远看不到半截 JSON；不用 fopen/fclose，句柄不泄漏。
 *  2. 容错：坏文件 / 空文件 / 缺字段一律按「未命中」处理并顺手删除，不刷 PHP 告警。
 *  3. 代际号（epoch）：写库时递增一个极小文件的内容，缓存内容里带上它，
 *     读取时不一致即视为未命中，旧内容由下次写入同名覆盖。
 *     内容用「时间戳-随机串」而不是自增计数器，避免并发自增丢更新。
 *     请求级备忘录带「mtime 距现在 > 1 秒」的前置条件，规避文件系统 mtime 只有秒级精度
 *     导致「同一秒内 bump 后仍读到旧代际号」的坑。
 *  4. 键归一化：所有目录键统一 rtrim 掉尾部分隔符，正斜杠/反斜杠两种写法指向同一个代际文件。
 *  5. GC：过期条目、旧命名格式残留、崩溃残留的 .tmp 会被回收
 *     （低概率触发 + 每次扫描有预算上限，成本有界）。
 *
 * 目录里会出现三类文件：
 *   db.epoch        当前代际号（内容即代际标识）
 *   {md5}.cache     缓存条目（代际号与过期时间都在内容里，文件名只由键决定）
 *   *.tmp           写入中间态
 */
class DbCache
{
    /** 代际号文件名 */
    const EPOCH_FILE = 'db.epoch';

    /** 缓存条目后缀 */
    const CACHE_SUFFIX = '.cache';

    /** 临时文件后缀（GC 会回收陈旧的那些） */
    const TMP_SUFFIX = '.tmp';

    /** 代际号格式：时间戳-8位十六进制，供文件名解析/GC 复用 */
    const EPOCH_PATTERN = '\d+-[0-9a-f]{8}';

    /** 临时文件视为「崩溃残留」的年龄（秒） */
    const TMP_TTL = 300;

    /**
     * 缓存目录
     *
     * @var string|null
     */
    protected $cacheDir = null;

    /**
     * 缓存时间（秒）
     *
     * @var int|null
     */
    protected $cache = null;

    /**
     * 缓存过期时间戳
     *
     * @var int|null
     */
    protected $finish = null;

    /**
     * 本次读取时看到的代际号（一个实例只服务一条 SQL）
     *
     * setCache() 用它判断「读和写之间是否发生过写库」：代际已变说明查到的结果
     * 可能早于那次写入，宁可放弃缓存也不写脏数据（否则脏值会留满一整个 TTL）。
     *
     * @var string|null
     */
    protected $epochAtRead = null;

    /**
     * 是否输出诊断日志（json_encode 失败等）
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * 请求级代际号备忘录：归一化目录 => ['mtime' => int, 'value' => string]
     *
     * @var array
     */
    protected static $epochs = [];

    /**
     * 本进程已做过 GC 的目录（避免一个请求里反复扫目录）
     *
     * @var array
     */
    protected static $gcDone = [];

    /**
     * 缓存构造函数
     *
     * @param string|null $dir   缓存目录
     * @param int         $time  缓存时间（秒）
     * @param bool        $debug 是否输出诊断日志
     */
    public function __construct($dir = null, $time = 0, $debug = false)
    {
        if ($dir !== null && $dir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->cacheDir = $dir;
        $this->cache = $time;
        $this->finish = time() + (int)$time;
        $this->debug = (bool)$debug;
    }

    /**
     * 取缓存数据（兼容旧接口）
     *
     * 未命中 / 已过期 / 文件损坏统一返回 false。
     * 需要区分「未命中」与「缓存的值就是 false / null」时请用 fetch()。
     *
     * @param string $sql   缓存键（SQL + 绑定参数）
     * @param bool   $array 是否返回数组（false 返回对象）
     *
     * @return mixed|false
     */
    public function getCache($sql, $array = false)
    {
        $hit = $this->fetch($sql, $array);

        return $hit['hit'] ? $hit['data'] : false;
    }

    /**
     * 取缓存数据，并明确告知是否命中
     *
     * 返回 ['hit' => bool, 'data' => mixed]。用 hit 标记而不是「null/false 代表未命中」，
     * 才能把查询结果本身就是 false/null 的情况也缓存住。
     *
     * @param string $sql   缓存键（SQL + 绑定参数）
     * @param bool   $array 是否返回数组（false 返回对象）
     *
     * @return array{hit:bool,data:mixed}
     */
    public function fetch($sql, $array = true)
    {
        $miss = ['hit' => false, 'data' => null];

        if (!$this->enabled()) {
            return $miss;
        }

        // 文件名不含代际号（文件数才有上限），代际校验靠内容，所以先取当前代际号
        $epoch = $this->epoch();
        $this->epochAtRead = $epoch;

        $file = $this->cacheFile($sql);

        if (!is_file($file)) {
            $this->maybeCollectGarbage();
            return $miss;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            // 空文件只可能来自旧实现的截断写或手工破坏，直接清掉
            @unlink($file);
            return $miss;
        }

        $decoded = json_decode($raw, $array ? true : false);
        if (!$this->isValidPayload($decoded, $array)) {
            @unlink($file);
            return $miss;
        }

        // 代际不符 = 已被写库作废。这里不删文件：同名位置下次 setCache() 直接覆盖，
        // 删了反而要多付一次 unlink（Windows 上 unlink 比覆盖写贵一个数量级）。
        $fileEpoch = $array
            ? (isset($decoded['epoch']) ? (string)$decoded['epoch'] : '')
            : (isset($decoded->epoch) ? (string)$decoded->epoch : '');
        if ($fileEpoch !== $epoch) {
            // 失效刚发生，正是扫一遍过期条目的时机（内部仍是 2% 概率 + 每请求一次）
            $this->maybeCollectGarbage();
            return $miss;
        }

        $finish = $array ? (int)$decoded['finish'] : (int)$decoded->finish;
        if ($finish < time()) {
            @unlink($file);
            return $miss;
        }

        return [
            'hit'  => true,
            'data' => $array ? $decoded['data'] : $decoded->data,
        ];
    }

    /**
     * 写入缓存
     *
     * 原子替换：先写同目录 .tmp，再 rename 覆盖。任何一步失败都返回 false 且不留半成品。
     *
     * @param string $sql   缓存键（SQL + 绑定参数）
     * @param mixed  $result 查询结果
     *
     * @return bool 是否写入成功
     */
    public function setCache($sql, $result)
    {
        if (!$this->enabled()) {
            return false;
        }

        $epoch = $this->epoch();

        // 读和写之间发生过写库：查到的结果可能早于那次写入，写进去会脏一整个 TTL。
        // 一个 DbCache 实例只服务一条 SQL，所以 epochAtRead 不会串到别的查询上。
        if ($this->epochAtRead !== null && $this->epochAtRead !== $epoch) {
            return false;
        }

        $payload = json_encode([
            'data'   => $result,
            'finish' => (int)$this->finish,
            'epoch'  => $epoch,
        ]);
        if ($payload === false) {
            // 非法 UTF-8 / INF / 资源句柄等：宁可不缓存，也不写 0 字节坏文件
            if ($this->debug) {
                error_log('[startmvc][DbCache] json_encode 失败，跳过缓存: ' . json_last_error_msg());
            }
            return false;
        }

        $dir = $this->epochKey();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $file = $this->cacheFile($sql);
        $tmp = $file . '.' . getmypid() . '.' . substr(md5(uniqid('', true)), 0, 8) . self::TMP_SUFFIX;

        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }

        if (!@rename($tmp, $file)) {
            // Windows 上目标文件被读者占用时 rename 会失败，退化为「先删后改名」
            @unlink($file);
            if (!@rename($tmp, $file)) {
                @unlink($tmp);
                return false;
            }
        }

        return true;
    }

    /**
     * 当前代际号（无代际文件时为 '0'）
     *
     * @return string
     */
    public function epoch()
    {
        return self::readEpoch($this->cacheDir);
    }

    /**
     * 递增代际号（写库后调用）
     *
     * 未启用查询缓存（没有代际文件）时是空操作 —— 写库路径只需一次 is_file 探测。
     *
     * @return bool
     */
    public function bumpEpoch()
    {
        return self::bumpEpochFor($this->cacheDir);
    }

    /**
     * 回收过期条目 / 旧命名格式残留 / 崩溃残留的临时文件
     *
     * @param bool $force  跳过「低概率触发」限制（测试与运维脚本用）
     * @param int  $budget 单次最多检查多少个目录项（防一次性扫爆内存 / 拖死请求）
     *
     * @return int 回收的文件数
     */
    public function collectGarbage($force = false, $budget = 500)
    {
        $dir = $this->epochKey();
        if ($dir === '' || !is_dir($dir)) {
            return 0;
        }

        if (!$force) {
            if (!empty(self::$gcDone[$dir])) {
                return 0;
            }
            // 2% 概率触发，避免每个请求都扫目录；单次扫描量由 $budget 兜底
            if (mt_rand(1, 100) > 2) {
                return 0;
            }
        }
        self::$gcDone[$dir] = true;

        $now = time();
        $removed = 0;
        $scanned = 0;

        // 流式遍历 + 扫描预算：单次最多看 $budget 个目录项。
        // 不用 glob()：几十万文件时它会一次性把所有路径装进内存（实测 2 万条 +4MB），
        // 也没有中途退出的机会，一个请求就会被拖死。
        $handle = @opendir($dir);
        if ($handle === false) {
            return 0;
        }

        // 旧命名 {md5}-{epoch}.cache：升级残留，直接回收
        $legacyPattern = '/^[0-9a-f]{32}-' . self::EPOCH_PATTERN . preg_quote(self::CACHE_SUFFIX, '/') . '$/';
        // 当前命名 {md5}.cache：按内容里的 finish 判过期
        $currentPattern = '/^[0-9a-f]{32}' . preg_quote(self::CACHE_SUFFIX, '/') . '$/';
        $tmpMark = self::CACHE_SUFFIX . '.';

        while (($base = readdir($handle)) !== false) {
            if ($base === '.' || $base === '..') {
                continue;
            }
            if (++$scanned > $budget) {
                break;
            }

            $file = $dir . DIRECTORY_SEPARATOR . $base;

            if (preg_match($legacyPattern, $base)) {
                if (@unlink($file)) {
                    $removed++;
                }
                continue;
            }

            if (preg_match($currentPattern, $base)) {
                $raw = @file_get_contents($file);
                $data = ($raw === false || $raw === '') ? null : json_decode($raw, true);
                if (!is_array($data) || !array_key_exists('finish', $data) || (int)$data['finish'] < $now) {
                    if (@unlink($file)) {
                        $removed++;
                    }
                }
                continue;
            }

            // 崩溃残留的临时文件：{md5}.cache.{pid}.{rand}.tmp
            if (strpos($base, $tmpMark) !== false && substr($base, -strlen(self::TMP_SUFFIX)) === self::TMP_SUFFIX) {
                $mtime = @filemtime($file);
                if ($mtime !== false && $now - $mtime > self::TMP_TTL && @unlink($file)) {
                    $removed++;
                }
            }

            // 其他 .cache 文件可能是别的组件（如 Cache 文件驱动）的，不要动
        }

        closedir($handle);

        return $removed;
    }

    /**
     * 清空查询缓存目录（CLI / 运维用）
     *
     * 只删自己的 .cache 与 .tmp，保留 db.epoch 与任何无关文件。
     * 删缓存永远安全：最坏结果是冷缓存回源一次。
     *
     * 升级到「文件名不含代际」之后，老目录里的 {md5}-{epoch}.cache 靠这个一次性清掉；
     * 不要放进 Web 请求（几十万文件的遍历 + unlink 会把请求拖死）。
     *
     * @param string $dir 缓存目录
     *
     * @return int 删除的文件数
     */
    public static function purge($dir)
    {
        $key = self::normalizeDir($dir);
        if ($key === '' || !is_dir($key)) {
            return 0;
        }

        $removed = 0;
        $handle = @opendir($key);
        if ($handle === false) {
            return 0;
        }

        while (($base = readdir($handle)) !== false) {
            if ($base === '.' || $base === '..' || $base === self::EPOCH_FILE) {
                continue;
            }

            $isCache = substr($base, -strlen(self::CACHE_SUFFIX)) === self::CACHE_SUFFIX;
            $isTmp = substr($base, -strlen(self::TMP_SUFFIX)) === self::TMP_SUFFIX;
            if (!$isCache && !$isTmp) {
                continue;
            }

            if (@unlink($key . DIRECTORY_SEPARATOR . $base)) {
                $removed++;
            }
        }
        closedir($handle);

        unset(self::$epochs[$key]);

        return $removed;
    }

    /**
     * 确保某目录已启用代际号（首次调用 cache() 时建立）
     *
     * @param string $dir 缓存目录
     *
     * @return bool
     */
    public static function ensureEpochFor($dir)
    {
        $key = self::normalizeDir($dir);
        if ($key === '') {
            return false;
        }

        if (!is_dir($key) && !@mkdir($key, 0755, true) && !is_dir($key)) {
            return false;
        }

        $file = $key . DIRECTORY_SEPARATOR . self::EPOCH_FILE;
        clearstatcache(true, $file);
        if (is_file($file)) {
            return true;
        }

        if (!self::writeEpochToken($file)) {
            return false;
        }

        unset(self::$epochs[$key]);
        return true;
    }

    /**
     * 递增某目录的代际号（静态入口，写库路径用）
     *
     * @param string $dir 缓存目录
     *
     * @return bool 是否真的递增了（目录未启用查询缓存时返回 false）
     */
    public static function bumpEpochFor($dir)
    {
        $key = self::normalizeDir($dir);
        if ($key === '') {
            return false;
        }

        $file = $key . DIRECTORY_SEPARATOR . self::EPOCH_FILE;
        clearstatcache(true, $file);
        if (!is_file($file)) {
            // 从未用过查询缓存：零开销直接返回，不创建目录/文件
            return false;
        }

        if (!self::writeEpochToken($file)) {
            return false;
        }

        // 键必须与 readEpoch() 存的键一致（归一化后），否则同进程后续查询仍用旧代际号
        unset(self::$epochs[$key]);
        return true;
    }

    /**
     * 读取某目录的当前代际号
     *
     * @param string $dir 缓存目录
     *
     * @return string
     */
    public static function readEpoch($dir)
    {
        $key = self::normalizeDir($dir);
        if ($key === '') {
            return '0';
        }

        $file = $key . DIRECTORY_SEPARATOR . self::EPOCH_FILE;
        clearstatcache(true, $file);
        if (!is_file($file)) {
            unset(self::$epochs[$key]);
            return '0';
        }

        $mtime = @filemtime($file);
        $mtime = $mtime === false ? 0 : $mtime;

        // 1 秒内变动过的一律重读：mtime 只有秒级精度，同一秒内的多次 bump 无法用 mtime 区分
        if ((time() - $mtime) > 1
            && isset(self::$epochs[$key])
            && self::$epochs[$key]['mtime'] === $mtime) {
            return self::$epochs[$key]['value'];
        }

        $value = trim((string)@file_get_contents($file));
        if ($value === '' || !preg_match('/^' . self::EPOCH_PATTERN . '$/', $value)) {
            $value = '0';
        }

        self::$epochs[$key] = ['mtime' => $mtime, 'value' => $value];

        return $value;
    }

    /**
     * 根据SQL生成缓存文件完整路径
     *
     * 文件名只由「键」决定，不含代际号、也不含过期时间：
     *  - 含代际号 → 每次写库整体换一套文件名，旧文件永远不可达，
     *    文件数 = 写库次数 × 条目数（2026-11-22 线上 4.4G 事故的根因）；
     *  - 含 finish → 不同请求（构造时间不同）算出的文件名不一样，跨请求永远命不中。
     * 代际号与过期时间都放在文件内容里，由 fetch() 校验。
     *
     * @param string $name 缓存键
     *
     * @return string
     */
    protected function cacheFile($name)
    {
        return $this->epochKey() . DIRECTORY_SEPARATOR . $this->fileName($name) . self::CACHE_SUFFIX;
    }

    /**
     * 根据SQL生成缓存文件名主干
     *
     * @param string $name 缓存键
     *
     * @return string
     */
    protected function fileName($name)
    {
        return md5($name);
    }

    /**
     * 缓存是否可用（未设置时间或目录时视为关闭）
     *
     * @return bool
     */
    protected function enabled()
    {
        return !is_null($this->cache) && $this->cacheDir !== null && $this->cacheDir !== '';
    }

    /**
     * 校验反序列化后的缓存结构
     *
     * @param mixed $decoded json_decode 结果
     * @param bool  $array   是否数组模式
     *
     * @return bool
     */
    protected function isValidPayload($decoded, $array)
    {
        if ($array) {
            return is_array($decoded)
                && array_key_exists('finish', $decoded)
                && array_key_exists('data', $decoded)
                && is_numeric($decoded['finish']);
        }

        return is_object($decoded)
            && property_exists($decoded, 'finish')
            && property_exists($decoded, 'data')
            && is_numeric($decoded->finish);
    }

    /**
     * 低概率清理（缓存未命中时顺手做）
     *
     * @return void
     */
    protected function maybeCollectGarbage()
    {
        $this->collectGarbage(false);
    }

    /**
     * 目录键归一化：正斜杠/反斜杠/尾部分隔符写法统一
     *
     * @param string|null $dir
     *
     * @return string
     */
    protected function epochKey()
    {
        return self::normalizeDir($this->cacheDir);
    }

    /**
     * 目录归一化（静态）
     *
     * @param string|null $dir
     *
     * @return string
     */
    protected static function normalizeDir($dir)
    {
        $dir = (string)$dir;

        return rtrim($dir, '/\\');
    }

    /**
     * 写入新一代际标识（原地写，不走 tmp+rename）
     *
     * 为什么这里不做原子替换：代际文件只有「读不到合法 token」这一种坏结果，
     * 而读不到只会让读取方退化为「全部未命中」，永远不会产生脏命中（格式不对一律当 '0'）。
     * 换来的是写库路径上少一次 rename —— Windows 下 rename 实测 2~4ms，还会偶发「拒绝访问」。
     *
     * token 用「时间戳 + 随机串」而不是自增计数器：两个进程同时「读到 5 写 6」会丢一次更新，
     * 导致其中一方刚写的缓存仍可命中。随机串保证每次 bump 的标识都不同。
     *
     * @param string $file 代际文件路径
     *
     * @return bool
     */
    protected static function writeEpochToken($file)
    {
        $token = time() . '-' . sprintf('%08x', mt_rand(0, 0xffffffff));

        return @file_put_contents($file, $token) !== false;
    }
}
