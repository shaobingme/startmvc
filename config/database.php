<?php
// Mysql 数据库
return [
	'host'		=> 'localhost',//数据库服务器地址
	'driver'	=> 'mysql',//数据库类型
	'database'	=> 'startmvc',//数据库名称
	'username'	=> 'root',//数据库用户名
	'password'	=> '',//数据库密码
	'charset'	=> 'utf8',//数据库字符集
    'port' => 3306,  //数据库端口
	'collation'	=> 'utf8_general_ci',//数据表编码
	'prefix'	 => 'sm_'//数据表前缀
];

/*

// Sqlite 数据库

return [
    'database_type' => 'sqlite',                                //数据库类型
    'database_file' => ROOT_PATH . '/database/database.db',     //数据库文件路径
    'prefix' => 'sm_'                                         //数据表前缀
];

*/