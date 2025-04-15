<?php
namespace app\home\controller;
//use startmvc\core\Controller;
use app\common\BaseController;
use startmvc\core\Config;
use startmvc\core\Db;

class IndexController extends BaseController{
	
	public function indexAction()
	{
		$data['title'] = '超轻量php框架-欢迎使用Startmvc';
		$data['content'] = 'Hello StartMVC!';

		//$result=Db::select('id')->table('article')->get();
		//dump($result);
		
		$this->assign($data);
		$this->display();
	}
}
