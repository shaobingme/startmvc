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
     * 获取所有输入（静态方法）
     * @return array
     */
    public static function all()
    {
        return array_merge($_GET, $_POST);
    }
    
    /**
     * 获取输入值（静态方法）
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function input($key = null, $default = null)
    {
        $data = self::all();
        return $key ? ($data[$key] ?? $default) : $data;
    }
    
    /**
     * 获取请求头（静态方法）
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function header($key = null, $default = null)
    {
        $headers = function_exists('getallheaders') ? getallheaders() : self::headers();
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
     * 判断是否为AJAX请求（静态方法）
     * @return bool
     */
    public static function isAjax()
    {
        return self::header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * 获取GET参数
     * @param string $key 键名
     * @param array $options 处理选项
     * @return mixed
     */
    public static function get($key, $options = [])
    {
        $val = isset($_GET[$key]) ? $_GET[$key] : null;
        return Http::handling($val, $options);
    }

    /**
     * 获取POST参数
     * @param string $key 键名(为空则返回所有POST数据)
     * @param array $options 处理选项
     * @return mixed
     */
    public static function post($key = '', $options = [])
    {
        $val = isset($_POST[$key]) ? $_POST[$key] : ($_POST ?: null);
        return Http::handling($val, $options);
    }

    /**
     * 获取原始POST输入
     * @return string
     */
    public static function postInput()
    {
        return file_get_contents('php://input');
    }

    /**
     * 获取JSON格式的POST数据
     * @param bool $assoc 是否转换为关联数组
     * @return mixed
     */
    public static function getJson($assoc = true)
    {
        return json_decode(self::postInput(), $assoc);
    }

    /**
     * 获取所有请求头
     * @return array
     */
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

    /**
     * 获取请求方法
     * @return string
     */
    public static function method()
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    /**
     * 判断是否为GET请求
     * @return bool
     */
    public static function isGet()
    {
        return self::method() === 'GET';
    }

    /**
     * 判断是否为POST请求
     * @return bool
     */
    public static function isPost()
    {
        return self::method() === 'POST';
    }

    /**
     * 判断是否为HTTPS请求
     * @return bool
     */
    public static function isHttps()
    {
        return isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)
            || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
    }

    /**
     * 获取客户端IP地址
     * @return string
     */
    public static function ip()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $match)) {
            foreach ($match[0] as $xip) {
                if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                    $ip = $xip;
                    break;
                }
            }
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        
        return $ip;
    }
}