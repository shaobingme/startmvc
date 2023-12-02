<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */
namespace startmvc\lib;

class view{

	public $_tpl_vars = array();
	public $tpl_left_delimiter = '{';
	public $tpl_right_delimiter = '}';
	public $tpl_template_dir = '';
	public $tpl_compile_dir = '';
	public $tpl_safe_mode = false;
	//public $tpl_check = true;
	public $vars = array();
	public $compiled_file='';


	private static $rules = [

		// {$var}, {$array['key']}
		'/{\$([^\}|\.]{1,})}/i' => '<?php echo \$${1}?>',

		// array: {$array.key}
		'/{\$([0-9a-z_]{1,})\.([0-9a-z_]{1,})}/i' => '<?php echo \$${1}[\'${2}\']?>',

		// two-demensional array
		'/{\$([0-9a-z_]{1,})\.([0-9a-z_]{1,})\.([0-9a-z_]{1,})}/i' => '<?php echo \$${1}[\'${2}\'][\'${3}\']?>',

		// for loop
		'/{for ([^\}]+)}/i' => '<?php for ${1} {?>',
		'/{\/for}/i' => '<?php } ?>',

		// foreach ( $array as $key => $value )
		'/{loop\s+\$([^\}]{1,})\s+\$([^\}]{1,})\s+\$([^\}]{1,})\s*}/i' => '<?php foreach ( \$${1} as \$${2} => \$${3} ) { ?>',
		'/{\/loop}/i' => '<?php } ?>',

		// foreach ( $array as $value )
		'/{loop\s+\$(.*?)\s+\$([0-9a-z_]{1,})\s*}/i' => '<?php foreach ( \$${1} as \$${2} ) { ?>',

		// foreach ( $array as $key => $value )
		'/{foreach\s+(.*?)}/i' => '<?php foreach ( ${1} ) { ?>',
		//end foreach
		'/{\/foreach}/i' => '<?php } ?>',
		// expr: excute the php expression
		// echo: print the php expression
		'/{expr\s+(.*?)}/i' => '<?php ${1} ?>',
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

	];

	function __construct(){
		$this->tpl_template_dir = APP_PATH . DS .MODULE . DS. 'view'.DS;
		$this->tpl_compile_dir = TEMP_PATH.MODULE.DS;
		$this->left_delimiter_quote = preg_quote($this->tpl_left_delimiter);
		$this->right_delimiter_quota = preg_quote($this->tpl_right_delimiter);
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

	}

	//支持多级目录
	public function display($name='',$data=[]){
		if ($name == '') {
			$name = strtolower(CONTROLLER . '_' . ACTION);
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
		// 开启输出缓冲
		ob_start();
		$this->_compile($tplFile,$cacheFile);
		// 包含模板文件
		include $cacheFile;
		// 获取缓冲区内容并清空缓冲区
		$output = ob_get_clean();
		return $output;
	}

	/**
	 * compile template
	 */
	private function _compile($tplFile,$cacheFile)
	{
		// compile template
		$content = @file_get_contents($tplFile);
		if ($content === false) {
			throw new \Exception("failed to  load template file {$tplFile}");
		}
		$content = preg_replace(array_keys(self::$rules), self::$rules, $content);

		$tplCacheDir = dirname($cacheFile);

		//未编译或模板文件已修改时, 编译生成模板缓存文件
		if (!is_file($cacheFile) || (filemtime($tplFile) > filemtime($cacheFile))) {
			if (!is_dir($tplCacheDir)) {
				mkdir($tplCacheDir, 0777, true);
			}
			file_put_contents($cacheFile, $content, LOCK_EX);
		}

	}

	// 获取被包含模板的路径
	public function getInclude($name = null){
		if (empty($name)) {
			return '';
		}
		$tplFile = $this->tpl_template_dir.$name.'.php';
		$cacheFile = $this->tpl_compile_dir . $name . '.php';
		if(file_exists($tplFile)){
			$this->_compile($tplFile,$cacheFile);
			return $cacheFile;
		}

	}


}