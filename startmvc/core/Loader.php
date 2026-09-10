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

class Loader
{
	public static function getInstance($className)
	{
		// 统一走容器：共享绑定、单例缓存、循环依赖检测与直接反射注入保持一致
		return Container::getInstance()->make($className);
	}

	public static function make($controller, $action, $argv)
	{
		try {
			// 控制器实例化走容器，构造函数中的类类型依赖会被自动递归解析
			$instance = Container::getInstance()->make($controller);
		} catch (\Exception $e) {
			throw new \Exception("控制器实例化失败：" . $e->getMessage(), $e->getCode(), $e);
		}

		if (!method_exists($instance, $action)) {
			// 方法不存在按 404 处理（路由目标错误），而非 500 服务器错误
			throw new \Exception("方法{$action}不存在", 404);
		}

		// 方法参数按签名解析：类类型参数（如 Request）由容器注入，其余按位置传路由参数
		$argv = self::resolveArguments(new \ReflectionMethod($instance, $action), $argv);

		// 方法调用在 try 之外：动作内部抛出的异常（如 HttpResponseException）
		// 必须原样向上传播，交由 App::run 统一处理，不得在此包装
		return call_user_func_array([$instance, $action], $argv);
	}

	/**
	 * 执行闭包并按签名注入依赖（路由闭包与控制器方法共用同一套参数解析规则）
	 * @param \Closure $closure 路由闭包
	 * @param array $argv 位置参数（路由匹配参数）
	 * @return mixed 闭包返回值
	 */
	public static function invoke(\Closure $closure, array $argv = [])
	{
		return call_user_func_array($closure, self::resolveArguments(new \ReflectionFunction($closure), $argv));
	}

	/**
	 * 按方法/闭包签名解析调用参数
	 *
	 * 规则：
	 *   - 类类型参数（Request 及任意可由容器解析的类）→ Container::make() 注入；
	 *     Request 在 App::run 中绑定为当前请求单例，因此注入的就是贯穿中间件管道的同一实例
	 *   - 其余参数按位置依次消费 $argv（路由参数）
	 *   - $argv 不足时依次回退：默认值 → 可空类型 null → 抛出异常
	 *   - 签名中无任何类类型提示时原样返回 $argv（零开销，与旧行为完全一致）
	 *
	 * @param \ReflectionFunctionAbstract $reflector 方法/闭包反射
	 * @param array $argv 位置参数（路由参数）
	 * @return array
	 */
	protected static function resolveArguments(\ReflectionFunctionAbstract $reflector, array $argv)
	{
		$params = $reflector->getParameters();

		// 快速路径：无类类型提示的旧式方法直接按位置传参
		$hasClassHint = false;
		foreach ($params as $param) {
			$type = $param->getType();
			if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
				$hasClassHint = true;
				break;
			}
		}
		if (!$hasClassHint) {
			return $argv;
		}

		$args = [];
		$pos = 0;
		$count = count($argv);
		$container = Container::getInstance();

		foreach ($params as $param) {
			// 可变参数：剩余位置参数全部追加
			if ($param->isVariadic()) {
				for (; $pos < $count; $pos++) {
					$args[] = $argv[$pos];
				}
				break;
			}

			$type = $param->getType();
			// 类类型依赖走容器（Request 即当前请求实例）
			if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
				$args[] = $container->make($type->getName());
				continue;
			}

			if ($pos < $count) {
				$args[] = $argv[$pos];
				$pos++;
				continue;
			}
			if ($param->isDefaultValueAvailable()) {
				$args[] = $param->getDefaultValue();
				continue;
			}
			if ($type instanceof \ReflectionNamedType && $type->allowsNull()) {
				$args[] = null;
				continue;
			}

			throw new \ArgumentCountError(
				'无法解析参数 $' . $param->getName() . '（' . $reflector->getName() . '），路由参数不足且无默认值'
			);
		}

		return $args;
	}

	protected static function filter($doc)
	{
		if ($doc) {
			preg_match_all('/filter\[[\S\s]+\]/U', $doc, $matches); 
			foreach ($matches[0] as $filter) {
				$filterClass = preg_replace('/filter\[([\S\s]+)\(([\S\s]*)\)\]/', '${1}', $filter);
				$filterClass = '\\Filter\\' . $filterClass;
				$filterParamArr = preg_replace('/filter\[([\S\s]+)\(([\S\s]*)\)\]/', '${2}', $filter);
				$filterParamArr = explode(',', $filterParamArr);
				for ($i = 0; $i < count($filterParamArr); $i++) {
					$filterParamArr[$i] = trim($filterParamArr[$i]);
				}
				$instance = self::getInstance($filterClass);
				if (method_exists($instance, 'handle')) {
					$instance->handle(...$filterParamArr);
				}
			}
		}
	}
}