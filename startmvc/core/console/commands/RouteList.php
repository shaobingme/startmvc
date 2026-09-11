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
use startmvc\core\Router;

/**
 * 列出已注册的路由
 *
 * 直接读取 Router 的分表结果（静态 / 占位符 / 原生正则），
 * 并显示编译缓存的当前状态——改完路由后先跑一次这个命令，
 * 可以立刻确认路由是否按预期注册、缓存是否已自动失效重建。
 */
class RouteList extends Command
{
    /**
     * @var string
     */
    protected $name = 'route:list';

    /**
     * @var string
     */
    protected $description = '列出已注册的全部路由，并显示编译缓存状态';

    /**
     * @param array $args
     * @return bool
     */
    protected function handle(array $args)
    {
        // 触发路由加载（CLI 下同样会把编译结果写入缓存）
        Router::loadRoutes();
        $routes = Router::dump();

        $rows = [];
        foreach ($routes as $method => $list) {
            foreach ($list as $route) {
                $rows[] = [
                    $method,
                    $route['type'],
                    '/' . ltrim((string)$route['uri'], '/'),
                    $this->actionText($route['action']),
                    $route['middleware'] ? implode(', ', $route['middleware']) : '-',
                ];
            }
        }

        $this->blank();
        $this->info('路由列表');
        $this->blank();

        if (!$rows) {
            $this->warn('尚未定义任何自定义路由，所有请求都会走「模块/控制器/方法」自动解析。');
        } else {
            $this->table(['METHOD', '类型', 'URI', '目标', '中间件'], $rows);
        }

        // 编译缓存状态：与本次改动直接相关，单独列出
        $cacheFile = Router::cacheFile();
        $hasCache = is_file($cacheFile);

        $this->blank();
        $this->line(sprintf('共 %d 条路由（static=精确匹配，dynamic=占位符，raw=原生正则）', count($rows)));

        if ($hasCache) {
            $this->line(sprintf(
                '编译缓存：已生成  %s（%s）',
                $this->relative($cacheFile),
                number_format(filesize($cacheFile) / 1024, 1) . ' KB'
            ));
            $this->muted('  失效依据：config/route.php 的修改时间 + 框架版本号；可用 cache:clear 手动清理');
        } else {
            $this->warn('编译缓存：未生成（路由中含闭包时不会写缓存，属正常情况）');
        }

        $this->blank();
        return true;
    }

    /**
     * 把路由目标转成可读文本
     *
     * @param mixed $action
     * @return string
     */
    protected function actionText($action)
    {
        if ($action instanceof \Closure) {
            return 'Closure';
        }
        return (string)$action;
    }
}
