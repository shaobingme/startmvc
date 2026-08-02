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
 * CSRF 助手函数
 *
 * 按主题独立拆分，便于框架升级时维护，也避免与用户在 custom_function.php
 * 中编写的自定义函数发生修改冲突。
 */

if (!function_exists('csrf_token')) {
    /**
     * 获取当前 CSRF Token（不存在或已过期时自动生成新的）
     *
     * @return string
     */
    function csrf_token()
    {
        return \startmvc\core\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * 生成 CSRF 表单隐藏域，在表单内输出即可：
     * <form method="post"><?php echo csrf_field(); ?></form>
     *
     * @return string
     */
    function csrf_field()
    {
        $config = config('csrf') ?: [];
        $name = $config['token_name'] ?? 'csrf_token';

        return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" value="' . htmlspecialchars(\startmvc\core\Csrf::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_meta')) {
    /**
     * 生成 CSRF meta 标签（放在 <head> 中），供 AJAX 请求读取后放入请求头：
     * var token = document.querySelector('meta[name="csrf-token"]').content;
     * fetch(url, {method:'POST', headers:{'X-CSRF-TOKEN': token}, body: data});
     *
     * @return string
     */
    function csrf_meta()
    {
        $config = config('csrf') ?: [];
        $name = $config['token_name'] ?? 'csrf_token';

        return '<meta name="csrf-token-name" content="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">' . "\n"
            . '<meta name="csrf-token" content="' . htmlspecialchars(\startmvc\core\Csrf::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
