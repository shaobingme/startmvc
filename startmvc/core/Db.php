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
     * 数据库实例
     * @var DbCore
     */
    protected static $instance;
    
    /**
     * 获取数据库表实例
     * @param string $table 表名
     * @return DbCore
     */
    public static function table($table)
    {
        return static::getInstance()->table($table);
    }
    
    /**
     * 获取数据库实例
     * @return DbCore
     */
    protected static function getInstance()
    {
        if (static::$instance === null) {
            $config = include CONFIG_PATH . '/database.php';
            if (isset($config['driver']) && $config['driver'] !== '') {
                static::$instance = DbCore::getInstance($config['connections'][$config['driver']]);
            } else {
                throw new \Exception('数据库配置不正确，请检查配置文件');
            }
        }
        return static::$instance;
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