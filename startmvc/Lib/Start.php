<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace Startmvc\Lib;

use Startmvc\Lib\Db\Sql;
use Startmvc\Lib\Loader;

abstract class Start
{
    public $conf;
    protected $db;
    public function __construct()
    {
        $this->conf = include CONFIG_PATH . 'common.php';
        if($this->conf['db_auto_connect']){
	    	$dbConf = include CONFIG_PATH . '/database.php';
	        if ($dbConf['default'] != '') {
	            $this->db= new Sql($dbConf['connections'][$dbConf['default']]);
	        }
        }
        if($this->conf['cache_status']){
	        $this->cache=new \Startmvc\Lib\Cache($this->conf['cache_type'],$this->conf['cache_host'],$this->conf['cache_port']);
        }
    }
    protected function model($model, $module = MODULE)
    {       
        //if($model){
	       // $model = APP_NAMESPACE.'\\' . ($module != '' ? $module . '\\' : '') . 'Model\\' . $model . 'Model';
        //}else{
	       // $model = CORE_PATH.'\\Model';
        //}
        

        $model = APP_NAMESPACE.'\\' . ($module != '' ? $module . '\\' : '') . 'Model\\' . $model . 'Model';

        return Loader::getInstance($model);
    }
    protected function url($url)
    {
        $url = $url . $this->conf['url_suffix'];
        if ($this->conf['urlrewrite']) {
            $url = '/' . $url;
        } else {
            $url = '/index.php/' . $url;
        }
        return str_replace('%2F', '/', urlencode($url));
    }
    protected function lang($key)
    {
    	static $lang = array();
    	$locale = $this->conf['locale']?:'zh_cn';
    	if ($this->conf['muti_module']) {
    		$lang_path = APP_PATH .MODULE.'/Language/'.$locale.'.php';
		}else{
	    	$lang_path = APP_PATH .'Language/'.$locale.'.php';
    	}
    	if(is_file($lang_path)){
			$lang=include $lang_path;
    	}else{
	    	die('语言包文件不存在');
    	}
    	return $key?$lang[$key]:$key;

    }

}