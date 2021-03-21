<?php
namespace App\Admin\Controller;
use Startmvc\Core\Controller;

class IndexController extends Controller{
	function __construct (){

	}
	public function indexAction (){
		$admin="hello world!!admin";
		$this->assign('admin',$admin);
		//$this->view();
	}
}
