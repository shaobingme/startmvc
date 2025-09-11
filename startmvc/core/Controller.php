<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */
 
namespace startmvc\core;
use startmvc\core\Request;
use startmvc\core\Loader;
use startmvc\core\View;

abstract class Controller
{
	public $conf;
	public $assign;
	protected $view;
	
	public function __construct()
	{
		//$this->conf = config();
		$this->view = new View();
	}
	/**
	 * 模型定义
	 */
	protected function model($model, $module = MODULE)
	{
		$model = APP_NAMESPACE.'\\' . $module . '\\'. 'model\\' . $model . 'Model';
		return Loader::getInstance($model);
	}
	/**
	 * url的方法
	 */
	protected function url($url)
	{
		$url = $url . config['url_suffix'];
		if (config['urlrewrite']) {
			$url = '/' . $url;
		} else {
			$url = '/index.php/' . $url;
		}
		return str_replace('%2F', '/', urlencode($url));
	}

	/**
	 * 为模板对象赋值
	 */
	protected function assign($name=[], $data='')
	{
		$this->view->assign($name, $data);
		return $this; // 支持链式调用
	}

	/**
	 * 调用视图
	 */
	 
	protected function display($tplfile='',$data=[])
	{
		// 直接调用视图的display方法，输出内容
		$this->view->display($tplfile,$data);
		
		// 如果开启了 trace，在页面末尾添加 trace 信息
		if (config('trace')) {
			\startmvc\core\App::outputTrace();
		}
	}
	
	/**
	 * 获取渲染内容但不输出
	 */
	protected function fetch($tplfile='',$data=[])
	{
		return $this->view->fetch($tplfile,$data);
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
			include __DIR__.DS.'tpl/jump.php';
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
	protected function redirect($url='')
	{
		$url=$url?:'/';
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
}
