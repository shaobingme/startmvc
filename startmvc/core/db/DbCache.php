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
 *  3. 代际号（epoch）：写库时递增一个极小文件的内容，缓存文件名里带上它，
 *     旧文件自然不可命中。内容用「时间戳-随机串」而不是自增计数器，避免并发自增丢更新。
 *     请求级备忘录带「mtime 距现在 > 1 秒」的前置条件，规避文件系统 mtime 只有秒级精度
 *     导致「同一秒内 bump 后仍读到旧代际号」的坑。
 *  4. 键归一化：所有目录键统一 rtrim 掉尾部分隔符，正斜杠/反斜杠两种写法指向同一个代际文件。
 *  5. GC：过期文件、旧代际文件、崩溃残留的 .tmp 会被回收（低概率触发，也可显式调用）。
 *
 * 目录里会出现三类文件：
 *   db.epoch                     当前代际号（内容即代际标识）
 *   {md5}-{epoch}.cache          缓存条目（过期时间在内容里，与文件名无关）
 *   *.tmp                        写入中间态
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

        $payload = json_encode(['data' => $result, 'finish' => (int)$this->finish]);
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
     * 回收过期 / 旧代际 / 残留临时文件
     *
     * @param bool $force 跳过「低概率触发」限制（测试与运维脚本用）
     *
     * @return int 回收的文件数
     */
    public function collectGarbage($force = false)
    {
        $dir = $this->epochKey();
        if ($dir === '' || !is_dir($dir)) {
            return 0;
        }

        if (!$force) {
            if (!empty(self::$gcDone[$dir])) {
                return 0;
            }
            // 2% 概率触发，避免每个请求都扫目录；扫一次的收益是全量清理
            if (mt_rand(1, 100) > 2) {
                return 0;
            }
        }
        self::$gcDone[$dir] = true;

        $epoch = $this->epoch();
        $now = time();
        $removed = 0;
        $pattern = '/^[0-9a-f]{32}-(' . self::EPOCH_PATTERN . ')' . preg_quote(self::CACHE_SUFFIX, '/') . '$/';
        // 旧命名格式（重构前是 {md5}.cache，没有代际号）
        $legacy = '/^[0-9a-f]{32}' . preg_quote(self::CACHE_SUFFIX, '/') . '$/';
        $files = (array)glob($dir . DIRECTORY_SEPARATOR . '*' . self::CACHE_SUFFIX);

        // 大目录（上万条）本轮只收旧代际，不逐个读内容判过期，
        // 避免一次 GC 变成上万次读盘；过期条目会在后续轮次被收掉
        $skipExpiryScan = count($files) > 3000;

        foreach ($files as $file) {
            $base = basename($file);

            if (preg_match($pattern, $base, $m)) {
                // 旧代际：不用读内容，直接回收
                if ($m[1] !== $epoch) {
                    if (@unlink($file)) {
                        $removed++;
                    }
                    continue;
                }

                if ($skipExpiryScan) {
                    continue;
                }

                // 当前代际：按内容里的 finish 判断是否过期
                $raw = @file_get_contents($file);
                $data = ($raw === false || $raw === '') ? null : json_decode($raw, true);
                if (!is_array($data) || !array_key_exists('finish', $data) || (int)$data['finish'] < $now) {
                    if (@unlink($file)) {
                        $removed++;
                    }
                }
                continue;
            }

            // 旧命名格式：清掉，帮升级后的目录减负
            if (preg_match($legacy, $base)) {
                if (@unlink($file)) {
                    $removed++;
                }
            }

            // 其他 .cache 文件可能是别的组件（如 Cache 文件驱动）的，不要动
        }

        // 只清理自己的临时文件（{md5}-{epoch}.cache.{pid}.{rand}.tmp）
        foreach ((array)glob($dir . DIRECTORY_SEPARATOR . '*' . self::CACHE_SUFFIX . '.*' . self::TMP_SUFFIX) as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $now - $mtime > self::TMP_TTL && @unlink($file)) {
                $removed++;
            }
        }

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
     * 根据SQL生成缓存文件完整路径（含代际号）
     *
     * 文件名里只放「键 + 代际号」，过期时间放在文件内容里：
     * 若把 finish 也编进文件名，不同请求（构造时间不同）算出的文件名会不一样，
     * 跨请求就永远命不中——这个坑在重构中踩过一次，别再踩。
     *
     * @param string $name 缓存键
     *
     * @return string
     */
    protected function cacheFile($name)
    {
        return $this->epochKey() . DIRECTORY_SEPARATOR
            . $this->fileName($name) . '-' . $this->epoch() . self::CACHE_SUFFIX;
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
