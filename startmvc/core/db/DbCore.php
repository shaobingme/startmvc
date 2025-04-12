<?php

namespace startmvc\core\db;

use Closure;
use PDO;
use PDOException;
use startmvc\core\Exception;
use startmvc\core\Config;
use startmvc\core\Logger;

/**
 * Dbcore - 实用的查询构建器和PDO类
 *
 * 数据查询的默认返回类型为关联数组(PDO::FETCH_ASSOC)。
 * 如果需要返回对象，可以在查询方法中指定类型为'object'。
 *
 * @package  Pdox
 */
class DbCore implements DbInterface
{
    /**
     * PDOx 版本
     *
     * @var string
     */
    const VERSION = '1.6.0';

    /**
     * @var PDO|null PDO实例
     */
    public $pdo = null;

    /**
     * @var mixed 查询变量
     */
    protected $select = '*';
    protected $from = null;
    protected $where = null;
    protected $limit = null;
    protected $offset = null;
    protected $join = null;
    protected $orderBy = null;
    protected $groupBy = null;
    protected $having = null;
    protected $grouped = false;
    protected $numRows = 0;
    protected $insertId = null;
    protected $query = null;
    protected $error = null;
    protected $result = [];
    protected $prefix = null;

    /**
     * @var array SQL运算符
     */
    protected $operators = ['=', '!=', '<', '>', '<=', '>=', '<>'];

    /**
     * @var Cache|null 缓存实例
     */
    protected $cache = null;

    /**
     * @var string|null 缓存目录
     */
    protected $cacheDir = null;

    /**
     * @var int 查询总数
     */
    protected $queryCount = 0;

    /**
     * @var bool 调试模式
     */
    protected $debug = true;

    /**
     * @var int 事务总数
     */
    protected $transactionCount = 0;

    // 存储临时更新数据，用于getSql()方法
    protected $_updateData = [];
    
    // 存储临时插入数据，用于getSql()方法
    protected $_insertData = [];
    
    // 存储最后构建的查询
    protected $_lastQuery = '';
    
    // 存储查询类型
    protected $_queryType = '';

    // 设置返回 SQL 标志
    protected $_returnSql = false;

    /**
     * @var array SQL查询日志
     */
    protected static $sqlLogs = [];

    /**
     * 单例实例
     * @var DbCore
     */
    protected static $instance;
    
    /**
     * 是否已连接
     * @var bool
     */
    protected $connected = false;

    /**
     * 数据库配置
     * @var array
     */
    protected $config;

    /**
     * 构造函数
     * @param array $config 数据库配置
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->prefix = $config['prefix'] ?? ''; // 设置表前缀
        $this->connect(); // 在构造函数中初始化连接
    }
    
    /**
     * 获取单例实例
     * @param array $config 数据库配置
     * @return DbCore
     */
    public static function getInstance($config = null)
    {
        if (static::$instance === null) {
            if ($config === null) {
                $config = include CONFIG_PATH . '/database.php';
                $config = $config['connections'][$config['driver']];
            }
            static::$instance = new static($config);
        }
        return static::$instance;
    }

    /**
     * 连接数据库
     * @return PDO
     * @throws Exception
     */
    protected function connect()
    {
        if ($this->connected) {
            return $this->pdo;
        }
        
        try {
            $dsn = "{$this->config['driver']}:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->connected = true;
            return $this->pdo;
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败：' . $e->getMessage());
        }
    }

    /**
     * 获取PDO实例
     * @return PDO
     */
    public function getPdo()
    {
        return $this->connect();
    }

    /**
     * 设置查询的表名
     * 
     * @param $table 表名
     *
     * @return $this
     */
    public function table($table)
    {
        if (is_array($table)) {
            $from = '';
            foreach ($table as $key) {
                $from .= $this->prefix . $key . ', ';
            }
            $this->from = rtrim($from, ', ');
        } else {
            if (strpos($table, ',') > 0) {
                $tables = explode(',', $table);
                foreach ($tables as $key => &$value) {
                    $value = $this->prefix . ltrim($value);
                }
                $this->from = implode(', ', $tables);
            } else {
                $this->from = $this->prefix . $table;
            }
        }

        return $this;
    }

    /**
     * 设置查询的表名（table方法的别名）
     * 
     * @param $table 表名
     *
     * @return $this
     */
    public function from($table)
    {
        return $this->table($table);
    }

    /**
     * 设置SELECT查询的字段
     * 
     * @param array|string $fields 要查询的字段
     *
     * @return $this
     */
    public function select($fields)
    {
        $select = is_array($fields) ? implode(', ', $fields) : $fields;
        $this->optimizeSelect($select);

        return $this;
    }

    /**
     * 获取字段的最大值
     * 
     * @param string      $field 字段名
     * @param string|null $name 结果别名
     *
     * @return $this
     */
    public function max($field, $name = null)
    {
        $column = 'MAX(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);

        return $this;
    }

    /**
     * 获取字段的最小值
     * 
     * @param string      $field 字段名
     * @param string|null $name 结果别名
     *
     * @return $this
     */
    public function min($field, $name = null)
    {
        $column = 'MIN(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);

        return $this;
    }

