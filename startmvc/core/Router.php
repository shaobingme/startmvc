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

class Router 
{
    private $routes = [];
    private $currentRoute = [];
    private static $instance = null;

    // 路由规则
    private $patterns = [
        ':any' => '.*?',
        ':num' => '[0-9]+',
        ':alpha' => '[a-zA-Z]+',
        ':alphanum' => '[a-zA-Z0-9]+',
    ];

    private function __construct() 
    {
        $this->routes = require_once(CONFIG_PATH.'route.php');
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function parse()
    {
        $pathInfo = $this->getPathInfo();
        $pathInfo = $this->matchRoute($pathInfo);
        
        // 解析路由参数
        $segments = explode('/', $pathInfo);
        $this->currentRoute = [
            'module' => isset($segments[0]) && $segments[0] != '' ? $segments[0] : config('default_module'),
            'controller' => isset($segments[1]) && $segments[1] != '' ? ucfirst($segments[1]) : config('default_controller'),
            'action' => isset($segments[2]) && $segments[2] != '' ? $segments[2] : config('default_action'),
            'params' => array_map(function($arg) {
                return strip_tags(htmlspecialchars(stripslashes($arg)));
            }, array_slice($segments, 3))
        ];

        $this->defineConstants();
        return $this->currentRoute;
    }

    private function getPathInfo()
    {
        $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : 
                   (isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : 
                   (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
                   
        $pathInfo = str_replace('/index.php', '', $pathInfo);
        return str_replace(config('url_suffix'), '', substr($pathInfo, 1));
    }

    private function matchRoute($pathInfo)
    {
        foreach ($this->routes as $route) {
            if (strpos($route[0], '(:') !== false) {
                $pattern = '#^' . strtr($route[0], $this->patterns) . '$#';
            } else {
                $pattern = $route[0];
            }
            $pathInfo = preg_replace($pattern, $route[1], $pathInfo);
        }
        return $pathInfo;
    }

    private function defineConstants()
    {
        define('MODULE', $this->currentRoute['module']);
        define('CONTROLLER', $this->currentRoute['controller']);
        define('ACTION', $this->currentRoute['action']);
        define('VIEW_PATH', APP_PATH.MODULE . DS .'view');
    }
} 