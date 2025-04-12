<?php
/*------------------------------------------------------------------
 | Software: APHP - A PHP TOP Framework
 | Site: https://aphp.top
 |------------------------------------------------------------------
 | (C)2020-2025 无念<24203741@qq.com>,All Rights Reserved.
 |-----------------------------------------------------------------*/
declare(strict_types=1);

namespace aphp\core\db;

use aphp\core\Config;
use ArrayAccess;
use Closure;
use Exception;
use Iterator;
use PDO;
use PDOStatement;
use aphp\core\Cache;
use aphp\core\Log;
use aphp\core\Middleware;
use aphp\core\Pagination;
use aphp\core\Single;

/**
 * 查询构造器
 */
class Query implements ArrayAccess, Iterator
{
    use Single;

    protected object $db; //数据库连接
    protected object $builder; //sql构建器
    protected string $prefix; //表前缀
    protected string $table = ''; //默认表
    protected array $fieldList = []; //表字段
    protected string $pk = 'id'; //表主键
    protected array $options = []; //构建选项
    protected array $bind = []; //绑定参数
    public array $data = []; //对象数据
    protected ?object $page = null; //分页对象
    protected bool $recordLog = true; //是否记录日志

    private function __construct(string $table = '', $config = [])
    {
        $this->db = Connection::init($config);
        $this->prefix = $this->db->prefix;
        if (!empty($table)) {
            $this->fieldList = $this->getFields($table);
            $this->pk = $this->fieldList['pk'] ?? 'id';
            $this->table = $table;
        }
        $this->builder = Builder::init($this->db, $this);
    }

    //获取构建选项
    public function getOptions(string $name = '')
    {
        if ('' === $name) {
            return $this->options;
        }
        return $this->options[$name] ?? null;
    }

    //获取数据库配置
    public function getConfig(string $name = '')
    {
        return $this->db->getConfig($name);
    }

    //获取表前缀
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    //获取受影响条数或记录条数
    public function getNumRows(): int
    {
        return $this->db->getNumRows();
    }

    //获取最后插入id
    public function getInsertId(?string $pk = null)
    {
        return $this->db->getInsertId($pk);
    }

    //转义特殊字符
    public function quote(string $value): string
    {
        return $this->db->quote($value);
    }

    //事务处理
    public function trans(Closure $closure): bool
    {
        return $this->db->trans($closure);
    }

    //开启事务
    public function startTrans(): object
    {
        $this->db->startTrans();
        return $this;
    }

    //事务提交
    public function commit(): object
    {
        $this->db->commit();
        return $this;
    }

    //事务回滚
    public function rollback(): object
    {
        $this->db->rollback();
        return $this;
    }

    //获取当前操作表
    public function getTable(): string
    {
        $table = $this->getOptions('table');
        return $table ?: $this->table;
    }

    //获取表字段
    public function getFields(string $table = ''): array
    {
        if (empty($table)) {
            $table = $this->getTable();
        }
        if ($table === $this->table) {
            return $this->fieldList;
        }
        return Cache::init()->make('field/' . $table . '_field', fn() => $this->parseFieldList($table));
    }

    //获取表字段列表
    protected function parseFieldList(string $table): array
    {
        $data = [];
        $res = $this->db->query("SHOW COLUMNS FROM `$this->prefix$table`");
        foreach ((array)$res as $vo) {
            if ($vo['Key'] == 'PRI' && $vo['Extra'] == 'auto_increment') {
                $data['pk'] = $vo['Field'];
            } else {
                $data[] = $vo['Field'];
            }
        }
        return $data;
    }

    //获取表主键
    public function getPk(string $table = ''): string
    {
        if (empty($table)) {
            $table = $this->getTable();
        }
        if ($table === $this->table) {
            return $this->pk;
        }
        $sql = 'SHOW COLUMNS FROM `' . $this->prefix . $table . '` WHERE `Key` = "PRI" AND `Extra` ="auto_increment"';
        $result = $this->db->query($sql);
        return $result[0]['Field'] ?? 'id';
    }

    //表是否存在
    public function hasTable(string $table): bool
    {
        $res = $this->db->query("SHOW TABLES LIKE '$this->prefix$table'");
        return !empty($res);
    }

