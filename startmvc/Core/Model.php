<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2021
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */

namespace Startmvc\Core;

abstract class Model extends Start
{
	protected $table;

	//查询单条数据
	public function find($field="*",$where='',$getsql=false)
	{
		$where=!is_numeric($where)?:['id'=>$where];
		$res=self::findAll($field,$where,'',1,$getsql=false);
		if($res){
			return $res[0];
		}
	}
	
	//查询多条数据
	public function findAll($field="*",$where=[],$order='',$limit='',$getsql=false)
	{
		$field=$field?:'*';
		$this->db->select($field)->table($this->table);
		if (is_array($where) && !empty($where)) {
			$where=$this->db->where($where);
		}
		if($order){
			$this->db->orderBy($order);
		}
		if($limit){
			$this->db->limit($limit);
		}
		return $this->db->getAll($getsql);
	}


}
