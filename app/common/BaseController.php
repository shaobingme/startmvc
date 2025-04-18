<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace app\common;
use startmvc\core\Controller;
use startmvc\core\Session;
class BaseController extends Controller{
	public $category_list;
	function __construct(){
		parent::__construct();//调用父级构造方法
		
		$this->_initialize();
	}
	
	protected function _initialize()
	{
		// 子类可以重写此方法
	}
}