    //sql查询
    public function query(string $sql, array $bind = [], array $options = [])
    {
        $this->recordLog($sql, $bind);
        return $this->db->query($sql, $bind, $options);
    }

    //sql操作
    public function execute(string $sql, array $bind = [], bool $getInsertId = false)
    {
        $this->recordLog($sql, $bind, true);
        $res = $this->db->execute($sql, $bind, $getInsertId);
        if ($res > 0 && $this->recordLog) {
            $realSql = $this->getRealSql($sql, $bind);
            Middleware::init()->execute('framework.database_execute', ['sql' => $realSql]);
        } else {
            $this->recordLog = true;
        }
        return $res;
    }

    //记录日志
    protected function recordLog(string $sql, array $bind = [], bool $isExecute = false): void
    {
        $realSql = $this->getRealSql($sql, $bind);
        $log_sql = Config::init()->get('app.log_sql_level', 0);
        if ($log_sql == 2 || ($log_sql == 1 && $isExecute)) {
            Log::init()->record($realSql, 'sql');
        }
        if (!$isExecute) {
            Middleware::init()->execute('framework.database_query', ['sql' => $realSql]);
        }
    }

    //解析前置选项
    public function parseOptions(): array
    {
        $options = $this->options;
        if (empty($options['table'])) {
            $options['table'] = $this->table;
        }
        if (empty($options['table'])) {
            throw new Exception('The query table is not set!');
        }
        $options['field'] ??= '*';
        $options['data'] ??= [];
        $options['where'] ??= [];
        $options['order'] ??= [];
        $options['lock'] ??= false;
        $options['distinct'] ??= false;
        $options['sql'] ??= false;
        $options['obj'] ??= false;
        $options['expire'] ??= -1;
        $params = ['join', 'union', 'group', 'having', 'limit', 'force', 'comment', 'extra', 'using', 'duplicate'];
        foreach ($params as $name) {
            $options[$name] ??= '';
        }
        $this->options = [];
        return $options;
    }

    public function getBind(): array
    {
        $bind = $this->bind;
        $this->bind = [];
        return $bind;
    }

    public function isBind(string $key): bool
    {
        return isset($this->bind[$key]);
    }

    public function bind($key, $value = false, int $type = PDO::PARAM_STR): object
    {
        if (is_array($key)) {
            $this->bind = array_merge($this->bind, $key);
        } else {
            $this->bind[$key] = [$value, $type];
        }
        return $this;
    }

    // 缓存有效时间(秒)
    public function cache(int $expire = 0): object
    {
        $this->options['expire'] = $expire;
        return $this;
    }

    //查询多条
    public function select()
    {
        $options = $this->parseOptions();
        $sql = $this->builder->select($options);
        $bind = $this->getBind();
        if ($options['sql']) {
            return $this->getRealSql($sql, $bind);
        }
        return $this->query($sql, $bind, $options);
    }

    //分页查询
    public function paginate(int $pageSize = 0, int $showNum = 0, string $getVar = '')
    {
        $options = $this->options;
        $total = $this->count();
        $this->page = Pagination::init($total, $pageSize, $showNum, $getVar);
        $this->options = $options;
        $this->options['limit'] = $this->page->getLimit();
        $res = $this->select();
        if (!is_array($res)) {
            return $res;
        }
        $this->data = $res;
        return $this;
    }

    // 分页链接html
    public function links(): string
    {
        return !is_null($this->page) ? $this->page->getHtml() : '';
    }

    // 分页属性
    public function attr(string $type = '')
    {
        return !is_null($this->page) ? $this->page->getAttr($type) : '';
    }

    //查询单条
    public function find($id = 0)
    {
        $id = intval($id);
        if ($id > 0) {
            $this->where($this->getPk(), $id);
        }
        $res = $this->limit(1)->select();
        if (is_string($res) || $res instanceof PDOStatement) {
            return $res;
        }
        return $res[0] ?? [];
    }

    //查询单字段
    public function value(string $field)
    {
        $res = $this->field($field)->find();
        if (is_string($res) || $res instanceof PDOStatement) {
            return $res;
        }
        return $res[$field] ?? '';
    }

    //根据设置获取column
    public function getColumn(string $setting)
    {
        if (preg_match('/^(\w+)\.(\w+)=(\w+)@?(.*)$/', $setting, $match)) {
            [, $table, $pk, $field, $where] = $match;
            return $this->table($table)->where($where)->column($field, $pk);
        }
        return false;
    }

