# StartMVC 超轻量 PHP 框架

![StartMVC](https://img.kancloud.cn/a0/3f/a03fbd0f272ec563a545ab568995c1eb_557x223.png)

**StartMVC** 是一款超轻量 PHP 7 框架，面向对象开发，`小巧、优雅、高效`，遵循 Apache2 开源协议发布的，支持 Composer 和 RESTful 的 PHP 开源框架。

StartMVC 能够帮助开发者以最小的学习成本快速构建 Web 应用，在满足开发者最基础的分层开发、数据库和缓存访问等少量功能基础上，做到尽可能精简，以帮助您的应用基于框架高效运行。

## 特性

- **轻量极致**：打包后只有 50KB，高效运行
- **Composer 支持**：完全支持 Composer，代码遵循 PSR-2、PSR-4 规范
- **原生 PHP 视图**：采用原生 PHP 语法作为视图引擎，无需学习模板语法
- **数据库**：采用 PDO 操作，支持 MySQL、PostgreSQL、SQLite 等多种数据库
- **MVC 结构**：清晰的分层架构，易于维护
- **中间件**：支持 Auth、CSRF、Log 等多种中间件
- **RESTful API**：原生支持 RESTful API 开发
- **缓存支持**：支持 File、Redis、Memcached 等多种缓存驱动
- **依赖注入**：完整的容器实现
- **命令行工具**：提供 Console 命令行工具

## 安装

### 手动安装

1. 解压后上传到服务器项目目录
2. 将域名绑定（指向）到 `public` 目录
3. 访客无法访问除 public 目录之外的文件，安全性高

### Composer 安装

```bash
composer create-project shaobingme/startmvc
```

## 目录结构

```
startmvc/
├── app/                      # 应用目录
│   ├── admin/               # 后台模块
│   │   ├── controller/      # 控制器
│   │   └── view/            # 视图
│   ├── common/              # 公共基类
│   ├── home/                # 前台模块
│   │   ├── controller/      # 控制器
│   │   ├── model/           # 模型
│   │   ├── view/            # 视图
│   │   └── language/        # 语言包
│   └── middleware/          # 中间件
├── config/                   # 配置文件目录
│   ├── cache.php            # 缓存配置
│   ├── common.php           # 公共配置
│   ├── database.php         # 数据库配置
│   ├── log.php              # 日志配置
│   ├── middleware.php       # 中间件配置
│   ├── pagination.php       # 分页配置
│   ├── route.php            # 路由配置
│   └── view.php             # 视图配置
├── extend/                   # 扩展类库
│   └── captcha/             # 验证码扩展
├── function/                 # 公共函数
├── public/                   # Web 根目录（公开访问）
│   ├── static/              # 静态资源
│   │   ├── css/
│   │   ├── font/
│   │   ├── images/
│   │   └── js/
│   ├── upload/              # 上传目录
│   └── index.php            # 入口文件
├── startmvc/                 # 框架核心
│   ├── autoload.php         # 自动加载
│   ├── boot.php             # 启动文件
│   ├── function.php         # 框架函数
│   └── core/                # 核心类
│       ├── App.php          # 应用类
│       ├── Cache.php        # 缓存类
│       ├── Config.php       # 配置类
│       ├── Container.php    # 容器类
│       ├── Controller.php   # 控制器基类
│       ├── Cookie.php       # Cookie 类
│       ├── Csrf.php         # CSRF 防护类
│       ├── Db.php           # 数据库类
│       ├── Event.php        # 事件类
│       ├── Exception.php    # 异常处理类
│       ├── Http.php         # HTTP 工具类
│       ├── Loader.php       # 加载器类
│       ├── Logger.php       # 日志类
│       ├── Middleware.php   # 中间件类
│       ├── Model.php        # 模型基类
│       ├── Pagination.php   # 分页类
│       ├── Request.php      # 请求类
│       ├── Response.php     # 响应类
│       ├── Router.php       # 路由类
│       ├── Session.php      # Session 类
│       ├── Upload.php       # 上传类
│       ├── Validator.php    # 验证器类
│       ├── View.php         # 视图类
│       ├── cache/           # 缓存驱动
│       │   ├── File.php
│       │   ├── Memcached.php
│       │   └── Redis.php
│       ├── console/         # 命令行工具
│       │   ├── Command.php
│       │   ├── Console.php
│       │   ├── Output.php
│       │   └── commands/    # 内置命令
│       │       ├── CacheClear.php
│       │       ├── LogClear.php
│       │       ├── MakeController.php
│       │       ├── MakeModel.php
│       │       └── RouteList.php
│       ├── db/              # 数据库核心
│       │   ├── DbCore.php
│       │   ├── DbCache.php
│       │   └── DbInterface.php
│       └── tpl/             # 模板文件
└── vendor/                   # Composer 依赖
```

## 快速开始

### 创建控制器

```php
<?php
namespace app\home\controller;

class IndexController extends \app\common\BaseController
{
    public function indexAction()
    {
        return $this->display();
    }
}
```

### 创建模型

```php
<?php
namespace app\home\model;

use startmvc\core\Model;

class TestModel extends Model
{
    protected $table = 'test';
    
    public function getData()
    {
        return $this->findAll();
    }
}
```

### 数据库操作

```php
// 查询
$list = db('user')->where('status', 1)->findAll();

// 插入
db('user')->insert(['name' => 'test', 'created_at' => time()]);

// 更新
db('user')->where('id', 1)->update(['name' => 'new name']);

// 删除
db('user')->where('id', 1)->delete();
```

### 路由配置

在 `config/route.php` 中配置路由：

```php
use startmvc\core\Router;

Router::get('/', 'home\index@index');
Router::get('/user/{id}', 'home\user@show');
Router::post('/user', 'home\user@create');
```

### 使用中间件

```php
// config/middleware.php
return [
    'csrf' => \app\middleware\CsrfMiddleware::class,
    'auth' => \app\middleware\AuthMiddleware::class,
    'log' => \app\middleware\LogMiddleware::class,
];
```

## 配置说明

### 数据库配置 (config/database.php)

```php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'startmvc',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'prefix' => 'sm_',
        ],
    ],
];
```

### 缓存配置 (config/cache.php)

```php
return [
    'default' => 'file',
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => '../storage/cache',
        ],
        'redis' => [
            'driver' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => 0,
        ],
    ],
];
```

## 命令行工具

```bash
# 创建控制器
php startmvc console make:controller admin/User

# 创建模型
php startmvc console make:model home/User

# 清除缓存
php startmvc console cache:clear

# 清除日志
php startmvc console log:clear

# 查看路由列表
php startmvc console route:list
```

## 辅助函数

框架提供了丰富的辅助函数：

```php
// 获取配置
config('database.default');

// 获取缓存
cache('key');
cache('key', $value, 3600);

// 获取请求参数
input('name');
input('id', 0, 'int');

// 生成 URL
url('home/index/index');

// 获取数据库实例
db('user')->find(1);

// 获取模型实例
model('Test', 'home');

// 语言包
lang('common.success');
```

## 验证器

```php
$validator = new \startmvc\core\Validator();
$validator->setRules([
    'username' => 'required|minlen:3|maxlen:20',
    'email' => 'required|isEmail',
    'password' => 'required|minlen:6',
]);

if ($validator->validate($_POST)) {
    // 验证通过
} else {
    $errors = $validator->getError();
}
```

## 官方资源

- 官网：http://www.startmvc.com
- QQ群：231304282（加群口令：startmvc）

## 开源协议

本项目遵循 Apache 2.0 开源协议。

## 贡献

欢迎提交 Pull Request 或 Issue。