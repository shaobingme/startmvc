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
];