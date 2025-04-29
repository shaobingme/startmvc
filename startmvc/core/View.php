<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */
namespace startmvc\core;

class view{

	public $_tpl_vars = array();
	public $tpl_left_delimiter = '{';
	public $tpl_right_delimiter = '}';
	public $tpl_template_dir = '';
	public $tpl_compile_dir = '';
	public $tpl_safe_mode = false;
	public $tpl_cache_time = 0; // 缓存时间(秒)，0表示不缓存
	public $vars = array();
	public $compiled_file='';
	protected $left_delimiter_quote;
	protected $right_delimiter_quote;


	private static $rules = [
		// {$var}, {$array['key']}
		'/{\$([^\}|\.]{1,})}/i' => '<?php echo isset($${1}) ? $${1} : \'\'; ?>',

		// array: {$array.key}
		'/{\$([0-9a-z_]{1,})\.([0-9a-z_]{1,})}/i' => '<?php echo isset($${1}[\'${2}\']) ? $${1}[\'${2}\'] : \'\'; ?>',

		// two-demensional array
		'/{\$([0-9a-z_]{1,})\.([0-9a-z_]{1,})\.([0-9a-z_]{1,})}/i' => '<?php echo isset($${1}[\'${2}\'][\'${3}\']) ? $${1}[\'${2}\'][\'${3}\'] : \'\'; ?>',

		// for loop
		'/{for ([^\}]+)}/i' => '<?php for ${1} {?>',
		'/{\/for}/i' => '<?php } ?>',

		// foreach ( $array as $key => $value )
		'/{loop\s+\$([^\}]{1,})\s+\$([^\}]{1,})\s+\$([^\}]{1,})\s*}/i' => '<?php if(isset($${1}) && is_array($${1})) foreach ( $${1} as $${2} => $${3} ) { ?>',
		'/{\/loop}/i' => '<?php } ?>',

		// foreach ( $array as $value )
		'/{loop\s+\$(.*?)\s+\$([0-9a-z_]{1,})\s*}/i' => '<?php if(isset($${1}) && is_array($${1})) foreach ( $${1} as $${2} ) { ?>',

		// foreach ( $array as $key => $value )
		'/{foreach\s+(.*?)}/i' => '<?php foreach ( ${1} ) { ?>',
		//end foreach
		'/{\/foreach}/i' => '<?php } ?>',
		
		// php: excute the php expression
		// echo: print the php expression
		'/{php\s+(.*?)}/i' => '<?php ${1} ?>',
		'/{echo\s+(.*?)}/i' => '<?php echo ${1} ?>',

		// if else tag
		'/{if\s+(.*?)}/i' => '<?php if ( ${1} ) { ?>',
		'/{else}/i' => '<?php } else { ?>',
		'/{elseif\s+(.*?)}/i' => '<?php } elseif ( ${1} ) { ?>',
		'/{\/if}/i' => '<?php } ?>',

		//lang
		'/\{lang\(\'([^\']+)\'\)\}/'=>'<?php echo lang(\'${1}\');?>',

		// require|include tag
		'/{include\s+([^}]+)\}/i'=> '<?php include $this->getInclude(\'${1}\')?>',
		
		// comment tag (不会被解析)
		'/{\/\*(.*?)\*\/}/s' => '',
		
		// 三元运算
		'/{\$([^\}|\.]{1,})\?(.*?):(.*?)}/i' => '<?php echo isset($${1}) && !empty($${1}) ? ${2} : ${3}; ?>',
		
		// 输出带HTML标签的内容
		'/{html\s+\$(.*?)}/i' => '<?php echo isset($${1}) ? $${1} : \'\'; ?>',
		
		// 日期格式化
		'/{date\s+\$(.*?)\s+(.*?)}/i' => '<?php echo isset($${1}) ? date(\'${2}\', $${1}) : \'\'; ?>',
	];

	function __construct(){
		// 使用常量或默认值
		$module = defined('MODULE') ? MODULE : 'home';
		$controller = defined('CONTROLLER') ? CONTROLLER : 'Index';
		$action = defined('ACTION') ? ACTION : 'index';
		
		$theme=config('theme')?config('theme').DS:'';
		$this->tpl_template_dir = APP_PATH .MODULE . DS. 'view'.DS.$theme;
		$this->tpl_compile_dir = TEMP_PATH.MODULE.DS;
		$this->left_delimiter_quote = preg_quote($this->tpl_left_delimiter);
		$this->right_delimiter_quote = preg_quote($this->tpl_right_delimiter);
		
		// 读取配置的缓存时间
		$this->tpl_cache_time = intval(config('tpl_cache_time', 0));
	}

	//模板赋值
	public function assign($name, $value='') {
		if (is_array($name)) {
			foreach ($name as $k => $v) {
				if ($k != '') {
					$this->vars[$k] = $v;
				}
			}
		} else {
			$this->vars[$name] = $value;
		}
		return $this; // 支持链式调用
	}

