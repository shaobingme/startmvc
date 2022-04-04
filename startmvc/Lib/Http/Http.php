<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace Startmvc\Lib\Http;

class Http
{
    public static function getConfig()
    {
        return include ROOT_PATH . '/config/common.php';
    }
    public static function handling($val, $options = [])
    {
        $default = isset($options['default']) ? $options['default'] : '';
        $val = is_null($val) ? $default : $val;
        $type = isset($options['type']) ? $options['type'] : '';
        if($type){
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

        //$type = gettype($val);
        //switch ($type) {
        //    case 'string':
        //        $val = (string)$val;
        //        break;
        //    case 'integer':
        //        $val = (int)$val;
        //        break;
        //    case 'double':
        //        $val = (float)$val;
        //        break;                
        //    case 'array':
        //        $val = (array)$val;
        //        break;
        //    case 'boolean':
        //        $val = (bool)$val;
        //        break;
        //    default:
        //        $val = (string)$val;
        //}
        $function = isset($options['function']) ? $options['function'] : [];
        $function = is_array($function) ? $function : [$function];
        $filter = isset($options['filter']) ? $options['filter'] : true;
        if ($filter && $type == 'string') {
            $function = array_merge(['htmlspecialchars'], $function);
        }
        foreach ($function as $fun) {
            $fun = explode(':', $fun);
            $fun_name = $fun[0];
            $parameter = isset($fun[1]) ? explode(',', $fun[1]) : [''];
            for($i = 0; $i < count($parameter); $i++){
                if($parameter[$i] == '')
                    $parameter[$i] = $val;
            }
            $val = call_user_func_array($fun_name, $parameter);
        }
        return $val;
    }
}