    /**
     * 获取字段的总和
     * 
     * @param string      $field 字段名
     * @param string|null $name 结果别名
     *
     * @return $this
     */
    public function sum($field, $name = null)
    {
        $column = 'SUM(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);

        return $this;
    }

    /**
     * 获取记录数量
     * 
     * @param string      $field 字段名
     * @param string|null $name 结果别名
     *
     * @return $this
     */
    public function count($field, $name = null)
    {
        $column = 'COUNT(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);

        return $this;
    }

    /**
     * 获取字段的平均值
     * 
     * @param string      $field 字段名
     * @param string|null $name 结果别名
     *
     * @return $this
     */
    public function avg($field, $name = null)
    {
        $column = 'AVG(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);

        return $this;
    }

    /**
     * 表连接操作
     * 
     * @param string      $table 要连接的表名
     * @param string|null $field1 第一个字段
     * @param string|null $operator 操作符
     * @param string|null $field2 第二个字段
     * @param string      $type 连接类型
     *
     * @return $this
     */
    public function join($table, $field1 = null, $operator = null, $field2 = null, $type = '')
    {
        $on = $field1;
        $table = $this->prefix . $table;

        if (!is_null($operator)) {
            $on = !in_array($operator, $this->operators)
                ? $field1 . ' = ' . $operator . (!is_null($field2) ? ' ' . $field2 : '')
                : $field1 . ' ' . $operator . ' ' . $field2;
        }

        $this->join = (is_null($this->join))
            ? ' ' . $type . 'JOIN' . ' ' . $table . ' ON ' . $on
            : $this->join . ' ' . $type . 'JOIN' . ' ' . $table . ' ON ' . $on;

        return $this;
    }

    /**
     * 内连接
     * 
     * @param string $table 要连接的表名
     * @param string $field1 第一个字段
     * @param string $operator 操作符
     * @param string $field2 第二个字段
     *
     * @return $this
     */
    public function innerJoin($table, $field1, $operator = '', $field2 = '')
    {
        return $this->join($table, $field1, $operator, $field2, 'INNER ');
    }

    /**
     * 左连接
     * 
     * @param string $table 要连接的表名
     * @param string $field1 第一个字段
     * @param string $operator 操作符
     * @param string $field2 第二个字段
     *
     * @return $this
     */
    public function leftJoin($table, $field1, $operator = '', $field2 = '')
    {
        return $this->join($table, $field1, $operator, $field2, 'LEFT ');
    }

    /**
     * 右连接
     * 
     * @param string $table 要连接的表名
     * @param string $field1 第一个字段
     * @param string $operator 操作符
     * @param string $field2 第二个字段
     *
     * @return $this
     */
    public function rightJoin($table, $field1, $operator = '', $field2 = '')
    {
        return $this->join($table, $field1, $operator, $field2, 'RIGHT ');
    }

    /**
     * 完全外连接
     * 
     * @param string $table 要连接的表名
     * @param string $field1 第一个字段
     * @param string $operator 操作符
     * @param string $field2 第二个字段
     *
     * @return $this
     */
    public function fullOuterJoin($table, $field1, $operator = '', $field2 = '')
    {
        return $this->join($table, $field1, $operator, $field2, 'FULL OUTER ');
    }

    /**
     * 左外连接
     * 
     * @param string $table 要连接的表名
     * @param string $field1 第一个字段
     * @param string $operator 操作符
     * @param string $field2 第二个字段
     *
     * @return $this
     */
    public function leftOuterJoin($table, $field1, $operator = '', $field2 = '')
    {
        return $this->join($table, $field1, $operator, $field2, 'LEFT OUTER ');
    }

    /**
     * 右外连接
     * 
     * @param string $table 要连接的表名
     * @param string $field1 第一个字段
     * @param string $operator 操作符
     * @param string $field2 第二个字段
     *
     * @return $this
     */
    public function rightOuterJoin($table, $field1, $operator = '', $field2 = '')
    {
        return $this->join($table, $field1, $operator, $field2, 'RIGHT OUTER ');
    }

