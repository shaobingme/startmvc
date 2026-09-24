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
 *   2. 清空 runtime/temp/<模块>/ 下的模板编译产物（含 <模块>/<主题>/ 子目录）
 *
 * 安全边界：只处理 app/ 下真实存在的模块名对应的目录，且只删其中的 .php 文件；
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

        // ---- 2. 模板编译缓存：runtime/temp/<模块>/**/*.php ----
        // 编译产物可能是 <模块>/*.php，也可能是 <模块>/<主题>/*.php（多主题），所以要递归。
        // 只认 app/ 下真实存在的模块名：runtime/temp/ 里还可能有开发者自己的目录
        // （脚本、源码快照等），不能因为名字对不上就顺手删掉。
        $tempDir = defined('TEMP_PATH') ? TEMP_PATH : $this->rootPath() . 'runtime/temp/';
        $tempDir = rtrim(str_replace('\\', '/', $tempDir), '/');

        $appDir = defined('APP_PATH') ? APP_PATH : $this->rootPath() . 'app/';
        $appDir = rtrim(str_replace('\\', '/', $appDir), '/');

        $templateDeleted = 0;

        foreach ((array)glob($appDir . '/*', GLOB_ONLYDIR) as $module) {
            $dir = $tempDir . '/' . basename($module);
            if (!is_dir($dir)) {
                continue;
            }

            $templateDeleted += $this->purgeCompiled($dir, $deleted);

            // 目录已空则一并移除，下次渲染自动重建
            if (empty(glob($dir . '/*'))) {
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

    /**
     * 递归删除目录下的 .php 编译产物，返回删除数量
     *
     * 模板编译缓存可能是 <模块>/*.php，也可能是 <模块>/<主题>/*.php，
     * 所以必须递归；非 .php 文件一律保留（那是开发者自己的东西）。
     *
     * @param string $dir     要清理的目录
     * @param int    $deleted 累计删除总数（引用传出）
     * @return int 本次删除的文件数
     */
    private function purgeCompiled($dir, &$deleted)
    {
        $count = 0;

        foreach ((array)glob($dir . '/*') as $path) {
            if (is_dir($path)) {
                $count += $this->purgeCompiled($path, $deleted);
                if (empty(glob($path . '/*'))) {
                    @rmdir($path);
                }
            } elseif (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
                if (@unlink($path)) {
                    $count++;
                    $deleted++;
                }
            }
        }

        return $count;
    }
}
