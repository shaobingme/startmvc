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
 * 分页 HTML 渲染器
 *
 * 只负责把「记录数 / 每页条数 / 当前页」渲染成页码链接，不负责取数。
 * 取数请用 Db::table('t')->page($pageSize, $page) 或 Model::paginate()。
 *
 * 配置读取顺序（后者覆盖前者）：
 *   1. 类内置默认值 $defaults
 *   2. config/pagination.php（整个文件缺失时全部走默认值，不报错）
 *   3. 构造时传入的 $config 数组
 *
 * 页码区（%link%）有两种形态，由 $ellipsis 是否为空决定：
 *   连续窗口（默认）—— 从当前页向两侧取 $pageShowCount 个页码，贴边时整体回拉。
 *                       形如 12 13 14 15 16 17
 *   省略号窗口       —— 首尾各锚定 $edgeCount 页 + 当前页邻域，断开处用 $ellipsis 填充。
 *                       形如 1 2 … 5 6 7 8 9 … 78 79
 * 两种形态下 theme 与其它占位符（header/first/last/prev/next）完全一致，互不影响。
 *
 * 用法：
 *   $p = new Pagination();
 *   echo $p->Show($total, 10, $page, '/list?page={page}');
 *
 *   // 只改当前页样式，不动配置文件
 *   echo (new Pagination(['currentClass' => 'active']))->Show($total, 10, $page, $url);
 *
 *   // 开启省略号窗口（页数多时更友好）
 *   echo (new Pagination([
 *       'ellipsis'  => '<span class="dots">…</span>',
 *       'edgeCount' => 2,
 *   ]))->Show($total, 10, $page, $url, 5);
 */
class Pagination
{
    /**
     * 内置默认值：配置文件缺失、或某个键没写时用它兜底
     *
     * ellipsis  省略号 HTML 片段；留空字符串 = 关闭（保持连续窗口的老行为）。
     *           填任意 HTML 即开启，例如 '<span class="dots">…</span>'
     * edgeCount 省略号开启时，首尾各锚定几页；填 0 则不要首尾锚点
     *
     * @var array
     */
    private static $defaults = [
        'theme'        => '%header% %first% %prev% %link% %next% %last%',
        'header'       => '共 %count% 条记录 第 %page% / %pageCount% 页',
        'first'        => '首页',
        'last'         => '末页',
        'prev'         => '上一页',
        'next'         => '下一页',
        'currentClass' => 'current',
        'ellipsis'     => '',
        'edgeCount'    => 2,
    ];

    /**
     * config/pagination.php 的读取结果缓存
     * @var array|null
     */
    private static $fileConfig = null;

    public $theme, $header, $first, $last, $prev, $next, $currentClass, $ellipsis, $edgeCount;

    public function __construct(array $config = [])
    {
        // 修复：原实现是 include '../config/pagination.php'（相对路径，按当前工作目录
        // 解析）。Web 下 CWD 是入口目录、CLI 下是调用目录，实际几乎必然找不到该文件，
        // 结果是每次实例化都报 Warning，且配置全部失效（currentClass 一直是硬编码的
        // 'current'）。这里统一走 Config，路径由 CONFIG_PATH 决定，与框架其它配置读取
        // 方式保持一致。
        $file = self::loadFileConfig();

        // 逐键回退：传入的 $config > 配置文件 > 内置默认值。
        // 原实现拿到 $config 参数后立刻被 include 的结果整体覆盖，参数形同虚设。
        $options = array_merge(
            self::$defaults,
            self::withoutNull($file),
            self::withoutNull($config)
        );

        $this->theme        = $options['theme'];
        $this->header       = $options['header'];
        $this->first        = $options['first'];
        $this->last         = $options['last'];
        $this->prev         = $options['prev'];
        $this->next         = $options['next'];
        $this->currentClass = $options['currentClass'];
        $this->ellipsis     = (string)$options['ellipsis'];
        $this->edgeCount    = max(0, (int)$options['edgeCount']);
    }

