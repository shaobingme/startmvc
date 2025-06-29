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

class Cookie
{
    /**
     * 获取带前缀的Cookie键名
     * @param string $key 原始键名
     * @return string 带前缀的键名
     */
    private static function getKey($key)
    {
	    $config=Config::load('common');
        return $config['cookie_prefix'] . $key;
    }
    
    /**
     * 设置Cookie值
     * @param string $key Cookie键名
     * @param mixed $val Cookie值
     * @param array $options 选项
     *        - expire: 过期时间(时间戳)，默认为0(会话结束)
     *        - path: Cookie路径，默认为'/'
     *        - domain: Cookie域，默认为null
     *        - secure: 是否仅通过HTTPS传输，默认为false
     *        - httponly: 是否仅允许HTTP访问，默认为true
     * @return bool 是否成功设置
     */
    public static function set($key, $val, $options = [])
    {
        $expire = isset($options['expire']) ? $options['expire'] : 0;
        $path = isset($options['path']) ? $options['path'] : '/';
        $domain = isset($options['domain']) ? $options['domain'] : null;
        $secure = isset($options['secure']) ? $options['secure'] : false;
        $httponly = isset($options['httponly']) ? $options['httponly'] : true;
        
        return setcookie(self::getKey($key), $val, $expire, $path, $domain, $secure, $httponly);
    }
    
    /**
     * 获取Cookie值
     * @param string $key Cookie键名
     * @param array $options 处理选项，传递给Http::handling方法
     * @return mixed
     */
    public static function get($key, $options = [])
    {
        $prefixedKey = self::getKey($key);
        $val = isset($_COOKIE[$prefixedKey]) ? $_COOKIE[$prefixedKey] : null;
        return Http::handling($val, $options);
    }
    
    /**
     * 检查Cookie是否存在
     * @param string $key Cookie键名
     * @return bool
     */
    public static function has($key)
    {
        return isset($_COOKIE[self::getKey($key)]);
    }
    
    /**
     * 删除Cookie
     * @param string $key Cookie键名
     * @param array $options 选项
     *        - path: Cookie路径，默认为'/'
     *        - domain: Cookie域，默认为null
     *        - secure: 是否仅通过HTTPS传输，默认为false
     *        - httponly: 是否仅允许HTTP访问，默认为true
     * @return bool 是否成功删除
     */
    public static function delete($key, $options = [])
    {
        $path = isset($options['path']) ? $options['path'] : '/';
        $domain = isset($options['domain']) ? $options['domain'] : null;
        $secure = isset($options['secure']) ? $options['secure'] : false;
        $httponly = isset($options['httponly']) ? $options['httponly'] : true;
        
        return setcookie(self::getKey($key), '', time()-1, $path, $domain, $secure, $httponly);
    }
    
    /**
     * 获取所有Cookie值
     * @param bool $withPrefix 是否保留前缀
     * @return array
     */
    public static function all($withPrefix = false)
    {
        if ($withPrefix) {
            return $_COOKIE;
        }
        
        $prefix = Config::load()['cookie_prefix'];
        $prefixLength = strlen($prefix);
        $result = [];
        
        foreach ($_COOKIE as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $newKey = substr($key, $prefixLength);
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
}
