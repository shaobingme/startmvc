<?php

namespace startmvc\core\db;

/**
 * SQL 快照构建器（getSql() 的返回值）
 *
 * 与 DbCore 共享同一份构建状态（表名 / where / join / 排序 / 绑定参数 …），
 * 但所有执行器都只产出 SQL 字符串，绝不执行。
 *
 * 用法：
 *   Db::table('users')->where('id', 1)->getSql()->first();
 *   Db::table('users')->getSql()->insert(['name' => 'x']);
 *   Db::table('users')->where('id', 1)->getSql()->update(['name' => 'y']);
 *   Db::table('users')->getSql()->truncate();
 *
 * 职责划分：DbCore 负责「构建 + 执行」，本类只负责「构建」。SQL 拼接全部走
 * 父类的 buildXxxQuery()，两边共用同一份逻辑，不会出现「看到的 SQL 和真正
 * 执行的 SQL 不一样」。
 *
 * 为什么只覆盖 22 个而不覆盖全部：
 *   many/one/find/findOrFail/findMany/insertGetId/touch/toggle 这 8 个是包装
 *   方法，内部调的是 $this->get()/first()/insert()/update()/invert() ——
 *   PHP 动态绑定会自动落到本类的覆盖版本，覆盖它们反而是把包装逻辑复制一份。
 *
 * @package startmvc
 */
class SqlSnapshot extends DbCore
{
    /**
     * 由 DbCore::getSql() 调用：复制构建状态
     *
     * 不用 clone 是因为 clone 出来的对象类型仍然是 DbCore；copyStateTo() 走
     * get_object_vars()，父类将来新增构建属性会自动带上，不会漏。
     *
     * 构造传空配置，紧接着由 copyStateTo() 覆盖成源实例的配置：
     * 避免为了读 protected 的 $config 而多做一次跨实例访问。
     *
     * @param DbCore $source 源构建器（共享实例）
     * @return static
     */
    public static function createFrom(DbCore $source)
    {
        $snapshot = new static([]);
        $source->copyStateTo($snapshot);

        return $snapshot;
    }

    /**
     * 兜底提示：直接 echo 快照时给出可执行的下一步，而不是抛 Error
     *
     * 刻意不抛异常 —— PHP 7.2/7.3 不允许 __toString() 抛异常（会变成 fatal，
     * 而本框架声明支持 7.2+）。返回串以 SQL 注释开头，万一被当成 SQL 执行
     * 也只是一条空语句，不会误伤数据。
     *
     * @return string
     */
    public function __toString()
    {
        return '-- getSql() 返回的是 SQL 快照，请链一个执行器：'
            . '->get() / ->first() / ->insert($data) / ->update($data) / ->delete()';
    }

    // ------------------------------------------------------------------
    // 查询
    // ------------------------------------------------------------------

    /**
     * 获取多条记录 → SELECT SQL
     *
     * @param string|null $type 父类用于 FETCH_CLASS 的类名；快照忽略
     * @param mixed $argument 父类用于类构造参数；快照忽略
     * @return string SQL 语句
     */
    public function get($type = null, $argument = null)
    {
        return $this->snapshotSql($this->buildSelectQuery());
    }

    /**
     * 获取单条记录 → SELECT ... LIMIT 1
     *
     * @param string|null $type 父类用于 FETCH_CLASS 的类名；快照忽略
     * @param mixed $argument 父类用于类构造参数；快照忽略
     * @return string SQL 语句
     */
    public function first($type = null, $argument = null)
    {
        $this->limit(1);

        return $this->snapshotSql($this->buildSelectQuery());
    }

    /**
     * 获取单个字段的值 → SELECT 字段 ... LIMIT 1
     *
     * 父类此处会用 is_array() 判断结果，first() 返回字符串时落到 return null。
     *
     * @param string $column 字段名
     * @return string SQL 语句
     */
    public function value($column)
    {
        $this->select($this->validateIdentifier($column, true));

        // first() 在本类返回 SQL 字符串，原样透传
        return $this->first();
    }

    /**
     * 获取指定列 → SELECT 列 [,...]
     *
     * 父类单参分支自己 prepare/execute，会真的查一次库。
     *
     * @param string      $column 字段名
     * @param string|null $key    作为返回数组索引的字段名
     * @return string SQL 语句
     */
    public function column($column, $key = null)
    {
        $this->validateIdentifier($column, true);

        if (!is_null($key)) {
            $this->select(implode(', ', [$this->validateIdentifier($key, true), $column]));
        } else {
            $this->select($column);
        }

        return $this->snapshotSql($this->buildSelectQuery());
    }

    // ------------------------------------------------------------------
    // 聚合
    // ------------------------------------------------------------------

    /**
     * 统计行数 → SELECT COUNT(...)
     *
     * @param string $field 字段名
     * @return string SQL 语句
     */
    public function count($field = '*')
    {
        return $this->aggregateSql('COUNT', $field);
    }

    /**
     * 最大值 → SELECT MAX(...)
     *
     * @param string $field 字段名
     * @return string SQL 语句
     */
    public function max($field)
    {
        return $this->aggregateSql('MAX', $field);
    }

    /**
     * 最小值 → SELECT MIN(...)
     *
     * @param string $field 字段名
     * @return string SQL 语句
     */
    public function min($field)
    {
        return $this->aggregateSql('MIN', $field);
    }

