<?php
namespace app\admin\controller;
use app\common\BaseController;
use startmvc\core\Controller;
class IndexController extends Controller{

	public function indexAction(){
		$admin = '欢迎使用后台模块';
		$this->assign('admin', $admin);
		$this->display();
	}
}