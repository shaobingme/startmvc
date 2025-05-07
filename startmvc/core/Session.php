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

class Session
{
    /**
     * 获取带前缀的会话键名
     * @param string $key 原始键名
     * @return string 带前缀的键名
     */
    private static function getKey($key)
    {
        $conf = Config::load();
        return $conf['session_prefix'] . $key;
    }
    
    /**
     * 设置会话值
     * @param string $key 键名
     * @param mixed $val 值
     * @return void
     */
    public static function set($key, $val)
    {
        $_SESSION[self::getKey($key)] = $val;
    }
    
    /**
     * 获取会话值
     * @param string $key 键名
     * @param array $options 处理选项
     * @return mixed
     */
    public static function get($key, $options = [])
    {
        $prefixedKey = self::getKey($key);
        $val = isset($_SESSION[$prefixedKey]) ? $_SESSION[$prefixedKey] : null;
        return Http::handling($val, $options);
    }
    
    /**
     * 检查会话键是否存在
     * @param string $key 键名
     * @return bool
     */
    public static function has($key)
    {
        return isset($_SESSION[self::getKey($key)]);
    }
    
    /**
     * 删除会话值
     * @param string $key 键名
     * @return void
     */
    public static function delete($key)
    {
        unset($_SESSION[self::getKey($key)]);
    }
    
    /**
     * 清空所有会话
     * @return void
     */
    public static function clear()
    {
        $_SESSION = [];
    }
    
    /**
     * 销毁会话
     * @return bool
     */
    public static function destroy()
    {
        self::clear();
        return session_destroy();
    }
    
    /**
     * 获取所有会话数据
     * @param bool $withPrefix 是否保留前缀
     * @return array
     */
    public static function all($withPrefix = false)
    {
        if ($withPrefix) {
            return $_SESSION;
        }
        
        $prefix = Config::load()['session_prefix'];
        $prefixLength = strlen($prefix);
        $result = [];
        
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $newKey = substr($key, $prefixLength);
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
}
