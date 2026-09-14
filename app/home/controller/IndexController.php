<?php
namespace app\home\controller;
//use startmvc\core\Controller;
use app\common\BaseController;
use startmvc\core\Config;
//use startmvc\core\Db;

class IndexController extends BaseController{
	
	public function indexAction()
	{
		
		$data['title'] = '超轻量 PHP 框架，为快速构建 Web 应用而生';
		$data['content'] = 'Hello StartMVC';

		$this->assign($data);
		$this->display();
	}

}