    /**
     * 求和 → SELECT SUM(...)
     *
     * @param string $field 字段名
     * @return string SQL 语句
     */
    public function sum($field)
    {
        return $this->aggregateSql('SUM', $field);
    }

    /**
     * 平均值 → SELECT AVG(...)
     *
     * @param string $field 字段名
     * @return string SQL 语句
     */
    public function avg($field)
    {
        return $this->aggregateSql('AVG', $field);
    }

    // ------------------------------------------------------------------
    // 写入
    // ------------------------------------------------------------------

    /**
     * 插入 → INSERT / INSERT IGNORE / REPLACE / ON DUPLICATE KEY UPDATE
     *
     * @param array  $data 单条（一维）或批量（二维）数据
     * @param string $type 插入类型：INSERT | IGNORE | REPLACE | DUPLICATE
     * @return string SQL 语句
     * @throws \InvalidArgumentException 参数非法（复用父类的校验）
     */
    public function insert(array $data, $type = 'INSERT')
    {
        $type = $this->prepareInsert($type, func_num_args());

        return $this->snapshotSql($this->buildInsertQuery($data, $type));
    }

    /**
     * 更新 → UPDATE ... SET ... [WHERE ...]
     *
     * @param array $data 要更新的数据
     * @return string SQL 语句
     * @throws \InvalidArgumentException 传了第二个参数（旧开关）
     */
    public function update(array $data)
    {
        if (func_num_args() > 1) {
            throw new \InvalidArgumentException(
                'update() 只接受 1 个参数（$data）。快照本身就是「只看 SQL」，不需要第二个开关'
            );
        }

        return $this->snapshotSql($this->buildUpdateQuery($data));
    }

    /**
     * 删除 → DELETE ... [WHERE ...]
     *
     * @return string SQL 语句
     * @throws \InvalidArgumentException 传了参数（旧开关）
     */
    public function delete()
    {
        if (func_num_args() > 0) {
            throw new \InvalidArgumentException(
                'delete() 不接受参数。快照本身就是「只看 SQL」，不需要开关'
            );
        }

        return $this->snapshotSql($this->buildDeleteQuery());
    }

    /**
     * 布尔值取反 → UPDATE ... SET col = !col
     *
     * @param string $column 列名
     * @return string SQL 语句
     */
    public function invert($column)
    {
        return $this->snapshotSql($this->buildCounterQuery($column, '!'));
    }

    /**
     * 列值递增 → UPDATE ... SET col = col + n
     *
     * @param string $column 列名
     * @param int    $count  递增的数值
     * @return string SQL 语句
     */
    public function inc($column, int $count = 1)
    {
        return $this->snapshotSql($this->buildCounterQuery($column, '+', $count));
    }

    /**
     * 列值递减 → UPDATE ... SET col = col - n
     *
     * @param string $column 列名
     * @param int    $count  递减的数值
     * @return string SQL 语句
     */
    public function dec($column, int $count = 1)
    {
        return $this->snapshotSql($this->buildCounterQuery($column, '-', $count));
    }

    // ------------------------------------------------------------------
    // 表维护（DDL）
    // ------------------------------------------------------------------

    /**
     * 清空表 → TRUNCATE TABLE
     *
     * @return string SQL 语句
     */
    public function truncate()
    {
        return $this->snapshotSql($this->buildTableMaintenanceQuery('TRUNCATE TABLE'));
    }

    /**
     * 删除表 → DROP TABLE
     *
     * @return string SQL 语句
     */
    public function drop()
    {
        return $this->snapshotSql($this->buildTableMaintenanceQuery('DROP TABLE'));
    }

    /**
     * 修复表 → REPAIR TABLE
     *
     * @return string SQL 语句
     */
    public function repair()
    {
        return $this->snapshotSql($this->buildTableMaintenanceQuery('REPAIR TABLE'));
    }

    /**
     * 优化表 → OPTIMIZE TABLE
     *
     * @return string SQL 语句
     */
    public function optimize()
    {
        return $this->snapshotSql($this->buildTableMaintenanceQuery('OPTIMIZE TABLE'));
    }

    /**
     * 分析表 → ANALYZE TABLE
     *
     * @return string SQL 语句
     */
    public function analyze()
    {
        return $this->snapshotSql($this->buildTableMaintenanceQuery('ANALYZE TABLE'));
    }

    /**
     * 检查表完整性 → CHECK TABLE
     *
     * @return string SQL 语句
     */
    public function check()
    {
        return $this->snapshotSql($this->buildTableMaintenanceQuery('CHECK TABLE'));
    }

    /**
     * 计算表校验和 → CHECKSUM TABLE
     *
     * @return string SQL 语句
     */
    public function checksum()
    {
        return $this->snapshotSql($this->buildTableMaintenanceQuery('CHECKSUM TABLE'));
    }

    // ------------------------------------------------------------------
    // 内部
    // ------------------------------------------------------------------

    /**
     * 把带占位符的 SQL 插值成可读语句，并交还构建状态
     *
     * @param string $query 带占位符的 SQL
     * @return string 插值后的可读 SQL
     */
    protected function snapshotSql($query)
    {
        $sql = $this->interpolateQuery($query);
        $this->reset();

        return $sql;
    }

    /**
     * 聚合查询的统一出口
     *
     * @param string $function 聚合函数名
     * @param string $field    字段名
     * @return string SQL 语句
     */
    protected function aggregateSql($function, $field)
    {
        return $this->snapshotSql($this->buildAggregateQuery($function, $field));
    }
}
