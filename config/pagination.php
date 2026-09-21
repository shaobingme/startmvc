<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
//分页配置
return [
    'theme' => '%header% %first% %prev% %link% %next% %last%',//分页样式
    //分页头部：%count% = 记录总数，%page% = 当前页，%pageCount% = 总页数
    'header' => '共 %count% 条记录 第 %page% / %pageCount% 页',
    'first' => '首页',//首页
    'last' => '末页',//末页
    'prev' => '上一页',//上一页
    'next' => '下一页',//下一页
    'currentClass' => 'is-current',//当前页码类

    //省略号：留空 = 关闭，页码连续排列（与旧版本行为完全一致）
    //开启后页码区变成「首尾锚点 + 当前页邻域」，断开处用省略号连接，例如：
    //  1 2 … 5 6 7 8 9 … 78 79
    //邻域大小由 Show() 的第 5 个参数 $pageShowCount 控制（开启省略号时建议传 5）
    'ellipsis' => '',//省略号 HTML，例：'<span class="dots">…</span>'
    'edgeCount' => 2,//省略号开启时，首尾各锚定几页（填 0 则不要首尾锚点）
];