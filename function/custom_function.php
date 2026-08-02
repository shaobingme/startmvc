<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */

/**
 * 用户自定义函数保留文件
 *
 * 框架内置或按主题整理的助手函数，建议拆分到 function 目录下的独立文件中，
 * 以减少升级时与用户自定义内容产生冲突。
 */

if(!function_exists('test')){
    /**
     * 自定义函数
     *
     * @param      string  $para   The para
     */
    function test($para='') {
        return '这是自定的test函数';
    }
}