    //键名=>字段值
    public function column(string $fields, string $key = '')
    {
        $isGetOne = !empty($fields) && $fields != '*' && !str_contains($fields, ','); //是否获取一维数组
        $isClearKey = false;
        if (!empty($key) && $fields != '*') {
            $fields = explode(',', $fields);
            if (!in_array($key, $fields)) {
                $fields[] = $key;
                $isClearKey = true;
            }
        }
        $res = $this->field($fields)->select();
        if (is_string($res) || $res instanceof PDOStatement) {
            return $res;
        }
        $data = [];
        foreach ($res as $k => $v) {
            if (!empty($key)) {
                $k = $v[$key];
            }
            if ($isClearKey) {
                unset($v[$key]);
            }
            $data[$k] = $isGetOne ? current($v) : $v;
        }
        return $data;
    }

    //show status 返因处理
    public function getResult(string $sql, array $bind = []): array
    {
        $res = $this->query($sql, $bind);
        if (isset($res[0]['Variable_name'])) {
            $data = [];
            foreach ($res as $re) {
                $data[$re['Variable_name']] = $re['Value'];
            }
            return $data;
        }
        return $res;
    }

    //删除记录
    public function delete($ids = [])
    {
        if (!empty($ids)) {
            $pk = $this->getPk();
            if (is_numeric($ids)) {
                $this->where($pk, $ids);
            } else {
                $this->where($pk, 'in', $ids);
            }
        }
        $options = $this->parseOptions();
        if (empty($options['where'])) {
            throw new Exception('The delete operation query condition cannot be empty!');
        }
        $sql = $this->builder->delete($options);
        $bind = $this->getBind();
        if ($options['sql']) {
            return $this->getRealSql($sql, $bind);
        }
        return $this->execute($sql, $bind);
    }

    //数据字段过滤
    public function getFilterData(array $data, string $table = '', bool $getSql = false): array
    {
        $fields = $this->getFields($table);
        if (!$getSql) {
            unset($fields['pri']);
        }
        return array_filter($data, fn($k) => in_array($k, $fields), ARRAY_FILTER_USE_KEY);
    }

    //更新数据
    public function update(array $data = [])
    {
        $options = $this->parseOptions();
        if (empty($options['where'])) {
            throw new Exception('The update operation query condition cannot be empty!');
        }
        $data = array_merge($options['data'], $data);
        $data = $this->getFilterData($data, $options['table'], $options['sql']);
        $sql = $this->builder->update($data, $options);
        if (!$sql) {
            throw new Exception('The generated query statement is empty!');
        }
        $bind = $this->getBind();
        if ($options['sql']) {
            return $this->getRealSql($sql, $bind);
        }
        return $this->execute($sql, $bind);
    }

    //更新单个字段值
    public function setField(string $field, $value)
    {
        return $this->data($field, $value)->update();
    }

    //更新自增
    public function setInc($field, int $step = 1)
    {
        return $this->inc($field, $step)->update();
    }

    //更新自减
    public function setDec($field, int $step = 1)
    {
        return $this->dec($field, $step)->update();
    }

    //新增
    public function insert(array $data = [], bool $getInsertId = false, bool $replace = false)
    {
        $options = $this->parseOptions();
        $data = array_merge($options['data'], $data);
        $data = $this->getFilterData($data, $options['table'], $options['sql']);
        $sql = $this->builder->insert($data, $options, $replace);
        $bind = $this->getBind();
        if ($options['sql']) {
            return $this->getRealSql($sql, $bind);
        }
        return empty($sql) ? false : $this->execute($sql, $bind, $getInsertId);
    }

    //替换新增
    public function replace(array $data = [], bool $getInsertId = false)
    {
        return $this->insert($data, $getInsertId, true);
    }

    //新增返回插入ID
    public function insertGetId(array $data = [])
    {
        return $this->insert($data, true);
    }

    //批量新增
    public function insertAll(array $data = [], bool $replace = false)
    {
        $options = $this->parseOptions();
        if (!is_array($data)) {
            return false;
        }
        $sql = $this->builder->insertAll($data, $options, $replace);
        $bind = $this->getBind();
        if ($options['sql']) {
            return $this->getRealSql($sql, $bind);
        }
        return $this->execute($sql, $bind);
    }

