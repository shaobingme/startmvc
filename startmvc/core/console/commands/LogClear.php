<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

namespace startmvc\core\console\commands;

use startmvc\core\console\Command;
use startmvc\core\Logger;

/**
 * 清理历史日志文件
 *
 * 背景：Logger 按天轮转但从不自动清理——这是刻意的。在写入路径里静默删除
 * 用户文件的风险远大于收益（split=true 时「保留 30 个文件」其实只有三四天，
 * 一旦业务代码开始写 info/debug，历史日志会被无声抹掉）。
 * 所以清理做成显式动作：由本命令手动执行，默认保留最近 30 天。
 *
 * 用法：
 *   php startmvc.php log:clear                   保留最近 30 天
 *   php startmvc.php log:clear --keep-days=7     保留最近 7 天
 *   php startmvc.php log:clear --keep-days=0     全部清空（等价于 --all）
 *   php startmvc.php log:clear --all             全部清空
 *   php startmvc.php log:clear --dry-run         只预览，不删除
 *
 * 安全边界（改动前必读）：
 *   1. 只在日志目录的「当层」操作，绝不递归子目录，绝不删除目录本身；
 *   2. 只删文件名匹配 {日期}.log / {日期}_{级别}.log 的文件，
 *      目录里其他名字的文件一律不碰（可能是开发者自己放的东西）；
 *   3. 符号链接一律跳过，避免顺着链接删到日志目录之外；
 *   4. 单个文件删除失败只记警告并继续，不中断整批清理。
 *
 * 日志目录取自 Logger::getPath()——与写入方共用同一套路径解析逻辑
 * （含目录不可用时退化到系统临时目录的情形），避免两处各算一遍。
 */
class LogClear extends Command
{
    /**
     * @var string
     */
    protected $name = 'log:clear';

    /**
     * @var string
     */
    protected $description = '清理历史日志文件（默认保留最近 30 天）';

    /**
     * 默认保留天数
     */
    const DEFAULT_KEEP_DAYS = 30;

    /**
     * 保留天数上限，防止误传超大值让清理形同虚设
     */
    const MAX_KEEP_DAYS = 3650;

    /**
     * 日志文件名规则：{Y-m-d}.log 或 {Y-m-d}_{级别}.log
     */
    const FILE_PATTERN = '/^(\d{4}-\d{2}-\d{2})(?:_[a-z]+)?\.log$/';

    /**
     * 参数纠偏提示，攒到参数区打印，避免插在标题中间
     * @var string
     */
    protected $notice = '';

    /**
     * @param array $args
     * @return bool 全部删除成功返回 true；有文件删不掉返回 false（退出码 1）
     */
    protected function handle(array $args)
    {
        $dir = $this->logDir();

        // --all 与 --keep-days=0 等价，都表示全清
        $keepDays = $this->hasOption('all') ? 0 : $this->resolveKeepDays();
        $purgeAll = ($keepDays === 0);
        $dryRun = $this->hasOption('dry-run');

        $this->blank();
        $this->info('清理历史日志');
        $this->blank();
        $this->line('  目录      ' . $this->relative($dir));
        $this->line('  策略      ' . ($purgeAll ? '全部清空' : '保留最近 ' . $keepDays . ' 天'));
        if ($dryRun) {
            $this->warn('  模式      预览，不会真正删除（--dry-run）');
        }
        if ($this->notice !== '') {
            $this->warn('  ' . $this->notice);
        }
        $this->blank();

        if (!is_dir($dir)) {
            $this->muted('  日志目录不存在，无需清理');
            $this->blank();
            return true;
        }

        $files = $this->scan($dir);
        if (empty($files)) {
            $this->muted('  目录下没有符合命名规则的日志文件');
            $this->blank();
            return true;
        }

        // 文件名里的日期就是写入日期，Y-m-d 格式可直接按字符串比较，无需 stat
        $cutoff = $purgeAll ? '' : $this->cutoffDate($keepDays);

        $targets = [];
        $kept = 0;
        foreach ($files as $file) {
            if ($purgeAll || $file['date'] < $cutoff) {
                $targets[] = $file;
            } else {
                $kept++;
            }
        }

        if (empty($targets)) {
            $this->muted(sprintf('  无需清理，%d 个日志文件全部在保留期内', count($files)));
            $this->blank();
            return true;
        }

        $deleted = 0;
        $failed = 0;
        $freed = 0;

        foreach ($targets as $file) {
            if ($dryRun) {
                $this->line(sprintf('  待删除  %s  %s', $file['name'], $this->humanSize($file['size'])));
                $freed += $file['size'];
                continue;
            }

            if (@unlink($file['path'])) {
                $deleted++;
                $freed += $file['size'];
                $this->line(sprintf('  已删除  %s  %s', $file['name'], $this->humanSize($file['size'])));
            } else {
                $failed++;
                $this->error(sprintf('  删除失败  %s（文件被占用或权限不足）', $file['name']));
            }
        }

        $this->blank();

        if ($dryRun) {
            $this->warn(sprintf('预览：%d 个文件将被删除，可释放 %s', count($targets), $this->humanSize($freed)));
            $this->muted('  去掉 --dry-run 即执行删除');
        } else {
            $this->info(sprintf('已删除 %d 个日志文件，释放 %s', $deleted, $this->humanSize($freed)));
            if ($kept > 0) {
                $this->muted(sprintf('  保留 %d 个文件（最近 %d 天）', $kept, $keepDays));
            }
            if ($failed > 0) {
                $this->warn(sprintf('  %d 个文件未能删除，稍后可重试', $failed));
            }
        }

        $this->blank();

        return $failed === 0;
    }

