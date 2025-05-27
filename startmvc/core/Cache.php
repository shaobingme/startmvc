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
	/**
	 * 缓存驱动实例
	 * @var object
	 */
	private $drive;
	
	/**
	 * 构造函数，初始化缓存驱动
	 * @param string $driveName 驱动名称，默认从配置读取
	 * @param array $params 驱动参数
	 * @throws \Exception 当驱动不存在时抛出异常
	 */
	public function __construct(string $driveName = null, array $params = []) {
		$config = Config::load('cache');
		$driveName = $driveName ?? $config['drive'];
		$params = $params ?: $config[$driveName];
		
		$className = 'startmvc\\core\\cache\\' . ucfirst($driveName);
		
		if (!class_exists($className)) {
			throw new \Exception("缓存驱动 {$driveName} 不存在");
		}
		
		$this->drive = new $className($params);
	}
	
	/**
	 * 设置缓存
	 * @param string $key 缓存键名
	 * @param mixed $val 缓存数据
	 * @return $this
	 */
	public function set(string $key, $val) {
		$this->drive->set($key, $val);
		return $this;
	}
	
	/**
	 * 检查缓存是否存在
	 * @param string $key 缓存键名
	 * @return bool
	 */
	public function has(string $key) {
		return $this->drive->has($key);
	}
	
	/**
	 * 获取缓存
	 * @param string $key 缓存键名
	 * @return mixed
	 */
	public function get(string $key) {
		return $this->drive->get($key);
	}
	
	/**
	 * 删除缓存
	 * @param string $key 缓存键名
	 * @return $this
	 */
	public function delete(string $key) {
		$this->drive->delete($key);
		return $this;
	}
	
	/**
	 * 清空所有缓存
	 * @return $this
	 */
	public function clear() {
		$this->drive->clear();
		return $this;
	}
	
	/**
	 * 创建缓存实例的静态方法
	 * @param string $driver 驱动名称
	 * @param array $params 驱动参数
	 * @return Cache
	 */
	public static function store(string $driver = null, array $params = [])
	{
		return new self($driver, $params);
	}
}
