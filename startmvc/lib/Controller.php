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
use startmvc\lib\http\Request;
use startmvc\lib\db\Sql;
use startmvc\lib\Loader;

abstract class Controller
{

	public $conf;
	protected $db;
	public $assign=[];
	public function __construct()
	{
		$this->conf = include CONFIG_PATH . 'common.php';
		if($this->conf['db_auto_connect']){
			$dbConf = include CONFIG_PATH . '/database.php';
			if ($dbConf['default'] != '') {
				$this->db= new Sql($dbConf['connections'][$dbConf['default']]);
			}
		}
		if($this->conf['cache_status']){
			$this->cache=new \startmvc\lib\Cache($this->conf['cache_type'],$this->conf['cache_host'],$this->conf['cache_port']);
		}
	}
	/**
	 * 模型定义
	 */
	protected function model($model, $module = MODULE)
	{
		//if($model){
		   // $model = APP_NAMESPACE.'\\' . ($module != '' ? $module . '\\' : '') . 'Model\\' . $model . 'Model';
		//}else{
		   // $model = CORE_PATH.'\\Model';
		//}
		$model = APP_NAMESPACE.'\\' . $module . '\\'. 'Model\\' . $model . 'Model';
		return Loader::getInstance($model);
	}
	/**
	 * url的方法
	 */
	protected function url($url)
	{
		$url = $url . $this->conf['url_suffix'];
		if ($this->conf['urlrewrite']) {
			$url = '/' . $url;
		} else {
			$url = '/index.php/' . $url;
		}
		return str_replace('%2F', '/', urlencode($url));
	}
	/**
	 * 语言调用
	 */
	protected function lang($key)
	{
		static $lang = array();
		$locale = $this->conf['locale']?:'zh_cn';
		$lang_path = APP_PATH .MODULE.'/Language/'.$locale.'.php';
		if(is_file($lang_path)){
			$lang=include $lang_path;
		}else{
			die('语言包文件不存在');
		}
		return $key?$lang[$key]:$key;

	}

	/**
	 * 为模板对象赋值
	 */
	public function assign($name, $data=null)
	{
		if(is_array($name)){
			$this->assign = $name;
		}else{
			$this->assign[$name] = $data;
		}
		//print_r($this->assign);
	}

	/**
	 * 视图调用
	 */
	protected function view($template = '')
	{
		if (is_array($template)) {
			$template = APP_PATH . '/' . $template[0] . '/view/' . $template[1] . '.php';
		} else {
			if ($template == '') {
				$template = CONTROLLER . '_' . ACTION;
			}
			$template = APP_PATH . '/' .MODULE . '/'. 'view/' . $template . '.php';
		}
		if (file_exists($template)) {
	
			//header('Content-Type:text/html; charset=utf-8');
			$contents=$this->show($template);
			header('Content-Type:text/html; charset=utf-8');
			echo $contents;
			die();

		} else {
			$this->content('视图文件不存在：' . $template);
		}
	}
	/**
	 * 模板标签解析
	 */
	private function parse($str)
	{
		preg_match_all('/{include (.+)}/Ui', $str, $include);
		foreach ($include[1] as $inc) {
			$inc_array = explode('|', $inc);
			if (isset($inc_array[1])) {
				$inc_file = APP_PATH . '/' . $inc_array[1] . '/view/' . $inc_array[0] . '.php';
			} else {
				$inc_file = APP_PATH . '/' .MODULE . '/'. '/view/' . $inc_array[0] . '.php';
			}
			$inc_content = file_get_contents($inc_file);
			$str = str_replace('{include ' . $inc . '}', $inc_content, $str);
			
		}
		$str = str_replace('<?=', '<?php echo ', $str);
		$str = str_replace('<?', '<?php ', $str);
		$str = str_replace('<?php php', '<?php', $str);
		
		$str = preg_replace("/\{if\s+(.+?)\}/is", "<?php if(\\1) { ?>", $str);
		$str = preg_replace("/\{elseif\s+(.+?)\}/is", "<?php } elseif(\\1) { ?>", $str);
		$str = preg_replace("/\{else\}/i", "<?php } else { ?>", $str);
		$str = preg_replace("/\{\/if\}/i", "<?php } ?>", $str);
		$str = preg_replace("/\{foreach\s+(.+?)\}/is", "<?php foreach(\\1) { ?>", $str);
		$str = preg_replace("/\{\/foreach\}/i", "<?php } ?>", $str);
		$str = preg_replace("/\{eval\s+(.+?)\}/is", "<?php \\1 ?>", $str);
		$str = preg_replace("/\{\\$(.+?)\}/i", "<?php echo $\\1; ?>", $str);
		$str = preg_replace("/\{\\$(.+?)\}/i", "<?php echo $\\1; ?>", $str);
		$str = preg_replace("/\{lang\((.+?)\)\}/is", "<?php echo lang(\\1); ?>", $str);

		return $str;
	}

	/**
	 * 调用模板show的方法
	 */

	protected function show($template)
	{

		//$view_file=VIEW_PATH.DS.CONTROLLER . '_' . ACTION.'.php';

		if(!is_dir(TEMP_PATH)){
			mkdir(TEMP_PATH,0777);
			chmod(TEMP_PATH,0777);
		}

		$runtime_file = TEMP_PATH .DS.MODULE . '_'.CONTROLLER . '_' . ACTION.'.php';
		if(!file_exists($runtime_file) || filemtime($runtime_file) < filemtime($template)) {
			$contents = file_get_contents($template);
			$contents = $this->parse($contents);
			$of = fopen($runtime_file, 'w+');
			fwrite($of, $contents);
			fclose($of);
		}

		if(is_object($this->assign)) {
			extract((array)$this->assign);
		}else{
			extract($this->assign);
		}

		ob_start();
		include_once($runtime_file);
		$content = ob_get_contents();
		ob_end_clean();

		return $content;

	}

	/**
	 * 调用内容
	 */

	public function content($content)
	{
		header('Content-Type:text/plain; charset=utf-8');
		echo $content;
	}
	protected function success($msg='',$url='',$data=[],$ajax=false)
	{
		$this->response(1,$msg,$url,$data,$ajax);
	}
	protected function error($msg='',$url='',$data=[],$ajax=false)
	{
		$this->response(0,$msg,$url,$data,$ajax);
	}
	protected function response($code='',$msg='',$url='',$data=[],$ajax=false)
	{
		if($ajax || Request::isAjax()){
			$data=[
				'code'=>$code,//1-成功 0-失败
				'msg'=>$msg,
				'url'=>$url,
				'data'=>$data,
			];
			$this->json($data);
		}else{
			include '../startmvc/lib/location.php';
			exit();
		}

	}

	/**
	 * json方法
	 */
	protected function json($data)
	{
		header('Content-Type:application/json; charset=utf-8');
		//echo json_encode($data, JSON_UNESCAPED_UNICODE);
		exit(json_encode($data, JSON_UNESCAPED_UNICODE));
	}


	/**
	 * 跳转
	 */
	protected function redirect($url)
	{
		header('location:' . $url);
		exit();
	}
	/**
	 * 404方法
	 */
	protected function notFound()
	{
		header("HTTP/1.1 404 Not Found");  
		header("Status: 404 Not Found");
	}
	public function __call($fun, $arg)
	{
		$this->notFound();
	}
}