    //统计
    public function total(string $field, string $type = 'count')
    {
        $alias = 'aphp_' . strtolower($type);
        $type = strtoupper($type);
        if (!in_array($type, ['COUNT', 'SUM', 'MIN', 'MAX', 'AVG'])) {
            $type = 'COUNT';
        }
        $options = [];
        $options['table'] = $this->options['table'] ?? '';
        $options['where'] = $this->options['where'] ?? [];
        $options['sql'] = $this->options['sql'] ?? false;
        $options['expire'] = $this->options['expire'] ?? -1;
        $this->options = $options;
        $res = $this->field($type . '(' . $field . ') AS ' . $alias)->find();
        if (is_string($res)) {
            return $res;
        }
        $total = $res[$alias] ?? 0;
        return ($type == 'COUNT') ? intval($total) : $total;
    }

    //总记录数
    public function count(string $field = '*'): int
    {
        return (int)$this->total($field);
    }

    //总和
    public function sum(string $field)
    {
        return $this->total($field, 'sum');
    }

    //最大值
    public function max(string $field)
    {
        return $this->total($field, 'max');
    }

    //最小值
    public function min(string $field)
    {
        return $this->total($field, 'min');
    }

    //平均值
    public function avg(string $field)
    {
        return $this->total($field, 'avg');
    }

    //获取真实SQL
    public function getRealSql(string $sql, array $bind = []): string
    {
        return empty($bind) ? $sql : $this->db->getRealSql($sql, $bind);
    }

    //设置获取对象
    public function getObj(): object
    {
        $this->options['obj'] = true;
        return $this;
    }

    //设置获取SQL
    public function getSql(): object
    {
        $this->options['sql'] = true;
        return $this;
    }

    //设置不记录日志
    public function noLog(): object
    {
        $this->recordLog = false;
        return $this;
    }

    //设置数据
    public function data($field, $value = null): object
    {
        if (is_array($field)) {
            $this->options['data'] = isset($this->options['data']) ? array_merge($this->options['data'], $field) : $field;
        } else {
            $this->options['data'][$field] = $value;
        }
        return $this;
    }

    //设置自增
    public function inc($field, int $step = 1): object
    {
        $fields = is_string($field) ? explode(',', $field) : $field;
        foreach ($fields as $field) {
            $this->data($field, ['inc', $step]);
        }
        return $this;
    }

    //设置自减
    public function dec($field, int $step = 1): object
    {
        $fields = is_string($field) ? explode(',', $field) : $field;
        foreach ($fields as $field) {
            $this->data($field, ['dec', $step]);
        }
        return $this;
    }

    //设置表名
    public function table($table): object
    {
        if (is_string($table) && !str_contains($table, ')')) {
            if (strpos($table, ',')) {
                $tables = explode(',', $table);
                $table = [];
                foreach ($tables as $item) {
                    [$item, $alias] = explode(' ', trim($item));
                    if ($alias) {
                        $this->alias([$item => $alias]);
                        $table[$item] = $alias;
                    } else {
                        $table[] = $item;
                    }
                }
            } elseif (strpos($table, ' ')) {
                [$table, $alias] = explode(' ', trim($table));
                $table = [$table => $alias];
                $this->alias($table);
            }
        }
        $this->options['table'] = $table;
        return $this;
    }

    //设置别名
    public function alias($alias): object
    {
        if (is_array($alias)) {
            $this->options['alias'] = $alias;
        } else {
            $table = is_array($this->options['table']) ? key($this->options['table']) : $this->options['table'];
            if (!$table) {
                $table = $this->table;
            }
            $this->options['alias'][$table] = $alias;
        }
        return $this;
    }

