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

/**
 * Model基类 - 轻量数据模型，按需声明属性即可开启ORM特性
 *
 * 特性全部默认关闭，未声明的模型行为与旧版完全一致：
 *
 *   protected $timestamps = true;   // 自动维护 created_at / updated_at
 *   protected $softDelete  = true;  // 软删除：delete()改写 deleted_at，查询自动排除
 *   protected $casts = [           // 读取时类型转换（数组写入由Db自动JSON编码）
 *       'price' => 'float', 'views' => 'int', 'is_admin' => 'bool', 'ext' => 'json',
 *   ];
 *   protected $hasOne = [          // 一对一：[关联名 => [关联模型, 关联表外键, 本表主键]]
 *       'profile' => [ProfileModel::class, 'user_id', 'id'],
 *   ];
 *   protected $belongsTo = [       // 反向关联：[关联名 => [关联模型, 本表外键, 关联表主键]]
 *       'dept' => [DeptModel::class, 'dept_id', 'id'],
 *   ];
 *
 * 说明：
 * - 类型转换与关联装载自动应用于 find / findAll / paginate（关联批量IN查询，无N+1）
 * - 链式查询（where()->get() 等）经查询构建器代理，软删除范围同样生效，返回原始数组
 * - 软删除查询范围：withTrashed() 含已删 / onlyTrashed() 仅已删，均在链式调用前使用
 */
abstract class Model
{
	/**
	 * 表名
	 * @var string
	 */
	protected $table;

	/**
	 * 主键
	 * @var string
	 */
	protected $pk = 'id';

	/**
	 * 自动时间戳开关
	 * @var bool
	 */
	protected $timestamps = false;

	/**
	 * 创建时间字段名（设为null可单独禁用）
	 * @var string|null
	 */
	protected $createTime = 'created_at';

	/**
	 * 更新时间字段名（设为null可单独禁用）
	 * @var string|null
	 */
	protected $updateTime = 'updated_at';

	/**
	 * 软删除开关
	 * @var bool
	 */
	protected $softDelete = false;

	/**
	 * 软删除标记字段名
	 * @var string
	 */
	protected $deleteTime = 'deleted_at';

	/**
	 * 字段类型转换：['字段' => 'int|float|bool|string|json']
	 * @var array
	 */
	protected $casts = [];

	/**
	 * 一对一关联：[关联名 => [关联模型类, 关联表外键, 本表主键]]
	 * @var array
	 */
	protected $hasOne = [];

	/**
	 * 反向关联：[关联名 => [关联模型类, 本表外键, 关联表主键]]
	 * @var array
	 */
	protected $belongsTo = [];

	/**
	 * 模型数据
	 * @var array
	 */
	protected $data = [];

	/**
	 * 软删除查询范围：0排除已删 1含已删 2仅已删
	 * @var int
	 */
	protected $trashedMode = 0;

	/**
	 * 构造函数（保留空实现以兼容子类调用 parent::__construct()）
	 */
	public function __construct()
	{
	}

	/**
	 * 设置表名
	 *
	 * @param string $table 表名
	 * @return $this
	 */
	public function table($table)
	{
		$this->table = $table;
		return $this;
	}

	/**
	 * 设置模型数据
	 *
	 * @param array $data 数据
	 * @return $this
	 */
	public function data($data)
	{
		$this->data = array_merge($this->data, $data);
		return $this;
	}

	/**
	 * 创建查询构建器（软删除启用时自动附加范围条件）
	 *
	 * @return \startmvc\core\db\DbCore
	 */
	protected function newQuery()
	{
		return $this->applySoftDeleteScope(Db::table($this->table));
	}

	/**
	 * 在查询构建器上附加软删除范围条件
	 *
	 * @param mixed $query 查询构建器
	 * @return mixed
	 */
	protected function applySoftDeleteScope($query)
	{
		if ($this->softDelete) {
			if ($this->trashedMode === 2) {
				$query->whereNotNull($this->deleteTime);
			} elseif ($this->trashedMode === 0) {
				$query->whereNull($this->deleteTime);
			}
		}

		return $query;
	}

	/**
	 * 应用查询条件（数字或不含运算符的字符串视为主键值）
	 *
	 * @param mixed $query 查询构建器
	 * @param mixed $where 条件(数组、字符串或主键值)
	 * @return mixed
	 */
	protected function applyWhere($query, $where)
	{
		if ($where === null || $where === '' || $where === []) {
			return $query;
		}

		if (is_numeric($where) || (is_string($where) && !preg_match('/[=<>!]/', $where))) {
			return $query->where($this->pk, $where);
		}

		return $query->where($where);
	}

