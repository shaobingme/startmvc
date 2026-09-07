<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

namespace startmvc\core\cache;

class Memcached {
    /**
     * Memcached连接实例
     * @var \Memcached
     */
    private $memcached;

    /**
     * 缓存有效期（秒）
     * @var int
     */
    private $cacheTime;

    /**
     * 构造函数
     * @param array $params 连接参数
     * @throws \Exception 当Memcached扩展未安装或连接失败时抛出异常
     */
    public function __construct($params = []) {
        if (!extension_loaded('memcached')) {
            throw new \Exception('Memcached扩展未安装');
        }

        $this->memcached = new \Memcached();
        $this->memcached->addServer($params['host'] ?? '127.0.0.1', (int)($params['port'] ?? 11211));
        $this->cacheTime = $params['cacheTime'] ?? 3600;

        // 连接失败检测（延迟到首次操作才报错，这里主动探测一次）
        $stats = $this->memcached->getStats();
        if (empty($stats)) {
            throw new \Exception('Memcached连接失败');
        }
    }

    /**
     * 获取带前缀的缓存键名
     * @param string $key 原始键名
     * @return string 带前缀的键名
     */
    private function getKey($key) {
        return 'cache:' . md5($key);
    }

    /**
     * 设置缓存（Memcached原生过期，无需自行存储过期时间）
     * @param string $key 缓存键名
     * @param mixed $data 缓存数据
     * @param int|null $ttl 有效期（秒），null 时使用构造时的默认 cacheTime
     * @return bool 是否成功
     */
    public function set($key, $data, $ttl = null) {
        $expire = $ttl ?? $this->cacheTime;
        return $this->memcached->set($this->getKey($key), $data, $expire);
    }

    /**
     * 获取缓存
     * @param string $key 缓存键名
     * @return mixed 缓存数据，不存在或已过期返回null
     */
    public function get($key) {
        $result = $this->memcached->get($this->getKey($key));

        // 用结果码区分"未命中"与"缓存了false值"，未命中时get返回false
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return null;
        }

        return $result;
    }

    /**
     * 检查缓存是否存在且有效
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool {
        $this->memcached->get($this->getKey($key));
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    /**
     * 删除缓存
     * @param string $key 缓存键名
     * @return bool 是否成功
     */
    public function delete($key) {
        return $this->memcached->delete($this->getKey($key));
    }

    /**
     * 清空缓存
     *
     * 注意：Memcached 无法按前缀批量删除，flush 会清空整个实例上
     * 所有 key（含其他应用共享该实例的数据），共享实例时请慎用。
     *
     * @return bool 是否成功
     */
    public function clear() {
        return $this->memcached->flush();
    }
}
