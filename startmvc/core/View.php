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
	// 将 vars 改为静态属性，使所有视图实例共享变量
	protected static $vars = array();
	public $compiled_file='';
	protected $left_delimiter_quote;
	protected $right_delimiter_quote;
	protected $tpl_suffix = '.php'; // 默认模板后缀


	private static $rules = [
		// for loop
		'/{for ([^\}]+)}/i' => '<?php for ${1} {?>',
		'/{\/for}/i' => '<?php } ?>',
		'/{\/loop}/i' => '<?php } ?>',

		// foreach ( $array as $key => $value )
		'/{foreach\s+(.*?)}/i' => '<?php foreach ( ${1} ) { ?>',
		//end foreach
		'/{\/foreach}/i' => '<?php } ?>',
		
		// php: excute the php expression
		'/{php\s+(.*?)}/i' => '<?php ${1} ?>',

		// if else tag
		'/{else}/i' => '<?php } else { ?>',
		'/{\/if}/i' => '<?php } ?>',

		//lang
		'/\{lang\(\'([^\']+)\'\)\}/'=>'<?php echo lang(\'${1}\');?>',

		// require|include tag
		'/{include\s+([^}]+)\}/i'=> '<?php echo $this->getInclude(\'${1}\');?>',

		// comment tag (不会被解析)
		'/{\/\*(.*?)\*\/}/s' => '',
		
		// 输出带HTML标签的内容
		'/{html\s+\$(.*?)}/i' => '<?php echo isset($${1}) ? $${1} : \'\'; ?>',
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
		
		// 读取模板后缀配置
		$viewConfig = Config::load('view');
		if (isset($viewConfig['suffix']) && !empty($viewConfig['suffix'])) {
			$this->tpl_suffix = $viewConfig['suffix'];
		}

		// 读取模板安全模式配置，开启后禁用 {php}、{echo} 标签
		if (isset($viewConfig['tpl_safe_mode']) && $viewConfig['tpl_safe_mode']) {
			$this->tpl_safe_mode = true;
		}
	}

	//模板赋值
	public function assign($name, $value='') {
		if (is_array($name)) {
			foreach ($name as $k => $v) {
				if ($k != '') {
					self::$vars[$k] = $v; // 使用静态属性
				}
			}
		} else {
			self::$vars[$name] = $value; // 使用静态属性
		}
		return $this; // 支持链式调用
	}
	
	/**
	 * 获取模板文件路径和缓存文件路径
	 * 
	 * @param string $name 模板名称
	 * @return array 包含模板文件路径和缓存文件路径的数组
	 */
	protected function getTemplatePaths($name) {
		if ($name == '') {
			$name = strtolower(CONTROLLER . DS . ACTION);
		}
		
		// 检查是否已经包含文件扩展名
		$fileExtension = pathinfo($name, PATHINFO_EXTENSION);
		$hasExtension = !empty($fileExtension);
		
		// 基础路径（不包含扩展名）
		$baseName = $hasExtension ? substr($name, 0, strrpos($name, '.')) : $name;
		
		// 模板文件路径
		$tplFile = $this->tpl_template_dir . $baseName;
		if ($hasExtension) {
			$tplFile .= '.' . $fileExtension;
		} else {
			$tplFile .= $this->tpl_suffix;
		}
		
		// 缓存文件路径（始终使用.php后缀）
		$cacheFile = $this->tpl_compile_dir . $baseName . '.php';
		
		return ['tplFile' => $tplFile, 'cacheFile' => $cacheFile];
	}

	//视图渲染 支持多级目录
	public function display($name='', $data=[])
	{
		$paths = $this->getTemplatePaths($name);
		$tplFile = $paths['tplFile'];
		$cacheFile = $paths['cacheFile'];
		
		// 模板文件不存在直接返回
		if (!file_exists($tplFile)) {
			throw new \Exception($tplFile.' 模板文件不存在');
		}
		
		if (!empty($data)) {
			self::$vars = array_merge(self::$vars, $data); // 使用静态属性
		}
		// 将变量导入到当前
		extract(self::$vars); // 使用静态属性
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
		$paths = $this->getTemplatePaths($name);
		$tplFile = $paths['tplFile'];
		$cacheFile = $paths['cacheFile'];
		
		// 模板文件不存在直接返回
		if (!file_exists($tplFile)) {
			throw new \Exception($tplFile.' 模板文件不存在');
		}
		
		if (!empty($data)) {
			self::$vars = array_merge(self::$vars, $data); // 使用静态属性
		}
		// 将变量导入到当前
		extract(self::$vars); // 使用静态属性
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
		
		// 处理include标签，将include的内容合并到主模板
		$content = $this->parseIncludeTags($content);
		
		// 执行模板标签替换
		$content = $this->compileTemplateContent($content);

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

	/**
	 * 统一编译模板表达式标签
	 */
	protected function compileTemplateContent($content) {
		$content = preg_replace_callback(
			'/\{\$([^{}]+)\}/',
			function ($matches) {
				return $this->compileOutputTag($matches[1]);
			},
			$content
		);

		$content = preg_replace_callback(
			'/{loop\s+(.+?)\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s*}/i',
			function ($matches) {
				return $this->compileLoopTag($matches[1], $matches[2], $matches[3]);
			},
			$content
		);

		$content = preg_replace_callback(
			'/{loop\s+(.+?)\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s*}/i',
			function ($matches) {
				return $this->compileLoopTag($matches[1], null, $matches[2]);
			},
			$content
		);

		$content = preg_replace_callback(
			'/{if\s+([^}]+)}/i',
			function ($matches) {
				return '<?php if ( ' . $this->compileConditionExpression($matches[1]) . ' ) { ?>';
			},
			$content
		);

		$content = preg_replace_callback(
			'/{elseif\s+([^}]+)}/i',
			function ($matches) {
				return '<?php } elseif ( ' . $this->compileConditionExpression($matches[1]) . ' ) { ?>';
			},
			$content
		);

		$content = preg_replace_callback(
			'/{echo\s+([^}]+)}/i',
			function ($matches) {
				// 安全模式下禁用 {echo} 标签，防止任意 PHP 表达式执行
				if ($this->tpl_safe_mode) {
					return '';
				}
				return '<?php echo ' . $this->compilePhpExpression($matches[1]) . '; ?>';
			},
			$content
		);

		$content = preg_replace_callback(
			'/{date\s+([^\s}]+)\s+([^}]+)}/i',
			function ($matches) {
				return $this->compileDateTag($matches[1], $matches[2]);
			},
			$content
		);

		// 安全模式下剔除 {php} 标签，禁止模板内执行任意 PHP 代码
		if ($this->tpl_safe_mode) {
			$content = preg_replace('/{php\s+.*?}/is', '', $content);
		}

		return preg_replace(array_keys(self::$rules), self::$rules, $content);
	}

	/**
	 * 编译 loop 标签，支持点号路径数据源
	 */
	protected function compileLoopTag($sourceExpression, $keyVariable = null, $valueVariable = null) {
		$sourceExpression = $this->compileLoopSourceExpression($sourceExpression);
		$foreachTarget = '$' . $valueVariable;

		if ($keyVariable !== null) {
			$foreachTarget = '$' . $keyVariable . ' => $' . $valueVariable;
		}

		return '<?php if(isset(' . $sourceExpression . ') && is_array(' . $sourceExpression . ')) foreach ( '
			. $sourceExpression . ' as ' . $foreachTarget . ' ) { ?>';
	}

	/**
	 * 编译 loop 数据源表达式
	 */
	protected function compileLoopSourceExpression($expression) {
		$expression = trim($expression);
		if ($expression !== '' && $expression[0] !== '$') {
			$expression = '$' . $expression;
		}

		return $this->transformDotNotationInExpression($expression);
	}

	/**
	 * 编译 date 标签，支持点号路径数据源
	 */
	protected function compileDateTag($sourceExpression, $format) {
		$sourceExpression = $this->compileLoopSourceExpression($sourceExpression);
		$format = var_export(trim($format), true);

		return '<?php echo isset(' . $sourceExpression . ') ? date(' . $format . ', ' . $sourceExpression . ') : \'\'; ?>';
	}

	/**
	 * 编译输出标签 {$...}
	 */
	protected function compileOutputTag($expression) {
		$expression = '$' . ltrim(trim($expression), '$');

		if ($this->isSimpleVariableExpression($expression)) {
			$phpExpression = $this->transformDotNotationInExpression($expression);
			return '<?php echo isset(' . $phpExpression . ') ? ' . $phpExpression . ' : \'\'; ?>';
		}

		return '<?php echo ' . $this->compilePhpExpression($expression) . '; ?>';
	}

	/**
	 * 编译条件表达式，简单变量走 empty 判断以兼容未定义变量
	 */
	protected function compileConditionExpression($expression) {
		return $this->compilePhpExpression($expression, false, true);
	}

	/**
	 * 将模板表达式编译为 PHP 表达式
	 */
	protected function compilePhpExpression($expression, $guardSimpleVariable = false, $conditionContext = false) {
		$expression = trim($expression);
		$ternaryParts = $this->splitTernaryExpression($expression);

		if ($ternaryParts !== false) {
			return '('
				. $this->compileConditionExpression($ternaryParts['condition'])
				. ' ? '
				. $this->compilePhpExpression($ternaryParts['if_true'], true, false)
				. ' : '
				. $this->compilePhpExpression($ternaryParts['if_false'], true, false)
				. ')';
		}

		$phpExpression = $this->transformDotNotationInExpression($expression);

		if ($conditionContext && $this->isSimpleVariableExpression($expression)) {
			return '!empty(' . $phpExpression . ')';
		}

		if ($guardSimpleVariable && $this->isSimpleVariableExpression($expression)) {
			return '(isset(' . $phpExpression . ') ? ' . $phpExpression . ' : \'\')';
		}

		return $phpExpression;
	}

	/**
	 * 判断是否为简单变量表达式，支持多级点号和数组下标
	 */
	protected function isSimpleVariableExpression($expression) {
		return preg_match('/^\$[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*|\[[^\]]+\])*$/', trim($expression)) === 1;
	}

	/**
	 * 将表达式中的多级点号路径转为 PHP 数组下标
	 */
	protected function transformDotNotationInExpression($expression) {
		return preg_replace_callback(
			'/\$[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)+/',
			function ($matches) {
				return $this->convertDotPathToArrayAccess($matches[0]);
			},
			$expression
		);
	}

	/**
	 * 将 $item.user.email 转为 $item['user']['email']
	 */
	protected function convertDotPathToArrayAccess($expression) {
		$segments = explode('.', $expression);
		$phpExpression = array_shift($segments);

		foreach ($segments as $segment) {
			$phpExpression .= "['" . $segment . "']";
		}

		return $phpExpression;
	}

	/**
	 * 拆分顶层三元表达式，支持多层嵌套和字符串字面量
	 */
	protected function splitTernaryExpression($expression) {
		$length = strlen($expression);
		$questionPos = null;
		$colonPos = null;
		$nestedTernaryCount = 0;
		$bracketDepth = 0;
		$quote = null;

		for ($i = 0; $i < $length; $i++) {
			$char = $expression[$i];

			if ($quote !== null) {
				if ($char === '\\' && $i + 1 < $length) {
					$i++;
					continue;
				}

				if ($char === $quote) {
					$quote = null;
				}
				continue;
			}

			if ($char === '\'' || $char === '"') {
				$quote = $char;
				continue;
			}

			if ($char === '(' || $char === '[' || $char === '{') {
				$bracketDepth++;
				continue;
			}

			if ($char === ')' || $char === ']' || $char === '}') {
				if ($bracketDepth > 0) {
					$bracketDepth--;
				}
				continue;
			}

			if ($bracketDepth !== 0) {
				continue;
			}

			if ($char === '?') {
				if ($questionPos === null) {
					$questionPos = $i;
					$nestedTernaryCount = 1;
				} else {
					$nestedTernaryCount++;
				}
				continue;
			}

			if ($char === ':' && $questionPos !== null) {
				$nestedTernaryCount--;
				if ($nestedTernaryCount === 0) {
					$colonPos = $i;
					break;
				}
			}
		}

		if ($questionPos === null || $colonPos === null) {
			return false;
		}

		return [
			'condition' => trim(substr($expression, 0, $questionPos)),
			'if_true' => trim(substr($expression, $questionPos + 1, $colonPos - $questionPos - 1)),
			'if_false' => trim(substr($expression, $colonPos + 1)),
		];
	}
	
	/**
	 * 校验模板文件路径是否位于指定目录内，防止 ../ 路径穿越
	 */
	protected function isValidTemplatePath($tplFile, $baseDir) {
		$realPath = realpath($tplFile);
		$realDir = realpath($baseDir);
		if ($realPath === false || $realDir === false) {
			return false;
		}
		return strpos($realPath, $realDir . DS) === 0;
	}

	/**
	 * 处理模板中的include标签，将被包含文件的内容合并到主模板中
	 */
	protected function parseIncludeTags($content) {
		return preg_replace_callback(
			'/{include\s+([^}]+)}/i',
			function($matches) {
				return $this->getIncludeContent($matches[1]);
			},
			$content
		);
	}
	
	/**
	 * 获取被包含模板的内容（不执行，只返回内容）
	 */
	protected function getIncludeContent($name) {
		if (empty($name)) {
			return '';
		}
		
		// 解析可能的参数
		$params = [];
		if (strpos($name, '?') !== false) {
			list($name, $query) = explode('?', $name, 2);
			parse_str($query, $params);
		}
		
		// 检查是否指定了模块 {include common/header|Admin}
		$tplFile = '';
		$baseDir = '';
		if (strpos($name, '|') !== false) {
			list($path, $module) = explode('|', $name, 2);
			$module = trim($module);
			$path = trim($path);

			// 检查是否已经包含文件扩展名
			$fileExtension = pathinfo($path, PATHINFO_EXTENSION);

			// 构建跨模块模板路径
			$theme = config('theme') ? config('theme') . DS : '';
			$moduleDir = APP_PATH . strtolower($module) . DS . 'view' . DS . $theme;
			$baseDir = $moduleDir;

			if (!empty($fileExtension)) {
				$tplFile = $moduleDir . $path;
			} else {
				$tplFile = $moduleDir . $path . $this->tpl_suffix;
			}
		} else {
			// 使用当前模块
			$fileExtension = pathinfo($name, PATHINFO_EXTENSION);
			$baseDir = $this->tpl_template_dir;
			if (!empty($fileExtension)) {
				$tplFile = $this->tpl_template_dir . $name;
			} else {
				$tplFile = $this->tpl_template_dir . $name . $this->tpl_suffix;
			}
		}

		// 校验路径未穿越出模板目录
		if (!file_exists($tplFile) || !$this->isValidTemplatePath($tplFile, $baseDir)) {
			return '<!-- 包含文件 ' . $name . ' 不存在 -->';
		}

		// 读取包含文件内容
		$content = file_get_contents($tplFile);

		// 递归处理嵌套的include标签
		$content = $this->parseIncludeTags($content);

		// 如果有参数，将参数作为变量添加到内容中
		if (!empty($params)) {
			$paramCode = '';
			foreach ($params as $key => $value) {
				$paramCode .= '<?php $' . $key . ' = ' . var_export($value, true) . '; ?>';
			}
			$content = $paramCode . $content;
		}

		return $content;
	}

	// 获取被包含模板的内容（用于运行时）
	public function getInclude($name = null) {
		if (empty($name)) {
			return '';
		}
		
		// 解析可能的参数
		$params = [];
		if (strpos($name, '?') !== false) {
			list($name, $query) = explode('?', $name, 2);
			parse_str($query, $params);
		}
		// 检查是否指定了模块 {include common/header|Admin}
		$tplFile = '';
		$baseDir = '';
		if (strpos($name, '|') !== false) {
			list($path, $module) = explode('|', $name, 2);
			$module = trim($module);
			$path = trim($path);

			// 检查是否已经包含文件扩展名
			$fileExtension = pathinfo($path, PATHINFO_EXTENSION);

			// 构建跨模块模板路径
			$theme = config('theme') ? config('theme') . DS : '';
			$moduleDir = APP_PATH . strtolower($module) . DS . 'view' . DS . $theme;
			$baseDir = $moduleDir;

			if (!empty($fileExtension)) {
				$tplFile = $moduleDir . $path;
			} else {
				$tplFile = $moduleDir . $path . $this->tpl_suffix;
			}
		} else {
			// 使用当前模块
			$fileExtension = pathinfo($name, PATHINFO_EXTENSION);
			$baseDir = $this->tpl_template_dir;
			if (!empty($fileExtension)) {
				$tplFile = $this->tpl_template_dir . $name;
			} else {
				$tplFile = $this->tpl_template_dir . $name . $this->tpl_suffix;
			}
		}

		// 校验路径未穿越出模板目录
		if (!file_exists($tplFile) || !$this->isValidTemplatePath($tplFile, $baseDir)) {
			return '<!-- 包含文件 ' . $name . ' 不存在 -->';
		}

		// 读取包含文件内容
		$content = file_get_contents($tplFile);

		// 递归编译包含文件中的包含标签
		$content = $this->parseIncludeTags($content);

		// 编译其他模板标签
		$content = $this->compileTemplateContent($content);

		// 创建临时文件以执行
		$tempFile = $this->tpl_compile_dir . md5($name . microtime(true)) . '.php';
		file_put_contents($tempFile, $content);

		// 合并当前变量和传递的参数
		$mergedVars = array_merge(self::$vars, $params);

		// 捕获输出
		ob_start();
		extract($mergedVars); // 提取变量到当前作用域
		include $tempFile;
		$output = ob_get_clean();

		// 清理临时文件
		@unlink($tempFile);

		return $output;
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
