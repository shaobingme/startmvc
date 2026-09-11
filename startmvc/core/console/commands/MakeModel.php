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
 * 生成模型骨架
 *
 * 用法：php startmvc.php make:model [模块/]名称 [--table=表名] [--force]
 *   make:model home/Article          → app/home/model/ArticleModel.php，表名 article
 *   make:model home/Article --table=news   → 指定表名 news
 */
class MakeModel extends Command
{
    /**
     * @var string
     */
    protected $name = 'make:model';

    /**
     * @var string
     */
    protected $description = '生成模型骨架，用法：make:model [模块/]名称 [--table=表名]';

    /**
     * @param array $args
     * @return bool
     */
    protected function handle(array $args)
    {
        list($module, $name) = $this->splitTarget($this->arg(0));

        if ($name === '') {
            $this->error('请指定模型名称，例如：make:model home/Article');
            return false;
        }

        $class = $this->studly($name);
        if (substr($class, -5) !== 'Model') {
            $class .= 'Model';
        }
        $short = substr($class, 0, -5);

        // 表名：优先 --table 选项，否则按类名推断（ArticleType → article_type）
        $table = (string)$this->option('table', $this->snake($short));

        $path = $this->rootPath() . 'app/' . $module . '/model/' . $class . '.php';

        if (is_file($path) && !$this->hasOption('force')) {
            $this->warn('文件已存在，未覆盖：' . $this->relative($path));
            $this->line('如需覆盖，请追加 --force');
            return false;
        }

        if (!$this->writeFile($path, $this->stub($module, $class, $table))) {
            return false;
        }

        $this->blank();
        $this->info('模型已生成：' . $this->relative($path));
        $this->blank();
        $this->line('类名  ：' . APP_NAMESPACE . '\\' . $module . '\\model\\' . $class);
        $this->line('表名  ：' . $table . '（可在模型里修改 $table，或用 --table=表名 重新指定）');
        $this->blank();

        return true;
    }

    /**
     * 模型模板（nowdoc 保持原样，避免模板里的 $this 被当成插值）
     *
     * @param string $module
     * @param string $class
     * @param string $table
     * @return string
     */
    protected function stub($module, $class, $table)
    {
        $template = <<<'PHP'
<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * 由命令行工具生成：php startmvc.php make:model
 */

namespace app\{MODULE}\model;

use startmvc\core\Model;

class {CLASS} extends Model
{
    /**
     * 表名
     * @var string
     */
    protected $table = '{TABLE}';

    // 需要自定义查询时在这里扩展，例如：
    // public function getList($limit = 10)
    // {
    //     return $this->order('id', 'desc')->limit($limit)->select();
    // }
}
PHP;

        return str_replace(
            ['{MODULE}', '{CLASS}', '{TABLE}'],
            [$module, $class, $table],
            $template
        );
    }
}
