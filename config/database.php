<?php
// 数据库连接配置,支持mysql,sqlite,pgsql,oracle
// 环境变量优先级高于配置文件，支持 .env 文件配置
// 环境变量命名规则: DB_DRIVER, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_CHARSET, DB_PREFIX
return [
	'driver'	=>	env('DB_DRIVER') ?? 'mysql',//指定数据库类型
	'connections'	=>	[
		'mysql'	=>	[
			'driver'	=> 'mysql',//数据库类型
			'host'		=> env('DB_HOST') ?? 'localhost',//数据库服务器地址
			'database'	=> env('DB_DATABASE') ?? 'startmvc',//数据库名称
			'username'	=> env('DB_USERNAME') ?? 'root',//数据库用户名
			'password'	=> env('DB_PASSWORD') ?? '',//数据库密码
			'charset'	=> env('DB_CHARSET') ?? 'utf8',//数据库字符集
			'port'      => env('DB_PORT') ?? 3306,  //数据库端口
			'collation'	=> env('DB_COLLATION') ?? 'utf8_general_ci',//数据表编码
			'prefix'	 => env('DB_PREFIX') ?? 'sm_',//数据表前缀
			'cachetime' => 3600,//缓存时间(秒)
			'cachedir'	=> ROOT_PATH . 'runtime'.DS.'db'.DS,//缓存目录(可选)
			'options' => [ ]//连接选项(像SSL证书等可选)
		],
		'sqlite'	=>	[
			'driver' => 'sqlite',//数据库类型
			'database' => env('DB_DATABASE') ?? BASE_PATH.'data/database/test.db',//数据库文件路径
			'prefix' => env('DB_PREFIX') ?? 'sm_',//数据表前缀
			'cachetime' => 3600,//缓存时间(秒)
			'cachedir'	=> ROOT_PATH . 'runtime'.DS.'db'.DS,//缓存目录(可选)
			'options' => [ ]//连接选项(像SSL证书等可选)
		],
		'pgsql'	=>	[
			'driver'	=> 'pgsql',//数据库类型
			'host'		=> env('DB_HOST') ?? 'localhost',//数据库服务器地址
			'database'	=> env('DB_DATABASE') ?? 'startmvc',//数据库名称
			'username'	=> env('DB_USERNAME') ?? 'root',//数据库用户名
			'password'	=> env('DB_PASSWORD') ?? '',//数据库密码
			'charset'	=> env('DB_CHARSET') ?? 'utf8',//数据库字符集
			'port'      => env('DB_PORT') ?? 5432,  //数据库端口
			'collation'	=> env('DB_COLLATION') ?? 'utf8_general_ci',//数据表编码
			'prefix'	 => env('DB_PREFIX') ?? 'sm_',//数据表前缀
			'cachetime' => 3600,//缓存时间(秒)
			'cachedir'	=> ROOT_PATH . 'runtime'.DS.'db'.DS,//缓存目录(可选)
			'options' => [ ]//连接选项(像SSL证书等可选)
		],
		'oracle'	=>	[
			'driver'	=> 'oracle',//数据库类型
			'host'		=> env('DB_HOST') ?? 'localhost:8000',//数据库服务器地址
			'database'	=> env('DB_DATABASE') ?? 'startmvc',//数据库名称
			'username'	=> env('DB_USERNAME') ?? 'root',//数据库用户名
			'password'	=> env('DB_PASSWORD') ?? '',//数据库密码
			'charset'	=> env('DB_CHARSET') ?? 'utf8',//数据库字符集
			'port'      => env('DB_PORT') ?? 3306,  //数据库端口
			'collation'	=> env('DB_COLLATION') ?? 'utf8_general_ci',//数据表编码
			'prefix'	 => env('DB_PREFIX') ?? 'sm_',//数据表前缀
			'cachetime' => 3600,//缓存时间(秒)
			'cachedir'	=> ROOT_PATH . 'runtime'.DS.'db'.DS,//缓存目录(可选)
			'options' => [ ]//连接选项(像SSL证书等可选)
		],
		
	],
];