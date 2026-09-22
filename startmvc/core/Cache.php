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
	 * 标签版本号在缓存中的键前缀（框架保留前缀，业务键请避开）
	 */
	const TAG_PREFIX = '__sm_tag_version:';

	/**
	 * 标签版本号自身的保留期（秒）
	 *
	 * 必须远大于标签化缓存条目的 TTL，否则版本号会先于数据过期，
	 * 版本回落到 0 会让 flush 之前的旧缓存"复活"。
	 * 取 30 天是因为 Memcached 会把大于 2592000 的过期时间当作绝对时间戳解释。
	 */
	const TAG_VERSION_TTL = 2592000;

	/**
	 * 已创建的驱动实例池（按驱动名复用，避免 Redis/Memcached 重复建连）
	 * @var array
	 */
	private static $instances = [];

	/**
	 * 缓存驱动实例
	 * @var object
	 */
	private $drive;

	/**
	 * 当前实例关联的标签（由 tag() 产生的副本持有，共享实例上恒为空）
	 * @var array
	 */
	private $tags = [];

	/**
	 * 构造函数，初始化缓存驱动
	 * @param string $driveName 驱动名称，默认从配置读取
	 * @param array $params 驱动参数
	 * @throws \Exception 当驱动不存在时抛出异常
	 */
	public function __construct(string $driveName = null, array $params = []) {
		$config = Config::load('cache');
		$driveName = $driveName ?? $config['drive'];
		$params = $params ?: ($config[$driveName] ?? []);

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
	 * @param int|null $ttl 有效期（秒），null 时使用驱动配置的默认 cacheTime
	 * @return bool 是否写入成功
	 */
	public function set(string $key, $val, $ttl = null) {
		return (bool)$this->drive->set($this->taggedKey($key), $val, $ttl);
	}

	/**
	 * 读取缓存，未命中时执行回调并写入缓存（get-or-set）
	 *
	 * 回调返回 null 视为不可缓存（null 在本框架中表示"未命中"），下次仍会执行回调。
	 * 高并发下同 key 回调可能同时执行（无锁），对必须单次执行的场景请自行加锁。
	 *
	 * @param string $key 缓存键名
	 * @param callable $callback 未命中时的取值回调
	 * @param int|null $ttl 有效期（秒），null 时使用驱动配置的默认 cacheTime
	 * @return mixed 缓存值或回调返回值
	 */
	public function remember(string $key, callable $callback, $ttl = null) {
		// 只在此处解析一次标签版本，后续直接操作存储键，
		// 避免再次经过 taggedKey() 造成重复标记
		$key = $this->taggedKey($key);

		$value = $this->drive->get($key);
		if ($value !== null) {
			return $value;
		}
		$value = $callback();
		if ($value !== null) {
			$this->drive->set($key, $value, $ttl);
		}
		return $value;
	}
	
	/**
	 * 检查缓存是否存在
	 * @param string $key 缓存键名
	 * @return bool
	 */
	public function has(string $key) {
		return $this->drive->has($this->taggedKey($key));
	}
	
	/**
	 * 获取缓存
	 * @param string $key 缓存键名
	 * @return mixed
	 */
	public function get(string $key) {
		return $this->drive->get($this->taggedKey($key));
	}
	
	/**
	 * 删除缓存
	 * @param string $key 缓存键名
	 * @return bool 是否删除成功（键不存在时返回false）
	 */
	public function delete(string $key) {
		return (bool)$this->drive->delete($this->taggedKey($key));
	}

	/**
	 * 创建带标签的缓存作用域
	 *
	 * 返回的是一个浅拷贝，因此不会污染 Cache::store() 共享实例的标签状态。
	 * 标签化条目的实际存储键会内嵌各标签的当前版本号，
	 * flushTag() 只需递增版本号，就能让该标签下的全部条目一次性失效。
	 *
	 * @param string|array $name 单个标签或标签数组
	 * @return Cache 带标签的缓存实例
	 */
	public function tag($name) {
		$clone = clone $this;
		$clone->tags = array_values(array_unique(array_filter(
			array_merge($this->tags, (array)$name),
			'strlen'
		)));
		return $clone;
	}

	/**
	 * 使指定标签下的所有缓存立即失效
	 *
	 * 实现方式是递增标签版本号（单次写，无读改写竞态），
	 * 而非逐个删除条目——后者要维护标签索引，并发写入时存在漏删风险。
	 *
	 * 代价：旧版本号对应的条目不再被访问，只能等自身 TTL 到期后由驱动惰性回收；
	 * File 驱动没有主动 GC，因此标签化缓存应设置较短的 TTL，
	 * 并在低峰期用 clear() 回收残留文件。
	 *
	 * @param string|array $name 单个标签或标签数组
	 * @return $this
	 */
	public function flushTag($name) {
		foreach ((array)$name as $tag) {
			$tag = (string)$tag;
			if ($tag === '') {
				continue;
			}
			$this->drive->set(self::TAG_PREFIX . $tag, $this->tagVersion($tag) + 1, self::TAG_VERSION_TTL);
		}
		return $this;
	}

	/**
	 * 读取标签的当前版本号
	 * @param string $tag 标签名
	 * @return int 版本号，从未被 flush 过时为 0
	 */
	private function tagVersion($tag) {
		$version = $this->drive->get(self::TAG_PREFIX . $tag);
		return is_numeric($version) ? (int)$version : 0;
	}

	/**
	 * 把标签版本号编进存储键
	 *
	 * 未打标签时原样返回，保证不带标签的用法与旧版本行为完全一致。
	 *
	 * @param string $key 业务键名
	 * @return string 实际存储键
	 */
	private function taggedKey(string $key) {
		if (empty($this->tags)) {
			return $key;
		}

		$parts = [];
		foreach ($this->tags as $tag) {
			$parts[] = $tag . ':' . $this->tagVersion($tag);
		}

		return $key . '|@' . implode(',', $parts);
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
	 * 创建（或复用）缓存实例的静态方法
	 * @param string $driver 驱动名称
	 * @param array $params 驱动参数，非空时不进实例池（避免不同配置串扰）
	 * @return Cache
	 */
	public static function store(string $driver = null, array $params = [])
	{
		// 带自定义参数时不走实例池，避免不同配置串扰
		if ($params) {
			return new self($driver, $params);
		}
		// 未指定驱动时以配置的默认驱动名做池键，保证 store() 与 store('file') 复用同一实例
		$driver = $driver ?? (Config::load('cache')['drive'] ?? 'file');
		if (!isset(self::$instances[$driver])) {
			self::$instances[$driver] = new self($driver);
		}
		return self::$instances[$driver];
	}
}
