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

class Http
{
    /**
     * 处理输入值并进行类型转换和过滤
     *
     * @param mixed $val     需要处理的值
     * @param array $options 处理选项
     *                       - default: 默认值，当$val为null时使用
     *                       - type: 类型转换('string', 'int', 'float', 'array', 'bool')
     *                       - function: 要应用的函数，可以是函数名或函数名数组
     *                       - filter: 是否过滤HTML特殊字符(默认为false；输入端不转义，输出时请使用e()函数)
     * @return mixed 处理后的值
     */
    public static function handling($val, $options = [])
    {
        // 设置默认值
        $default = isset($options['default']) ? $options['default'] : '';
        $val = is_null($val) ? $default : $val;
        
        // 类型转换
        $type = isset($options['type']) ? $options['type'] : '';
        if ($type) {
	        switch ($type) {
	            case 'string':
	                $val = (string)$val;
	                break;
	            case 'int':
	                $val = (int)$val;
	                break;
	            case 'float':
	                $val = (float)$val;
	                break;
	            case 'array':
	                $val = (array)$val;
	                break;
	            case 'bool':
	                $val = (bool)$val;
	                break;
	            default:
	                $val = (string)$val;
	        }
        }

        // 应用函数处理
        $function = isset($options['function']) ? $options['function'] : [];
        $function = is_array($function) ? $function : [$function];
        
        // HTML特殊字符过滤：默认不转义（输出转义请使用e()函数），仅显式开启时生效；
        // 数组值递归过滤，保证行为一致
        $filter = isset($options['filter']) ? (bool)$options['filter'] : false;
        if ($filter) {
            $val = self::escape($val);
        }
        
        // 应用所有函数
        foreach ($function as $fun) {
            if (empty($fun)) continue;
            
            $fun = explode(':', $fun);
            $fun_name = $fun[0];
            
            // 验证函数是否存在
            if (!is_callable($fun_name)) {
                continue;
            }
            
            $parameter = isset($fun[1]) ? explode(',', $fun[1]) : [''];
            for ($i = 0; $i < count($parameter); $i++) {
                if ($parameter[$i] == '') {
                    $parameter[$i] = $val;
                }
            }
            $val = call_user_func_array($fun_name, $parameter);
        }
        
        return $val;
    }

    /**
     * 递归转义HTML特殊字符（与e()助手函数语义一致）
     *
     * @param mixed $val
     * @return mixed
     */
    private static function escape($val)
    {
        if (is_array($val)) {
            $result = [];
            foreach ($val as $k => $v) {
                $result[self::escape((string)$k)] = self::escape($v);
            }
            return $result;
        }
        if (is_string($val)) {
            return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        }
        return $val;
    }
}