<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace Startmvc\Lib;
class Cache{
    public $prefix = '';
    private $type, $conn;
    function __construct($type = 'file', $host = '127.0.0.1', $port = 0){
        $this->type = $type;
        if($type == 'redis'){
            if($port == ''){
                $port == 6379;
            }
            $this->conn = new \Redis();
            $this->conn->connect($host, $port);
        }
    }
    function set($key, $val, $expire = 3600){
        if($this->type == 'file'){
            $data['expire'] = (int)$expire + time();
            $data['data'] = $val;
            $cacheData = json_encode($data, JSON_UNESCAPED_UNICODE);
            $cacheDir = ROOT_PATH . '/runtime/cache';
            if(!is_dir($cacheDir)){
                mkdir($cacheDir);
            }
            $cacheFile = $cacheDir . '/' . base64_encode($key);
            file_put_contents($cacheFile, $cacheData);
        }elseif($this->type == 'redis'){
            $data['data'] = $val;
            $cacheData = json_encode($data, JSON_UNESCAPED_UNICODE);
            $this->conn->setex($this->prefix . $key, $expire, $cacheData);
        }
    }
    function get($key){
        if($this->type == 'file'){
            $cacheFile = ROOT_PATH . '/runtime/cache/' . base64_encode($key);
            if(!is_file($cacheFile)){
                return false;
            }
            $data = json_decode(file_get_contents($cacheFile), 1);
            if($data['expire'] < time()){
                unlink($cacheFile);
                return false;
            }
        }elseif($this->type == 'redis'){
            $data = json_decode($this->conn->get($this->prefix . $key), 1);
        }            
        return $data['data'];
    }
    function del($key){
        if($this->type == 'file'){
            $cacheFile = ROOT_PATH . '/runtime/cache/' . base64_encode($key);
            if(is_file($cacheFile)){
                unlink($cacheFile);
            }
        }elseif($this->type == 'redis'){
            $this->conn->delete($this->prefix . $key);
        }            
    }
    function clear(){
        if($this->type == 'file'){
            $cacheDir = ROOT_PATH . '/runtime/cache';
            if($dir = opendir($cacheDir)){
                while($file = readdir($dir)){
                    if($file != '.' && $file != '..'){
                        unlink($cacheDir . '/' .$file);
                    }
                }
            }
        }elseif($this->type == 'redis'){
            $this->conn->flushAll();
        }            
    }

   //内置缓存方法
	function cache($name, $val, $expire = 3600)
	{
		$res=$this->get($name);
		if(!$res){
			$this->set($name, $val,$expire);
			$res=$this->get($name);
		}
		return $res;
	}
    
}