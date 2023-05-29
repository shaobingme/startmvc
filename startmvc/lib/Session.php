<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace startmvc\lib;
class Session
{
    public static function set($key, $val)
    {
        $conf = Config::load();
        $_SESSION[$conf['session_prefix'] . $key] = $val;
    }
    public static function get($key, $options = [])
    {
        $conf = Config::load();
        $val = isset($_SESSION[$conf['session_prefix'] . $key]) ? $_SESSION[$conf['session_prefix'] . $key] : null;
        return Http::handling($val, $options);
    }
    public static function delete($key)
    {
        $conf = Config::load();
        unset($_SESSION[$conf['session_prefix'] . $key]);
    }
}
