<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
// 系统目录分隔符
define('DS', DIRECTORY_SEPARATOR);
// 项目根目录
define('ROOT_PATH', realpath(__DIR__.DS.'..'.DS).DS);    // 入口文件在 public 中
//define('ROOT_PATH', dirname(__FILE__).DS);    // 入口文件在项目根目录
require(ROOT_PATH .'startmvc'.DS.'boot.php');