	//视图渲染 支持多级目录
	public function display($name='', $data=[])
	{
		if ($name == '') {
			$name = strtolower(CONTROLLER . DS . ACTION);
		}

		$tplFile = $this->tpl_template_dir . $name .'.php';
		$cacheFile = $this->tpl_compile_dir . $name .'.php';
		// 模板文件不存在直接返回
		if (!file_exists($tplFile)) {
			throw new \Exception($tplFile.' 模板文件不存在');
		}
		
		if (!empty($data)) {
			$this->vars = array_merge_recursive($this->vars, $data);
		}
		// 将变量导入到当前
		extract($this->vars);
		// 获取渲染后的内容
		ob_start();
		$this->_compile($tplFile, $cacheFile);
		include $cacheFile;
		$content = ob_get_clean();
		
		// 直接输出内容，不要处理trace
		echo $content;
		
		return $this; // 支持链式调用
	}
	
	// 返回渲染后的内容，而不是直接输出
	public function fetch($name='', $data=[])
	{
		if ($name == '') {
			$name = strtolower(CONTROLLER . DS . ACTION);
		}

		$tplFile = $this->tpl_template_dir . $name .'.php';
		$cacheFile = $this->tpl_compile_dir . $name .'.php';
		// 模板文件不存在直接返回
		if (!file_exists($tplFile)) {
			throw new \Exception($tplFile.' 模板文件不存在');
		}
		
		if (!empty($data)) {
			$this->vars = array_merge_recursive($this->vars, $data);
		}
		// 将变量导入到当前
		extract($this->vars);
		// 获取渲染后的内容
		ob_start();
		$this->_compile($tplFile, $cacheFile);
		include $cacheFile;
		return ob_get_clean();
	}

	/**
	 * compile template
	 */
	private function _compile($tplFile, $cacheFile)
	{
		$tplCacheDir = dirname($cacheFile);
		
		// 检查缓存是否有效
		if (file_exists($cacheFile)) {
			$cacheModified = filemtime($cacheFile);
			$tplModified = filemtime($tplFile);
			
			// 如果缓存未过期且模板未修改，直接使用缓存
			if ($this->tpl_cache_time > 0 && 
				(time() - $cacheModified < $this->tpl_cache_time) && 
				$tplModified <= $cacheModified) {
				return;
			}
		}
		
		// 编译模板
		$content = @file_get_contents($tplFile);
		if ($content === false) {
			throw new \Exception("无法加载模板文件 {$tplFile}");
		}
		
		// 增加编译前的钩子，可以自定义修改模板内容
		if (method_exists($this, 'beforeCompile')) {
			$content = $this->beforeCompile($content);
		}
		
		// 执行模板标签替换
		$content = preg_replace(array_keys(self::$rules), self::$rules, $content);
		
		// 增加编译后的钩子
		if (method_exists($this, 'afterCompile')) {
			$content = $this->afterCompile($content);
		}

		// 确保缓存目录存在
		if (!is_dir($tplCacheDir)) {
			mkdir($tplCacheDir, 0777, true);
		}
		
		// 添加编译时间戳注释
		$content = "<?php /* 模板编译于: " . date('Y-m-d H:i:s') . " */ ?>\n" . $content;
		
		file_put_contents($cacheFile, $content, LOCK_EX);
	}

	// 获取被包含模板的路径
	public function getInclude($name = null) {
		if (empty($name)) {
			return ''; 
		}
		
		$tplFile = $this->tpl_template_dir . $name . '.php';
		
		if (file_exists($tplFile)) {
			// 读取包含文件内容
			$content = file_get_contents($tplFile);
			
			// 递归编译包含文件中的包含标签
			$content = preg_replace_callback(
				'/{include\s+([^}]+)}/i',
				function($matches) {
					return $this->getInclude($matches[1]);
				},
				$content
			);
			
			// 编译其他模板标签
			$content = preg_replace(array_keys(self::$rules), self::$rules, $content);
			
			return "<?php /* 包含: {$name} */ ?>\n" . $content;
		}
		
		return '';
	}
	
	// 清除模板缓存
	public function clearCache($name = null) {
		if ($name === null) {
			// 清除所有缓存
			$this->_clearDir($this->tpl_compile_dir);
		} else {
			// 清除指定模板缓存
			$cacheFile = $this->tpl_compile_dir . $name . '.php';
			if (file_exists($cacheFile)) {
				@unlink($cacheFile);
			}
		}
		return $this;
	}
	
	// 清空目录
	private function _clearDir($dir) {
		if (!is_dir($dir)) return;
		
		$handle = opendir($dir);
		while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..') {
				$path = $dir . $file;
				if (is_dir($path)) {
					$this->_clearDir($path . DS);
					@rmdir($path);
				} else {
					@unlink($path);
				}
			}
		}
		closedir($handle);
	}
}