	/**
	 * 补充时间戳字段
	 *
	 * @param array $data 数据（引用修改）
	 * @param bool $isInsert 是否插入
	 * @return void
	 */
	protected function applyTimestamps(array &$data, $isInsert)
	{
		if (!$this->timestamps) {
			return;
		}

		$now = date('Y-m-d H:i:s');
		if ($isInsert && $this->createTime !== null && !isset($data[$this->createTime])) {
			$data[$this->createTime] = $now;
		}
		if ($this->updateTime !== null && !isset($data[$this->updateTime])) {
			$data[$this->updateTime] = $now;
		}
	}

	/**
	 * 对单行数据应用类型转换
	 *
	 * @param array $row 数据行
	 * @return array
	 */
	protected function castRow(array $row)
	{
		foreach ($this->casts as $field => $type) {
			if (array_key_exists($field, $row)) {
				$row[$field] = $this->castValue($row[$field], $type);
			}
		}
		return $row;
	}

	/**
	 * 转换单个字段值
	 *
	 * @param mixed $value 字段值
	 * @param string $type 目标类型
	 * @return mixed
	 */
	protected function castValue($value, $type)
	{
		if ($value === null) {
			return null;
		}

		switch ($type) {
			case 'int':
			case 'integer':
				return (int)$value;
			case 'float':
			case 'double':
				return (float)$value;
			case 'bool':
			case 'boolean':
				return (bool)$value;
			case 'string':
				return (string)$value;
			case 'json':
			case 'array':
				return is_array($value) ? $value : json_decode((string)$value, true);
		}

		return $value;
	}

	/**
	 * 处理单行查询结果：类型转换与关联装载
	 *
	 * @param mixed $row 查询结果行
	 * @return mixed
	 */
	protected function processRow($row)
	{
		if (!is_array($row) || empty($row)) {
			return $row;
		}

		$row = $this->castRow($row);
		if (!empty($this->hasOne) || !empty($this->belongsTo)) {
			$rows = [$row];
			$this->attachRelations($rows);
			$row = $rows[0];
		}
		return $row;
	}

	/**
	 * 处理多行查询结果：类型转换与关联装载
	 *
	 * @param mixed $rows 查询结果集
	 * @return mixed
	 */
	protected function processRows($rows)
	{
		if (!is_array($rows) || empty($rows)) {
			return $rows;
		}

		foreach ($rows as &$row) {
			if (is_array($row)) {
				$row = $this->castRow($row);
			}
		}
		unset($row);

		$this->attachRelations($rows);
		return $rows;
	}

	/**
	 * 批量装载关联数据（每条关联一次IN查询，避免N+1）
	 *
	 * @param array $rows 数据行集（引用修改）
	 * @return void
	 */
	protected function attachRelations(array &$rows)
	{
		if (empty($rows)) {
			return;
		}

		// hasOne：关联表外键 -> 本表主键
		foreach ($this->hasOne as $name => $relation) {
			list($class, $foreignKey, $localKey) = $relation;
			$map = $this->loadRelationMap($class, $foreignKey, $this->relationKeys($rows, $localKey));
			foreach ($rows as &$row) {
				$row[$name] = isset($map[$row[$localKey]]) ? $map[$row[$localKey]] : null;
			}
			unset($row);
		}

		// belongsTo：本表外键 -> 关联表主键
		foreach ($this->belongsTo as $name => $relation) {
			list($class, $foreignKey, $ownerKey) = $relation;
			$map = $this->loadRelationMap($class, $ownerKey, $this->relationKeys($rows, $foreignKey));
			foreach ($rows as &$row) {
				$row[$name] = isset($map[$row[$foreignKey]]) ? $map[$row[$foreignKey]] : null;
			}
			unset($row);
		}
	}

	/**
	 * 按外键值批量查询关联记录，返回 [外键值 => 关联行] 映射
	 *
	 * @param string $class 关联模型类
	 * @param string $foreignKey 关联表外键字段
	 * @param array $keys 外键值集合
	 * @return array
	 */
	protected function loadRelationMap($class, $foreignKey, array $keys)
	{
		if (empty($keys)) {
			return [];
		}
		if (!class_exists($class)) {
			throw new \Exception("关联模型 {$class} 不存在");
		}

		$related = new $class();
		$map = [];
		foreach ($related->newQuery()->in($foreignKey, $keys)->get() as $row) {
			if (!isset($map[$row[$foreignKey]])) {
				$map[$row[$foreignKey]] = $related->castRow($row);
			}
		}
		return $map;
	}

