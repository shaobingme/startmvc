<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

use startmvc\core\Config;
use startmvc\core\Cache;
use startmvc\core\Db;

/**
 * 语言包调用
 *
 * @param string $key
 * @param string $default (可选) 默认值
 * @return string
 * @throws \Exception
 */
function lang($key, $default = '') {
	static $langCache = [];
	if (empty($key)) {
		return $default;
	}
	// 如果语言包已经加载过，则直接返回对应的值
	if (isset($langCache[$key])) {
		return $langCache[$key];
	}

	$conf = include ROOT_PATH . '/config/common.php';
	$locale = $conf['locale'] ?: 'zh_cn';
	$langPath = APP_PATH . MODULE . '/language/' . $locale . '.php';

	if (is_file($langPath)) {
		$lang = include $langPath;
		if (!empty($lang[$key])) {
			$langCache[$key] = $lang[$key];
			return $lang[$key];
		}
	} else {
		throw new \Exception('语言包文件不存在');
	}

	// 如果未找到对应的语言包键值，则返回默认值或者键名本身
	return $default ?: $key;
}


/**
 * 格式化变量输出
 *
 * @param mixed $var
 * @param string $label
 * @param boolean $echo
 */
function dump($var, $label = null, $echo = true)
{
	ob_start();
	var_dump($var);
	$output = ob_get_clean();
	$output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);

	$cli = preg_match("/cli/i", PHP_SAPI) ? true : false;

	if ($cli === true) {
		$output = PHP_EOL . $label . PHP_EOL . $output . PHP_EOL;
	} else {
		$output = '<pre>' . PHP_EOL . $label . PHP_EOL . $output . '</pre>' . PHP_EOL;
	}

	if ($echo) {
		echo $output;
	}

	return $output;
}

/**
 * 配置文件函数
 * @param string|array $key 配置键名或配置数组
 * @param mixed $value 配置值，不提供则为获取配置
 * @return mixed
 */
function config($key = null, $value = null)
{
	// 获取所有配置
	if ($key === null) {
		return \startmvc\core\Config::get();
	}
	
	// 加载配置文件
	if (is_string($key) && strpos($key, '@') === 0) {
		return \startmvc\core\Config::load(substr($key, 1));
	}
	
	// 设置多个配置
	if (is_array($key)) {
		foreach ($key as $k => $v) {
			\startmvc\core\Config::set($k, $v);
		}
		return true;
	}
	
	// 设置单个配置
	if (func_num_args() > 1) {
		return \startmvc\core\Config::set($key, $value);
	}
	
	// 获取配置
	return \startmvc\core\Config::get($key);
}

/**
 * 缓存助手函数
 * 
 * @param string $name 缓存名称（注意命名唯一性，防止重复）
 * @param mixed $value 缓存值，为null时表示获取缓存
 * @param int $expire 缓存时间（秒），默认3600秒
 * @param string $driver 缓存驱动，默认使用配置中的驱动
 * @return mixed 获取缓存时返回缓存值，设置缓存时返回true/false
 */
function cache($name, $value = null, $expire = 3600, $driver = null)
{
    static $instance = [];
    
    // 获取缓存驱动实例
    $driverName = $driver ?: config('cache.drive', 'file');
    if (!isset($instance[$driverName])) {
        $instance[$driverName] = Cache::store($driverName);
    }
    
    // 获取缓存
    if ($value === null) {
        return $instance[$driverName]->get($name);
    }
    
    // 删除缓存
    if ($value === false) {
        return $instance[$driverName]->delete($name);
    }
    
    // 自定义缓存参数
    $cacheConfig = config('cache.' . $driverName, []);
    if ($expire !== 3600) {
        $cacheConfig['cacheTime'] = $expire;
    }
    
    // 设置缓存
    return $instance[$driverName]->set($name, $value);
}

/**
 * url的方法
 */
function url($url){
	$url = $url . config('url_suffix');
	if (config('urlrewrite')) {
		$url = '/' . $url;
	} else {
		$url = '/index.php/' . $url;
	}
	return str_replace('%2F', '/', urlencode($url));
}

/**
 * 数据库助手函数 - 支持链式操作和自定义配置
 * 
 * 使用示例：
 * db('user')->where('uid', 1)->get()                           // 使用默认配置
 * db('user', $config)->where('uid', 1)->get()                 // 使用自定义配置
 * db()->table('user')->where('uid', 1)->get()                 // 链式调用
 * 
 * 更多示例：
 * db('user')->where('status', 1)->select('id,name')->getAll()
 * db('user')->insert(['name' => 'test', 'email' => 'test@example.com'])
 * db('user')->where('id', 1)->update(['name' => 'updated'])
 * db('user')->where('id', 1)->delete()
 * 
 * @param string $table 表名
 * @param array $config 数据库配置（可选）
 * @return \startmvc\core\db\DbCore
 */
function db($table = '', $config = [])
{
    // 如果指定了表名，直接调用Db::connect()方法
    if (!empty($table)) {
        return Db::connect($config, $table);
    }
    
    // 如果没有指定表名，返回Db门面类的代理对象以支持其他静态方法调用
    return new class($config) {
        private $config;
        
        public function __construct($config = []) {
            $this->config = $config;
        }
        
        public function __call($method, $args) {
            // 对于table方法，传入配置参数
            if ($method === 'table' && !empty($this->config)) {
                return Db::table($args[0], $this->config);
            }
            return call_user_func_array([Db::class, $method], $args);
        }
    };
}



/**
 * 获取客户端的真实IP地址
 */
function get_ip() {
	// 优先检查HTTP_X_FORWARDED_FOR，因为它可能包含多个IP，我们取第一个非未知的IP
	$ip = null;
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
		foreach ($ips as $tmp) {
			$ip = trim($tmp);
			if ($ip !== 'unknown') {
				break;
			}
		}
	}

	// 如果没有通过HTTP_X_FORWARDED_FOR获取到IP，尝试其他可能的服务器变量
	if (!$ip) {
		$ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_CDN_SRC_IP'] ?? '0.0.0.0';
	}

	// 验证IP地址格式，如果不是有效的IPv4或IPv6，返回默认值
	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
		$ip = '0.0.0.0';
	}

	return $ip;
}