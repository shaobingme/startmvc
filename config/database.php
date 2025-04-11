<?php
// 数据库连接配置,支持mysql,sqlite,pgsql,oracle
return [
	'driver'	=>	'mysql',//指定数据库类型
	'connections'	=>	[
		'mysql'	=>	[
			'driver'	=> 'mysql',//数据库类型
			'host'		=> 'localhost',//数据库服务器地址
			'database'	=> 'startmvc',//数据库名称
			'username'	=> 'root',//数据库用户名
			'password'	=> '123456',//数据库密码
			'charset'	=> 'utf8',//数据库字符集
			'port' => 3306,  //数据库端口
			'collation'	=> 'utf8_general_ci',//数据表编码
			'prefix'	 => 'sm_',//数据表前缀
		],
		'sqlite'	=>	[
			'driver' => 'sqlite',//数据库类型
			'database' => BASE_PATH.'data/database/test.db',//数据库文件路径
			'prefix' => 'sm_'//数据表前缀
		],
		'pgsql'	=>	[
			'driver'	=> 'pgsql',//数据库类型
			'host'		=> 'localhost',//数据库服务器地址
			'database'	=> 'startmvc',//数据库名称
			'username'	=> 'root',//数据库用户名
			'password'	=> '',//数据库密码
			'charset'	=> 'utf8',//数据库字符集
			'port' => 3306,  //数据库端口
			'collation'	=> 'utf8_general_ci',//数据表编码
			'prefix'	 => 'sm_'//数据表前缀
		],
		'oracle'	=>	[
			'driver'	=> 'oracle',//数据库类型
			'host'		=> 'localhost:8000',//数据库服务器地址
			'database'	=> 'startmvc',//数据库名称
			'username'	=> 'root',//数据库用户名
			'password'	=> '',//数据库密码
			'charset'	=> 'utf8',//数据库字符集
		],
		
	],
];