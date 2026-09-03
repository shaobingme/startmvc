<?php
/**
 * 视图配置
 */
return [
    'suffix' => '.php', // 模板文件后缀,默认php，可配置为html，.blade, .tpl, .twig, .phtml之类的
    'auto_escape' => true, // {$var} 默认 HTML 转义防止 XSS；原文输出请使用 {html $var} 或 {raw $var}，设为 false 可关闭自动转义
];