<?php

namespace startmvc\core\db;

use Closure;
use PDO;
use PDOException;
use startmvc\core\Exception;
use startmvc\core\Config;
use startmvc\core\Logger;
use startmvc\core\db\DbCache;

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

    /**
     * 子节点查询配置
     * @var array
     */
    protected $joinNodes = [];

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
        
        // 初始化缓存目录
        if (!empty($config['cachedir'])) {
            $this->cacheDir = $config['cachedir'];
            
            // 确保缓存目录存在
            if (!file_exists($this->cacheDir)) {
                mkdir($this->cacheDir, 0755, true);
            }
        }
        
        // 延迟连接，只在需要时连接
        // $this->connect(); 
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
            throw new \Exception('数据库连接失败：' . $e->getMessage());
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
     * @param string $field 字段名
     * @return mixed 字段的最大值
     */
    public function max($field)
    {
        return $this->executeAggregate('MAX', $field);
    }

    /**
     * 获取字段的最小值
     * 
     * @param string $field 字段名
     * @return mixed 字段的最小值
     */
    public function min($field)
    {
        return $this->executeAggregate('MIN', $field);
    }

    /**
     * 获取字段的总和
     * 
     * @param string $field 字段名
     * @return int|float|string|null 字段的总和或SQL字符串
     */
    public function sum($field)
    {
        $result = $this->executeAggregate('SUM', $field);
        
        // 如果返回的是SQL字符串，直接返回
        if (is_string($result)) {
            return $result;
        }
        
        return $result !== null ? (is_numeric($result) ? +$result : $result) : null;
    }

    /**
     * 获取记录数量
     * 
     * @param string $field 字段名，默认为 '*'
     * @return int|string 记录数量或SQL字符串
     */
    public function count($field = '*')
    {
        $result = $this->executeAggregate('COUNT', $field);
        
        // 如果返回的是SQL字符串，直接返回
        if (is_string($result)) {
            return $result;
        }
        
        return $result !== null ? (int)$result : 0;
    }

    /**
     * 获取字段的平均值
     * 
     * @param string $field 字段名
     * @return float|string|null 字段的平均值或SQL字符串
     */
    public function avg($field)
    {
        $result = $this->executeAggregate('AVG', $field);
        
        // 如果返回的是SQL字符串，直接返回
        if (is_string($result)) {
            return $result;
        }
        
        return $result !== null ? (float)$result : null;
    }

    /**
     * 克隆查询构建器（用于聚合查询等场景）
     * 
     * @return void
     */
    public function __clone()
    {
        // PDO 对象不能被克隆，需要保持引用
        // 其他属性会自动被克隆
    }

    /**
     * 执行聚合查询的通用方法
     * 
     * @param string $function 聚合函数名（MAX、MIN、SUM、COUNT、AVG）
     * @param string $field 字段名
     * @return mixed 聚合结果
     */
    protected function executeAggregate($function, $field)
    {
        // 如果设置了返回SQL标志，构建查询并返回SQL
        if ($this->_returnSql) {
            $clone = clone $this;
            $clone->select = $function . '(' . $field . ') as aggregate_value';
            $query = $clone->buildSelectQuery();
            $this->_returnSql = false;
            return $query;
        }
        
        // 克隆当前查询构建器，避免污染原对象
        $clone = clone $this;
        
        // 聚合查询不需要 select、limit、offset、orderBy
        $clone->select = $function . '(' . $field . ') as aggregate_value';
        $clone->limit = null;
        $clone->offset = null;
        $clone->orderBy = null;
        
        // 构建并执行查询
        $query = $clone->buildSelectQuery();
        $result = $clone->query($query, false);
        
        // 返回聚合结果
        if ($result && is_array($result) && isset($result['aggregate_value'])) {
            return $result['aggregate_value'];
        }
        
        return null;
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
            ? ' ' . $type . ' JOIN' . ' ' . $table . ' ON ' . $on
            : $this->join . ' ' . $type . ' JOIN' . ' ' . $table . ' ON ' . $on;

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
     * WHERE条件查询 - 支持多种灵活写法
     * 
     * 支持的用法：
     * where('id', 1)                           // id = 1
     * where('id', '>', 1)                      // id > 1
     * where('id', 'in', [1,2,3])              // id IN (1,2,3)
     * where('name', 'like', '%admin%')         // name LIKE '%admin%'
     * where('id', 'between', [1,10])          // id BETWEEN 1 AND 10
     * where('user|email', 'admin')             // user='admin' OR email='admin'
     * where('id&status', 1)                    // id=1 AND status=1
     * where('tag_ids', 'find_in_set', 6)      // FIND_IN_SET(6, tag_ids)
     * where('id=? AND status=?', [1, 1])      // 参数绑定
     * where(['id' => 1, 'status' => 1])       // 数组条件
     * where([['id', '>', 1], ['name', 'like', '%admin%']]) // 复杂数组
     * 
     * @param array|string $where 条件
     * @param string|array $operator 操作符或值
     * @param mixed $val 值
     * @param string $logic 逻辑连接符 AND/OR
     *
     * @return $this
     */
    public function where($where, $operator = null, $val = null, $logic = 'AND')
    {
        if (is_null($where) || (is_string($where) && empty($where))) {
            return $this;
        }

        $condition = $this->parseWhereCondition($where, $operator, $val, $logic);
        
        if ($this->grouped) {
            $condition = '(' . $condition;
            $this->grouped = false;
        }

        $this->where = is_null($this->where) 
            ? $condition 
            : $this->where . ' ' . $logic . ' ' . $condition;

        return $this;
    }

    /**
     * 解析WHERE条件
     * 
     * @param mixed $where 条件
     * @param mixed $operator 操作符
     * @param mixed $val 值
     * @param string $logic 逻辑连接符
     * @return string 解析后的条件
     */
    protected function parseWhereCondition($where, $operator, $val, $logic)
    {
        // 1. 数组条件处理
        if (is_array($where)) {
            return $this->parseArrayWhere($where, $logic);
        }

        // 2. 参数绑定 where('id=? AND status=?', [1, 1])
        if (is_array($operator)) {
            return $this->parseBindWhere($where, $operator);
        }

        // 3. 特殊字段语法 user|email 或 id&status
        if (strpos($where, '|') !== false || strpos($where, '&') !== false) {
            return $this->parseSpecialFieldWhere($where, $operator);
        }

        // 4. 标准条件处理
        return $this->parseStandardWhere($where, $operator, $val);
    }

    /**
     * 解析数组WHERE条件
     * 
     * @param array $conditions 条件数组
     * @param string $logic 逻辑连接符
     * @return string
     */
    protected function parseArrayWhere($conditions, $logic)
    {
        $whereParts = [];
        
        foreach ($conditions as $key => $condition) {
            if (is_numeric($key)) {
                // 索引数组: [['id', '>', 1], ['name', 'like', '%admin%']]
                if (is_array($condition)) {
                    $field = $condition[0] ?? '';
                    $op = $condition[1] ?? '=';
                    $value = $condition[2] ?? '';
                    $subLogic = $condition[3] ?? 'AND';
                    
                    $whereParts[] = $this->parseStandardWhere($field, $op, $value);
                }
            } else {
                // 关联数组: ['id' => 1, 'status' => 1]
                $whereParts[] = $key . ' = ' . $this->escape($condition);
            }
        }
        
        return implode(' ' . $logic . ' ', $whereParts);
    }

    /**
     * 解析参数绑定WHERE条件
     * 
     * @param string $where SQL模板
     * @param array $params 参数数组
     * @return string
     */
    protected function parseBindWhere($where, $params)
    {
        $parts = explode('?', $where);
        $result = '';
        
        foreach ($parts as $key => $part) {
            $result .= $part;
            if (isset($params[$key])) {
                $result .= $this->escape($params[$key]);
            }
        }
        
        return $result;
    }

    /**
     * 解析特殊字段WHERE条件 (user|email 或 id&status)
     * 
     * @param string $where 字段表达式
     * @param mixed $operator 值
     * @return string
     */
    protected function parseSpecialFieldWhere($where, $operator)
    {
        if (strpos($where, '|') !== false) {
            // OR条件: user|email
            $fields = explode('|', $where);
            $conditions = [];
            foreach ($fields as $field) {
                $conditions[] = trim($field) . ' = ' . $this->escape($operator);
            }
            return '(' . implode(' OR ', $conditions) . ')';
        }
        
        if (strpos($where, '&') !== false) {
            // AND条件: id&status
            $fields = explode('&', $where);
            $conditions = [];
            foreach ($fields as $field) {
                $conditions[] = trim($field) . ' = ' . $this->escape($operator);
            }
            return '(' . implode(' AND ', $conditions) . ')';
        }
        
        return $where . ' = ' . $this->escape($operator);
    }

    /**
     * 解析标准WHERE条件
     * 
     * @param string $field 字段名
     * @param string $operator 操作符
     * @param mixed $val 值
     * @return string
     */
    protected function parseStandardWhere($field, $operator, $val)
    {
        // 检查是否是原生SQL条件 (如 'id=10', 'age>18', 'status=1 AND type=2')
        if (is_null($operator) && is_null($val) && $this->isRawSqlCondition($field)) {
            return $field; // 直接返回原生SQL条件
        }
        
        // 如果只有两个参数，第二个参数是值
        if (is_null($val)) {
            $val = $operator;
            $operator = '=';
        }

        $operator = strtolower($operator);

        switch ($operator) {
            case 'in':
            case 'not in':
                return $this->buildInCondition($field, $val, $operator);
                
            case 'between':
            case 'not between':
                return $this->buildBetweenCondition($field, $val, $operator);
                
            case 'like':
            case 'not like':
                return $field . ' ' . strtoupper($operator) . ' ' . $this->escape($val);
                
            case 'find_in_set':
                return 'FIND_IN_SET(' . $this->escape($val) . ', ' . $field . ')';
                
            case 'is null':
            case 'isnull':
                return $field . ' IS NULL';
                
            case 'is not null':
            case 'isnotnull':
                return $field . ' IS NOT NULL';
                
            default:
                // 标准操作符: =, >, <, >=, <=, <>, !=
                if (in_array($operator, ['=', '>', '<', '>=', '<=', '<>', '!='])) {
                    return $field . ' ' . $operator . ' ' . $this->escape($val);
                }
                // 默认等于
                return $field . ' = ' . $this->escape($operator);
        }
    }

    /**
     * 构建IN条件
     * 
     * @param string $field 字段名
     * @param mixed $val 值
     * @param string $operator 操作符
     * @return string
     */
    protected function buildInCondition($field, $val, $operator)
    {
        if (is_string($val)) {
            $val = explode(',', $val);
        }
        
        if (!is_array($val)) {
            $val = [$val];
        }
        
        $values = array_map([$this, 'escape'], $val);
        return $field . ' ' . strtoupper($operator) . ' (' . implode(', ', $values) . ')';
    }

    /**
     * 构建BETWEEN条件
     * 
     * @param string $field 字段名
     * @param mixed $val 值
     * @param string $operator 操作符
     * @return string
     */
    protected function buildBetweenCondition($field, $val, $operator)
    {
        if (is_string($val)) {
            $val = explode(',', $val);
        }
        
        if (!is_array($val) || count($val) < 2) {
            throw new \InvalidArgumentException('BETWEEN条件需要两个值');
        }
        
        return $field . ' ' . strtoupper($operator) . ' ' . 
               $this->escape($val[0]) . ' AND ' . $this->escape($val[1]);
    }

    /**
     * 检查是否是原生SQL条件
     * 
     * @param string $condition 条件字符串
     * @return bool
     */
    protected function isRawSqlCondition($condition)
    {
        // 检查是否包含SQL操作符
        $sqlOperators = ['=', '>', '<', '>=', '<=', '<>', '!=', 'LIKE', 'IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL'];
        $condition = strtoupper($condition);
        
        foreach ($sqlOperators as $operator) {
            if (strpos($condition, $operator) !== false) {
                return true;
            }
        }
        
        // 检查是否包含AND/OR逻辑操作符
        if (strpos($condition, ' AND ') !== false || strpos($condition, ' OR ') !== false) {
            return true;
        }
        
        return false;
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
    public function page($perPage, $page)
    {
        $this->limit = $perPage;
        $this->offset = (($page > 0 ? $page : 1) - 1) * $perPage;

        return $this;
    }

    /**
     * 设置查询结果的分组
     * 
     * @param string|array $groupBy 分组字段
     *
     * @return $this
     */
    public function group($groupBy)
    {
        if (is_array($groupBy)) {
            $this->groupBy = implode(', ', $groupBy);
        } else {
            $this->groupBy = $groupBy;
        }
        return $this;
    }

    /**
     * 设置查询结果的分组（groupBy方法的别名，保持API一致性）
     * 
     * @param string|array $groupBy 分组字段
     *
     * @return $this
     */
    public function groupBy($groupBy)
    {
        return $this->group($groupBy);
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
     * 获取多条记录
     * 
     * @param bool|string $returnSql 是否仅返回SQL或返回类型
     * @param string $argument 参数（当$returnSql指定为类名时使用）
     *
     * @return mixed 返回多条记录
     */
    public function get($returnSql = null, $argument = null)
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
        
        return $this->query($query, true, $returnSql, $argument);
    }

    /**
     * 获取单条记录
     * 
     * @param bool|string $returnSql 是否仅返回SQL或返回类型
     * @param string $argument 参数（当$returnSql指定为类名时使用）
     *
     * @return mixed 返回单条记录
     */
    public function first($returnSql = null, $argument = null)
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
        
        return $this->query($query, false, $returnSql, $argument);
    }

    /**
     * 获取单条记录（现代化命名）
     * 注：此方法是 first() 的别名，为了提供更现代的API而添加，不是接口要求
     * 
     * @param bool|string $returnSql 是否仅返回SQL或返回类型
     * @param string $argument 参数（当$returnSql指定为类名时使用）
     *
     * @return mixed 返回单条记录
     */
    public function one($returnSql = null, $argument = null)
    {
        return $this->first($returnSql, $argument);
    }

    /**
     * 通过主键查找单条记录
     * 
     * @param mixed $id 主键值
     * @param string $primaryKey 主键字段名，默认为'id'
     * @param bool $throwIfNotFound 是否在找不到时抛出异常，默认false
     * @throws \Exception 当记录不存在且$throwIfNotFound为true时
     * @return mixed|null 返回找到的记录，未找到返回null（除非设置了抛异常）
     */
    public function find($id, $primaryKey = 'id', $throwIfNotFound = false)
    {
        if (is_null($id)) {
            if ($throwIfNotFound) {
                throw new \Exception("Invalid ID: null provided");
            }
            return null;
        }
        
        $result = $this->where($primaryKey, $id)->first();
        
        if (is_null($result) && $throwIfNotFound) {
            throw new \Exception("Record not found with {$primaryKey} = {$id}");
        }
        
        return $result;
    }

    /**
     * 通过主键查找单条记录，找不到抛出异常
     * 注：此方法是 find() 的便捷方法，等同于 find($id, $primaryKey, true)
     * 
     * @deprecated 2.0.0 建议使用 find($id, 'id', true) 代替
     * @param mixed $id 主键值
     * @param string $primaryKey 主键字段名，默认为'id'
     * @throws \Exception 当记录不存在时
     * @return mixed 返回找到的记录
     */
    public function findOrFail($id, $primaryKey = 'id')
    {
        return $this->find($id, $primaryKey, true);
    }

    /**
     * 通过主键查找多条记录
     * 
     * @param array $ids 主键值数组
     * @param string $primaryKey 主键字段名，默认为'id'
     * @return array 返回找到的记录数组
     */
    public function findMany(array $ids, $primaryKey = 'id')
    {
        if (empty($ids)) {
            return [];
        }
        
        return $this->in($primaryKey, $ids)->get();
    }

    /**
     * 获取多条记录（现代化命名，与one()对称）
     * 注：此方法是 get() 的别名，为了提供更现代的API而添加，不是接口要求
     * 
     * @param bool|string $returnSql 是否仅返回SQL或返回类型
     * @param string $argument 参数（当$returnSql指定为类名时使用）
     *
     * @return mixed 返回多条记录
     */
    public function many($returnSql = null, $argument = null)
    {
        return $this->get($returnSql, $argument);
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
     * @param bool|string $returnSql 是否仅返回SQL或返回类型
     * @param string $type 插入类型：INSERT, INSERT IGNORE, REPLACE, DUPLICATE
     *
     * @return bool|string|int|null|$this
     */
    public function insert(array $data, $returnSql = false, $type = 'INSERT')
    {
        $query = $this->buildInsertQuery($data, $type);
        
        // 存储插入数据和查询，用于getSql方法
        $this->_insertData = $data;
        $this->_lastQuery = $query;
        $this->_queryType = strtolower(explode(' ', $type)[0]); // insert, replace, etc.

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
     * @param string $type 插入类型：INSERT, INSERT IGNORE, REPLACE, DUPLICATE
     * @return string 构建好的SQL语句
     */
    protected function buildInsertQuery(array $data, $type = 'INSERT')
    {
        // 标准化插入类型，默认为标准INSERT
        $type = strtoupper($type);
        
        // 根据类型设置SQL前缀
        switch ($type) {
            case 'INSERT IGNORE':
            case 'IGNORE':
                $query = 'INSERT IGNORE INTO ' . $this->from;
                break;
            case 'INSERT OR IGNORE':
                $query = 'INSERT OR IGNORE INTO ' . $this->from;
                break;
            case 'REPLACE':
                $query = 'REPLACE INTO ' . $this->from;
                break;
            default:
        $query = 'INSERT INTO ' . $this->from;
                break;
        }

        $values = array_values($data);
        if (isset($values[0]) && is_array($values[0])) {
            $column = implode(', ', array_keys($values[0]));
            $query .= ' (' . $column . ') VALUES ';
            foreach ($values as $value) {
                $val = implode(', ', array_map([$this, 'escape'], $value));
                $query .= '(' . $val . '), ';
            }
            $query = trim($query, ', ');
            
            // 处理ON DUPLICATE KEY UPDATE
            if ($type === 'DUPLICATE') {
                $query .= ' ON DUPLICATE KEY UPDATE ';
                $updates = [];
                foreach (array_keys($values[0]) as $column) {
                    $updates[] = "$column = VALUES($column)";
                }
                $query .= implode(', ', $updates);
            }
        } else {
            $column = implode(', ', array_keys($data));
            $val = implode(', ', array_map([$this, 'escape'], $data));
            $query .= ' (' . $column . ') VALUES (' . $val . ')';
            
            // 处理ON DUPLICATE KEY UPDATE
            if ($type === 'DUPLICATE') {
                $query .= ' ON DUPLICATE KEY UPDATE ';
                $updates = [];
                foreach (array_keys($data) as $column) {
                    $updates[] = "$column = VALUES($column)";
                }
                $query .= implode(', ', $updates);
            }
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
     * 清空表数据
     *
     * @return mixed
     */
    public function truncate()
    {
        return $this->query('TRUNCATE TABLE ' . $this->from, false);
    }

    /**
     * 删除表
     *
     * @return mixed
     */
    public function drop()
    {
        return $this->query('DROP TABLE ' . $this->from, false);
    }

    /**
     * 检查数据表是否存在
     * 
     * @param string $table 表名（可选，如果不提供则使用当前设置的表名）
     * @return bool
     */
    public function is_table($table = null)
    {
        try {
            $tableName = $table ?: $this->from;
            if (empty($tableName)) {
                return false;
            }
            
            // 移除表前缀进行检查，因为SHOW TABLES会显示完整表名
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
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
        // 确保数据库连接已建立
        if ($this->pdo === null) {
            $this->connect();
        }
        
        // 初始化查询相关的属性
        $this->query = null;
        $this->error = null;
        $this->result = [];
        $this->numRows = 0;
        
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
                if ($type === PDO::FETCH_CLASS) {
                    $sql->setFetchMode($type, $argument);
                } else {
                    $sql->setFetchMode($type);
                }
                $this->result = $all ? $sql->fetchAll() : $sql->fetch();
                
                // 正确设置numRows - 对于SELECT查询，使用结果数量
                if ($this->result !== false) {
                    if ($all && is_array($this->result)) {
                        $this->numRows = count($this->result);
                    } else if (!$all && $this->result !== false) {
                        $this->numRows = 1;
                    } else {
                        $this->numRows = 0;
                    }
                } else {
                    $this->numRows = 0;
                }
                
                // 保存当前的joinNodes，因为reset会清空它
                $currentJoinNodes = $this->joinNodes;
                
                // 处理子节点查询结果
                if (!empty($currentJoinNodes) && is_array($this->result)) {
                    $this->result = $this->nodeParser($this->result);
                }

                if (!is_null($this->cache) && $type !== PDO::FETCH_CLASS) {
                    $this->cache->setCache($this->query, $this->result);
                }
                $this->cache = null;
            } else {
                $this->cache = null;
                $this->numRows = 0;
                $this->error = $this->pdo->errorInfo()[2];
                $this->error();
            }
        } elseif ((!$cache && !$str) || ($cache && !$str)) {
            $this->cache = null;
            $this->result = $this->pdo->exec($this->query);

            if ($this->result === false) {
                $this->numRows = 0;
                $this->error = $this->pdo->errorInfo()[2];
                $this->error();
            } else {
                // 对于非SELECT查询（INSERT、UPDATE、DELETE），exec()返回影响的行数
                $this->numRows = $this->result;
            }
        } else {
            $this->cache = null;
            $this->result = $cache;
            $this->numRows = is_array($this->result) ? count($this->result) : ($this->result === '' ? 0 : 1);
            
            // 对缓存结果进行子节点处理
            $currentJoinNodes = $this->joinNodes;
            if (!empty($currentJoinNodes) && is_array($this->result)) {
                $this->result = $this->nodeParser($this->result);
            }
        }

        // 计算执行时间并记录SQL
        $executionTime = microtime(true) - $startTime;
        self::logSql($this->query, $params, $executionTime);

        $this->queryCount++;
        
        // 自动重置查询构建器状态，防止数据污染
        // 注意：保留查询结果状态（numRows, insertId, query, error, result）
        $this->reset();
        
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
        if ($data === null) {
            return 'NULL';
        }
        
        if (is_int($data) || is_float($data)) {
            return $data;
        }
        
        if (is_bool($data)) {
            return $data ? 1 : 0;
        }
        
        // 如果是数组或对象，转换为JSON字符串
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }
        
        // 如果PDO连接存在，使用PDO的quote方法
        if ($this->pdo !== null) {
            return $this->pdo->quote($data);
        }
        
        // 如果没有PDO连接，使用简单的转义（主要用于SQL生成）
        return "'" . addslashes($data) . "'";
    }

    /**
     * 设置缓存
     * 
     * @param int|null $time 缓存时间（秒），null表示使用默认配置
     *
     * @return $this
     */
    public function cache($time = null)
    {
        // 如果未指定缓存时间，则使用配置中的默认值
        if ($time === null) {
            // 优先使用数据库配置中的cacheTime
            if (!empty($this->config['cachetime'])) {
                $time = $this->config['cachetime'];
            } else {
                // 如果数据库配置中没有设置，使用缓存配置中的defaultTime
                $cacheConfig = config('cache');
                $time = $cacheConfig['file']['cacheTime'] ?? 3600; // 默认1小时
            }
        }
        
        // 优先使用数据库配置中的cachedir
        if (!empty($this->config['cachedir'])) {
            $cacheDir = $this->config['cachedir'];
        } else {
            // 如果数据库配置中没有设置，则使用缓存配置
            $cacheConfig = config('cache');
            $cacheDir = CACHE_PATH . ($cacheConfig['file']['cacheDir'] ?? 'db/');
        }
        
        // 确保缓存目录存在
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        // 使用DbCache类创建缓存实例
        $this->cache = new DbCache($cacheDir, $time);

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
        $this->joinNodes = []; // 重置子节点查询配置
        // 注意：不重置 numRows, insertId, query, error, result，这些是查询结果状态
    }

    /**
     * 重置所有状态（包括查询结果）
     *
     * @return void
     */
    protected function resetAll()
    {
        $this->reset();
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
            'time' => number_format($executionTime * 1000, 2) . 'ms', // 转换为毫秒
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
     * 获取单个字段的值
     * @param string $column 字段名
     * @return mixed
     */
    public function value($column)
    {
        $this->select($column);
        $result = $this->first();
        if ($result && is_array($result) && isset($result[$column])) {
            return $result[$column];
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
    public static function lastQuery($withTime = false)
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

    /**
     * 布尔值取反（0变1，1变0）
     * 
     * @param string $column 列名
     * @return int|bool 影响的行数或失败时返回false
     */
    public function invert($column)
    {
        $query = "UPDATE {$this->from} SET {$column} = !{$column}";
        
        if (!is_null($this->where)) {
            $query .= ' WHERE ' . $this->where;
        }
        
        // 存储查询，用于getSql方法
        $this->_lastQuery = $query;
        $this->_queryType = 'update';
        
        if ($this->_returnSql) {
            $this->_returnSql = false;
            $this->reset();
            return $query;
        }
        
        return $this->query($query, false);
    }

    /**
     * 切换布尔字段值（toggle方法是invert的别名）
     * 
     * @param string $column 列名
     * @return int|bool 影响的行数或失败时返回false
     */
    public function toggle($column)
    {
        return $this->invert($column);
    }

    /**
     * 更新记录的时间戳字段
     * 类似Laravel的touch方法，用于更新updated_at等时间戳字段
     * 
     * @param string|array $columns 要更新的时间戳字段，默认为'updated_at'
     * @return int|bool 影响的行数或失败时返回false
     */
    public function touch($columns = 'updated_at')
    {
        $data = [];
        $timestamp = date('Y-m-d H:i:s');
        
        if (is_string($columns)) {
            $columns = [$columns];
        }
        
        foreach ($columns as $column) {
            $data[$column] = $timestamp;
        }
        
        return $this->update($data);
    }

    /**
     * 列值递增更新
     * 
     * @param string $column 列名
     * @param int $count 递增的数值，默认为1
     * @return int|bool 影响的行数或失败时返回false
     */
    public function inc($column, int $count = 1)
    {
        $query = "UPDATE {$this->from} SET {$column} = {$column} + {$count}";
        
        if (!is_null($this->where)) {
            $query .= ' WHERE ' . $this->where;
        }
        
        // 存储查询，用于getSql方法
        $this->_lastQuery = $query;
        $this->_queryType = 'update';
        
        if ($this->_returnSql) {
            $this->_returnSql = false;
            $this->reset();
            return $query;
        }
        
        return $this->query($query, false);
    }

    /**
     * 列值递减更新
     * 
     * @param string $column 列名
     * @param int $count 递减的数值，默认为1
     * @return int|bool 影响的行数或失败时返回false
     */
    public function dec($column, int $count = 1)
    {
        $query = "UPDATE {$this->from} SET {$column} = {$column} - {$count}";
        
        if (!is_null($this->where)) {
            $query .= ' WHERE ' . $this->where;
        }
        
        // 存储查询，用于getSql方法
        $this->_lastQuery = $query;
        $this->_queryType = 'update';
        
        if ($this->_returnSql) {
            $this->_returnSql = false;
            $this->reset();
            return $query;
        }
        
        return $this->query($query, false);
    }

    /**
     * 定义子节点查询
     * 
     * @param string $alias 子节点名称，将作为结果集中的键名
     * @param array $columns 子节点要查询的字段，格式为 ['字段别名' => '表.字段名']
     * @return $this
     */
    public function joinNode($alias, $columns)
    {
        // 检查是否有JOIN语句
        if (is_null($this->join)) {
            return $this;
        }
        
        $this->joinNodes[] = $alias;

        // 构建字段列表
        $fieldList = [];
        foreach ($columns as $alias => $field) {
            $fieldList[] = "{$field} AS {$alias}";
        }

        // 使用子查询构建嵌套数据
        $subQuery = "(SELECT " . implode(', ', $fieldList) . " FROM " . $this->from . " WHERE " . $this->where . ")";
        
        // 将子查询添加到SELECT中
        $this->select("({$subQuery}) AS {$alias}");
        
        // 添加GROUP BY子句
        if (is_null($this->groupBy)) {
            $this->group($this->from . '.id');
        }
                    
        return $this;
    }

    /**
     * 解析查询结果中的子查询数据
     * 递归处理结果集中的子节点数据
     * 
     * @param mixed $results 查询结果
     * @return mixed 处理后的结果
     */
    protected function nodeParser($results)
    {
        if (empty($this->joinNodes)) {
            return $results;
        }
        
        // 使用array_walk_recursive递归处理所有结果
        array_walk_recursive($results, function(&$value, $key) {
            // 处理嵌套对象
            if (is_object($value)) {
                return;
            }
            
            // 如果键名在joinNodes中，处理子查询结果
            if (in_array($key, $this->joinNodes)) {
                // 将子查询结果转换为数组
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
            }
        });
        
        return $results;
    }

    /**
     * 设置排序
     * 
     * @param string|array $columns 排序字段
     * @param string $order 排序方式
     * @return $this
     */
    public function order($columns, $order = 'ASC')
    {
        if (is_array($columns)) {
            $this->orderBy = implode(', ', array_map(function($column) use ($order) {
                return "{$column} {$order}";
            }, $columns));
        } else {
            // 处理特殊情况如RAND()
            if (strtolower($columns) === 'rand()') {
                $this->orderBy = $columns;
            } else if (stristr($columns, ' ')) {
                // 如果已经包含空格，可能已经指定了排序方向
                $this->orderBy = $columns;
            } else {
                $this->orderBy = "{$columns} {$order}";
            }
        }
        return $this;
    }

    /**
     * 判断字段为NULL
     * 
     * @param string|array $column 字段名
     * @return $this
     */
    public function isNull($column)
    {
        if (is_array($column)) {
            foreach ($column as $col) {
                $this->where($col, 'IS NULL');
            }
        } else {
            $this->where($column, 'IS NULL');
        }
        return $this;
    }

    /**
     * OR条件判断字段为NULL
     * 
     * @param string|array $column 字段名
     * @return $this
     */
    public function orIsNull($column)
    {
        if (is_array($column)) {
            foreach ($column as $col) {
                $this->orWhere($col, 'IS NULL');
            }
        } else {
            $this->orWhere($column, 'IS NULL');
        }
        return $this;
    }

    /**
     * 判断字段不为NULL
     * 
     * @param string|array $column 字段名
     * @return $this
     */
    public function notNull($column)
    {
        if (is_array($column)) {
            foreach ($column as $col) {
                $this->where($col, 'IS NOT NULL');
            }
        } else {
            $this->where($column, 'IS NOT NULL');
        }
        return $this;
    }

    /**
     * OR条件判断字段不为NULL
     * 
     * @param string|array $column 字段名
     * @return $this
     */
    public function orNotNull($column)
    {
        if (is_array($column)) {
            foreach ($column as $col) {
                $this->orWhere($col, 'IS NOT NULL');
            }
        } else {
            $this->orWhere($column, 'IS NOT NULL');
        }
        return $this;
    }
}