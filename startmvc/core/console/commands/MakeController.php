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

/**
 * 生成控制器骨架
 *
 * 用法：php startmvc.php make:controller [模块/]名称 [--force]
 *   make:controller home/Article   → app/home/controller/ArticleController.php
 *   make:controller Article        → 模块取 default_module 配置
 *
 * 名称会自动归一化：article-type → ArticleTypeController，
 * 与 Router::convertController() 的转换规则保持一致。
 */
class MakeController extends Command
{
    /**
     * @var string
     */
    protected $name = 'make:controller';

    /**
     * @var string
     */
    protected $description = '生成控制器骨架，用法：make:controller [模块/]名称';

    /**
     * @param array $args
     * @return bool
     */
    protected function handle(array $args)
    {
        list($module, $name) = $this->splitTarget($this->arg(0));

        if ($name === '') {
            $this->error('请指定控制器名称，例如：make:controller home/Article');
            return false;
        }

        // 归一化类名：article-type → ArticleTypeController
        $class = $this->studly($name);
        if (substr($class, -10) === 'Controller') {
            $class = substr($class, 0, -10);
        }
        $class .= 'Controller';

        $short = substr($class, 0, -10);
        $path = $this->rootPath() . 'app/' . $module . '/controller/' . $class . '.php';

        if (is_file($path) && !$this->hasOption('force')) {
            $this->warn('文件已存在，未覆盖：' . $this->relative($path));
            $this->line('如需覆盖，请追加 --force');
            return false;
        }

        if (!$this->writeFile($path, $this->stub($module, $class))) {
            return false;
        }

        $this->blank();
        $this->info('控制器已生成：' . $this->relative($path));
        $this->blank();
        $this->line('类名    ：' . APP_NAMESPACE . '\\' . $module . '\\controller\\' . $class);
        $this->line('默认视图：app/' . $module . '/view/' . strtolower($short) . '/index.php');
        $this->muted('  提示：模板路径由「模块/控制器/方法」推导，控制器名全小写即为视图目录名');
        $this->blank();

        return true;
    }

    /**
     * 控制器模板
     *
     * 用 nowdoc 保持原样，再替换占位符——避免 heredoc 把模板里的
     * $this 等写成当前对象的插值结果。
     *
     * @param string $module
     * @param string $class
     * @return string
     */
    protected function stub($module, $class)
    {
        $short = substr($class, 0, -10);
        $view = strtolower($short);

        $template = <<<'PHP'
<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * 由命令行工具生成：php startmvc.php make:controller
 */

namespace app\{MODULE}\controller;

use app\common\BaseController;

class {CLASS} extends BaseController
{
    public function indexAction()
    {
        // 需要渲染视图时改用下面两行（模板文件：app/{MODULE}/view/{VIEW}/index.php）
        // $this->assign(['title' => '{SHORT}']);
        // $this->display();

        return '{SHORT} 控制器已就绪';
    }
}
PHP;

        return str_replace(
            ['{MODULE}', '{CLASS}', '{SHORT}', '{VIEW}'],
            [$module, $class, $short, $view],
            $template
        );
    }
}
