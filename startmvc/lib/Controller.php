<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */
 
namespace startmvc\lib;
use startmvc\lib\http\Request;
use startmvc\lib\db\Sql;
use startmvc\lib\Loader;
use startmvc\lib\View;

abstract class Controller
{

	public $conf;
	protected $db;
	public $assign;
	protected $view;
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
			$this->cache=new \startmvc\lib\Cache($this->conf['cache_type'],$this->conf['cache_host'],$this->conf['cache_port']);
		}
		$this->view = new View();
	}
	/**
	 * 模型定义
	 */
	protected function model($model, $module = MODULE)
	{
		//if($model){
		   // $model = APP_NAMESPACE.'\\' . ($module != '' ? $module . '\\' : '') . 'Model\\' . $model . 'Model';
		//}else{
		   // $model = CORE_PATH.'\\Model';
		//}
		$model = APP_NAMESPACE.'\\' . $module . '\\'. 'Model\\' . $model . 'Model';
		return Loader::getInstance($model);
	}
	/**
	 * url的方法
	 */
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
	/**
	 * 语言调用
	 */
	protected function lang($key)
	{
		static $lang = array();
		$locale = $this->conf['locale']?:'zh_cn';
		$lang_path = APP_PATH .MODULE.'/Language/'.$locale.'.php';
		if(is_file($lang_path)){
			$lang=include $lang_path;
		}else{
			throw new \Exception('语言包文件不存在');
		}
		return $key?$lang[$key]:$key;

	}

	/**
	 * 为模板对象赋值
	 */
	protected function assign($name, $data=null)
	{
		$this->view->assign($name, $data);

	}

	/**
	 * 调用视图
	 */
	 
	protected function display($tplfile = null,$data=array())
	{
		$this->view->display($tplfile,$data);
	}
	
	/**
	 * 调用内容
	 */

	public function content($content)
	{
		header('Content-Type:text/plain; charset=utf-8');
		echo $content;
	}
	protected function success($msg='',$url='',$data=[],$ajax=false)
	{
		$this->response(1,$msg,$url,$data,$ajax);
	}
	protected function error($msg='',$url='',$data=[],$ajax=false)
	{
		$this->response(0,$msg,$url,$data,$ajax);
	}
	protected function response($code='',$msg='',$url='',$data=[],$ajax=false)
	{
		if($ajax || Request::isAjax()){
			$data=[
				'code'=>$code,//1-成功 0-失败
				'msg'=>$msg,
				'url'=>$url,
				'data'=>$data,
			];
			$this->json($data);
		}else{
			include '../startmvc/lib/location.php';
			exit();
		}

	}

	/**
	 * json方法
	 */
	protected function json($data)
	{
		header('Content-Type:application/json; charset=utf-8');
		//echo json_encode($data, JSON_UNESCAPED_UNICODE);
		exit(json_encode($data, JSON_UNESCAPED_UNICODE));
	}


	/**
	 * 跳转
	 */
	protected function redirect($url)
	{
		header('location:' . $url);
		exit();
	}
	/**
	 * 404方法
	 */
	protected function notFound()
	{
		header("HTTP/1.1 404 Not Found");  
		header("Status: 404 Not Found");
	}
	public function __call($fun, $arg)
	{
		$this->notFound();
	}
}