    /**
     * 读取 config/pagination.php
     *
     * 文件不存在时返回空数组（全部走内置默认值），不再产生 Warning。
     *
     * @return array
     */
    private static function loadFileConfig()
    {
        if (self::$fileConfig !== null) {
            return self::$fileConfig;
        }

        if (\function_exists('config')) {
            $config = \config('pagination');
        } else {
            // 未经框架引导（例如单独引入本类做单元测试）时的兜底：
            // 本文件位于 <项目根>/startmvc/core/，向上两级即项目根
            $path = (defined('CONFIG_PATH') ? CONFIG_PATH : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR) . 'pagination.php';
            $config = is_file($path) ? include $path : null;
        }

        self::$fileConfig = is_array($config) ? $config : [];
        return self::$fileConfig;
    }

    /**
     * 剔除值为 null 的键，使「显式传 null」等同于「没传」，继续走下一级回退
     *
     * 注意不能用裸 array_filter()：那会把 '' 和 0 也一并滤掉，
     * 而 'header' => ''（不显示表头）是合法配置。
     *
     * @param array $options
     * @return array
     */
    private static function withoutNull(array $options)
    {
        foreach ($options as $key => $value) {
            if ($value === null) {
                unset($options[$key]);
            }
        }
        return $options;
    }

    /**
     * 渲染分页 HTML
     *
     * @param int    $count         记录总数
     * @param int    $pageSize      每页条数
     * @param int    $page          当前页码（从 1 开始）
     * @param string $url           链接模板，用 {page} 占位，如 '/list?page={page}'
     * @param int    $pageShowCount 页码窗口大小。连续窗口下是「最多显示几个页码」；
     *                              省略号窗口下是「当前页邻域几个页码」（省略号不占名额）
     * @return string
     */
    public function Show($count, $pageSize, $page, $url, $pageShowCount = 10)
    {
        // 参数兜底：原实现让这些值直接参与运算，
        // $pageSize 传 0 → 除零；$page 传 0/负数 → 生成 ?page=0、?page=-3 这类死链；
        // ceil() 返回 float → 末页链接变成 ?page=2.0
        $count         = max(0, (int)$count);
        $pageSize      = max(1, (int)$pageSize);
        $pageShowCount = max(1, (int)$pageShowCount);
        $pageCount     = (int)ceil($count / $pageSize);
        $page          = min(max(1, (int)$page), max(1, $pageCount));

        // 总数为 0 时没有页可翻，但「首页/末页」仍要指向第 1 页，
        // 不能落到 ?page=0（原实现就是这个问题）
        $lastPage = max(1, $pageCount);

        $header = '<a>' . str_replace(['%count%', '%page%', '%pageCount%'], [$count, $page, $pageCount], $this->header) . '</a>';
        $first  = '<a href="' . $this->url($url, 1) . '">' . $this->first . '</a>';
        $last   = '<a href="' . $this->url($url, $lastPage) . '">' . $this->last . '</a>';
        $prev   = '<a' . ($page > 1 ? ' href="' . $this->url($url, $page - 1) . '"' : '') . '>' . $this->prev . '</a>';
        $next   = '<a' . ($page < $pageCount ? ' href="' . $this->url($url, $page + 1) . '"' : '') . '>' . $this->next . '</a>';

        $link = $this->buildLink($page, $pageCount, $pageShowCount, $url);

        return str_replace([
            '%header%', '%first%', '%prev%', '%link%', '%next%', '%last%'
        ], [
            $header, $first, $prev, $link, $next, $last
        ], $this->theme);
    }

    /**
     * 渲染页码区（对应 %link% 占位符）
     *
     * 按 $ellipsis 是否为空分两条路：
     *   - 空字符串 → 连续窗口（加省略号之前的老行为，输出逐字节相同）
     *   - 非空     → 首尾锚点 + 当前页邻域，中间用 $ellipsis 填充
     *
     * @param int    $page      当前页码（已夹取到合法区间）
     * @param int    $pageCount 总页数（可能为 0）
     * @param int    $window    页码窗口大小
     * @param string $url       链接模板
     * @return string
     */
    private function buildLink($page, $pageCount, $window, $url)
    {
        if ($this->ellipsis === '') {
            return $this->buildSlideLink($page, $pageCount, $window, $url);
        }
        return $this->buildEllipsisLink($page, $pageCount, $window, $url);
    }

