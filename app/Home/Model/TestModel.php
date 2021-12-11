<?php
namespace App\Home\Model;
use Startmvc\Lib\Model;

class TestModel extends Model{

	protected $table='Test';//表名
	
	function getData(){
	    //return $this->find('*',3);
	    //return Model::find('*',3);
	    //return $this->model('test')->find('*',3);
	    //return self::find('*',2);
	    return parent::find('*',3);
	}
}
