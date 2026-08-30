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
        // cookie_prefix 缺失时回退空串，避免配置缺失直接报错
        $config = Config::load('common');
        return ($config['cookie_prefix'] ?? '') . $key;
    }

    /**
     * 设置Cookie值
     * @param string $key Cookie键名
     * @param mixed $val Cookie值
     * @param array $options 选项（显式传入的优先于配置）
     *        - expire: 过期时间(时间戳)，默认为0(会话结束)
     *        - path: Cookie路径，默认为'/'
     *        - domain: Cookie域，默认为''
     *        - secure: 是否仅通过HTTPS传输，支持 true/false/'auto'（默认取配置 cookie.cookie_secure，缺省 'auto' 自动检测）
     *        - httponly: 是否仅允许HTTP访问，默认为true
     *        - samesite: 防CSRF策略，默认取配置 cookie.cookie_samesite，缺省 'Lax'
     * @return bool 是否成功设置
     */
    public static function set($key, $val, $options = [])
    {
        return self::send($key, $val, $options);
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
     * @param array $options 选项，含义同 set()
     * @return bool 是否成功删除
     */
    public static function delete($key, $options = [])
    {
        $options['expire'] = time() - 1;
        return self::send($key, '', $options);
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

        // 原实现 Config::load() 无参调用返回 null 再取下标，PHP 8 下直接报错；
        // 统一走 common 配置并带回退默认值
        $prefix = Config::load('common')['cookie_prefix'] ?? '';
        $prefixLength = strlen($prefix);
        $result = [];

        foreach ($_COOKIE as $key => $value) {
            if ($prefix !== '' && strpos($key, $prefix) === 0) {
                $newKey = substr($key, $prefixLength);
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * 实际发送 Set-Cookie 头
     *
     * 安全加固（与 Session::start 策略对齐）：
     * - secure 支持 true/false/'auto'，'auto' 自动检测 HTTPS
     * - SameSite 默认 Lax，防 CSRF（None 必须配合 secure=true）
     *
     * @param string $key Cookie键名（已含前缀）
     * @param string $val Cookie值
     * @param array $options 选项
     * @return bool
     */
    private static function send($key, $val, $options = [])
    {
        $config = Config::load('common');
        $cookieConfig = is_array($config['cookie'] ?? null) ? $config['cookie'] : [];

        $expire = isset($options['expire']) ? $options['expire'] : 0;
        $path = isset($options['path']) ? $options['path'] : '/';
        $domain = isset($options['domain']) ? $options['domain'] : '';
        $httponly = isset($options['httponly']) ? $options['httponly'] : true;

        // secure：显式传入 > 配置值；'auto' 自动检测 HTTPS（与 Session 一致）
        $secure = $options['secure'] ?? ($cookieConfig['cookie_secure'] ?? 'auto');
        if ($secure === 'auto' || $secure === null) {
            $secure = Request::isHttps();
        }

        $samesite = $options['samesite'] ?? ($cookieConfig['cookie_samesite'] ?? 'Lax');

        if (PHP_VERSION_ID >= 70300) {
            // PHP >= 7.3 数组签名，原生支持 SameSite
            return setcookie(self::getKey($key), $val, [
                'expires'  => $expire,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ]);
        }

        // PHP < 7.3 通过 path 后缀方式兼容 SameSite（与 Session 相同的做法）
        return setcookie(
            self::getKey($key),
            $val,
            $expire,
            $path . '; samesite=' . $samesite,
            $domain,
            $secure,
            $httponly
        );
    }
}
