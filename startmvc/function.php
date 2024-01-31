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
 * @param string $name
 * @param string $value
 * @param string $file
 * @return mixed
 */

function config($name = '', $value = '',$file='common') {
	$config=Config::load($file);
	if ('' === $value) {
		return $config[$name];
	}
	return $config[$name]=$value;
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