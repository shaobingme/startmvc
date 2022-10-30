<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */


	/**
	 * 语言包调用
	 *
	 * @param string $str
	 * @return string
	 */
	
    function lang($key) {
    	$lang = array();
    	$conf = include ROOT_PATH . '/config/common.php';
    	$locale = $conf['locale']?:'zh_cn';
    	$lang_path = APP_PATH .MODULE.'/Language/'.$locale.'.php';
    	if(is_file($lang_path)){
			$lang=include $lang_path;
    	}else{
	    	die('语言包文件不存在');
    	}
    	$lang_word=!empty($lang)?$lang[$key]:'';
    	return $key?$lang[$key]:$key;
    }
