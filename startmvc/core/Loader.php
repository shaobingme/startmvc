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
		$paramArr = self::getMethodParams($className);
		return (new \ReflectionClass($className))->newInstanceArgs($paramArr);
	}

	public static function make($controller, $action, $argv)
	{
		try {
			$class = new \ReflectionClass($controller);
			$instance = $class->newInstanceArgs();
			
			if (!method_exists($instance, $action)) {
				throw new \Exception("方法{$action}不存在");
			}
			
			return call_user_func_array([$instance, $action], $argv);
		} catch (\ReflectionException $e) {
			throw new \Exception("控制器实例化失败：" . $e->getMessage());
		}
	}

	protected static function getMethodParams($className, $methodsName = '__construct')
	{
		$class = new \ReflectionClass($className);
		$paramArr = [];
		if ($class->hasMethod($methodsName)) {
			$method = $class->getMethod($methodsName);
			$params = $method->getParameters();
			if (count($params) > 0) {
				foreach ($params as $key => $param) {
					// 使用 getType() 代替 getClass()
					$type = $param->getType();
					if ($type && !$type->isBuiltin() && $type instanceof \ReflectionNamedType) {
						$paramClassName = $type->getName();
						$args = self::getMethodParams($paramClassName);
						$paramArr[] = (new \ReflectionClass($paramClassName))->newInstanceArgs($args);
					}
				}
			}
		}
		return $paramArr;
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