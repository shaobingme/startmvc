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
use startmvc\core\Config;

class Cache {
	private $drive;
	public function __construct(string $driveName, array $params = array()) {
		$config=Config::load('cache');
		$driveName=$driveName??$config['drive'];
		$params=$params?$params:$config[$driveName];
	    $classFile = __DIR__ . DS .'cache'.DS. ucfirst($driveName) . '.php';
	    
	    // 检查类文件是否存在
	    if (file_exists($classFile)) {
	        // 加载类文件
	        //require_once $classFile;
	         $className = 'startmvc\\core\\cache\\' . ucfirst($driveName);
	        // 检查类是否存在
	        if (class_exists($className)) {
	            // 实例化类
	            $this->drive = new $className($params);
	        } else {
	            // 处理类不存在的情况
	            throw new \Exception("类 $className 不存在");
	        }
	    } else {
	        // 处理文件不存在的情况
	        throw new \Exception("文件 $classFile 没有找到");
	    }
	}
  public function set(string $key, $val) {
    $this->drive->set($key, $val);
    return $this;
  }
  public function has(string $key) {
    return $this->drive->has($key);
  }
  public function get(string $key) {
    return $this->drive->get($key);
  }
  public function delete(string $key) {
    $this->drive->delete($key);
    return $this;
  }
  public function clear() {
    $this->drive->clear();
    return $this;
  }

}
