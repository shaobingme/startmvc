<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace App\Common;
use Startmvc\Lib\Controller;
use Startmvc\Lib\Http\Session;
class BaseController extends Controller{
	public $category_list;
	function __construct(){
		parent::__construct();//调用父级构造方法
		
		}
}