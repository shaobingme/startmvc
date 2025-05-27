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

class File {
    /**
     * 缓存目录
     * @var string
     */
    private $cacheDir;
    
    /**
     * 缓存有效期（秒）
     * @var int
     */
    private $cacheTime;

    /**
     * 构造函数
     * @param array $params 配置参数
     */
    public function __construct($params = []) {
        $this->cacheDir = ROOT_PATH . '/runtime/' . $params['cacheDir'];
        $this->cacheTime = $params['cacheTime'];
        
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * 获取缓存文件路径
     * @param string $key 缓存键名
     * @return string 缓存文件路径
     */
    private function getPath($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }

    /**
     * 设置缓存
     * @param string $key 缓存键名
     * @param mixed $data 缓存数据
     * @return void
     */
    public function set($key, $data) {
        $cacheFile = $this->getPath($key);
        $cacheData = [
            'data' => $data,
            'expire' => time() + $this->cacheTime
        ];
        file_put_contents($cacheFile, serialize($cacheData));
    }

    /**
     * 获取缓存
     * @param string $key 缓存键名
     * @return mixed 缓存数据，不存在或已过期返回null
     */
    public function get($key) {
        $cacheFile = $this->getPath($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = unserialize(file_get_contents($cacheFile));
        
        // 检查是否过期
        if (time() > $cacheData['expire']) {
            $this->delete($key);
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
        $cacheFile = $this->getPath($key);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $cacheData = unserialize(file_get_contents($cacheFile));
        return time() <= $cacheData['expire'];
    }

    /**
     * 删除缓存
     * @param string $key 缓存键名
     * @return void
     */
    public function delete($key) {
        $cacheFile = $this->getPath($key);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * 清空所有缓存
     * @return void
     */
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
