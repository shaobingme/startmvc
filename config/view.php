<?php
/**
 * 视图配置
 */
return [
    'suffix' => '.php', // 模板文件后缀,默认php，可配置为html，.blade, .tpl, .twig, .phtml之类的
    'tpl_safe_mode' => false, // 模板安全模式，开启后禁用 {php}、{echo} 标签（后台可编辑模板的场景建议开启）
    'layout' => '', // 布局模板名（相对当前模块 view 目录），留空 = 关闭布局
    // 模板编译缓存时间(秒)，0 = 关闭（默认）。开启后模板只在改动时重新编译，
    // {include} 引入的文件被改动也会触发重编译（依赖追踪）。
    // 也兼容旧位置：顶层 tpl_cache_time（config/common.php 或 config/local.php），本组优先。
    // 'tpl_cache_time' => 60,
]; 