	/**
	 * 提取数据行集中指定字段的非空唯一值
	 *
	 * @param array $rows 数据行集
	 * @param string $field 字段名
	 * @return array
	 */
	protected function relationKeys(array $rows, $field)
	{
		$keys = [];
		foreach ($rows as $row) {
			if (isset($row[$field]) && $row[$field] !== '' && $row[$field] !== null) {
				$keys[] = $row[$field];
			}
		}
		return array_values(array_unique($keys));
	}

	/**
	 * 插入数据（自动补充创建/更新时间戳）
	 *
	 * @param array $data 数据
	 * @return int|bool 插入ID或结果
	 */
	public function insert($data = [])
	{
		if (!empty($data)) {
			$this->data = $data;
		}

		if ($this->timestamps) {
			$values = array_values($this->data);
			if (isset($values[0]) && is_array($values[0])) {
				// 批量插入：逐行补充时间戳
				foreach ($this->data as &$row) {
					$this->applyTimestamps($row, true);
				}
				unset($row);
			} else {
				$this->applyTimestamps($this->data, true);
			}
		}

		return Db::table($this->table)->insert($this->data);
	}

	/**
	 * 更新数据（自动补充更新时间戳，软删除启用时已删记录不可更新）
	 *
	 * @param array $data 要更新的数据
	 * @param mixed $where 条件(数组、字符串或整数id)
	 * @return int|bool 影响行数或结果
	 */
	public function update($data, $where = [])
	{
		if (!empty($data)) {
			$this->data = $data;
		}
		$this->applyTimestamps($this->data, false);

		$query = $this->newQuery();
		$this->applyWhere($query, $where);

		return $query->update($this->data);
	}

	/**
	 * 保存数据（自动判断插入或更新）
	 *
	 * @param array $data 数据
	 * @return int|bool 结果
	 */
	public function save($data = [])
	{
		if (!empty($data)) {
			$this->data = $data;
		}

		if (!empty($this->data[$this->pk])) {
			// 有主键，执行更新（主键不参与SET）
			$id = $this->data[$this->pk];
			$updateData = $this->data;
			unset($updateData[$this->pk]);
			return $this->update($updateData, $id);
		}

		// 无主键，执行插入并回写自增ID
		$result = $this->insert();
		if ($result) {
			$this->data[$this->pk] = $result;
		}
		return $result;
	}

	/**
	 * 删除数据（软删除启用时改写为更新删除标记）
	 *
	 * @param mixed $where 条件(数组、字符串或整数id)
	 * @return int|bool 影响行数或结果
	 */
	public function delete($where = null)
	{
		if ($this->softDelete) {
			return $this->update([$this->deleteTime => date('Y-m-d H:i:s')], $where);
		}
		return $this->forceDelete($where);
	}

	/**
	 * 真实删除（绕过软删除）
	 *
	 * @param mixed $where 条件(数组、字符串或整数id)
	 * @return int|bool 影响行数或结果
	 */
	public function forceDelete($where = null)
	{
		$query = Db::table($this->table);
		$this->applyWhere($query, $where);
		return $query->delete();
	}

	/**
	 * 恢复软删除记录
	 *
	 * @param mixed $where 条件(数组、字符串或整数id)
	 * @return int|bool 影响行数或结果
	 */
	public function restore($where)
	{
		if (!$this->softDelete) {
			throw new \Exception('模型未启用软删除');
		}

		$data = [$this->deleteTime => null];
		if ($this->timestamps && $this->updateTime !== null) {
			$data[$this->updateTime] = date('Y-m-d H:i:s');
		}

		// 绕过软删除范围，否则已删记录不可达
		$query = Db::table($this->table);
		$this->applyWhere($query, $where);
		return $query->update($data);
	}

	/**
	 * 查询范围：含已删除记录
	 *
	 * @return static
	 */
	public function withTrashed()
	{
		$clone = clone $this;
		$clone->trashedMode = 1;
		return $clone;
	}

	/**
	 * 查询范围：仅已删除记录
	 *
	 * @return static
	 */
	public function onlyTrashed()
	{
		$clone = clone $this;
		$clone->trashedMode = 2;
		return $clone;
	}

