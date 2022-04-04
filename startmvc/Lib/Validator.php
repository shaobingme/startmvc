<?php
/**
 * Validator 数据验证类
 * StartMVC超轻量级PHP开发框架
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
namespace Startmvc\Lib;
class Validator
{
    /**
     * 字符类型是否自动trim
     * @var bool
     */
    public $fieldAutoTrim = true;
    /**
     * 验证规则
     * @var array
     */
    private $_rules = [];
    /**
     * 验证过滤后的数据
     * @var array
     */
    private $_finalData = [];
    /**
     * 验证错误数组
     * @var array
     */
    private $_errorList = [];
    /**
     * 错误模板
     * @var array
     */
    private $_errorTpl = [
        'required' => '%s不能为空',
        'len' => '%s长度必须为%s个字符',
        'minlen' => '%s最小长度为%s个字符',
        'maxlen' => '%s最大长度为%s个字符',
        'width' => '%s长度必须为%s个字符',
        'minwidth' => '%s最小长度为%s个字符',
        'maxwidth' => '%s最大长度为%s个字符',
        'gt' => '%s必须大于%s',
        'lt' => '%s必须小于%s。',
        'gte' => '%s必须大于等于%s',
        'lte' => '%s必须小于等于%s',
        'eq' => '%s必须是%s',
        'neq' => '%s不能是%s',
        'in' => '%s只能是%s',
        'nin' => '%s不能是%s',
        'same' => '%s和%s必须一致',
        'is_mobile' => '手机号错误',
        'is_email' => '邮箱地址错误',
        'is_idcard' => '身份证号错误',
        'is_ip' => 'IP地址错误',
        'is_url' => 'URL地址错误',
        'is_array' => '%s必须是数组',
        'is_float' => '%s必须是浮点数',
        'is_int' => '%s必须是整数',
        'is_numeric' => '%s必须是数字',
        'is_string' => '%s必须是字符',
        'is_natural' => '%s必须是自然数',
        'is_natural_no_zero' => '%s必须是非零自然数',
        'is_hanzi' => '%s必须是中文',
        'is_mongoid' => '%s不是有效的MongoID',
        'is_alpha' => '%s必须是字母',
        'is_alpha_num' => '%s必须是字母或者数字',
        'is_alpha_num_dash' => '%s必须是字母、数字或者下划线',
    ];
    /**
     * 系统默认错误模板
     * @var array
     */
    private $_defaultTpl = [
        'default' => '%s格式错误',
        'call_error' => '%s cannot be callable',
    ];
    /**
     * 自定义函数
     * @var array
     */
    private $_tagMap = [];
    /**
     * Constructor
     */
    public function __construct()
    {
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }
    }
    /**
     * 是否默认去除两端空格
     * @param bool $autoTrim
     */
    public function setFieldAutoTrim($autoTrim = true)
    {
        $this->fieldAutoTrim = $autoTrim;
    }
    /**
     * 设置自定义函数
     * @param $tag
     * @param callable $func
     */
    public function setTagMap($tag, callable $func)
    {
        $this->_tagMap[$tag] = $func;
    }
    /**
     * 设置验证规则
     * @param array $rules
     * @return $this
     */
    public function setRules($rules = [])
    {
        if (empty($rules) || !is_array($rules)) {
            return $this;
        }
        $this->_rules = $rules;
        return $this;
    }
    /**
     * 验证数据
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function validate(array $data = [])
    {
        if (count($this->_rules) == 0) {
            return true;
        }
        $result = $this->_perform($data, $this->_rules);
        if (false === $result) {
            return false;
        } else {
            $this->_finalData = $result;
        }
        if (count($this->_errorList) == 0) {
            return true;
        }
        return false;
    }
    /**
     * 递归执行规则
     * @param $data
     * @param $rules
     * @return bool
     */
    private function _perform($data, $rules)
    {
        foreach ($rules as $field => $rule) {
            if (empty($field)) {
                continue;
            }
            if (is_array($rule)) {
                $data[$field] = $this->_perform($data[$field] ?? null, $rule);
            } else {
                $rule = $this->_parseOneRule($rule);
                $result = $this->_executeOneRule($data, $field, $rule['rules'], $rule['label'], $rule['msg']);
                if (false === $result) {
                    return false;
                }
                if (!is_bool($result)) {
                    $data[$field] = $result;
                }
            }
        }
        return $data;
    }
    /**
     * 解析单条规则
     * @param $rule
     * @return array
     */
    private function _parseOneRule($rule): array
    {
        $label = $msg = '';
        if (false !== strpos($rule, '`')) {
            if (preg_match('/``(.*)``/', $rule, $matches)) {
                $msg = $matches[1];
                $rule = preg_replace('/`(.*)`/', '', $rule);
            } elseif (preg_match('/`(.*)`/', $rule, $matches)) {
                $label = $matches[1];
                $rule = preg_replace('/`(.*)`/', '', $rule);
            }
        }
        return ['rules' => empty($rule) ? [] : explode('|', trim($rule)), 'label' => $label, 'msg' => $msg];
    }
    /**
     * 执行一条验证规则
     * @param $data
     * @param $field
     * @param array $rules
     * @param string $label
     * @param string $msg
     * @return bool
     */
    private function _executeOneRule($data, $field, $rules = [], $label = '', $msg = '')
    {
        if (empty($rules)) {
            return true;
        }
        //Auto Trim
        if ($this->fieldAutoTrim && isset($data[$field]) && (gettype($data[$field]) == 'string')) {
            $data[$field] = trim($data[$field]);
        }
        if (in_array('required', $rules)) {
            if (!isset($data[$field]) || !$this->required($data[$field])) {
                if (empty($msg)) {
                    $msg = sprintf($this->_getErrorTpl('required'), $label ?: $field);
                }
                $this->_setError($field, $msg);
                return false;
            }
            $rules = array_diff($rules, ['required']);
        } else {
            if (in_array('is_array', $rules)) {
                if (!isset($data[$field]) || !is_array($data[$field])) {
                    $data[$field] = [];
                }
                $rules = array_diff($rules, ['is_array']);
            } elseif (!isset($data[$field]) || !strlen(strval($data[$field]))) {
                return true;
            }
        }
        foreach ($rules as $rule) {
            if (empty($rule)) {
                continue;
            }
            //判断是否有参数 比如 max_length:8
            $param = [];
            $rawParam = '';
            if (strpos($rule, ':') !== false) {
                $match = explode(':', $rule);
                $rule = $match[0];
                $rawParam = $match[1];
                $param = explode(',', $match[1]);
            }
            //处理参数传递顺序
            if (false !== ($place = array_search('@@', $param))) {
                $param[$place] = $data[$field];
            } else {
                array_unshift($param, $data[$field]);
            }
            //same只能是同一层级的对比
            if ($rule == 'same') {
                $param[1] = $data[$param[1]] ?? null;
            }
            //下划线转驼峰
            $methodSnakeMapper = $this->_getMethodSnakeMapper($rule);
            if (method_exists(__CLASS__, $methodSnakeMapper)) {
                $result = call_user_func_array([__CLASS__, $methodSnakeMapper], $param);
            } elseif (function_exists($methodSnakeMapper)) {
                $result = call_user_func_array($methodSnakeMapper, $param);
            } elseif (method_exists(__CLASS__, $rule)) {
                $result = call_user_func_array([__CLASS__, $rule], $param);
            } elseif (function_exists($rule)) {
                $result = call_user_func_array($rule, $param);
            } elseif (isset($this->_tagMap[$rule])) {
                $result = call_user_func_array($this->_tagMap[$rule], $param);
            } else {
                $result = false;
                $msg = sprintf($this->_defaultTpl['call_error'], $rule);
            }
            if ($result === false) {
                if (empty($msg)) {
                    $tpl = $this->_getErrorTpl($rule);
                    if (false === $tpl) {
                        $msg = sprintf($this->_defaultTpl['default'], $label ?: $field);
                    } else {
                        $msg = sprintf($tpl, $label ?: $field, $rawParam);
                    }
                }
                $this->_setError($field, $msg);
                //continue;
                //break when an error accured
                return false;
            }
            //filter data
            if (!is_bool($result)) {
                $data[$field] = $result;
            }
        }
        return $data[$field];
    }
    /**
     * 下划线转驼峰函数名
     * @param $method
     * @return string
     */
    private function _getMethodSnakeMapper($method)
    {
        $method = array_reduce(explode('_', $method), function ($str, $item) {
            $str .= ucfirst($item);
            return $str;
        });
        return lcfirst($method);
    }
    /**
     * 获取错误模板
     * @param string $tag
     * @return bool|mixed
     */
    private function _getErrorTpl($tag = '')
    {
        return ($tag == '' || !isset($this->_errorTpl[$tag])) ? false : $this->_errorTpl[$tag];
    }
    /**
     * 设置错误
     * @param string $field
     * @param string $message
     */
    private function _setError($field = '', $message = '')
    {
        if (!isset($this->_errorList[$field])) {
            $this->_errorList[$field] = $message;
        }
    }
    /**
     * 获取所有数据 含未验证字段
     * @return array|mixed|string
     */
    public function getAllData()
    {
        return $this->_finalData;
    }
    /**
     * 获取所有数据 不含未验证字段
     * @return mixed
     */
    public function getData()
    {
        return $this->_getDataByRules($this->_rules, $this->_finalData);
    }
    /**
     * 递归获取数据
     * @param $rules
     * @param $data
     * @return array
     */
    private function _getDataByRules($rules, $data)
    {
        $result = [];
        foreach ($rules as $field => $rule) {
            if (is_array($rule)) {
                $result[$field] = $this->_getDataByRules($rule, $data[$field] ?? null);
            } else {
                if (array_key_exists($field, $data)) {
                    $result[$field] = $data[$field];
                }
            }
        }
        return $result;
    }
    /**
     * 获取指定key的数据 支持多级key 如user.hobby.name
     * @param string $field
     * @return array|mixed|string
     */
    public function getDataByField($field)
    {
        if (false === strpos($field, '.')) {
            return empty($field) ? '' : (array_key_exists($field, $this->_finalData) ? $this->_finalData[$field] : '');
        }
        $fields = explode('.', $field);
        $data = $this->_finalData;
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $data = $data[$field];
            } else {
                return '';
            }
        }
        return $data;
    }
    /**
     * 返回数组形式的错误
     * @return array
     */
    public function getError(): array
    {
        return $this->_errorList;
    }
    /**
     * 获取字符形式的错误
     * @param string $newline eg: <br> 、\n
     * @return string
     */
    public function getErrorString($newline = "\n"): string
    {
        return join($newline, $this->_errorList);
    }
    /**
     * Required
     * @param $var
     * @return bool
     */
    public function required($var): bool
    {
        return !(empty($var) && !is_numeric($var));
    }
    /**
     * 和另外一个字段值相同
     * @param $var
     * @param $compare_var
     * @return bool
     */
    public function same($var, $compare_var): bool
    {
        return ($var === $compare_var) ? true : false;
    }
    /**
     * 字符长度必须等于
     * @param $var
     * @param $len
     * @return bool
     */
    public static function len($var, $len): bool
    {
        $len = intval($len);
        return (mb_strlen($var) != $len) ? false : true;
    }
    /**
     * 字符最小长度 一个中文算1个字符
     * @param $var
     * @param $len
     * @return bool
     */
    public static function minlen($var, $len): bool
    {
        $len = intval($len);
        return (mb_strlen($var) < $len) ? false : true;
    }
    /**
     * 字符最大长度 一个中文算1个字符
     * @param $var
     * @param $len
     * @return bool
     */
    public static function maxlen($var, $len): bool
    {
        $len = intval($len);
        return (mb_strlen($var) > $len) ? false : true;
    }
    /**
     * 字符宽度必须等于 一个中文算2个字符
     * @param $var
     * @param $len
     * @return bool
     */
    public static function width($var, $len): bool
    {
        $len = intval($len);
        return (mb_strwidth($var) != $len) ? false : true;
    }
    /**
     * 字符最小宽度 一个中文算2个字符
     * @param $var
     * @param $len
     * @return bool
     */
    public static function minwidth($var, $len): bool
    {
        $len = intval($len);
        return (mb_strwidth($var) < $len) ? false : true;
    }
    /**
     * 字符最大宽度 一个中文算2个字符
     * @param $var
     * @param $len
     * @return bool
     */
    public static function maxwidth($var, $len): bool
    {
        $len = intval($len);
        return (mb_strwidth($var) > $len) ? false : true;
    }
    /**
     * 是否手机号
     * @param $var
     * @return bool
     */
    public static function isMobile($var): bool
    {
        return !!preg_match("/^1[3-9][0-9]{9}$/", $var);
    }
    /**
     * 是否邮箱
     * @access    public
     * @param    string
     * @return    bool
     */
    public static function isEmail($var): bool
    {
        return !!filter_var($var, FILTER_VALIDATE_EMAIL);
    }
    /**
     * 是否IP地址
     * @param string $var
     * @param string $type
     * @return boolean
     */
    public static function isIp($var, $type = 'ipv4'): bool
    {
        $type = strtolower($type);
        switch ($type) {
            case 'ipv6':
                $flag = FILTER_FLAG_IPV6;
                break;
            default:
                $flag = FILTER_FLAG_IPV4;
                break;
        }
        return !!filter_var($var, FILTER_VALIDATE_IP, $flag);
    }
    /**
     * 是否有效的URL地址
     * @param $var
     * @return bool
     */
    public static function isUrl($var): bool
    {
        //return !!filter_var($var, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED);
        return !!filter_var($var, FILTER_VALIDATE_URL);
    }
    /**
     * 是否身份证
     * @param $var
     * @return bool
     */
    public static function isIdcard($var): bool
    {
        if (preg_match('/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/', $var)) {
            return true;
        }
        return false;
    }
    /**
     * 自然数  (0,1,2,3, etc.)
     * @access    public
     * @param    string
     * @return    bool
     */
    public static function isNatural($var): bool
    {
        return (bool)preg_match('/^[0-9]+$/', $var);
    }
    /**
     * 自然数除了0  (1,2,3, etc.)
     * @access    public
     * @param    string
     * @return    bool
     */
    public static function isNaturalNoZero($var): bool
    {
        if (!preg_match('/^[0-9]+$/', $var)) {
            return false;
        }
        if ($var == 0) {
            return false;
        }
        return true;
    }
    /**
     * 判断是否中文
     * @param $var
     * @return bool
     */
    public static function isHanzi($var): bool
    {
        if (preg_match('/^\p{Han}+$/u', $var)) {
            return true;
        }
        return false;
    }
    /**
     * 是否有效的mongoid
     * @param $var
     * @return bool
     */
    public static function isMongoid($var): bool
    {
        return preg_match('/^[0-9a-fA-F]+$/', $var) && (strlen($var) == 24);
    }
    /**
     * 字母
     * @param $var
     * @return bool
     */
    public static function isAlpha($var): bool
    {
        return (!preg_match("/^([a-z])+$/i", $var)) ? false : true;
    }
    /**
     * 字母数字
     * @param $var
     * @return bool
     */
    public static function isAlphaNum($var): bool
    {
        return (!preg_match("/^([a-z0-9])+$/i", $var)) ? false : true;
    }
    /**
     * 字母、数字、下划线
     * @param $var
     * @return bool
     */
    public static function isAlphaNumDash($var): bool
    {
        return (!preg_match("/^([a-z0-9_])+$/i", $var)) ? false : true;
    }
    /**
     * 大于
     * @param $var
     * @param $min
     * @return bool
     */
    public static function gt($var, $min): bool
    {
        if (!is_numeric($var)) {
            return false;
        }
        return $var > $min;
    }
    /**
     * 小于
     * @param $var
     * @param $max
     * @return bool
     */
    public static function lt($var, $max): bool
    {
        if (!is_numeric($var)) {
            return false;
        }
        return $var < $max;
    }
    /**
     * 大于等于
     * @param $var
     * @param $min
     * @return bool
     */
    public static function gte($var, $min): bool
    {
        if (!is_numeric($var)) {
            return false;
        }
        return $var >= $min;
    }
    /**
     * 小于等于
     * @param $var
     * @param $max
     * @return bool
     */
    public static function lte($var, $max): bool
    {
        if (!is_numeric($var)) {
            return false;
        }
        return $var <= $max;
    }
    /**
     * 等于
     * @param $var
     * @param $obj
     * @return bool
     */
    public static function eq($var, $obj): bool
    {
        if (!is_numeric($var) && empty($var)) {
            return false;
        }
        return $var == $obj;
    }
    /**
     * 不等于
     * @param $var
     * @param $obj
     * @return bool
     */
    public static function neq($var, $obj): bool
    {
        if (!is_numeric($var) && empty($var)) {
            return false;
        }
        return $var != $obj;
    }
    /**
     * 必须在集合中
     * @param $var
     * @param array ...$set
     * @return bool
     */
    public static function in($var, ...$set): bool
    {
        if (in_array($var, $set)) {
            return true;
        }
        return false;
    }
    /**
     * 不在集合中
     * @param $var
     * @param array ...$set
     * @return bool
     */
    public static function nin($var, ...$set): bool
    {
        if (!in_array($var, $set)) {
            return true;
        }
        return false;
    }
    /**
     * 过滤三字节以上的字符
     * @param $var
     * @return string
     */
    public static function filterUtf8($var): string
    {
        /*utf8 编码表：
         * Unicode符号范围           | UTF-8编码方式
         * u0000 0000 - u0000 007F   | 0xxxxxxx
         * u0000 0080 - u0000 07FF   | 110xxxxx 10xxxxxx
         * u0000 0800 - u0000 FFFF   | 1110xxxx 10xxxxxx 10xxxxxx
         *
         */
        $ret = '';
        $var = str_split(bin2hex($var), 2);
        $mo = 1 << 7;
        $mo2 = $mo | (1 << 6);
        $mo3 = $mo2 | (1 << 5); //三个字节
        $mo4 = $mo3 | (1 << 4); //四个字节
        $mo5 = $mo4 | (1 << 3); //五个字节
        $mo6 = $mo5 | (1 << 2); //六个字节
        for ($i = 0; $i < count($var); $i++) {
            if ((hexdec($var[$i]) & ($mo)) == 0) {
                $ret .= chr(hexdec($var[$i]));
                continue;
            }
            //4字节 及其以上舍去
            if ((hexdec($var[$i]) & ($mo6)) == $mo6) {
                $i = $i + 5;
                continue;
            }
            if ((hexdec($var[$i]) & ($mo5)) == $mo5) {
                $i = $i + 4;
                continue;
            }
            if ((hexdec($var[$i]) & ($mo4)) == $mo4) {
                $i = $i + 3;
                continue;
            }
            if ((hexdec($var[$i]) & ($mo3)) == $mo3) {
                $i = $i + 2;
                if (((hexdec($var[$i]) & ($mo)) == $mo) && ((hexdec($var[$i - 1]) & ($mo)) == $mo)) {
                    $r = chr(hexdec($var[$i - 2])) .
                        chr(hexdec($var[$i - 1])) .
                        chr(hexdec($var[$i]));
                    $ret .= $r;
                }
                continue;
            }
            if ((hexdec($var[$i]) & ($mo2)) == $mo2) {
                $i = $i + 1;
                if ((hexdec($var[$i]) & ($mo)) == $mo) {
                    $ret .= chr(hexdec($var[$i - 1])) . chr(hexdec($var[$i]));
                }
                continue;
            }
        }
        return $ret;
    }
}