    /**
     * 连续窗口：从当前页向两侧取 $window 个页码，贴近首尾时整体回拉
     *
     * @param int    $page      当前页码
     * @param int    $pageCount 总页数
     * @param int    $window    页码窗口大小
     * @param string $url       链接模板
     * @return string
     */
    private function buildSlideLink($page, $pageCount, $window, $url)
    {
        list($start, $end) = $this->windowRange($page, $pageCount, $window);
        // $pageCount 为 0 时上面会得到 [1, 0]，循环自然不执行，不渲染任何页码
        $link = '';
        for ($p = $start; $p <= $end; $p++) {
            $link .= $this->pageTag($p, $page, $url);
        }
        return $link;
    }

    /**
     * 计算页码窗口的起止（含两端），贴近首尾时整体回拉
     *
     * 连续窗口与省略号窗口共用这段逻辑，抽出来避免两处各写一份、改一处忘一处。
     *
     * @param int $page      当前页码
     * @param int $pageCount 总页数
     * @param int $window    窗口大小
     * @return array [起, 止]；$pageCount 为 0 时返回 [1, 0]（空区间）
     */
    private function windowRange($page, $pageCount, $window)
    {
        // 用 intdiv 取窗口中心：原写法 $page - $window / 2 在窗口大小为奇数时
        // 会得到 2.5 这种小数起点，for 循环直接把 "?page=2.5" 拼进链接
        $start = $page - intdiv($window, 2);
        $start = $start < 1 ? 1 : $start;
        $end   = $start + $window - 1;
        if ($end > $pageCount) {
            $end   = $pageCount;
            $start = $end - $window + 1;
            $start = $start < 1 ? 1 : $start;
        }
        return [$start, $end];
    }

    /**
     * 省略号窗口：首尾锚点 + 当前页邻域，断开处用 $ellipsis 填充
     *
     * 实现要点：先算出「要显示的页码集合」（借数组键去重），排序后逐个输出；
     * 相邻页码不连续时，按缺口大小决定「补出那一页」还是「插省略号」。
     * 用集合而不是区间拼接，天然处理了「邻域与首尾锚点重叠」「当前页贴近首尾」
     * 「总页数比要显示的还少」等情况，不需要额外分支。
     *
     * @param int    $page      当前页码
     * @param int    $pageCount 总页数
     * @param int    $window    当前页邻域大小
     * @param string $url       链接模板
     * @return string
     */
    private function buildEllipsisLink($page, $pageCount, $window, $url)
    {
        if ($pageCount < 1) {
            return '';
        }

        // 首尾锚点
        $edge  = min($this->edgeCount, $pageCount);
        $pages = [];
        for ($i = 1; $i <= $edge; $i++) {
            $pages[$i] = true;
        }
        for ($i = max(1, $pageCount - $edge + 1); $i <= $pageCount; $i++) {
            $pages[$i] = true;
        }

        // 当前页邻域
        list($start, $end) = $this->windowRange($page, $pageCount, $window);
        for ($i = $start; $i <= $end; $i++) {
            $pages[$i] = true;
        }

        $pages = array_keys($pages);
        sort($pages);

        $link = '';
        $prev = null;
        foreach ($pages as $p) {
            if ($prev !== null) {
                $gap = $p - $prev;
                if ($gap === 2) {
                    // 中间只藏了 1 页，直接列出来比放省略号更清爽
                    $link .= $this->pageTag($prev + 1, $page, $url);
                } elseif ($gap > 2) {
                    $link .= $this->ellipsis;
                }
            }
            $link .= $this->pageTag($p, $page, $url);
            $prev = $p;
        }
        return $link;
    }

    /**
     * 生成单个页码的 <a> 标签；当前页不带 href、带上 $currentClass
     *
     * @param int    $p    页码
     * @param int    $page 当前页码
     * @param string $url  链接模板
     * @return string
     */
    private function pageTag($p, $page, $url)
    {
        if ($page == $p) {
            return '<a class="' . $this->currentClass . '">' . $p . '</a>';
        }
        return '<a href="' . $this->url($url, $p) . '">' . $p . '</a>';
    }

    /**
     * 把链接模板里的 {page} 替换成实际页码
     *
     * @param string $url  链接模板
     * @param int    $page 页码
     * @return string
     */
    public function url($url, $page)
    {
        return str_replace('{page}', $page, urldecode($url));
    }
}
