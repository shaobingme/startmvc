<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

namespace startmvc\core\console;

use startmvc\core\console\commands\CacheClear;
use startmvc\core\console\commands\LogClear;
use startmvc\core\console\commands\MakeController;
use startmvc\core\console\commands\MakeModel;
use startmvc\core\console\commands\RouteList;

/**
 * 命令行内核
 *
 * 负责命令注册、参数解析与分发。入口文件（项目根 startmvc.php）只做三件事：
 * 定义常量、加载框架、把 $argv 交给 Console::run()。
 *
 * 用法：php startmvc.php <命令> [参数...] [--选项=值]
 */
class Console
{
    /**
     * 命令注册表
     * @var array [命令名 => 命令类]
     */
    protected $commands = [];

    /**
     * 项目根目录（带末尾斜杠，统一 / 分隔）
     * @var string
     */
    protected $rootPath;

    /**
     * 输出器
     * @var Output
     */
    protected $output;

    /**
     * @param string $rootPath 项目根目录
     * @param Output|null $output 输出器
     */
    public function __construct($rootPath, Output $output = null)
    {
        $this->rootPath = rtrim(str_replace('\\', '/', $rootPath), '/') . '/';
        $this->output = $output ?: new Output();
    }

    /**
     * 项目根目录
     * @return string
     */
    public function rootPath()
    {
        return $this->rootPath;
    }

    /**
     * 输出器
     * @return Output
     */
    public function output()
    {
        return $this->output;
    }

    /**
     * 注册一个命令
     *
     * @param string $name 命令名
     * @param string $class 命令类（须继承 Command）
     * @return $this
     */
    public function register($name, $class)
    {
        $this->commands[$name] = $class;
        return $this;
    }

    /**
     * 注册框架内置命令
     * @return $this
     */
    public function registerDefaults()
    {
        $this->register('route:list', RouteList::class);
        $this->register('make:controller', MakeController::class);
        $this->register('make:model', MakeModel::class);
        $this->register('cache:clear', CacheClear::class);
        $this->register('log:clear', LogClear::class);
        return $this;
    }

    /**
     * 已注册的命令
     * @return array
     */
    public function commands()
    {
        return $this->commands;
    }

    /**
     * 执行命令行
     *
     * @param array $argv 原始参数（通常是全局 $argv）
     * @return int 进程退出码，0 表示成功
     */
    public function run(array $argv)
    {
        $name = isset($argv[1]) ? trim((string)$argv[1]) : '';

        // 无命令或显式请求帮助：列出全部命令
        if ($name === '' || $name === 'list' || $name === 'help' || $name === '--help' || $name === '-h') {
            $this->showCommands();
            return 0;
        }

        if (!isset($this->commands[$name])) {
            $this->output->error("未知命令：{$name}");
            $this->showCommands();
            return 1;
        }

        $class = $this->commands[$name];
        if (!is_subclass_of($class, Command::class)) {
            $this->output->error("命令类必须继承 " . Command::class . "：{$class}");
            return 1;
        }

        /** @var Command $command */
        $command = new $class($this);
        $args = $this->parseArgs(array_slice($argv, 2));

        try {
            $result = $command->run($args);
        } catch (\Throwable $e) {
            $this->output->blank();
            $this->output->error('执行失败：' . $e->getMessage());
            if (function_exists('config') && config('debug')) {
                $this->output->muted('  ' . $e->getFile() . ':' . $e->getLine());
            }
            return 1;
        }

        return ($result === false) ? 1 : 0;
    }

    /**
     * 解析参数
     *
     * --key=value 解析为选项；--flag 解析为 true；其余按出现顺序进位置参数。
     *
     * @param array $tokens
     * @return array ['_positional' => array, '_options' => array]
     */
    protected function parseArgs(array $tokens)
    {
        $parsed = ['_positional' => [], '_options' => []];

        foreach ($tokens as $token) {
            $token = (string)$token;

            if (strpos($token, '--') === 0) {
                $body = substr($token, 2);
                if ($body === '') {
                    continue;
                }
                if (strpos($body, '=') !== false) {
                    list($key, $value) = explode('=', $body, 2);
                    $parsed['_options'][$key] = $value;
                } else {
                    $parsed['_options'][$body] = true;
                }
                continue;
            }

            $parsed['_positional'][] = $token;
        }

        return $parsed;
    }

    /**
     * 列出全部命令
     * @return void
     */
    protected function showCommands()
    {
        $out = $this->output;

        $out->blank();
        $out->info('StartMVC 命令行工具' . (defined('SM_VERSION') ? '  v' . SM_VERSION : ''));
        $out->blank();

        $rows = [];
        foreach ($this->commands as $name => $class) {
            $description = '';
            if (is_subclass_of($class, Command::class)) {
                /** @var Command $instance */
                $instance = new $class($this);
                $description = $instance->description();
            }
            $rows[] = [$name, $description];
        }

        $out->table(['命令', '说明'], $rows);
        $out->blank();
        $out->line('用法：php startmvc.php <命令> [参数...] [--选项=值]');
        $out->blank();
    }
}
