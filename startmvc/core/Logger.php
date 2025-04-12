<?php
namespace startmvc\core;

class Logger
{
    /**
     * 日志级别
     * @var array
     */
    protected $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
    
    /**
     * 日志文件路径
     * @var string
     */
    protected $path;
    
    /**
     * 构造函数
     * @param string $path 日志文件路径
     */
    public function __construct($path = null)
    {
        $this->path = $path ?: ROOT_PATH . 'runtime/logs';
        
        // 确保目录存在
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }
    
    /**
     * 写入日志
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return bool
     */
    public function log($level, $message, array $context = [])
    {
        if (!in_array($level, $this->levels)) {
            throw new \InvalidArgumentException("无效的日志级别 [$level]");
        }
        
        // 格式化消息
        $message = $this->formatMessage($level, $message, $context);
        
        // 写入文件
        $file = $this->path . '/' . date('Y-m-d') . '.log';
        return file_put_contents($file, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 格式化日志消息
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
     * 记录调试信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return bool
     */
    public function debug($message, array $context = [])
    {
        return $this->log('debug', $message, $context);
    }
    
    /**
     * 记录错误信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return bool
     */
    public function error($message, array $context = [])
    {
        return $this->log('error', $message, $context);
    }
    
    // 其他级别的快捷方法...
} 