    /**
     * WHERE条件查询
     * 
     * @param array|string $where 条件
     * @param string       $operator 操作符
     * @param string       $val 值
     * @param string       $type 类型
     * @param string       $andOr 连接词（AND/OR）
     *
     * @return $this
     */
    public function where($where, $operator = null, $val = null, $type = '', $andOr = 'AND')
    {
        if (is_array($where) && !empty($where)) {
            $_where = [];
            foreach ($where as $column => $data) {
                $_where[] = $type . $column . '=' . $this->escape($data);
            }
            $where = implode(' ' . $andOr . ' ', $_where);
        } else {
            if (is_null($where) || empty($where)) {
                return $this;
            }

            if (is_array($operator)) {
                $params = explode('?', $where);
                $_where = '';
                foreach ($params as $key => $value) {
                    if (!empty($value)) {
                        $_where .= $type . $value . (isset($operator[$key]) ? $this->escape($operator[$key]) : '');
                    }
                }
                $where = $_where;
            } elseif (!in_array($operator, $this->operators) || $operator == false) {
                $where = $type . $where . ' = ' . $this->escape($operator);
            } else {
                $where = $type . $where . ' ' . $operator . ' ' . $this->escape($val);
            }
        }

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = is_null($this->where)
            ? $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * OR WHERE条件查询
     * 
     * @param array|string $where 条件
     * @param string|null  $operator 操作符
     * @param string|null  $val 值
     *
     * @return $this
     */
    public function orWhere($where, $operator = null, $val = null)
    {
        return $this->where($where, $operator, $val, '', 'OR');
    }

    /**
     * NOT WHERE条件查询
     * 
     * @param array|string $where 条件
     * @param string|null  $operator 操作符
     * @param string|null  $val 值
     *
     * @return $this
     */
    public function notWhere($where, $operator = null, $val = null)
    {
        return $this->where($where, $operator, $val, 'NOT ', 'AND');
    }

    /**
     * OR NOT WHERE条件查询
     * 
     * @param array|string $where 条件
     * @param string|null  $operator 操作符
     * @param string|null  $val 值
     *
     * @return $this
     */
    public function orNotWhere($where, $operator = null, $val = null)
    {
        return $this->where($where, $operator, $val, 'NOT ', 'OR');
    }

    /**
     * @param string $where
     * @param bool   $not
     *
     * @return $this
     */
    public function whereNull($where, $not = false)
    {
        $where = $where . ' IS ' . ($not ? 'NOT' : '') . ' NULL';
        $this->where = is_null($this->where) ? $where : $this->where . ' ' . 'AND ' . $where;

        return $this;
    }

    /**
     * @param string $where
     *
     * @return $this
     */
    public function whereNotNull($where)
    {
        return $this->whereNull($where, true);
    }

    /**
     * @param Closure $obj
     *
     * @return $this
     */
    public function grouped(Closure $obj)
    {
        $this->grouped = true;
        call_user_func_array($obj, [$this]);
        $this->where .= ')';

        return $this;
    }

    /**
     * IN条件查询
     * 
     * @param string $field 字段名
     * @param array  $keys 值数组
     * @param string $type 类型
     * @param string $andOr 连接词（AND/OR）
     *
     * @return $this
     */
    public function in($field, array $keys, $type = '', $andOr = 'AND')
    {
        if (is_array($keys)) {
            $_keys = [];
            foreach ($keys as $k => $v) {
                $_keys[] = is_numeric($v) ? $v : $this->escape($v);
            }
            $where = $field . ' ' . $type . 'IN (' . implode(', ', $_keys) . ')';

            if ($this->grouped) {
                $where = '(' . $where;
                $this->grouped = false;
            }

            $this->where = is_null($this->where)
                ? $where
                : $this->where . ' ' . $andOr . ' ' . $where;
        }

        return $this;
    }

    /**
     * NOT IN条件查询
     * 
     * @param string $field 字段名
     * @param array  $keys 值数组
     *
     * @return $this
     */
    public function notIn($field, array $keys)
    {
        return $this->in($field, $keys, 'NOT ', 'AND');
    }

    /**
     * OR IN条件查询
     * 
     * @param string $field 字段名
     * @param array  $keys 值数组
     *
     * @return $this
     */
    public function orIn($field, array $keys)
    {
        return $this->in($field, $keys, '', 'OR');
    }

    /**
     * OR NOT IN条件查询
     * 
     * @param string $field 字段名
     * @param array  $keys 值数组
     *
     * @return $this
     */
    public function orNotIn($field, array $keys)
    {
        return $this->in($field, $keys, 'NOT ', 'OR');
    }

    /**
     * FIND_IN_SET条件查询
     * 
     * @param string         $field 字段名
     * @param string|integer $key 查找的值
     * @param string         $type 类型
     * @param string         $andOr 连接词（AND/OR）
     *
     * @return $this
     */
    public function findInSet($field, $key, $type = '', $andOr = 'AND')
    {
        $key = is_numeric($key) ? $key : $this->escape($key);
        $where =  $type . 'FIND_IN_SET (' . $key . ', '.$field.')';

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = is_null($this->where)
            ? $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * NOT FIND_IN_SET条件查询
     * 
     * @param string $field 字段名
     * @param string $key 查找的值
     *
     * @return $this
     */
    public function notFindInSet($field, $key)
    {
        return $this->findInSet($field, $key, 'NOT ');
    }

    /**
     * OR FIND_IN_SET条件查询
     * 
     * @param string $field 字段名
     * @param string $key 查找的值
     *
     * @return $this
     */
    public function orFindInSet($field, $key)
    {
        return $this->findInSet($field, $key, '', 'OR');
    }

    /**
     * OR NOT FIND_IN_SET条件查询
     * 
     * @param string $field 字段名
     * @param string $key 查找的值
     *
     * @return $this
     */
    public function orNotFindInSet($field, $key)
    {
        return $this->findInSet($field, $key, 'NOT ', 'OR');
    }

    /**
     * BETWEEN条件查询
     * 
     * @param string     $field 字段名
     * @param string|int $value1 最小值
     * @param string|int $value2 最大值
     * @param string     $type 类型
     * @param string     $andOr 连接词（AND/OR）
     *
     * @return $this
     */
    public function between($field, $value1, $value2, $type = '', $andOr = 'AND')
    {
        $where = '(' . $field . ' ' . $type . 'BETWEEN ' . ($this->escape($value1) . ' AND ' . $this->escape($value2)) . ')';
        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = is_null($this->where)
            ? $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * NOT BETWEEN条件查询
     * 
     * @param string     $field 字段名
     * @param string|int $value1 最小值
     * @param string|int $value2 最大值
     *
     * @return $this
     */
    public function notBetween($field, $value1, $value2)
    {
        return $this->between($field, $value1, $value2, 'NOT ', 'AND');
    }

    /**
     * OR BETWEEN条件查询
     * 
     * @param string     $field 字段名
     * @param string|int $value1 最小值
     * @param string|int $value2 最大值
     *
     * @return $this
     */
    public function orBetween($field, $value1, $value2)
    {
        return $this->between($field, $value1, $value2, '', 'OR');
    }

    /**
     * OR NOT BETWEEN条件查询
     * 
     * @param string     $field 字段名
     * @param string|int $value1 最小值
     * @param string|int $value2 最大值
     *
     * @return $this
     */
    public function orNotBetween($field, $value1, $value2)
    {
        return $this->between($field, $value1, $value2, 'NOT ', 'OR');
    }

    /**
     * LIKE条件查询
     * 
     * @param string $field 字段名
     * @param string $data 匹配的数据
     * @param string $type 类型
     * @param string $andOr 连接词（AND/OR）
     *
     * @return $this
     */
    public function like($field, $data, $type = '', $andOr = 'AND')
    {
        $like = $this->escape($data);
        $where = $field . ' ' . $type . 'LIKE ' . $like;

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = is_null($this->where)
            ? $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * OR LIKE条件查询
     * 
     * @param string $field 字段名
     * @param string $data 匹配的数据
     *
     * @return $this
     */
    public function orLike($field, $data)
    {
        return $this->like($field, $data, '', 'OR');
    }

    /**
     * NOT LIKE条件查询
     * 
     * @param string $field 字段名
     * @param string $data 匹配的数据
     *
     * @return $this
     */
    public function notLike($field, $data)
    {
        return $this->like($field, $data, 'NOT ', 'AND');
    }

    /**
     * OR NOT LIKE条件查询
     * 
     * @param string $field 字段名
     * @param string $data 匹配的数据
     *
     * @return $this
     */
    public function orNotLike($field, $data)
    {
        return $this->like($field, $data, 'NOT ', 'OR');
    }

    /**
     * 设置查询结果的LIMIT
     * 
     * @param int      $limit 限制数量
     * @param int|null $limitEnd 结束位置
     *
     * @return $this
     */
    public function limit($limit, $limitEnd = null)
    {
        $this->limit = !is_null($limitEnd)
            ? $limit . ', ' . $limitEnd
            : $limit;

        return $this;
    }

    /**
     * 设置查询结果的OFFSET
     * 
     * @param int $offset 偏移量
     *
     * @return $this
     */
    public function offset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * 设置分页
     * 
     * @param int $perPage 每页记录数
     * @param int $page 页码
     *
     * @return $this
     */
    public function pagination($perPage, $page)
    {
        $this->limit = $perPage;
        $this->offset = (($page > 0 ? $page : 1) - 1) * $perPage;

        return $this;
    }

    /**
     * 设置查询结果的排序
     * 
     * @param string      $orderBy 排序字段
     * @param string|null $orderDir 排序方向
     *
     * @return $this
     */
    public function orderBy($orderBy, $orderDir = null)
    {
        if (!is_null($orderDir)) {
            $this->orderBy = $orderBy . ' ' . strtoupper($orderDir);
        } else {
            $this->orderBy = stristr($orderBy, ' ') || strtolower($orderBy) === 'rand()'
                ? $orderBy
                : $orderBy . ' ASC';
        }

        return $this;
    }

    /**
     * 设置查询结果的分组
     * 
     * @param string|array $groupBy 分组字段
     *
     * @return $this
     */
    public function groupBy($groupBy)
    {
        $this->groupBy = is_array($groupBy) ? implode(', ', $groupBy) : $groupBy;

        return $this;
    }

    /**
     * 设置HAVING条件
     * 
     * @param string            $field 字段名
     * @param string|array|null $operator 操作符
     * @param string|null       $val 值
     *
     * @return $this
     */
    public function having($field, $operator = null, $val = null)
    {
        if (is_array($operator)) {
            $fields = explode('?', $field);
            $where = '';
            foreach ($fields as $key => $value) {
                if (!empty($value)) {
                    $where .= $value . (isset($operator[$key]) ? $this->escape($operator[$key]) : '');
                }
            }
            $this->having = $where;
        } elseif (!in_array($operator, $this->operators)) {
            $this->having = $field . ' > ' . $this->escape($operator);
        } else {
            $this->having = $field . ' ' . $operator . ' ' . $this->escape($val);
        }

        return $this;
    }

    /**
     * 获取影响的行数
     *
     * @return int
     */
    public function numRows()
    {
        return $this->numRows;
    }

    /**
     * 获取最后插入的ID
     *
     * @return int|null
     */
    public function insertId()
    {
        return $this->insertId;
    }

    /**
     * 显示错误信息
     * 
     * @throw PDOException
     */
    public function error()
    {
        if ($this->debug === true) {
            if (php_sapi_name() === 'cli') {
                die("Query: " . $this->query . PHP_EOL . "Error: " . $this->error . PHP_EOL);
            }

            $msg = '<h1>Database Error</h1>';
            $msg .= '<h4>Query: <em style="font-weight:normal;">"' . $this->query . '"</em></h4>';
            $msg .= '<h4>Error: <em style="font-weight:normal;">' . $this->error . '</em></h4>';
            die($msg);
        }

        throw new PDOException($this->error . '. (' . $this->query . ')');
    }

    /**
     * 获取单条记录
     * 
     * @param bool|string $returnSql 是否仅返回SQL或返回类型
     * @param string $argument 参数（当$returnSql指定为类名时使用）
     *
     * @return mixed 返回单条记录
     */
    public function get($returnSql = null, $argument = null)
    {
        $this->limit(1);
        $query = $this->buildSelectQuery();
        
        // 存储查询，用于getSql方法
        $this->_lastQuery = $query;
        $this->_queryType = 'select';
        
        if ($returnSql === true || $this->_returnSql) {
            $this->_returnSql = false;
            $this->reset();
            return $query;
        }
        
        $this->reset();
        return $this->query($query, false, $returnSql, $argument);
    }

    /**
     * 获取多条记录
     * 
     * @param bool|string $returnSql 是否仅返回SQL或返回类型
     * @param string $argument 参数（当$returnSql指定为类名时使用）
     *
     * @return mixed 返回多条记录
     */
    public function getAll($returnSql = null, $argument = null)
    {
        $query = $this->buildSelectQuery();
        
        // 存储查询，用于getSql方法
        $this->_lastQuery = $query;
        $this->_queryType = 'select';
        
        if ($returnSql === true || $this->_returnSql) {
            $this->_returnSql = false;
            $this->reset();
            return $query;
        }
        
        $this->reset();
        // 执行查询...返回所有结果
        return $this->query($query, true, $returnSql, $argument);
    }

    /**
     * 构建SELECT查询SQL
     * 
     * @return string 构建好的SQL语句
     */
    protected function buildSelectQuery()
    {
        $query = 'SELECT ' . $this->select . ' FROM ' . $this->from;

        if (!is_null($this->join)) {
            $query .= $this->join;
        }

        if (!is_null($this->where)) {
            $query .= ' WHERE ' . $this->where;
        }

        if (!is_null($this->groupBy)) {
            $query .= ' GROUP BY ' . $this->groupBy;
        }

        if (!is_null($this->having)) {
            $query .= ' HAVING ' . $this->having;
        }

        if (!is_null($this->orderBy)) {
            $query .= ' ORDER BY ' . $this->orderBy;
        }

        if (!is_null($this->limit)) {
            $query .= ' LIMIT ' . $this->limit;
        }

        if (!is_null($this->offset)) {
            $query .= ' OFFSET ' . $this->offset;
        }

        return $query;
    }

    /**
     * 插入数据
     * 
     * @param array $data 要插入的数据
     * @param bool  $returnSql 是否仅返回SQL，而不执行
     *
     * @return bool|string|int|null|$this
     */
    public function insert(array $data, $returnSql = false)
    {
        $query = $this->buildInsertQuery($data);
        
        // 存储插入数据和查询，用于getSql方法
        $this->_insertData = $data;
        $this->_lastQuery = $query;
        $this->_queryType = 'insert';

        if ($returnSql === true || $this->_returnSql) {
            $this->_returnSql = false;
            $this->reset();
            return $query;
        }

        if ($this->query($query, false)) {
            $this->insertId = $this->pdo->lastInsertId();
            return $this->insertId();
        }

        return false;
    }

    /**
     * 构建INSERT查询SQL
     * 
     * @param array $data 要插入的数据
     * @return string 构建好的SQL语句
     */
    protected function buildInsertQuery(array $data)
    {
        $query = 'INSERT INTO ' . $this->from;

        $values = array_values($data);
        if (isset($values[0]) && is_array($values[0])) {
            $column = implode(', ', array_keys($values[0]));
            $query .= ' (' . $column . ') VALUES ';
            foreach ($values as $value) {
                $val = implode(', ', array_map([$this, 'escape'], $value));
                $query .= '(' . $val . '), ';
            }
            $query = trim($query, ', ');
        } else {
            $column = implode(', ', array_keys($data));
            $val = implode(', ', array_map([$this, 'escape'], $data));
            $query .= ' (' . $column . ') VALUES (' . $val . ')';
        }

        return $query;
    }

    /**
     * 更新数据
     * 
     * @param array $data 要更新的数据
     * @param bool  $returnSql 是否仅返回SQL，而不执行
     *
     * @return mixed|string|$this
     */
    public function update(array $data, $returnSql = false)
    {
        $query = $this->buildUpdateQuery($data);
        
        // 存储更新数据和查询，用于getSql方法
        $this->_updateData = $data;
        $this->_lastQuery = $query;
        $this->_queryType = 'update';
        
        if ($returnSql === true || $this->_returnSql) {
            $this->_returnSql = false;
            $this->reset();
            return $query;
        }
        
        return $this->query($query, false);
    }

    /**
     * 构建UPDATE查询SQL
     * 
     * @param array $data 要更新的数据
     * @return string 构建好的SQL语句
     */
    protected function buildUpdateQuery(array $data)
    {
        $query = 'UPDATE ' . $this->from . ' SET ';
        $values = [];

        foreach ($data as $column => $val) {
            $values[] = $column . '=' . $this->escape($val);
        }
        $query .= implode(',', $values);

        if (!is_null($this->where)) {
            $query .= ' WHERE ' . $this->where;
        }

        if (!is_null($this->orderBy)) {
            $query .= ' ORDER BY ' . $this->orderBy;
        }

        if (!is_null($this->limit)) {
            $query .= ' LIMIT ' . $this->limit;
        }

        return $query;
    }

    /**
     * 删除数据
     * 
     * @param bool $returnSql 是否仅返回SQL，而不执行
     *
     * @return mixed|string|$this
     */
    public function delete($returnSql = false)
    {
        $query = $this->buildDeleteQuery();
        
        // 存储查询，用于getSql方法
        $this->_lastQuery = $query;
        $this->_queryType = 'delete';
        
        if ($returnSql === true || $this->_returnSql) {
            $this->_returnSql = false;
            $this->reset();
            return $query;
        }
        
        return $this->query($query, false);
    }

    /**
     * 构建DELETE查询SQL
     * 
     * @return string 构建好的SQL语句
     */
    protected function buildDeleteQuery()
    {
        $query = 'DELETE FROM ' . $this->from;

        if (!is_null($this->where)) {
            $query .= ' WHERE ' . $this->where;
        }

        if (!is_null($this->orderBy)) {
            $query .= ' ORDER BY ' . $this->orderBy;
        }

        if (!is_null($this->limit)) {
            $query .= ' LIMIT ' . $this->limit;
        }

        if ($query === 'DELETE FROM ' . $this->from) {
            $query = 'TRUNCATE TABLE ' . $this->from;
        }

        return $query;
    }

    /**
     * 获取当前构建的SQL语句而不执行
     * 
     * @return $this
     */
    public function getSql()
    {
        $this->_returnSql = true;
        return $this;
    }

    /**
     * 分析表
     *
     * @return mixed
     */
    public function analyze()
    {
        return $this->query('ANALYZE TABLE ' . $this->from, false);
    }

    /**
     * 检查表
     *
     * @return mixed
     */
    public function check()
    {
        return $this->query('CHECK TABLE ' . $this->from, false);
    }

    /**
     * 校验表
     *
     * @return mixed
     */
    public function checksum()
    {
        return $this->query('CHECKSUM TABLE ' . $this->from, false);
    }

    /**
     * 优化表
     *
     * @return mixed
     */
    public function optimize()
    {
        return $this->query('OPTIMIZE TABLE ' . $this->from, false);
    }

    /**
     * 修复表
     *
     * @return mixed
     */
    public function repair()
    {
        return $this->query('REPAIR TABLE ' . $this->from, false);
    }

    /**
     * 开始事务
     *
     * @return bool
     */
    public function transaction()
    {
        if (!$this->transactionCount++) {
            return $this->pdo->beginTransaction();
        }

        $this->pdo->exec('SAVEPOINT trans' . $this->transactionCount);
        return $this->transactionCount >= 0;
    }

    /**
     * 提交事务
     *
     * @return bool
     */
    public function commit()
    {
        if (!--$this->transactionCount) {
            return $this->pdo->commit();
        }

        return $this->transactionCount >= 0;
    }

    /**
     * 回滚事务
     *
     * @return bool
     */
    public function rollBack()
    {
        if (--$this->transactionCount) {
            $this->pdo->exec('ROLLBACK TO trans' . ($this->transactionCount + 1));
            return true;
        }

        return $this->pdo->rollBack();
    }

    /**
     * 执行SQL语句
     *
     * @return mixed
     */
    public function exec()
    {
        if (is_null($this->query)) {
            return null;
        }

        $query = $this->pdo->exec($this->query);
        if ($query === false) {
            $this->error = $this->pdo->errorInfo()[2];
            $this->error();
        }

        return $query;
    }

    /**
     * 获取查询结果
     * 
     * @param string $type 返回类型
     * @param string $argument 参数
     * @param bool   $all 是否获取所有结果
     *
     * @return mixed
     */
    public function fetch($type = null, $argument = null, $all = false)
    {
        if (is_null($this->query)) {
            return null;
        }

        $query = $this->pdo->query($this->query);
        if (!$query) {
            $this->error = $this->pdo->errorInfo()[2];
            $this->error();
        }

        $type = $this->getFetchType($type);
        if ($type === PDO::FETCH_CLASS) {
            $query->setFetchMode($type, $argument);
        } else {
            $query->setFetchMode($type);
        }

        $result = $all ? $query->fetchAll() : $query->fetch();
        $this->numRows = is_array($result) ? count($result) : 1;
        return $result;
    }

    /**
     * 获取所有查询结果
     * 
     * @param string $type 返回类型
     * @param string $argument 参数
     *
     * @return mixed
     */
    public function fetchAll($type = null, $argument = null)
    {
        return $this->fetch($type, $argument, true);
    }

    /**
     * 执行SQL查询
     * 
     * @param string     $query SQL查询语句
     * @param array|bool $all 是否获取所有结果
     * @param string     $type 返回类型
     * @param string     $argument 参数
     *
     * @return $this|mixed
     */
    public function query($query, $all = true, $type = null, $argument = null)
    {
        $this->reset();
        
        // 记录SQL开始执行时间
        $startTime = microtime(true);
        $params = [];

        if (is_array($all) || func_num_args() === 1) {
            $params = explode('?', $query);
            $newQuery = '';
            foreach ($params as $key => $value) {
                if (!empty($value)) {
                    $newQuery .= $value . (isset($all[$key]) ? $this->escape($all[$key]) : '');
                }
            }
            $this->query = $newQuery;
            
            // 结束计时并记录日志
            $executionTime = microtime(true) - $startTime;
            self::logSql($this->query, is_array($all) ? $all : [], $executionTime);
            
            return $this;
        }

        $this->query = preg_replace('/\s\s+|\t\t+/', ' ', trim($query));
        $str = false;
        foreach (['select', 'optimize', 'check', 'repair', 'checksum', 'analyze'] as $value) {
            if (stripos($this->query, $value) === 0) {
                $str = true;
                break;
            }
        }
        
        $type = $this->getFetchType($type);
        $cache = false;
        if (!is_null($this->cache) && $type !== PDO::FETCH_CLASS) {
            // 查询缓存时，设置默认返回关联数组
            $cache = $this->cache->getCache($this->query, true);
        }

        if (!$cache && $str) {
            $sql = $this->pdo->query($this->query);
            if ($sql) {
                $this->numRows = $sql->rowCount();
                if ($this->numRows > 0) {
                    if ($type === PDO::FETCH_CLASS) {
                        $sql->setFetchMode($type, $argument);
                    } else {
                        $sql->setFetchMode($type);
                    }
                    $this->result = $all ? $sql->fetchAll() : $sql->fetch();
                }

                if (!is_null($this->cache) && $type !== PDO::FETCH_CLASS) {
                    $this->cache->setCache($this->query, $this->result);
                }
                $this->cache = null;
            } else {
                $this->cache = null;
                $this->error = $this->pdo->errorInfo()[2];
                $this->error();
            }
        } elseif ((!$cache && !$str) || ($cache && !$str)) {
            $this->cache = null;
            $this->result = $this->pdo->exec($this->query);

            if ($this->result === false) {
                $this->error = $this->pdo->errorInfo()[2];
                $this->error();
            }
        } else {
            $this->cache = null;
            $this->result = $cache;
            $this->numRows = is_array($this->result) ? count($this->result) : ($this->result === '' ? 0 : 1);
        }

        // 计算执行时间并记录SQL
        $executionTime = microtime(true) - $startTime;
        self::logSql($this->query, $params, $executionTime);

        $this->queryCount++;
        return $this->result;
    }

    /**
     * 转义字符串
     * 
     * @param $data 要转义的数据
     *
     * @return string
     */
    public function escape($data)
    {
        return $data === null ? 'NULL' : (
            is_int($data) || is_float($data) ? $data : $this->pdo->quote($data)
        );
    }

    /**
     * 设置缓存
     * 
     * @param $time 缓存时间
     *
     * @return $this
     */
    public function cache($time)
    {
        $this->cache = new Cache($this->cacheDir, $time);

        return $this;
    }

    /**
     * 获取查询次数
     *
     * @return int
     */
    public function queryCount()
    {
        return $this->queryCount;
    }

    /**
     * 获取当前查询语句
     *
     * @return string|null
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * 析构函数，释放PDO实例
     *
     * @return void
     */
    public function __destruct()
    {
        $this->pdo = null;
    }

    /**
     * 重置查询参数
     *
     * @return void
     */
    protected function reset()
    {
        $this->select = '*';
        $this->from = null;
        $this->where = null;
        $this->limit = null;
        $this->offset = null;
        $this->orderBy = null;
        $this->groupBy = null;
        $this->having = null;
        $this->join = null;
        $this->grouped = false;
        $this->numRows = 0;
        $this->insertId = null;
        $this->query = null;
        $this->error = null;
        $this->result = [];
        $this->transactionCount = 0;
    }

    /**
     * 获取获取结果的类型
     * 
     * @param  $type 类型
     *
     * @return int
     */
    protected function getFetchType($type)
    {
        return $type === 'class'
            ? PDO::FETCH_CLASS
            : ($type === 'object'
                ? PDO::FETCH_OBJ
                : PDO::FETCH_ASSOC);
    }

    /**
     * 优化选择的字段
     *
     * @param string $fields 字段
     *
     * @return void
     */
    private function optimizeSelect($fields)
    {
        $this->select = $this->select === '*'
            ? $fields
            : $this->select . ', ' . $fields;
    }

    /**
     * 记录SQL查询日志
     *
     * @param string $sql SQL语句
     * @param array $params 绑定参数
     * @param float $executionTime 执行时间（毫秒）
     * @return void
     */
    protected static function logSql($sql, $params = [], $executionTime = 0)
    {
        self::$sqlLogs[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => number_format($executionTime, 2) . 'ms',
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * 获取SQL日志列表
     *
     * @return array 所有记录的SQL日志
     */
    public static function getSqlLogs()
    {
        return self::$sqlLogs;
    }

    /**
     * 获取第一条记录
     * @return array|object|null
     */
    public function first()
    {
        $this->limit(1);
        $result = $this->get();
        return $result ? $result[0] : null;
    }

    /**
     * 获取单个字段的值
     * @param string $column 字段名
     * @return mixed
     */
    public function value($column)
    {
        $this->select($column);
        $this->limit(1);
        $result = $this->get();
        if ($result && isset($result[0])) {
            return $result[0]->$column ?? null;
        }
        return null;
    }

    /**
     * 获取指定列的所有值
     * @param string $column 要获取的字段名
     * @param string|null $key 作为返回数组索引的字段名
     * @return array
     */
    public function column($column, $key = null)
    {
        if (!is_null($key)) {
            // 如果提供了$key参数，选择两个字段并构建关联数组
            $this->select(implode(', ', [$key, $column]));
            $result = $this->get();
            
            if (is_array($result)) {
                // 使用array_column构建键值对数组
                return array_column($result, $column, $key);
            }
            
            return $result;
        } else {
            // 如果只提供了$column参数，直接使用PDO::FETCH_COLUMN获取结果
            $this->select($column);
            
            // 重置查询以便直接执行
            $query = $this->buildSelectQuery();
            $this->reset();
            $this->query = $query;
            
            // 直接使用PDO获取列数据
            $stmt = $this->pdo->query($this->query);
            if ($stmt) {
                return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            }
            
            return [];
        }
    }

    /**
     * 获取最后执行的SQL语句
     * @param bool $withTime 是否包含执行时间
     * @return string|array 最后执行的SQL语句
     */
    public static function getLastSql($withTime = false)
    {
        $logs = self::getSqlLogs();
        if (empty($logs)) {
            return '';
        }
        
        $last = end($logs);
        
        if ($withTime) {
            return [
                'sql' => $last['sql'],
                'time' => $last['time'],
                'timestamp' => $last['timestamp']
            ];
        }
        
        return $last['sql'];
    }
}

/**
 * 使用链式操作获取SQL示例：
 * 
 * // SELECT操作：获取查询SQL - 使用getSql()方法（推荐）
 * $sql = Db::table('users')->where('id', 1)->getSql()->get();
 * // 结果: SELECT * FROM users WHERE id = 1 LIMIT 1
 * 
 * // SELECT操作：使用参数方式获取SQL（向后兼容）
 * $sql = Db::table('users')->where('id', 1)->get(true);
 * // 结果: SELECT * FROM users WHERE id = 1 LIMIT 1
 * 
 * // 获取多条记录SQL
 * $sql = Db::table('users')->where('status', 1)->getSql()->getAll();
 * // 结果: SELECT * FROM users WHERE status = 1
 * 
 * // INSERT操作：获取插入SQL - 使用getSql()方法（推荐）
 * $data = ['name' => 'test', 'email' => 'test@example.com'];
 * $sql = Db::table('users')->getSql()->insert($data);
 * // 结果: INSERT INTO users (name, email) VALUES ('test', 'test@example.com')
 * 
 * // INSERT操作：使用参数方式获取SQL（向后兼容）
 * $data = ['name' => 'test', 'email' => 'test@example.com'];
 * $sql = Db::table('users')->insert($data, true);
 * // 结果: INSERT INTO users (name, email) VALUES ('test', 'test@example.com')
 * 
 * // UPDATE操作：获取更新SQL - 使用getSql()方法（推荐）
 * $data = ['name' => 'updated'];
 * $sql = Db::table('users')->where('id', 1)->getSql()->update($data);
 * // 结果: UPDATE users SET name='updated' WHERE id = 1
 * 
 * // UPDATE操作：使用参数方式获取SQL（向后兼容）
 * $data = ['name' => 'updated'];
 * $sql = Db::table('users')->where('id', 1)->update($data, true);
 * // 结果: UPDATE users SET name='updated' WHERE id = 1
 * 
 * // DELETE操作：获取删除SQL - 使用getSql()方法（推荐）
 * $sql = Db::table('users')->where('id', 1)->getSql()->delete();
 * // 结果: DELETE FROM users WHERE id = 1
 * 
 * // DELETE操作：使用参数方式获取SQL（向后兼容）
 * $sql = Db::table('users')->where('id', 1)->delete(true);
 * // 结果: DELETE FROM users WHERE id = 1
 * 
 * // 使用不同返回类型的示例：
 * // 返回关联数组（默认）
 * $result = Db::table('users')->where('id', 1)->get();
 * // $result = ['id' => 1, 'name' => 'test', 'email' => 'test@example.com']
 * 
 * // 返回对象
 * $result = Db::table('users')->where('id', 1)->get('object');
 * // $result->id = 1
 * // $result->name = 'test'
 */
