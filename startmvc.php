<?php
/**
 * StartMVC超轻量级PHP开发框架 —— 命令行入口
 *
 * 用法：
 *   php startmvc.php                                列出全部命令
 *   php startmvc.php route:list                     查看已注册路由与编译缓存状态
 *   php startmvc.php make:controller home/Article   生成控制器骨架
 *   php startmvc.php make:model home/Article        生成模型骨架
 *   php startmvc.php cache:clear                    清理路由与模板编译缓存
 *   php startmvc.php log:clear --keep-days=30       清理历史日志（保留最近 30 天）
 *
 * 说明：Web 请求请走 public/index.php，本文件仅用于命令行。
 */

// 只允许命令行运行，避免通过 Web 访问误执行命令
if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    exit('本文件仅供命令行使用，Web 请求请访问 public/index.php');
}

// 系统目录分隔符、项目根目录（与 public/index.php 保持一致的常量约定）
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', __DIR__ . DS);

require(ROOT_PATH . 'startmvc' . DS . 'boot.php');

// boot.php 在 CLI 下不会执行 HTTP 分发，此时框架组件均已可用
$console = new \startmvc\core\console\Console(ROOT_PATH);
$console->registerDefaults();

exit($console->run(isset($argv) ? $argv : []));
