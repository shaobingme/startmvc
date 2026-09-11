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

/**
 * 命令基类
 *
 * 子类只需声明 $name / $description 并实现 handle()。
 * 输出一律转调 Console 的输出器，保证所有命令排版一致；
 * 参数通过 arg() / option() / hasOption() 读取，无需自己解析 $argv。
 */
abstract class Command
{
    /**
     * 命令名，如 route:list
     * @var string
     */
    protected $name = '';

    /**
     * 命令说明（显示在命令列表中）
     * @var string
     */
    protected $description = '';

    /**
     * 命令行内核
     * @var Console
     */
    protected $console;

    /**
     * 本次执行的参数
     * @var array
     */
    protected $args = ['_positional' => [], '_options' => []];

    /**
     * @param Console $console
     */
    public function __construct(Console $console)
    {
        $this->console = $console;
    }

    /**
     * 命令名
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * 命令说明
     * @return string
     */
    public function description()
    {
        return $this->description;
    }

    /**
     * 执行命令（由 Console 调用）
     *
     * @param array $args 已解析的参数
     * @return bool 返回 false 表示执行失败（进程退出码 1）
     */
    public function run(array $args)
    {
        $this->args = $args + $this->args;
        $result = $this->handle($this->args);
        return $result === null ? true : $result;
    }

    /**
     * 子类实现具体逻辑
     * @param array $args
     * @return bool|void
     */
    abstract protected function handle(array $args);

    /* ==================== 参数读取 ==================== */

    /**
     * 按位置取参数
     * @param int $index
     * @param mixed $default
     * @return mixed
     */
    protected function arg($index, $default = null)
    {
        return isset($this->args['_positional'][$index]) ? $this->args['_positional'][$index] : $default;
    }

    /**
     * 取 --key=value 选项
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function option($key, $default = null)
    {
        return isset($this->args['_options'][$key]) ? $this->args['_options'][$key] : $default;
    }

    /**
     * 是否带 --flag 开关
     * @param string $key
     * @return bool
     */
    protected function hasOption($key)
    {
        return !empty($this->args['_options'][$key]);
    }

    /**
     * 全部位置参数
     * @return array
     */
    protected function positionalArgs()
    {
        return $this->args['_positional'];
    }

    /* ==================== 输出 ==================== */

    /**
     * @param string $text
     * @return void
     */
    protected function line($text = '')
    {
        $this->console->output()->line($text);
    }

    /**
     * @return void
     */
    protected function blank()
    {
        $this->console->output()->blank();
    }

    /**
     * @param string $text
     * @return void
     */
    protected function info($text)
    {
        $this->console->output()->info($text);
    }

    /**
     * @param string $text
     * @return void
     */
    protected function warn($text)
    {
        $this->console->output()->warn($text);
    }

    /**
     * @param string $text
     * @return void
     */
    protected function error($text)
    {
        $this->console->output()->error($text);
    }

    /**
     * @param string $text
     * @return void
     */
    protected function muted($text)
    {
        $this->console->output()->muted($text);
    }

    /**
     * @param array $headers
     * @param array $rows
     * @return void
     */
    protected function table(array $headers, array $rows)
    {
        $this->console->output()->table($headers, $rows);
    }

    /* ==================== 通用工具 ==================== */

    /**
     * 项目根目录（带末尾斜杠，统一用 / 分隔）
     * @return string
     */
    protected function rootPath()
    {
        return $this->console->rootPath();
    }

    /**
     * 转换为大驼峰（与 Router::convertController 同一套规则）
     * @param string $value
     * @return string
     */
    protected function studly($value)
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($value))));
    }

    /**
     * 转换为下划线小写（用于按类名推断表名）
     * @param string $value
     * @return string
     */
    protected function snake($value)
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value);
        return strtolower($value);
    }

    /**
     * 解析「模块/名称」形式的目标，缺省模块取 default_module 配置
     *
     * @param string $target 形如 home/Article 或 Article
     * @param string $defaultName $target 为空时的兜底名称
     * @return array [模块, 名称]
     */
    protected function splitTarget($target, $defaultName = '')
    {
        $target = trim((string)$target, '/');
        if ($target === '') {
            $target = $defaultName;
        }

        if (strpos($target, '/') !== false) {
            list($module, $name) = explode('/', $target, 2);
        } else {
            $module = config('default_module') ?: 'home';
            $name = $target;
        }

        $module = strtolower(trim($module));
        if ($module === '') {
            $module = config('default_module') ?: 'home';
        }

        return [$module, trim($name)];
    }

    /**
     * 写入文件（自动创建上级目录）
     *
     * @param string $path 绝对路径
     * @param string $content
     * @return bool
     */
    protected function writeFile($path, $content)
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $this->error("无法创建目录：{$dir}");
            return false;
        }
        if (@file_put_contents($path, $content) === false) {
            $this->error("写入失败：{$path}");
            return false;
        }
        return true;
    }

    /**
     * 路径展示：相对项目根目录，控制台里更短更好读
     * @param string $path
     * @return string
     */
    protected function relative($path)
    {
        $path = str_replace('\\', '/', $path);
        $root = $this->rootPath();
        if (strpos($path, $root) === 0) {
            return substr($path, strlen($root));
        }
        return $path;
    }
}