    //设置字段
    public function field($field = '', bool $isExcept = false): object
    {
        if (empty($field)) {
            return $this;
        }
        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }
        if ($isExcept) {
            $oldFields = $this->options['field'] ?? $this->getFields();
            $field = array_diff(array_values($oldFields), $field);
        } else {
            if (isset($this->options['field'])) {
                $field = array_merge($this->options['field'], $field);
            }
        }
        $this->options['field'] = array_unique($field);
        return $this;
    }

    //设置查询条数
    public function limit($offset, ?int $length = null): object
    {
        if (is_string($offset) && strpos($offset, ',')) {
            [$offset, $length] = explode(',', $offset);
        }
        $this->options['limit'] = intval($offset) . ($length ? ',' . intval($length) : '');
        return $this;
    }

    //设置分页查询
    public function page(int $page, int $length): object
    {
        $page = $page > 0 ? ($page - 1) : 0;
        return $this->limit($page * $length, $length);
    }

    //设置排序
    public function order($field, string $order = ''): object
    {
        if (!empty($field)) {
            if (is_string($field)) {
                if (strpos($field, ',')) {
                    $field = array_map('trim', explode(',', $field));
                } else {
                    $field = empty($order) ? $field : [$field => $order];
                }
            }
            $this->options['order'] ??= [];
            if (is_array($field)) {
                $this->options['order'] = array_merge($this->options['order'], $field);
            } else {
                $this->options['order'][] = $field;
            }
        }
        return $this;
    }

    //设置条件
    public function where($field, $op = null, $condition = null, ?string $logic = null): object
    {
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                if (!is_numeric($k)) {
                    if (!is_array($v)) {
                        $this->options['where'][] = [$k, $v];
                    } else {
                        array_unshift($v, $k);
                        $this->options['where'][] = $v;
                    }
                } else {
                    $this->options['where'][] = $v;
                }
            }
        } elseif (!empty($field)) {
            $this->options['where'][] = func_get_args();
        }
        return $this;
    }

    //设置关联查询
    public function join($join, $condition = null, string $type = 'INNER'): object
    {
        if (empty($condition)) {
            foreach ($join as $value) {
                if (is_array($value) && 2 <= count($value)) {
                    $this->join($value[0], $value[1], $value[2] ?? $type);
                }
            }
        } else {
            $table = $this->parseJoinTable($join);
            $this->options['join'][] = [$table, strtoupper($type), $condition];
        }
        return $this;
    }

    //解析关联表
    protected function parseJoinTable($join, &$alias = null)
    {
        if (is_array($join)) {
            $table = $join;
            $alias = array_shift($join);
        } else {
            $join = trim($join);
            if (str_contains($join, '(')) {
                $table = $join;
            } else {
                if (strpos($join, ' ')) {
                    list($table, $alias) = explode(' ', $join);
                } else {
                    $table = $join;
                    if (!str_contains($join, '.') && !str_starts_with($join, '__')) {
                        $alias = $join;
                    }
                }
            }
            if (isset($alias) && $table != $alias) {
                $table = [$table => $alias];
            }
        }
        return $table;
    }

    //查询union
    public function union($union, bool $all = false): object
    {
        $this->options['union']['type'] = $all ? 'UNION ALL' : 'UNION';
        if (is_array($union)) {
            $this->options['union'] = array_merge($this->options['union'], $union);
        } else {
            $this->options['union'][] = $union;
        }
        return $this;
    }

    //设置group查询
    public function group(string $group): object
    {
        $this->options['group'] = $group;
        return $this;
    }

    //设置having查询
    public function having(string $having): object
    {
        $this->options['having'] = $having;
        return $this;
    }

    //USING(用于多表删除)
    public function using($using): object
    {
        $this->options['using'] = $using;
        return $this;
    }

    //设置查询额外参数
    public function extra(string $extra): object
    {
        $this->options['extra'] = $extra;
        return $this;
    }

    //设置DUPLICATE
    public function duplicate($duplicate): object
    {
        $this->options['duplicate'] = $duplicate;
        return $this;
    }

    //查询lock
    public function lock($lock = false): object
    {
        $this->options['lock'] = $lock;
        return $this;
    }

    //distinct查询
    public function distinct($distinct = false): object
    {
        $this->options['distinct'] = $distinct;
        return $this;
    }

    //指定强制索引
    public function force(string $force): object
    {
        $this->options['force'] = $force;
        return $this;
    }

    //查询注释
    public function comment(string $comment): object
    {
        $this->options['comment'] = $comment;
        return $this;
    }

    //对象转换数据
    public function toArray(): array
    {
        return $this->data;
    }

    public function __sleep()
    {
        return ['table'];
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset): void
    {
        if (isset($this->data[$offset])) unset($this->data[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->data);
    }

    public function next(): void
    {
        next($this->data);
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->data);
    }

    public function rewind(): void
    {
        reset($this->data);
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return current($this->data);
    }
}