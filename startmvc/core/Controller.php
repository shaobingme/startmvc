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
use startmvc\core\Request;
use startmvc\core\Loader;
use startmvc\core\View;

abstract class Controller
	{
		protected $view;

		/**
		 * 当前请求实例（从容器获取，与中间件管道共享同一实例，
		 * 中间件通过 $request->foo = ... 附加的状态可直接读取）
		 * @var Request
		 */
		protected $request;
		
		public function __construct()
		{
			$this->request = Container::getInstance()->make(Request::class);
			// 视图复用同一请求实例，模板目录与默认模板名取自当前路由上下文
			$this->view = new View($this->request);
		}
	/**
	 * 模型定义：委托给全局 model() 助手，解析规则单点维护，控制器内外行为完全一致
	 */
	protected function model($model, $module = null)
	{
		return \model($model, $module);
	}
	/**
	 * url的方法：与全局 url() 助手保持同一实现
	 * （原先未做 ltrim，传入 '/user/1' 会拼出 '//user/1'）
	 */
	protected function url($url)
	{
		return \url($url);
	}

	/**
	 * 为模板对象赋值
	 */
	protected function assign($name=[], $data='')
	{
		$this->view->assign($name, $data);
		return $this; // 支持链式调用
	}

	/**
	 * 调用视图
	 */
	 
	protected function display($tplfile='',$data=[])
	{
		// 渲染结果交给统一响应出口发送：内容仍在控制器执行期间输出（保持既有顺序语义），
		// trace 面板改由 Response::send() 单点附加，不再在这里判断一次
		// 第 3 参 true：整页渲染，按 config/view.php 的 layout 设置套布局
		(new Response())->html($this->view->fetch($tplfile,$data,true))->send();
	}
	
	/**
	 * 获取渲染内容但不输出（取片段：强制不套布局，供 Ajax / 局部刷新使用）
	 */
	protected function fetch($tplfile='',$data=[])
	{
		return $this->view->fetch($tplfile,$data,false);
	}
	
	/**
	 * 设置布局模板（链式，作用于本次请求的视图渲染）
	 *
	 * 传空字符串关闭布局；前台 / 后台共用同一份视图时用来换壳。
	 *
	 * @param string $name 布局模板名，'' = 关闭
	 * @return $this
	 */
	protected function layout($name = '')
	{
		$this->view->layout($name);
		return $this;
	}
	
	/**
	 * 调用内容
	 *
	 * 保留历史语义：设置 text/plain 后立即输出，但**不终止**后续代码执行。
	 * 需要终止请用 exit()，或直接 return (new Response())->text(...) 交给框架发送。
	 */
	public function content($content)
	{
		(new Response())->text($content)->send();
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
		if($ajax || $this->request->isAjax()){
			$data=[
				'code'=>$code,//1-成功 0-失败
				'msg'=>$msg,
				'url'=>$url,
				'data'=>$data,
			];
			$this->json($data);
		}else{
			// 跳转页渲染为字符串装入 Response，通过响应异常交给框架统一发送
			ob_start();
			include __DIR__.DS.'tpl/jump.php';
			$jump = ob_get_clean();
			throw new HttpResponseException((new Response())->html($jump));
		}

	}

	/**
	 * json方法：构建 JSON Response 并以响应异常中断执行
	 * （异常由 App::run 统一捕获发送，保留"调用即终止"的语义）
	 */
	protected function json($data)
	{
		// JSON 构建统一走 Response::json，避免同一件事在框架里存两份实现
		throw new HttpResponseException((new Response())->json($data));
	}


	/**
	 * 跳转：构建带 Location 头的 Response 并以响应异常中断执行
	 */
	protected function redirect($url='')
	{
		throw new HttpResponseException((new Response())->redirect($url ?: '/'));
	}
	/**
	 * 404方法
	 *
	 * 保留历史语义：只设置 404 状态码、**不终止**执行、不输出响应体。
	 * 需要终止并渲染 404 页面请抛 \Exception('页面不存在', 404)，由 Exception 统一处理。
	 */
	protected function notFound()
	{
		(new Response())->setStatusCode(404)->withTrace(false)->send();
	}
}
