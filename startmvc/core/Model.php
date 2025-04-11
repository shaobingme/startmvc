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
use startmvc\core\db\DbCore;

abstract class Model
{
	protected $table;
	protected $db;
	protected $dbConf;

	public function __construct ()
	{
		$this->dbConf = include CONFIG_PATH . '/database.php';
		if ($this->dbConf['driver'] != '') {
			$this->db= new Db($this->dbConf['connections'][$this->dbConf['driver']]);
		}

	}


	//查询单条数据
	public function find($field="*",$where='')
	{
		$res=self::findAll($field,$where,'',1);
		if($res){
			return $res[0];
		}
	}
	
	//查询多条数据
	public function findAll($field="*",$where=[],$order='',$limit='')
	{
		//$prefix=$this->dbConf['connections'][$this->dbConf['default']]['prefix'];
		$this->db->select($field);
		$this->db->table($this->table);
		if (!empty($where)) {
			$this->db->where($where);
		}
		if($order){
			$this->db->order($order);
		}
		if ($limit){
			if(is_numeric($limit)){
				$this->db->limit($limit);
			}else{
				$limit_arr=explode(',',$limit);
				$this->db->limit($limit_arr[0],$limit_arr[1]);
			}
		}
		return $this->db->get();
	}
	//更新数据
	public function update($data,$where=[])
	{
		$this->db->table($this->table);
		if ($where){
			$this->db->where($where);
		}
		return $this->db->update($data);
	}
	//删除数据
	public function delete($where='')
	{
		$this->db->table($this->table);
		if ($where){
			$where=!is_numeric($where)?:['id'=>$where];
			$where=$this->db->where($where);
		}
		return $this->db->delete();
	}


}
