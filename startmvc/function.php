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
 * 修复要点：
 *   1. 按「模块|语言」缓存整个语言包，同一语言包只 include 一次
 *      （原先既每次重读 config/common.php，又对未命中的键反复读盘）
 *   2. 语言包文件缺失或键不存在时静默回退（默认值 → 键名），
 *      不再抛异常——模板里任意一个 {lang(x)} 都不该让整站 500
 *   3. 模块名取自当前路由上下文（Request::currentRoute），
 *      CLI / 队列下自动回退到 default_module，不再依赖 MODULE 常量
 *
 * 注意缓存粒度：缓存的是语言包（模块+语言确定后即为常量），而非解析结果。
 * 按 key 缓存解析结果会让不同 $default / 不同 $module 的调用相互串味。
 *
 * @param string $key
 * @param string $default (可选) 默认值
 * @param string $module (可选) 指定模块，缺省用当前路由上下文的模块
 * @return string
 */
function lang($key, $default = '', $module = null) {
	// 语言包缓存：'模块|语言' => array|false（false 表示该语言包不存在，避免反复探测）
	static $packs = [];

	if (empty($key)) {
		return $default;
	}

	$locale = config('locale') ?: 'zh_cn';
	$module = $module ?: \startmvc\core\Request::currentRoute('module', config('default_module') ?: 'home');
	$packKey = $module . '|' . $locale;

	if (!isset($packs[$packKey])) {
		$langPath = APP_PATH . $module . '/language/' . $locale . '.php';
		if (is_file($langPath)) {
			$pack = include $langPath;
			$packs[$packKey] = is_array($pack) ? $pack : false;
		} else {
			$packs[$packKey] = false;
		}
	}

	$pack = $packs[$packKey];
	if (is_array($pack) && isset($pack[$key])) {
		return $pack[$key];
	}

	// 语言包缺失或未命中该键：回退默认值，其次回退键名本身（不抛异常）
	return $default !== '' ? $default : $key;
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
 * @param mixed $value 缓存值，为null时表示获取缓存，为false时表示删除缓存
 * @param int|null $ttl 缓存时间（秒），null 时使用驱动配置的默认 cacheTime
 * @param string $driver 缓存驱动，默认使用配置中的驱动
 * @return mixed 获取缓存时返回缓存值（未命中为null），设置缓存时返回bool，删除缓存时返回bool
 */
function cache($name, $value = null, $ttl = null, $driver = null)
{
    // 注意：不能写成 config('cache.drive', 'file')——config() 收到两个参数会被当作写配置，
    // 返回 true 导致驱动名变成 '1'。这里先取值、为空时再回退默认驱动。
    // store() 内部按驱动名复用实例，避免每次调用重连 Redis/Memcached
    $instance = Cache::store($driver ?: (config('cache.drive') ?: 'file'));

    // 获取缓存
    if ($value === null) {
        return $instance->get($name);
    }

    // 删除缓存
    if ($value === false) {
        return $instance->delete($name);
    }

    // 设置缓存：ttl 为 null 时由驱动使用配置中的默认 cacheTime
    return $instance->set($name, $value, $ttl);
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
 * 模型助手函数
 *
 * 与 Controller::model() 同源解析规则：app\{模块}\model\{名称}Model，
 * 经容器反射实例化。此前模型入口是控制器的 protected 方法，
 * 模板 / 事件监听器 / 中间件 / CLI 里拿不到模型实例，本助手补上这个入口；
 * 开销为一次函数调用 + 字符串拼接（纳秒级，实测约 25ns，可忽略）。
 *
 * 特意不加实例缓存：未做 singleton 绑定时容器每次返回新实例，
 * 避免 Model 查询状态（where 条件等）在多次调用间串味泄漏。
 *
 * 用法：
 *   model('user')                  当前模块的 UserModel（CLI 下回退 default_module 配置）
 *   model('user', 'admin')         指定 admin 模块的 UserModel
 *   model('user')->where('id', 1)->find()
 *
 * @param string $name   模型名（不带 Model 后缀），如 'user' 对应 UserModel 类
 * @param string $module 模块名，缺省取当前路由上下文模块
 * @return object 模型实例
 * @throws \ReflectionException 模型类不存在时由容器抛出
 */
function model($name, $module = null)
{
    $module = $module ?: \startmvc\core\Request::currentRoute('module', config('default_module') ?: 'home');
    return \startmvc\core\Loader::getInstance(APP_NAMESPACE . '\\' . $module . '\\model\\' . $name . 'Model');
}

/**
 * 获取客户端的真实IP地址
 * 委托给 Request::ip()：仅当 REMOTE_ADDR 命中可信代理列表（config: trusted_proxies）
 * 时才解析 X-Forwarded-For，否则返回 REMOTE_ADDR，防止伪造IP。
 */
function get_ip() {
	return \startmvc\core\Request::ip();
}

/**
 * 请求助手函数
 *
 * 返回贯穿本次请求的 Request 实例（容器中绑定的唯一快照，与控制器、
 * 中间件读到的是同一份数据，中间件附加的状态不会丢）；未绑定（纯 CLI /
 * 早期引导阶段）时回退为基于当前超全局变量构造的临时实例。
 *
 * 与静态调用 Request::get() 的区别：静态形式经 __callStatic 每次新建
 * 临时实例，会丢掉中间件附加的数据；request() 始终指向同一实例。
 *
 * 与 input() 的分工：request() 给请求对象本身（可链式调全部 API），
 * input('key') 是取值的极简语法糖。
 *
 * 用法：
 *   request()->isPost();
 *   request()->get('id', ['type' => 'int']);
 *   request()->header('User-Agent');
 *   request()->method();
 *
 * @return \startmvc\core\Request
 */
function request()
{
    return \startmvc\core\Request::current();
}

/* ==================== 输入取值助手 ====================
 * 与 Request::input() 同源：类做引擎、函数做语法糖，风格与 config()/Config、
 * cache()/Cache、db()/Db 一致。取值来源为「POST 优先、GET 兜底」，
 * 键名支持点路径（如 user.name / list.0.id）。
 * 输入默认不转义——输出时请用 e()，避免双重转义。
 */

/**
 * 取输入值（POST 优先，GET 兜底）
 *
 * @param string|null $key     键名，支持点路径；为空返回全部输入
 * @param mixed       $default 取值失败时的默认值
 * @param string      $type    类型转换：''|string|int|float|bool|array
 * @param bool        $filter  是否 HTML 转义（默认否）
 * @return mixed
 *
 * 用法：
 *   input('id')                         取 id（字符串）
 *   input('id', 0, 'int')               取 id 转 int，缺省 0
 *   input('user.name')                  点路径取嵌套值
 *   input('list.0.id', 0, 'int')        点路径下钻 + 类型转换
 *   input('agree', false, 'bool')       'false'/'0'/'off' 均判为 false
 *   input('title', '', 'string', true)  取值并转义
 *   input()                             返回全部输入
 */
function input($key = null, $default = null, $type = '', $filter = false)
{
    return \startmvc\core\Request::current()->input($key, $default, [
        'type'   => $type,
        'filter' => (bool)$filter,
    ]);
}

/**
 * HTML 转义输出
 *
 * 与输入端「不转义」策略配套：输入层保持原始数据，输出到 HTML 时统一在此转义，
 * 避免「输入转义 + 输出转义」造成的双重转义（&amp;amp; 之类）与数据污染。
 * 数组会递归转义，便于直接用于列表渲染。
 *
 * @param mixed $value 待转义的值（数组将递归处理）
 * @param bool $doubleEncode 是否对已是 HTML 实体的内容再次转义（默认 true）
 * @return mixed
 */
function e($value, $doubleEncode = true)
{
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $value[$k] = e($v, $doubleEncode);
        }
        return $value;
    }
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8', $doubleEncode);
}