<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

use startmvc\core\Config;
use startmvc\core\Cache;

/**
 * 语言包调用
 *
 * @param string $key
 * @param string $default (可选) 默认值
 * @return string
 * @throws \Exception
 */
function lang($key, $default = '') {
	static $langCache = [];
	if (empty($key)) {
		return $default;
	}
	// 如果语言包已经加载过，则直接返回对应的值
	if (isset($langCache[$key])) {
		return $langCache[$key];
	}

	$conf = include ROOT_PATH . '/config/common.php';
	$locale = $conf['locale'] ?: 'zh_cn';
	$langPath = APP_PATH . MODULE . '/language/' . $locale . '.php';

	if (is_file($langPath)) {
		$lang = include $langPath;
		if (!empty($lang[$key])) {
			$langCache[$key] = $lang[$key];
			return $lang[$key];
		}
	} else {
		throw new \Exception('语言包文件不存在');
	}

	// 如果未找到对应的语言包键值，则返回默认值或者键名本身
	return $default ?: $key;
}


/**
 * 格式化变量输出
 *
 * @param mixed $var
 * @param string $label
 * @param boolean $echo
 */
function dump($var, $label = null, $echo = true)
{
	ob_start();
	var_dump($var);
	$output = ob_get_clean();
	$output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);

	$cli = preg_match("/cli/i", PHP_SAPI) ? true : false;

	if ($cli === true) {
		$output = PHP_EOL . $label . PHP_EOL . $output . PHP_EOL;
	} else {
		$output = '<pre>' . PHP_EOL . $label . PHP_EOL . $output . '</pre>' . PHP_EOL;
	}

	if ($echo) {
		echo $output;
	}

	return $output;
}

/**
 * 配置文件函数
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function config($key = null, $default = null)
{
	static $config = null;
	
	// 如果配置未加载，先加载配置
	if ($config === null) {
		// 加载公共配置文件
		$commonConfig = require ROOT_PATH . '/config/common.php';
		
		// 如果有环境配置文件，也加载它
		$envConfig = [];
		if (file_exists(ROOT_PATH . '/config/' . ENV . '.php')) {
			$envConfig = require ROOT_PATH . '/config/' . ENV . '.php';
		}
		
		// 合并配置
		$config = array_merge($commonConfig, $envConfig);
	}
	
	// 如果没有指定 key，返回所有配置
	if ($key === null) {
		return $config;
	}
	
	// 返回指定配置项，如果不存在返回默认值
	return isset($config[$key]) ? $config[$key] : $default;
}

/**
 * 缓存助手函数
 * 
 * @param string $name 缓存名称（注意命名唯一性，防止重复）
 * @param mixed $value 缓存值，为null时表示获取缓存
 * @param int $expire 缓存时间（秒），默认3600秒
 * @param string $driver 缓存驱动，默认使用配置中的驱动
 * @return mixed 获取缓存时返回缓存值，设置缓存时返回true/false
 */
function cache($name, $value = null, $expire = 3600, $driver = null)
{
    static $instance = [];
    
    // 获取缓存驱动实例
    $driverName = $driver ?: config('cache.drive', 'file');
    if (!isset($instance[$driverName])) {
        $instance[$driverName] = Cache::store($driverName);
    }
    
    // 获取缓存
    if ($value === null) {
        return $instance[$driverName]->get($name);
    }
    
    // 删除缓存
    if ($value === false) {
        return $instance[$driverName]->delete($name);
    }
    
    // 自定义缓存参数
    $cacheConfig = config('cache.' . $driverName, []);
    if ($expire !== 3600) {
        $cacheConfig['cacheTime'] = $expire;
    }
    
    // 设置缓存
    return $instance[$driverName]->set($name, $value);
}

/**
 * url的方法
 */
function url($url){
	$url = $url . config('url_suffix');
	if (config('urlrewrite')) {
		$url = '/' . $url;
	} else {
		$url = '/index.php/' . $url;
	}
	return str_replace('%2F', '/', urlencode($url));
}

/**
 * 获取客户端的真实IP地址
 */
function get_ip() {
	// 优先检查HTTP_X_FORWARDED_FOR，因为它可能包含多个IP，我们取第一个非未知的IP
	$ip = null;
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
		foreach ($ips as $tmp) {
			$ip = trim($tmp);
			if ($ip !== 'unknown') {
				break;
			}
		}
	}

	// 如果没有通过HTTP_X_FORWARDED_FOR获取到IP，尝试其他可能的服务器变量
	if (!$ip) {
		$ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_CDN_SRC_IP'] ?? '0.0.0.0';
	}

	// 验证IP地址格式，如果不是有效的IPv4或IPv6，返回默认值
	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
		$ip = '0.0.0.0';
	}

	return $ip;
}