<?php
namespace startmvc\core;

use startmvc\core\db\DbCore;
use startmvc\core\Config;

/**
 * 数据库门面类
 * 提供静态方法调用数据库操作
 */
class Db
{
    /**
     * 数据库实例缓存
     * @var array
     */
    protected static $instances = [];
    
    /**
     * 连接数据库并返回实例
     * @param array $config 数据库配置
     * @param string $table 表名
     * @return DbCore
     */
    public static function connect($config = [], $table = '')
    {
        $instance = static::getInstance($config);
        
        if (!empty($table)) {
            return $instance->table($table);
        }
        
        return $instance;
    }
    
    /**
     * 获取数据库表实例
     * @param string $table 表名
     * @param array $config 数据库配置
     * @return DbCore
     */
    public static function table($table, $config = [])
    {
        return static::getInstance($config)->table($table);
    }
    
    /**
     * 获取数据库实例
     * @param array $config 数据库配置
     * @return DbCore
     */
    protected static function getInstance($config = [])
    {
        // 生成配置的唯一标识
        $configKey = empty($config) ? 'default' : md5(serialize($config));
        
        if (!isset(static::$instances[$configKey])) {
            if (empty($config)) {
                // 使用默认配置
                $defaultConfig = include CONFIG_PATH . '/database.php';
                if (isset($defaultConfig['driver']) && $defaultConfig['driver'] !== '') {
                    $config = $defaultConfig['connections'][$defaultConfig['driver']];
                } else {
                    throw new \Exception('数据库配置不正确，请检查配置文件');
                }
            }
            static::$instances[$configKey] = DbCore::getInstance($config);
        }
        
        return static::$instances[$configKey];
    }
    
    /**
     * 检查数据表是否存在
     * @param string $table 表名
     * @param array $config 数据库配置（可选）
     * @return bool
     */
    public static function is_table($table, $config = [])
    {
        return static::getInstance($config)->is_table($table);
    }
    
    /**
     * 调用DbCore的其他方法
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return call_user_func_array([static::getInstance(), $method], $args);
    }
} 