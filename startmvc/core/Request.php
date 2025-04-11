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
class Request
{
    /**
     * 获取所有输入
     * @return array
     */
    public function all()
    {
        return array_merge($_GET, $_POST);
    }
    
    /**
     * 获取输入值
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        $data = $this->all();
        return $key ? ($data[$key] ?? $default) : $data;
    }
    
    /**
     * 获取请求头
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function header($key = null, $default = null)
    {
        $headers = getallheaders();
        if ($key) {
            $key = strtolower($key);
            foreach ($headers as $headerKey => $value) {
                if (strtolower($headerKey) === $key) {
                    return $value;
                }
            }
            return $default;
        }
        return $headers;
    }
    
    /**
     * 判断是否为AJAX请求
     * @return bool
     */
    public function isAjax()
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public static function get($key, $options = [])
    {
        $val = isset($_GET[$key]) ? $_GET[$key] : null;
        return Http::handling($val, $options);
    }
    public static function post($key='', $options = [])
    {
        $val = isset($_POST[$key]) ? $_POST[$key] : $_POST;
        return Http::handling($val, $options);
    }
    public static function postInput()
    {
        $val = file_get_contents('php://input');
        return $val;
    }
    public static function headers()
    {
        $headers = []; 
        foreach ($_SERVER as $key => $value) { 
            if ('HTTP_' == substr($key, 0, 5)) { 
                $headers[ucfirst(strtolower(str_replace('_', '-', substr($key, 5))))] = $value; 
            } 
        }
        return $headers;
    }
    public static function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }
    public static function isGet()
    {
        return strtoupper($_SERVER['REQUEST_METHOD']) == 'GET';
    }
    public static function isPost()
    {
        return strtoupper($_SERVER['REQUEST_METHOD']) == 'POST';
    }
}