<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */
namespace startmvc\core;

class Config
{
    /**
     * 用来存储已经加载的配置
     *
     * @var array
     */
    static public $conf = [];

    /**
     * 加载系统配置文件(直接加载整个配置文件),如果之前已经加载过,那么就直接返回
     *
     * @param string $file 文件名
     *
     * @return string|array
     */
    static public function load($file="common")
    {
        if (isset(self::$conf[$file])) {
            return self::$conf[$file];
        }
        else {
            $conf = CONFIG_PATH . DS . $file . '.php';
            if (file_exists($conf)) {
                self::$conf[$file] = include $conf;
                return self::$conf[$file];
            }
            else {
                throw new Exception('Config "' . $confName . '" not found.');
            }
        }

    }
}