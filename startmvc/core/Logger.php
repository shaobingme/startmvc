<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */

namespace startmvc\core;

/**
 * 日志记录器
 *
 * 三条设计约定（改动前务必先读）：
 *
 * 1. 写入永不抛异常。日志是旁路，任何 IO 失败只返回 false。
 *    若写入失败会抛异常，后果是：异常处理器（Exception::handleException）里调它记日志，
 *    日志自己失败了又把异常抛回异常处理器 —— 递归甚至致命错误。
 *
 * 2. 级别校验仍然 fail-fast。传入未定义的级别属于调用方 bug，抛 InvalidArgumentException。
 *    这与"写入失败静默"是两件事：前者是契约错误，后者是环境故障。
 *
 * 3. 目录不可用时逐级退化：配置目录 → 系统临时目录 → 静默丢弃（返回 false）。
 *
 * 文件命名：{日期}_{级别}.log（split=true，默认），与框架历史日志 naming 一致，
 * 天然按天轮转；split=false 时为 {日期}.log。
 */
class Logger
{
    /**
     * 合法日志级别 => 严重度
     * 同时承担两个职责：级别白名单（校验）、严重度排序（最低级别过滤）
     */
    const LEVEL_ORDER = [
        'debug'     => 0,
        'info'      => 1,
        'notice'    => 2,
        'warning'   => 3,
        'error'     => 4,
        'critical'  => 5,
        'alert'     => 6,
        'emergency' => 7,
    ];

    /**
     * 日志目录（绝对路径，可能已退化为系统临时目录）
     * @var string
     */
    protected $path;

    /**
     * 是否按级别分文件
     * @var bool
     */
    protected $split = true;

    /**
     * 最低记录级别
     * @var string
     */
    protected $minLevel = 'debug';

    /**
     * 是否启用日志
     * @var bool
     */
    protected $enabled = true;

    /**
     * 日志目录是否可用；不可用时所有写入静默丢弃
     * @var bool
     */
    protected $writable = false;

    /**
     * 构造函数
     *
     * @param string|null $path 日志目录，为 null 时依次取 config/log.php 的 path、runtime/logs
     */
    public function __construct($path = null)
    {
        $config = $this->loadConfig();

        if ($path === null && !empty($config['path'])) {
            $path = $config['path'];
        }
        if (isset($config['split'])) {
            $this->split = (bool) $config['split'];
        }
        if (isset($config['level'])) {
            $this->minLevel = (string) $config['level'];
        }
        if (isset($config['enabled'])) {
            $this->enabled = (bool) $config['enabled'];
        }

        $this->path = $path ?: ROOT_PATH . 'runtime' . DS . 'logs';

        $this->writable = $this->prepareDir($this->path);

        if (!$this->writable) {
            // 目标目录不可用（权限不足 / 同一路径被文件占用）：退化到系统临时目录。
            // 宁可把日志写错地方，也不能因为日志目录不可写而让业务中断。
            $fallback = sys_get_temp_dir() . DS . 'startmvc-logs';
            if ($this->prepareDir($fallback)) {
                $this->path = $fallback;
                $this->writable = true;
            }
        }
    }

    /**
     * 读取日志配置
     *
     * 配置文件缺失或写法有误时一律降级为默认配置，绝不向调用方抛异常
     *
     * @return array
     */
    protected function loadConfig()
    {
        if (!function_exists('config')) {
            return [];
        }

        try {
            $config = config('log');
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($config) ? $config : [];
    }

    /**
     * 确保目录存在且可写
     *
     * @param string $dir
     * @return bool
     */
    protected function prepareDir($dir)
    {
        if (is_dir($dir)) {
            return is_writable($dir);
        }

        // mkdir 失败会发 Warning，而框架把 Warning 提升为 ErrorException（见 Exception::handleError），
        // 必须用 @ 抑制，否则此处会抛异常，违背"写入永不抛"的约定
        return @mkdir($dir, 0755, true) && is_writable($dir);
    }

    /**
     * 写入日志
     *
     * @param string $level 日志级别（LEVEL_ORDER 的键）
     * @param string $message 日志消息，支持 {key} 占位符
     * @param array $context 上下文数据
     * @return bool 是否真正写入；未启用 / 被级别过滤 / 目录不可用 / 写入失败均返回 false
     * @throws \InvalidArgumentException 传入未定义的级别时
     */
    public function log($level, $message, array $context = [])
    {
        if (!isset(self::LEVEL_ORDER[$level])) {
            throw new \InvalidArgumentException("无效的日志级别 [$level]");
        }

        if (!$this->enabled || !$this->writable) {
            return false;
        }

        // 配置里写了未定义的级别时按 0 处理：配置错误宁可多记，不可少记
        if (self::LEVEL_ORDER[$level] < (self::LEVEL_ORDER[$this->minLevel] ?? 0)) {
            return false;
        }

        $line = $this->formatMessage($level, $message, $context) . PHP_EOL;

        try {
            return @file_put_contents($this->file($level), $line, FILE_APPEND | LOCK_EX) !== false;
        } catch (\Throwable $e) {
            // 兜底：任何写入异常都不得向上抛，否则会打断调用方（尤其是异常处理器）
            return false;
        }
    }

    /**
     * 计算日志文件路径
     *
     * @param string $level
     * @return string
     */
    protected function file($level)
    {
        $name = date('Y-m-d') . ($this->split ? '_' . $level : '') . '.log';

        return rtrim($this->path, '/\\') . DS . $name;
    }

    /**
     * 格式化日志消息
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return string
     */
    protected function formatMessage($level, $message, array $context = [])
    {
        // 替换上下文变量
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        $message = strtr($message, $replace);

        return '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($level) . ': ' . $message;
    }

    /**
     * 当前实际使用的日志目录（目录不可用时可能已退化为系统临时目录）
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /* ---------------- 各级别快捷方法（一次性补全 8 级，避免调用方猜哪些存在） ---------------- */

    public function debug($message, array $context = [])
    {
        return $this->log('debug', $message, $context);
    }

    public function info($message, array $context = [])
    {
        return $this->log('info', $message, $context);
    }

    public function notice($message, array $context = [])
    {
        return $this->log('notice', $message, $context);
    }

    public function warning($message, array $context = [])
    {
        return $this->log('warning', $message, $context);
    }

    public function error($message, array $context = [])
    {
        return $this->log('error', $message, $context);
    }

    public function critical($message, array $context = [])
    {
        return $this->log('critical', $message, $context);
    }

    public function alert($message, array $context = [])
    {
        return $this->log('alert', $message, $context);
    }

    public function emergency($message, array $context = [])
    {
        return $this->log('emergency', $message, $context);
    }
}
