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
    private $cacheDir;
    private $cacheTime;

    public function __construct($params=array()) {
	    
        $this->cacheDir=ROOT_PATH . '/runtime/'.$params['cacheDir'];
        $this->cacheTime = $params['cacheTime'];
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    private function getPath($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }

    public function set($key, $data) {
        $cacheFile = $this->getPath($key);
        $cacheData = [
            'data' => $data,
            'expire' => time() + $this->cacheTime
        ];
        $cacheData = serialize($cacheData);
        file_put_contents($cacheFile, $cacheData);
    }

    public function get($key) {
        $cacheFile = $this->getPath($key);
        if (file_exists($cacheFile) && (time()-filemtime($cacheFile))<$this->cacheTime) {
	        echo (time()-filemtime($cacheFile));
	        echo $this->cacheTime;
            $cacheData = file_get_contents($cacheFile);
            $cacheData = unserialize($cacheData);
            return $cacheData['data'];
        } else {
	        //unlink($cacheFile);
            return null;
        }
    }
    
	public function has(string $key): bool {
	    return $this->get($key) !== null;
	}

    public function delete($key) {
        $cacheFile = $this->getPath($key);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        //$currentTime = time();
        foreach ($files as $file) {
	        //if (filemtime($file) <= $currentTime) {
            unlink($file);
       		//}
        }
    }
}
