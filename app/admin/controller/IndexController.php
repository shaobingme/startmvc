<?php
namespace app\admin\controller;
use startmvc\lib\Controller;

class IndexController extends Controller{
	function __construct (){

	}
	public function indexAction(){
		$admin="hello world!!admin";
		$this->assign('admin',$admin);
		$this->view();
	}
}