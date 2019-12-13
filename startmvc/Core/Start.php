<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2021
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace Startmvc\Core;

use Startmvc\Lib\Db\Sql;
use Startmvc\Di;

abstract class Start
{
    public $conf;
    public static $dataContainer;
    public function __construct()
    {
        $this->conf = include ROOT_PATH . '/config/common.php';
        if(DB_AUTO_CONNECT){
	    	$dbConf = include ROOT_PATH . '/config/database.php';
	        if ($dbConf['driver'] != '') {
	            if (Start::$dataContainer == null) {
	                Start::$dataContainer = new Sql($dbConf);            
	            }
	            $this->db= Start::$dataContainer;
	        }
        }
        if($this->conf['cache_status']){
	        $this->cache=new \Startmvc\Lib\Cache($this->conf['cache_type'],$this->conf['cache_host'],$this->conf['cache_port']);
        }
    }
    protected function model($model, $module = MODULE)
    {       
        $model = APP_NAMESPACE.'\\' . ($module != '' ? $module . '\\' : '') . 'Model\\' . $model . 'Model';
        return Di::getInstance($model);
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

}