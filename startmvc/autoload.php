<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */
class Autoload {
	public static function load($class) {
		$file = strtr(ROOT_PATH.'/'.$class.'.php', '\\', '/');
		if (is_file($file)) include $file;
	}
	
	/**
	 * 注册自动加载函数
	 */
	public static function register() {
		spl_autoload_register('Autoload::load');
	}
}