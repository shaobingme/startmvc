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
     * @param array|mixed $options 处理选项；传入标量时视为默认值 default
     * @return mixed
     */
    public static function post($key = '', $options = [])
    {
        // 支持 Request::post('age', 0) 简写：标量 options 视为默认值
        if (!is_array($options)) {
            $options = ['default' => $options];
        }

        // 不传 key 时返回所有 POST 数据；传了 key 但不存在时返回 null（交由 handling 走默认值逻辑）
        if ($key === '' || $key === null) {
            $val = $_POST ?: null;
        } else {
            $val = array_key_exists($key, $_POST) ? $_POST[$key] : null;
        }

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
     * 仅当 REMOTE_ADDR 命中可信代理列表（config: trusted_proxies）时才解析 X-Forwarded-For，
     * 且从右向左取第一个非可信代理的地址（最右侧由最近的代理追加，客户端无法伪造），
     * 否则一律返回 REMOTE_ADDR，防止通过伪造请求头绕过登录日志、限流、审计。
     * @return string
     */
    public static function ip()
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $trustedProxies = (array)Config::get('trusted_proxies', []);
        if (empty($trustedProxies) || !self::isTrustedProxy($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }

        // 请求来自可信代理：从 X-Forwarded-For 最右侧向左找第一个非可信代理的有效IP
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            for ($i = count($ips) - 1; $i >= 0; $i--) {
                $ip = $ips[$i];
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    continue;
                }
                if (!self::isTrustedProxy($ip, $trustedProxies)) {
                    return $ip;
                }
            }
        }

        // X-Forwarded-For 无有效值时回退到 REMOTE_ADDR
        return $remoteAddr;
    }

    /**
     * 判断IP是否命中可信代理列表（支持精确IP和IPv4 CIDR）
     * @param string $ip 待检查的IP
     * @param array $trustedProxies 可信代理列表
     * @return bool
     */
    private static function isTrustedProxy($ip, array $trustedProxies)
    {
        foreach ($trustedProxies as $proxy) {
            $proxy = trim($proxy);
            if ($proxy === '') {
                continue;
            }
            if (strpos($proxy, '/') !== false) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && self::ipv4InCidr($ip, $proxy)) {
                    return true;
                }
            } elseif (strcasecmp($proxy, $ip) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断IPv4是否落在指定CIDR网段内
     * @param string $ip IPv4地址
     * @param string $cidr CIDR网段，如 10.0.0.0/8
     * @return bool
     */
    private static function ipv4InCidr($ip, $cidr)
    {
        list($subnet, $bits) = explode('/', $cidr);
        $bits = (int)$bits;
        if ($bits < 0 || $bits > 32
            || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        $mask = -1 << (32 - $bits);
        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }
}