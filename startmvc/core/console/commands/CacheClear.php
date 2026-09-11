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
 * 清理编译缓存
 *
 * 做两件事：
 *   1. 删除路由编译缓存 runtime/cache/routes.php（下次请求自动重建）
 *   2. 清空 runtime/temp/<模块>/ 下的模板编译产物
 *
 * 安全边界：只删各「模块子目录」内的 .php 文件，
 * 不触碰 runtime/temp/ 根目录下的文件（那里可能放着开发者自己的脚本）。
 */
class CacheClear extends Command
{
    /**
     * @var string
     */
    protected $name = 'cache:clear';

    /**
     * @var string
     */
    protected $description = '清理路由编译缓存与模板编译缓存';

    /**
     * @param array $args
     * @return bool
     */
    protected function handle(array $args)
    {
        $this->blank();
        $this->info('清理编译缓存');
        $this->blank();

        $deleted = 0;

        // ---- 1. 路由编译缓存 ----
        $routeCache = Router::cacheFile();
        if (is_file($routeCache)) {
            if (@unlink($routeCache)) {
                $this->line('  已删除  ' . $this->relative($routeCache));
                $deleted++;
            } else {
                $this->error('  删除失败  ' . $this->relative($routeCache) . '（请检查文件权限）');
                return false;
            }
        } else {
            $this->muted('  跳过    ' . $this->relative($routeCache) . '（不存在）');
        }

        // ---- 2. 模板编译缓存：runtime/temp/<模块>/*.php ----
        $tempDir = defined('TEMP_PATH') ? TEMP_PATH : $this->rootPath() . 'runtime/temp/';
        $tempDir = rtrim(str_replace('\\', '/', $tempDir), '/');

        $modules = glob($tempDir . '/*', GLOB_ONLYDIR);
        $templateDeleted = 0;

        foreach ((array)$modules as $dir) {
            $dir = rtrim(str_replace('\\', '/', $dir), '/');
            foreach ((array)glob($dir . '/*.php') as $file) {
                if (@unlink($file)) {
                    $templateDeleted++;
                    $deleted++;
                }
            }
            // 目录已空则一并移除，下次渲染自动重建
            $remaining = glob($dir . '/*');
            if (empty($remaining)) {
                @rmdir($dir);
            }
        }

        if ($templateDeleted > 0) {
            $this->line(sprintf('  已清空  %s/*/ 下的模板编译产物（%d 个文件）', $this->relative($tempDir), $templateDeleted));
        } else {
            $this->muted('  跳过    ' . $this->relative($tempDir) . '/*/（没有模板编译产物）');
        }

        $this->blank();
        if ($deleted > 0) {
            $this->info("缓存已清理，共删除 {$deleted} 个文件");
        } else {
            $this->info('没有需要清理的缓存');
        }
        $this->blank();

        return true;
    }
}
