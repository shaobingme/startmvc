<?php

namespace startmvc\core\db;

interface DbInterface
{
    /**
     * 获取多条记录
     * 
     * @param null $type
     * @param null $argument
     *
     * @return mixed
     */
    public function get($type = null, $argument = null);

    /**
     * 获取单条记录
     * 
     * @param null $type
     * @param null $argument
     *
     * @return mixed
     */
    public function first($type = null, $argument = null);

    /**
     * 通过主键查找单条记录
     * 
     * @param mixed $id 主键值
     * @param string $primaryKey 主键字段名，默认为'id'
     * @param bool $throwIfNotFound 是否在找不到时抛出异常，默认false
     * @throws \Exception 当记录不存在且$throwIfNotFound为true时
     * @return mixed|null 返回找到的记录，未找到返回null（除非设置了抛异常）
     */
    public function find($id, $primaryKey = 'id', $throwIfNotFound = false);

    /**
     * 通过主键查找多条记录
     * 
     * @param array $ids 主键值数组
     * @param string $primaryKey 主键字段名，默认为'id'
     * @return array 返回找到的记录数组
     */
    public function findMany(array $ids, $primaryKey = 'id');

    /**
     * @param array $data
     * @param bool  $type
     *
     * @return mixed
     */
    public function update(array $data, $type = false);

    /**
     * @param array $data
     * @param bool  $type
     *
     * @return mixed
     */
    public function insert(array $data, $type = false);

    /**
     * @param bool $type
     *
     * @return mixed
     */
    public function delete($type = false);

    /**
     * 布尔值取反（0变1，1变0）
     * 
     * @param string $column 列名
     * @return int|bool 影响的行数或失败时返回false
     */
    public function invert($column);

    /**
     * 切换布尔字段值（toggle方法是invert的别名）
     * 
     * @param string $column 列名
     * @return int|bool 影响的行数或失败时返回false
     */
    public function toggle($column);

    /**
     * 更新记录的时间戳字段
     * 
     * @param string|array $columns 要更新的时间戳字段，默认为'updated_at'
     * @return int|bool 影响的行数或失败时返回false
     */
    public function touch($columns = 'updated_at');

    /**
     * 列值递增更新
     * 
     * @param string $column 列名
     * @param int $count 递增的数值，默认为1
     * @return int|bool 影响的行数或失败时返回false
     */
    public function inc($column, int $count = 1);

    /**
     * 列值递减更新
     * 
     * @param string $column 列名
     * @param int $count 递减的数值，默认为1
     * @return int|bool 影响的行数或失败时返回false
     */
    public function dec($column, int $count = 1);
}
