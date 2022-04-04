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

class Session extends Http
{
    public static function set($key, $val)
    {
        $conf = self::getConfig();
        $_SESSION[$conf['session_prefix'] . $key] = $val;
    }
    public static function get($key, $options = [])
    {
        $conf = self::getConfig();
        $val = isset($_SESSION[$conf['session_prefix'] . $key]) ? $_SESSION[$conf['session_prefix'] . $key] : null;
        return self::handling($val, $options);
    }
    public static function delete($key)
    {
        $conf = self::getConfig();
        unset($_SESSION[$conf['session_prefix'] . $key]);
    }
}
