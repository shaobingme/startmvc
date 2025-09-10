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
 * Model基类 - 提供模型的基础功能，支持自动继承Db类方法
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
	 * 数据库配置
	 * @var array
	 */
	protected $dbConf;
	
	/**
	 * 模型数据
	 * @var array
	 */
	protected $data = [];

	/**
	 * 构造函数
	 */
	public function __construct()
	{
		// 只加载配置，不创建连接实例
		$this->dbConf = include CONFIG_PATH . '/database.php';
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
	 * 插入数据
	 * 
	 * @param array $data 数据
	 * @return int|bool 插入ID或结果
	 */
	public function insert($data = [])
	{
		if (!empty($data)) {
			$this->data = $data;
		}
		
		return Db::table($this->table)->insert($this->data);
	}
	
	/**
	 * 更新数据
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
		
		$query = Db::table($this->table);
		
		if (!empty($where)) {
			if (is_numeric($where)) {
				// 如果是纯数字，认为是按主键查询
				$query->where($this->pk, $where);
			} elseif (is_array($where)) {
				// 如果是数组，则按条件数组处理
				$query->where($where);
			} elseif (is_string($where)) {
				// 如果是字符串，判断是否为条件表达式
				if (preg_match('/[=<>!]/', $where)) {
					// 包含运算符，视为条件表达式
					$query->where($where);
				} else {
					// 不包含运算符，视为主键值
					$query->where($this->pk, $where);
				}
			}
		}
		
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
		
		if (isset($this->data[$this->pk]) && !empty($this->data[$this->pk])) {
			// 有主键，执行更新
			$id = $this->data[$this->pk];
			$updateData = $this->data;
			return $this->update($updateData, $id);
		} else {
			// 无主键，执行插入
			return $this->insert();
		}
	}
	
	/**
	 * 删除数据
	 * 
	 * @param mixed $where 条件(数组、字符串或整数id)
	 * @return int|bool 影响行数或结果
	 */
	public function delete($where = null)
	{
		$query = Db::table($this->table);
		
		if ($where !== null) {
			if (is_numeric($where)) {
				// 数字条件转为主键条件
				$query->where($this->pk, $where);
			} else {
				$query->where($where);
			}
		}
		
		return $query->delete();
	}
	
	/**
	 * 魔术方法：调用不存在的方法时自动调用db对象的方法
	 * 
	 * @param string $method 方法名
	 * @param array $args 参数
	 * @return mixed 返回结果
	 */
	public function __call($method, $args)
	{
		$query = Db::table($this->table);
		
		if (method_exists($query, $method)) {
			return call_user_func_array([$query, $method], $args);
		}
		
		throw new \Exception("方法 {$method} 不存在");
	}
	
	/**
	 * 查找单条记录
	 * 
	 * @param mixed $where 查询条件(主键值、条件数组或字符串条件表达式)
	 * @param string|array $fields 查询字段，默认为*
	 * @return array|null 返回符合条件的单条记录
	 */
	public function find($where, $fields = '*')
	{
		$query = Db::table($this->table);
		
		// 设置查询字段
		$query->select($fields);
		
		// 处理查询条件
		if (is_numeric($where)) {
			// 如果是纯数字，认为是按主键查询
			$query->where($this->pk, $where);
		} elseif (is_array($where)) {
			// 如果是数组，则按条件数组处理
			$query->where($where);
		} elseif (is_string($where)) {
			// 如果是字符串，判断是否为条件表达式
			if (preg_match('/[=<>!]/', $where)) {
				// 包含运算符，视为条件表达式
				$query->where($where);
			} else {
				// 不包含运算符，视为主键值
				$query->where($this->pk, $where);
			}
		}
		
		// 执行查询并返回单条记录
		return $query->first();
	}
	
	
	/**
	 * 查找多条记录
	 * 
	 * @param mixed $where 查询条件(条件数组或字符串条件表达式)
	 * @param string|array $fields 查询字段，默认为*
	 * @param string|array $order 排序方式
	 * @param int|string $limit 查询限制
	 * @return array 返回符合条件的记录集
	 */
	public function findAll($where = [], $fields = '*', $order = '', $limit = '')
	{
		$query = Db::table($this->table);
		
		// 设置查询字段
		$query->select($fields);
		
		// 处理查询条件
		if (!empty($where)) {
			if (is_array($where)) {
				$query->where($where);
			} elseif (is_string($where)) {
				// 字符串条件
				$query->where($where);
			}
		}
		
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
		
		// 执行查询
		return $query->get();
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
	 * 分页查询方法
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
		$countQuery = Db::table($this->table);
		if (!empty($where)) {
			if (is_array($where)) {
				$countQuery->where($where);
			} elseif (is_string($where)) {
				$countQuery->where($where);
			}
		}
		$total = $countQuery->count();
		
		// 计算总页数
		$totalPages = ceil($total / $pageSize);
		
		// 确保当前页码有效
		$currentPage = max(1, min($totalPages, $currentPage));
		
		// 查询当前页数据
		$query = Db::table($this->table);
		
		// 处理查询条件
		if (!empty($where)) {
			if (is_array($where)) {
				$query->where($where);
			} elseif (is_string($where)) {
				$query->where($where);
			}
		}
		
		// 设置排序
		if (!empty($order)) {
			$query->order($order);
		}
		
		// 设置分页
		$query->page($pageSize, $currentPage);
		
		// 执行查询
		$data = $query->get();
		
		// 返回分页数据
		return [
			'data' => $data,
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