    /**
     * 日志目录（与 Logger 写入位置保持一致）
     *
     * @return string 统一 / 分隔、不带末尾斜杠
     */
    protected function logDir()
    {
        $logger = new Logger();

        return rtrim(str_replace('\\', '/', $logger->getPath()), '/');
    }

    /**
     * 解析 --keep-days，非法值纠偏为默认值并记入 notice
     *
     * @return int 0 表示全部清空
     */
    protected function resolveKeepDays()
    {
        $value = trim((string)$this->option('keep-days', self::DEFAULT_KEEP_DAYS));

        if ($value === '' || !ctype_digit($value)) {
            $this->notice = '--keep-days 需要 0 或正整数，已按默认值 ' . self::DEFAULT_KEEP_DAYS . ' 处理';
            return self::DEFAULT_KEEP_DAYS;
        }

        $days = (int)$value;
        if ($days > self::MAX_KEEP_DAYS) {
            $this->notice = '--keep-days 超过上限 ' . self::MAX_KEEP_DAYS . '，已按上限处理';
            return self::MAX_KEEP_DAYS;
        }

        return $days;
    }

    /**
     * 保留期的起始日期：保留最近 N 天 = 保留日期 >= 今天-(N-1) 天
     *
     * @param int $keepDays
     * @return string Y-m-d
     */
    protected function cutoffDate($keepDays)
    {
        return date('Y-m-d', strtotime('today') - ($keepDays - 1) * 86400);
    }

    /**
     * 扫描日志目录，只收集符合命名规则的文件
     *
     * @param string $dir
     * @return array 每项为 ['name' =>, 'path' =>, 'date' =>, 'size' =>]
     */
    protected function scan($dir)
    {
        $files = [];
        $handle = @opendir($dir);
        if ($handle === false) {
            return $files;
        }

        while (($name = readdir($handle)) !== false) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $dir . '/' . $name;

            // 先判链接再判文件：is_file 会跟随链接，顺序反了就白判了
            if (is_link($path) || !is_file($path)) {
                continue;
            }
            if (!preg_match(self::FILE_PATTERN, $name, $match)) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'path' => $path,
                'date' => $match[1],
                'size' => (int)@filesize($path),
            ];
        }

        closedir($handle);

        // 旧的排前面，输出顺序即删除顺序
        usort($files, function ($a, $b) {
            if ($a['date'] === $b['date']) {
                return strcmp($a['name'], $b['name']);
            }
            return ($a['date'] < $b['date']) ? -1 : 1;
        });

        return $files;
    }

    /**
     * 字节数转可读大小
     *
     * @param int $bytes
     * @return string
     */
    protected function humanSize($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
