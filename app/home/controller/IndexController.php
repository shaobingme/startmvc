<?php
namespace app\home\controller;
//use startmvc\core\Controller;
use app\common\BaseController;
use startmvc\core\Config;
class IndexController extends BaseController{
	
	public function indexAction()
	{
		$data['title'] = '超轻量php框架-欢迎使用Startmvc';
		$data['content'] = 'Hello StartMVC!';

		$this->assign($data);
		//$this->display('',$data);
		$this->display();
	}

}
