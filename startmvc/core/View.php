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
	// 编译缓存的模块级根目录（不含 theme）；clearCache() 按它递归清，能一次清掉所有主题
	public $tpl_compile_base = '';
	public $tpl_safe_mode = false;
	public $tpl_cache_time = 0; // 缓存时间(秒)，0表示不缓存
	// 本次编译内联进来的 {include} 文件清单（路径 => true，键即路径，用于去重）。
	// 只在 tpl_cache_time > 0 时记录并写入编译产物，供 depsFresh() 判断依赖是否已更新；
	// 默认（0）不记录，编译产物与旧版逐字节一致。
	protected $compiled_deps = array();
	// 布局模板名；空字符串 = 关闭布局（默认，行为与旧版逐字节一致）
	public $tpl_layout = '';
	// 将 vars 改为静态属性，使所有视图实例共享变量
	protected static $vars = array();
	public $compiled_file='';
	protected $left_delimiter_quote;
	protected $right_delimiter_quote;
	protected $tpl_suffix = '.php'; // 默认模板后缀

	/**
	 * 当前请求实例（路由上下文来源，可在 CLI / 单元测试中显式注入）
	 * @var Request
	 */
	protected $request;

	/**
	 * 当前路由上下文（模板查找与默认模板名使用）
	 * @var string
	 */
	protected $route_module;
	protected $route_controller;
	protected $route_action;


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

	/**
	 * @param Request|null $request 当前请求实例；缺省取容器中 App::run 绑定的实例，
	 *                              CLI / 队列下未绑定时自动回退到配置的默认模块与模板
	 */
	function __construct(Request $request = null){
		// 路由上下文统一从请求对象读取，不再直接依赖 MODULE / CONTROLLER / ACTION 常量
		// （常量只写一次，CLI 或同进程多次分发时并不可靠）
		$request = $request ?: Container::getInstance()->make(Request::class);
		if (!$request instanceof Request) {
			$request = new Request();
		}
		$this->request = $request;

		$this->route_module = $request->route('module', config('default_module') ?: 'home');
		$this->route_controller = $request->route('controller', config('default_controller') ?: 'Index');
		$this->route_action = $request->route('action', config('default_action') ?: 'index');

		$theme=config('theme')?config('theme').DS:'';
		$this->tpl_template_dir = APP_PATH .$this->route_module . DS. 'view'.DS.$theme;
		// 编译目录与模板目录同构（同样带上 theme）：多主题站点两套模板内容不同，
		// 缓存也必须分开放，否则会撞同一个缓存文件；theme 为空时路径与旧版逐字节一致
		$this->tpl_compile_base = TEMP_PATH.$this->route_module.DS;
		$this->tpl_compile_dir = $this->tpl_compile_base . $theme;
		$this->left_delimiter_quote = preg_quote($this->tpl_left_delimiter);
		$this->right_delimiter_quote = preg_quote($this->tpl_right_delimiter);
		
		// 读取配置的缓存时间：优先 view 组（与 suffix / tpl_safe_mode / layout 同处，便于查找），
		// 回退顶层键以兼容旧位置（config/common.php 或 config/local.php）
		$this->tpl_cache_time = intval(config('view.tpl_cache_time', config('tpl_cache_time', 0)));
		
		// 读取模板后缀配置
		$viewConfig = Config::load('view');
		if (isset($viewConfig['suffix']) && !empty($viewConfig['suffix'])) {
			$this->tpl_suffix = $viewConfig['suffix'];
		}

		// 读取模板安全模式配置，开启后禁用 {php}、{echo} 标签
		if (isset($viewConfig['tpl_safe_mode']) && $viewConfig['tpl_safe_mode']) {
			$this->tpl_safe_mode = true;
		}

		// 读取布局配置：布局模板名（相对当前模块 view 目录），留空 = 关闭布局
		if (isset($viewConfig['layout'])) {
			$this->tpl_layout = (string) $viewConfig['layout'];
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
	 * 设置布局模板（链式）
	 *
	 * 布局模板就是普通模板文件，正文位置写 {$__content__}（不转义，直接输出）。
	 * 传空字符串即关闭布局（本次渲染不再套壳）。
	 *
	 * @param string $name 布局模板名（相对当前模块 view 目录），'' = 关闭
	 * @return $this
	 */
	public function layout($name = '')
	{
		$this->tpl_layout = (string) $name;
		return $this;
	}

	/**
	 * 获取模板文件路径和缓存文件路径
	 * 
	 * @param string $name 模板名称
	 * @return array 包含模板文件路径和缓存文件路径的数组
	 */
	protected function getTemplatePaths($name) {
		if ($name == '') {
			$name = strtolower($this->route_controller . DS . $this->route_action);
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

	//视图渲染 支持多级目录（整页渲染，按当前设置套布局）
	public function display($name='', $data=[])
	{
		// 直接输出内容，不要处理trace
		echo $this->fetch($name, $data, true);

		return $this; // 支持链式调用
	}

	/**
	 * 返回渲染后的内容，而不是直接输出
	 *
	 * @param string $name 模板名称
	 * @param array $data 追加的模板变量
	 * @param bool|null $withLayout 是否套布局：
	 *                              null  = 按当前设置（config/view.php 的 layout + layout()）
	 *                              false = 强制不套（取片段 / Ajax / 局部刷新）
	 *                              true  = 允许套（布局名为空时仍不套）
	 * @return string
	 */
	public function fetch($name='', $data=[], $withLayout=null)
	{
		$content = $this->renderTemplate($name, $data);

		if ($withLayout !== false && $this->tpl_layout !== '') {
			// 正文注入布局的 {$__content__} 位；渲染完立刻清掉，避免静态变量跨渲染残留
			$content = $this->renderTemplate($this->tpl_layout, ['__content__' => $content]);
			unset(self::$vars['__content__']);
		}

		return $content;
	}

	/**
	 * 渲染单个模板文件并返回内容（原 fetch() 的实现，未作改动）
	 */
	private function renderTemplate($name='', $data=[])
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
				$tplModified <= $cacheModified && 
				$this->depsFresh($cacheFile, $cacheModified)) {
				return;
			}
		}
		
		// 重置依赖清单：同一实例连续编译多个模板时，避免上一个模板的 {include} 残留进来
		$this->compiled_deps = array();

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
			mkdir($tplCacheDir, 0755, true);
		}
		
		// 添加编译时间戳注释
		$content = "<?php /* 模板编译于: " . date('Y-m-d H:i:s') . " */ ?>\n" . $content;

		// 开启编译缓存时，把本次内联的依赖清单写进产物首行（base64 编码，避免路径里的
		// */ 或引号破坏注释）。即使没有依赖也写空数组：这样「读不到 deps 行」就只可能
		// 是升级前的旧缓存，否则会陷入「无依赖 → 不写行 → 下次读不到 → 判失效 → 又重
		// 编译」的死循环。
		if ($this->tpl_cache_time > 0) {
			$deps = base64_encode(json_encode(array_keys($this->compiled_deps), JSON_UNESCAPED_SLASHES));
			$content = "<?php /* @deps " . $deps . " */ ?>\n" . $content;
		}
		
		file_put_contents($cacheFile, $content, LOCK_EX);
	}

	/**
	 * 校验编译产物的依赖清单是否仍然新鲜
	 *
	 * {include} 是编译期内联的，被包含文件的 mtime 不会影响主模板，
	 * 所以要把依赖清单写进产物、在这里逐个比对，否则改被包含文件不会触发重编译。
	 *
	 * @param string $cacheFile     编译产物路径
	 * @param int    $cacheModified 产物的 mtime
	 * @return bool true = 依赖都未更新，可以继续用缓存
	 */
	private function depsFresh($cacheFile, $cacheModified)
	{
		$fh = @fopen($cacheFile, 'rb');
		if (!$fh) {
			return false;
		}
		$line = fgets($fh);
		fclose($fh);

		// 首行没有 deps 标记 = 升级前生成的旧缓存（那时还没有依赖追踪）→ 保守判失效，
		// 重编译一次后产物就会带上 deps 行，不会反复重编译
		if ($line === false || !preg_match('~^<\?php /\* @deps ([A-Za-z0-9+/=]*) \*/ \?>~', $line, $m)) {
			return false;
		}

		$deps = json_decode((string) base64_decode($m[1], true), true);
		if (!is_array($deps)) {
			return false;
		}

		foreach ($deps as $dep) {
			$depModified = @filemtime($dep);
			// 依赖被改动（mtime 比产物新）或已被删除，都要重编译
			if ($depModified === false || $depModified > $cacheModified) {
				return false;
			}
		}
		return true;
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

		// 记录依赖：被包含文件是编译期内联的，它改了以后主模板的 mtime 不会变，
		// 必须单独记下来供下次 depsFresh() 比对（用键去重，嵌套 include 会重复进入）
		if ($this->tpl_cache_time > 0) {
			$this->compiled_deps[str_replace('\\', '/', $tplFile)] = true;
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
			// 清除所有缓存：按模块根目录递归，多主题子目录一并清掉。
			// 若外部把 tpl_compile_dir 指到了模块根目录之外（自定义目录），则尊重它，不动根目录。
			$base = $this->tpl_compile_base;
			$dir = ($base !== '' && strpos($this->tpl_compile_dir, $base) === 0)
				? $base
				: $this->tpl_compile_dir;
			$this->_clearDir($dir);
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
