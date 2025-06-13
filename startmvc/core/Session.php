<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      https://startmvc.com
 */

namespace startmvc\core;

class Session
{
    /**
     * Session键名前缀
     * @var string|null
     */
    private static $prefix = null;

    /**
     * 获取带前缀的会话键名
     * @param string $key 原始键名
     * @return string 带前缀的键名
     */
    private static function getKey($key)
    {
        // 首次加载时初始化前缀
        if (self::$prefix === null) {
            $conf = Config::load('common');
            self::$prefix = $conf['session_prefix'] ?? '';
        }
        return self::$prefix . $key;
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
     * @param mixed $default 默认值（当键不存在时返回）
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $prefixedKey = self::getKey($key);
        return $_SESSION[$prefixedKey] ?? $default;
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
     * 清空当前框架创建的所有会话
     * @return void
     */
    public static function clear()
    {
        $prefix = self::$prefix ?? (Config::load('common')['session_prefix'] ?? '');
        foreach (array_keys($_SESSION) as $key) {
            // 只处理带前缀且非空的键
            if ($prefix !== '' && strpos($key, $prefix) === 0) {
                unset($_SESSION[$key]);
            }
        }
    }

    /**
     * 销毁整个会话
     * @return bool 成功返回 true，失败返回 false
     */
    public static function destroy()
    {
        $result = session_destroy();
        $_SESSION = []; // 清空当前脚本中的会话数据
        return $result;
    }

    /**
     * 获取所有会话数据
     * @param bool $withPrefix 是否保留前缀
     * @return array 会话数据数组
     */
    public static function all($withPrefix = false)
    {
        $prefix = self::$prefix ?? (Config::load('common')['session_prefix'] ?? '');
        $result = [];
        
        foreach ($_SESSION as $key => $value) {
            // 只处理带前缀且非空的键
            if ($prefix !== '' && strpos($key, $prefix) === 0) {
                $newKey = $withPrefix ? $key : substr($key, strlen($prefix));
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * 启动会话（确保会话已初始化）
     * @return void
     */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // 加载会话安全配置
            $config = Config::load('common');
            $sessionConfig = $config['session'] ?? [];
            
            // 应用会话配置
            if (!empty($sessionConfig)) {
                // 设置会话cookie参数
                if (isset($sessionConfig['cookie_lifetime'])) {
                    session_set_cookie_params(
                        $sessionConfig['cookie_lifetime'],      // lifetime
                        '/',                                    // path
                        '',                                     // domain
                        false,                                  // secure
                        $sessionConfig['cookie_httponly'] ?? true  // httponly
                    );
                }
                
                // 设置其他会话选项
                foreach ($sessionConfig as $option => $value) {
                    if ($option !== 'cookie_lifetime' && $option !== 'cookie_httponly') {
                        $iniOption = 'session.' . $option;
                        ini_set($iniOption, $value);
                    }
                }
            }
            
            // 启动会话
            session_start();
            
            // 防止会话固定攻击，定期更新会话ID (每30分钟)
            if (!isset($_SESSION['_last_regenerate']) || 
                (time() - $_SESSION['_last_regenerate'] > 1800)) {
                session_regenerate_id(true);
                $_SESSION['_last_regenerate'] = time();
            }
        }
    }
}