<?php
/**
 * StartMVC 应用全局函数
 *
 * 由 App::loadUserFunctions() 在 boot.php 中加载，CLI 与 HTTP 行为一致。
 * 需要拆分时，把函数文件放到 app/common/functions/ 下，再在这里 require_once。
 *
 * 约定：每个函数都用 function_exists() 包裹——全局函数重复定义是致命错误，
 * 无法用 try/catch 捕获，只能靠这个守卫避免。
 */

if (!function_exists('test')) {
    /**
     * 示例函数
     *
     * @param string $para
     * @return string
     */
    function test($para = '')
    {
        return '这是自定义的 test 函数';
    }
}
