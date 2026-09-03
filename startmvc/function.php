<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

// 加载 .env 环境变量文件
(function () {
    $envFile = ROOT_PATH . '.env';
    if (!file_exists($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (stripos($line, 'export ') === 0) {
            $line = substr($line, 7);
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        $value = preg_replace_callback('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/', function ($m) {
            return getenv($m[1]) ?: '';
        }, $value);
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
})();

use startmvc\core\Config;
use startmvc\core\Cache;
use startmvc\core\Db;

/**
 * 获取环境变量值，支持默认值
 *
 * @param string $key     环境变量名
 * @param mixed  $default 默认值
 * @return mixed
 */
function env($key, $default = null)
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
    }
    return $value;
}

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
 * @param string|array|null $key 配置键名；传数组则为批量设置配置
 * @param mixed $default 获取配置时的默认值（仅读取时生效）
 * @return mixed
 *
 * 用法：
 *   config()                      获取全部配置
 *   config('debug')               获取单个配置
 *   config('db.host', 'localhost') 获取配置，不存在时返回默认值
 *   config(['debug' => true])     批量设置配置
 */
function config($key = null, $default = null)
{
	// 获取所有配置
	if ($key === null) {
		return \startmvc\core\Config::get();
	}

	// 设置多个配置
	if (is_array($key)) {
		foreach ($key as $k => $v) {
			\startmvc\core\Config::set($k, $v);
		}
		return true;
	}

	// 加载配置文件
	if (is_string($key) && strpos($key, '@') === 0) {
		return \startmvc\core\Config::load(substr($key, 1));
	}

	// 获取配置（第二参数为默认值）
	// 注意：旧版两参数会被误判为"写配置"并返回 true，导致所有带默认值的读取
	// 恒为真值（cache 助手、Exception 调试判断等均因此出过 bug），已废弃该用法
	return \startmvc\core\Config::get($key, $default);
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

    // 注意：不能写成 config('cache.drive', 'file')——config() 收到两个参数会被当作写配置，
    // 返回 true 导致驱动名变成 '1'。这里先取值、为空时再回退默认驱动。
    $driverName = $driver ?: (config('cache.drive') ?: 'file');
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

    // 设置缓存：显式传入的 $expire（区别于默认值 3600）作为本次写入的有效期，
    // 未显式指定时传 null，由驱动使用配置中的默认 cacheTime
    return $instance[$driverName]->set($name, $value, $expire !== 3600 ? $expire : null);
}

/**
 * url的方法
 */
function url($url){
	$url = ltrim($url, '/');
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
 * 委托给 Request::ip()：仅当 REMOTE_ADDR 命中可信代理列表（config: trusted_proxies）
 * 时才解析 X-Forwarded-For，否则返回 REMOTE_ADDR，防止伪造IP。
 */
function get_ip() {
	return \startmvc\core\Request::ip();
}