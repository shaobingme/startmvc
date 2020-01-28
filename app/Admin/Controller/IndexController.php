<?php
namespace App\Admin\Controller;
use Startmvc\Core\Controller;

class IndexController extends Controller{
	function __construct (){

	}
	public function indexAction (){
		echo "hello world!!admin";
	}
}
