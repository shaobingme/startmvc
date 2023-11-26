<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */


/**
 * 语言包调用
 *
 * @param string $str
 * @return string
 */
use startmvc\lib\Config;
function lang($key) {
	$lang = array();
	$conf = include ROOT_PATH . '/config/common.php';
	$locale = $conf['locale']?:'zh_cn';
	$lang_path = APP_PATH .MODULE.'/language/'.$locale.'.php';
	if(is_file($lang_path)){
		$lang=include $lang_path;
	}else{
		throw new \Exception('语言包文件不存在');
	}
	$lang_word=!empty($lang)?$lang[$key]:'';
	return $key?$lang[$key]:$key;
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