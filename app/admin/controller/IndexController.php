<?php
namespace app\admin\controller;
use app\common\BaseController;
use startmvc\core\Controller;
class IndexController extends Controller{

	public function indexAction(){
		$admin="hello world!!admin";
		$this->assign('admin',$admin);
		$this->display();
	}
}