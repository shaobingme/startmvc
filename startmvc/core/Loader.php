<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace startmvc\core;

class Loader
{
    public static function getInstance($className)
    {
        $paramArr = self::getMethodParams($className);
        return (new \ReflectionClass($className))->newInstanceArgs($paramArr);
    }
    public static function make($className, $methodName, $params = [])
    {
        $parent = $class = new \ReflectionClass($className);
        $isController = false;
        while ($parent = $parent->getParentClass()) {
            if ($parent->getName() == 'startmvc\core\Controller') {
                $isController = true;
                break;
            }
        }
        if ($isController) {
            self::filter($class->getDocComment());
        }
        $instance = self::getInstance($className);
        $method = $class->getMethod($methodName);
        if ($isController) {
            self::filter($method->getDocComment());
        }
        $paramArr = self::getMethodParams($className, $methodName);
        return $instance->{$methodName}(...array_merge($paramArr, $params));
    }
    protected static function getMethodParams($className, $methodsName = '__construct')
    {
        $class = new \ReflectionClass($className);
        $paramArr = []; 
        if ($class->hasMethod($methodsName)) {
            $construct = $class->getMethod($methodsName);
            $params = $construct->getParameters();
            if (count($params) > 0) {
                foreach ($params as $key => $param) {
                    if ($paramClass = $param->getClass()) {
                        $paramClassName = $paramClass->getName();
                        $args = self::getMethodParams($paramClassName);
                        $paramArr[] = (new \ReflectionClass($paramClass->getName()))->newInstanceArgs($args);
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