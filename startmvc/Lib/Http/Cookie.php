<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace Startmvc\Lib\Http;

class Cookie extends Http
{
    public static function set($key, $val, $options = [])
    {
        $expire = isset($options['expire']) ? $options['expire'] : 0;
        $path = isset($options['path']) ? $options['path'] : '/';
        $domain = isset($options['domain']) ? $options['domain'] : null;
        $secure = isset($options['secure']) ? $options['secure'] : false;
        $httponly = isset($options['httponly']) ? $options['httponly'] : true;
        $conf = self::getConfig();
        setcookie($conf['cookie_prefix'] . $key, $val, $expire, $path, $domain, $secure, $httponly);
    }
    public static function get($key, $options = [])
    {
        $conf = self::getConfig();
        $val = isset($_COOKIE[$conf['cookie_prefix'] . $key]) ? $_COOKIE[$conf['cookie_prefix'] . $key] : null;
        return self::handling($val, $options);
    }
    public static function delete($key, $options = [])
    {
        $path = isset($options['path']) ? $options['path'] : '/';
        $domain = isset($options['domain']) ? $options['domain'] : null;
        $secure = isset($options['secure']) ? $options['secure'] : false;
        $httponly = isset($options['httponly']) ? $options['httponly'] : true;
        $conf = self::getConfig();
        setcookie($conf['cookie_prefix'] . $key, '', time()-1, $path, $domain, $secure, $httponly);
    }
}
