<?php
namespace App\Home\Controller;
//use Startmvc\Core\Controller;
use App\Common\BaseController;


class IndexController extends BaseController{
	
    public function indexAction()
    {
	    $data['title'] = '超轻量php框架-欢迎使用Startmvc';
	    $data['content'] = 'Hello StartMVC!';
        $this->view($data);
    }
    public function __call($name,$arg)
    {
    	$this->content("走丢了。。。。。。。。");
    }
}
