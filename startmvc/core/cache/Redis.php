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
use startmvc\core\Config;

class Redis {
    /**
     * Redis连接实例
     * @var \Redis
     */
    private $redis;
    
    /**
     * 缓存有效期（秒）
     * @var int
     */
    private $cacheTime;

    /**
     * 构造函数
     * @param array $params 连接参数
     * @throws \Exception 当Redis扩展未安装时抛出异常
     */
    public function __construct($params = []) {
        if (!extension_loaded('redis')) {
            throw new \Exception('Redis扩展未安装');
        }
        
        $this->redis = new \Redis();
        
        // 连接Redis服务器
        $connect = $this->redis->connect($params['host'], $params['port']);
        if (!$connect) {
            throw new \Exception('Redis连接失败');
        }
        
        // 设置认证和数据库
        if (!empty($params['password'])) {
            $this->redis->auth($params['password']);
        }
        
        $this->redis->select((int)$params['database']);
        $this->cacheTime = $params['cacheTime'];
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
     * 设置缓存
     * @param string $key 缓存键名
     * @param mixed $data 缓存数据
     * @return bool 是否成功
     */
    public function set($key, $data) {
        $cacheKey = $this->getKey($key);
        $cacheData = [
            'data' => $data,
            'expire' => time() + $this->cacheTime
        ];
        
        return $this->redis->set($cacheKey, serialize($cacheData), $this->cacheTime);
    }

    /**
     * 获取缓存
     * @param string $key 缓存键名
     * @return mixed 缓存数据，不存在或已过期返回null
     */
    public function get($key) {
        $cacheKey = $this->getKey($key);
        $cacheData = $this->redis->get($cacheKey);

        if ($cacheData === false) {
            return null;
        }
        
        $cacheData = unserialize($cacheData);
        
        // 检查是否过期（双重检查，Redis自身会过期，这里是额外保障）
        if (time() > $cacheData['expire']) {
            $this->redis->del($cacheKey);
            return null;
        }
        
        return $cacheData['data'];
    }

    /**
     * 检查缓存是否存在且有效
     * @param string $key 缓存键名
     * @return bool
     */
    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    /**
     * 删除缓存
     * @param string $key 缓存键名
     * @return bool 是否成功
     */
    public function delete($key) {
        $cacheKey = $this->getKey($key);
        return $this->redis->del($cacheKey) > 0;
    }

    /**
     * 清空所有缓存
     * @param bool $onlyCache 是否只清除缓存前缀的键
     * @return bool 是否成功
     */
    public function clear($onlyCache = true) {
        if ($onlyCache) {
            // 只清除缓存前缀的键
            $keys = $this->redis->keys('cache:*');
            if (!empty($keys)) {
                return $this->redis->del($keys) > 0;
            }
            return true;
        }
        
        // 清除整个数据库（谨慎使用）
        return $this->redis->flushDB();
    }
}
