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
    private $redis;
    private $cacheTime;

    public function __construct($params = array()) {
        $this->redis = new \Redis(); // Assuming Redis extension is enabled
        $this->redis->connect($params['host'], $params['port']); // You may need to adjust the connection details
        if (!empty($params['password'])) {
            $this->redis->auth($params['password']);
        }
        $this->redis->select((int)$params['database']);
        $this->cacheTime = $params['cacheTime'];
    }

    private function getKey($key) {
        return 'cache:' . md5($key);
    }

    public function set($key, $data) {
        $cacheKey = $this->getKey($key);
        $cacheData = [
            'data' => $data,
            'expire' => time() + $this->cacheTime
        ];
        $cacheData = serialize($cacheData);
        $this->redis->set($cacheKey, $cacheData, $this->cacheTime);
    }

    public function get($key) {
        $cacheKey = $this->getKey($key);
        $cacheData = $this->redis->get($cacheKey);

        if ($cacheData !== false) {
            $cacheData = unserialize($cacheData);

            if (time() < $cacheData['expire']) {
                return $cacheData['data'];
            } else {
                $this->redis->del($cacheKey);
            }
        }

        return null;
    }

    public function has(string $key): bool {
        return $this->get($key) !== null;
    } h

    public function delete($key) {
        $cacheKey = $this->getKey($key);
        $this->redis->del($cacheKey);
    }

    public function clear() {
        $this->redis->flushDB(); // This will clear all keys from the current database, use with caution
    }
}