	/**
	 * 魔术方法：调用不存在的方法时自动代理到查询构建器（软删除范围生效）
	 *
	 * 注意：Db::table() 返回同配置共享的构建器实例，事务/原生执行/表维护类
	 * 方法不构成SELECT查询、执行后不会触发重置，直连透传以避免在共享构建器上
	 * 累积WHERE残留；其余查询方法附加软删除范围后透传。
	 *
	 * @param string $method 方法名
	 * @param array $args 参数
	 * @return mixed 返回结果
	 */
	public function __call($method, $args)
	{
		$query = Db::table($this->table);

		if (!method_exists($query, $method)) {
			throw new \Exception("方法 {$method} 不存在");
		}

		// 事务/原生执行/表维护/构建器配置类方法：不构成SELECT查询，直连透传
		$directMethods = [
			'transaction', 'commit', 'rollback', 'exec', 'fetch', 'fetchall', 'query',
			'getpdo', 'is_table', 'optimize', 'analyze', 'check', 'repair', 'checksum',
			'truncate', 'drop', 'cache', 'getsql', 'allowfulltable', 'escape',
		];
		if (!in_array(strtolower($method), $directMethods, true)) {
			$this->applySoftDeleteScope($query);
		}

		return call_user_func_array([$query, $method], $args);
	}

	/**
	 * 查找单条记录（应用类型转换与关联装载）
	 *
	 * @param mixed $where 查询条件(主键值、条件数组或字符串条件表达式)
	 * @param string|array $fields 查询字段，默认为*
	 * @return array|null 返回符合条件的单条记录
	 */
	public function find($where, $fields = '*')
	{
		$query = $this->newQuery();
		$query->select($fields);
		$this->applyWhere($query, $where);

		return $this->processRow($query->first());
	}

	/**
	 * 查找多条记录（应用类型转换与关联装载）
	 *
	 * @param mixed $where 查询条件(条件数组或字符串条件表达式)
	 * @param string|array $fields 查询字段，默认为*
	 * @param string|array $order 排序方式
	 * @param int|string $limit 查询限制
	 * @return array 返回符合条件的记录集
	 */
	public function findAll($where = [], $fields = '*', $order = '', $limit = '')
	{
		$query = $this->newQuery();
		$query->select($fields);
		$this->applyWhere($query, $where);

		// 设置排序
		if (!empty($order)) {
			if (is_array($order)) {
				foreach ($order as $field => $sort) {
					if (is_numeric($field)) {
						$query->order($sort);
					} else {
						$query->order($field, $sort);
					}
				}
			} else {
				$query->order($order);
			}
		}

		// 设置查询限制
		if (!empty($limit)) {
			if (is_numeric($limit)) {
				$query->limit($limit);
			} elseif (is_string($limit) && strpos($limit, ',') !== false) {
				list($offset, $rows) = explode(',', $limit);
				$query->limit($rows, $offset);
			}
		}

		return $this->processRows($query->get());
	}

	/**
	 * 静态方法：实例化模型
	 *
	 * @param string $table 表名
	 * @return static 模型实例
	 */
	public static function model($table = null)
	{
		$model = new static();

		if ($table !== null) {
			$model->table($table);
		}

		return $model;
	}

	/**
	 * 分页查询方法（应用软删除范围、类型转换与关联装载）
	 *
	 * @param int $pageSize 每页记录数
	 * @param int $currentPage 当前页码
	 * @param mixed $where 查询条件
	 * @param string $order 排序方式
	 * @return array 包含数据和分页信息的数组
	 */
	public function paginate($pageSize = 10, $currentPage = 1, $where = [], $order = '')
	{
		// 查询总记录数
		$countQuery = $this->newQuery();
		$this->applyWhere($countQuery, $where);
		$total = $countQuery->count();

		// 计算总页数
		$totalPages = $pageSize > 0 ? (int)ceil($total / $pageSize) : 0;

		// 确保当前页码有效
		$currentPage = max(1, min($totalPages, $currentPage));

		// 查询当前页数据
		$query = $this->newQuery();
		$this->applyWhere($query, $where);

		if (!empty($order)) {
			$query->order($order);
		}
		$query->page($pageSize, $currentPage);

		return [
			'data' => $this->processRows($query->get()),
			'pagination' => [
				'total' => $total,
				'per_page' => $pageSize,
				'current_page' => $currentPage,
				'total_pages' => $totalPages,
				'has_more' => $currentPage < $totalPages
			]
		];
	}
}
