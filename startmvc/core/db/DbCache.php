<?php
/**
 * Db - 实用的查询构建器和PDO类
 *
 * @class    Cache

 */

namespace startmvc\core\db;

/**
 * 数据库缓存类
 */
class DbCache
{
    /**
     * 缓存目录
     * 
     * @var string|null
     */
    protected $cacheDir = null;
    
    /**
     * 缓存时间
     * 
     * @var int|null
     */
    protected $cache = null;
    
    /**
     * 缓存过期时间
     * 
     * @var int|null
     */
    protected $finish = null;

    /**
     * 缓存构造函数
     *
     * @param null $dir 缓存目录
     * @param int  $time 缓存时间（秒）
     */
    function __construct($dir = null, $time = 0)
    {
        if (! file_exists($dir)) {
            mkdir($dir, 0755);
        }

        $this->cacheDir = $dir;
        $this->cache = $time;
        $this->finish = time() + $time;
    }

    /**
     * 获取缓存数据
     * 
     * @param      $sql SQL查询语句
     * @param bool $array 是否返回数组
     *
     * @return bool|void
     */
    public function getCache($sql, $array = false)
    {
        if (is_null($this->cache)) {
            return false;
        }

        $cacheFile = $this->cacheDir . $this->fileName($sql) . '.cache';
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), $array);

            if (($array ? $cache['finish'] : $cache->finish) < time()) {
                unlink($cacheFile);
                return;
            }

            return ($array ? $cache['data'] : $cache->data);
        }

        return false;
    }

    /**
     * 设置缓存数据
     * 
     * @param $sql SQL查询语句
     * @param $result 查询结果
     *
     * @return bool|void
     */
    public function setCache($sql, $result)
    {
        if (is_null($this->cache)) {
            return false;
        }

        $cacheFile = $this->cacheDir . $this->fileName($sql) . '.cache';
        $cacheFile = fopen($cacheFile, 'w');

        if ($cacheFile) {
            fputs($cacheFile, json_encode(['data' => $result, 'finish' => $this->finish]));
        }

        return;
    }

    /**
     * 根据SQL生成缓存文件名
     * 
     * @param $name SQL查询语句
     *
     * @return string
     */
    protected function fileName($name)
    {
        return md5($name);
    }
}
