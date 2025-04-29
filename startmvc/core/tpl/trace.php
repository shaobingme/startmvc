<!-- startmvc/core/tpl/trace.php -->
<?php
use startmvc\core\App;  // 添加命名空间引用

if(config('trace')): ?>
<style>
/* trace面板基础样式 */
#think_page_trace {
    display: none;
    position: fixed;
    bottom: 0;
    right: 0;
    font-size: 14px;
    width: 100%;
    z-index: 999999;
    color: #333;
    text-align: left;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    box-shadow: 0 -2px 4px rgba(0,0,0,.05);
    transition: transform 0.3s ease;
    transform: translateY(100%);
}
#think_page_trace_btn {
    position: fixed;
    bottom: 0;
    right: 0;
    z-index: 999999;
}
#think_page_trace_btn div {
    background: #1a73e8;
    color: #FFF;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 14px;
    border-top-left-radius: 4px;
    box-shadow: 0 -2px 4px rgba(0,0,0,.1);
}

/* 标签样式 */
.trace-tab {
    padding: 6px 12px;
    cursor: pointer;
    margin-right: 5px;
    border-radius: 4px 4px 0 0;
}
.trace-tab.active {
    background: #1a73e8;
    color: #fff;
}
.trace-tab:not(.active):hover {
    background-color: #f0f0f0;
}

/* 面板样式 */
.trace-panel {
    display: none;
    height: 250px;
    overflow-y: auto;
}
.trace-panel-active {
    display: block;
}

/* 数据项目样式 */
.trace-item {
    background: #fff;
    padding: 8px 15px;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}
.trace-item-title {
    color: #666;
}
.trace-item-content {
    color: #1a73e8;
    font-weight: bold;
}

/* 数据块样式 */
.trace-block {
    margin-bottom: 12px;
}
.trace-block-title {
    font-weight: bold;
    padding: 5px 0;
    color: #1a73e8;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 5px;
}
.trace-block-container {
    background: #fff;
    border-radius: 4px;
    border: 1px solid #e9ecef;
    max-height: 70px;
    overflow-y: auto;
}

/* 数据行样式 */
.trace-row {
    padding: 5px 12px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}
.trace-row:nth-child(even) {
    background: #f8f9fa;
}
.trace-label {
    color: #666;
    font-weight: bold;
}
.trace-value {
    word-break: break-all;
}

/* 统计信息栏 */
.trace-stats {
    padding: 8px 12px;
    background: #f8f9fa;
    font-size: 13px;
    border-top: 1px solid #e9ecef;
    font-weight: bold;
}
</style>

<div id="think_page_trace">
    <div style="padding:10px;">
        <!-- 标签导航 -->
        <div style="display:flex;border-bottom:1px solid #e9ecef;margin-bottom:10px;">
            <div id="tab-base" class="trace-tab active">基础信息</div>
            <div id="tab-request" class="trace-tab">请求</div>
            <div id="tab-sql" class="trace-tab">SQL查询</div>
            <div id="tab-file" class="trace-tab">加载文件</div>
            <div id="tab-config" class="trace-tab">配置</div>
            <div id="tab-error" class="trace-tab">错误</div>
            <div style="margin-left:auto;display:flex;align-items:center;">
                <span style="margin-right:15px;color:#666;">
                    总耗时: <span style="color:#28a745;font-weight:bold;"><?php echo isset(App::$trace['runtime']) ? App::$trace['runtime'] : '未记录'; ?></span>
                </span>
                <span style="margin-right:15px;color:#666;">
                    内存: <span style="color:#28a745;font-weight:bold;"><?php echo isset(App::$trace['memory']) ? App::$trace['memory'] : '未记录'; ?></span>
                </span>
            </div>
        </div>

        <!-- 基础信息面板 -->
        <div id="panel-base" class="trace-panel trace-panel-active">
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <div class="trace-item">
                    <span class="trace-item-title">请求方法：</span>
                    <span class="trace-item-content"><?php echo $_SERVER['REQUEST_METHOD']; ?></span>
                </div>
                <div class="trace-item">
                    <span class="trace-item-title">请求URI：</span>
                    <span class="trace-item-content"><?php echo $_SERVER['REQUEST_URI']; ?></span>
                </div>
                <div class="trace-item">
                    <span class="trace-item-title">控制器：</span>
                    <span class="trace-item-content"><?php echo isset($_GET['c']) ? $_GET['c'] : 'index'; ?></span>
                </div>
                <div class="trace-item">
                    <span class="trace-item-title">方法：</span>
                    <span class="trace-item-content"><?php echo isset($_GET['a']) ? $_GET['a'] : 'index'; ?></span>
                </div>
                <div class="trace-item">
                    <span class="trace-item-title">PHP版本：</span>
                    <span class="trace-item-content"><?php echo PHP_VERSION; ?></span>
                </div>
                <div class="trace-item">
                    <span class="trace-item-title">服务器：</span>
                    <span class="trace-item-content"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                </div>
            </div>
        </div>
        
        <!-- 请求信息面板 -->
        <div id="panel-request" class="trace-panel">
            <div style="display:flex;flex-wrap:wrap;gap:12px;">
                <!-- GET参数 -->
                <div style="flex:1;min-width:300px;">
                    <div class="trace-block-title">GET参数</div>
                    <div class="trace-block-container">
                        <?php if(empty($_GET)): ?>
                            <div class="trace-row" style="font-style:italic;color:#666;">无GET参数</div>
                        <?php else: ?>
                            <?php foreach($_GET as $key => $value): ?>
                            <div class="trace-row">
                                <span class="trace-label"><?php echo htmlspecialchars($key); ?>: </span>
                                <span class="trace-value">
                                    <?php
                                    if(is_array($value) || is_object($value)) {
                                        echo htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE));
                                    } else {
                                        echo htmlspecialchars($value);
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- POST参数 -->
                <div style="flex:1;min-width:300px;">
                    <div class="trace-block-title">POST参数</div>
                    <div class="trace-block-container">
                        <?php if(empty($_POST)): ?>
                            <div class="trace-row" style="font-style:italic;color:#666;">无POST参数</div>
                        <?php else: ?>
                            <?php foreach($_POST as $key => $value): ?>
                            <div class="trace-row">
                                <span class="trace-label"><?php echo htmlspecialchars($key); ?>: </span>
                                <span class="trace-value">
                                    <?php
                                    if(is_array($value) || is_object($value)) {
                                        echo htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE));
                                    } else {
                                        echo htmlspecialchars($value);
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 请求头信息 -->
            <div class="trace-block">
                <div class="trace-block-title">请求头</div>
                <div class="trace-block-container">
                    <?php 
                    $headers = function_exists('getallheaders') ? getallheaders() : [];
                    if(empty($headers)): ?>
                        <div class="trace-row" style="font-style:italic;color:#666;">无法获取请求头信息</div>
                    <?php else: ?>
                        <?php foreach($headers as $key => $value): ?>
                        <div class="trace-row">
                            <span class="trace-label"><?php echo htmlspecialchars($key); ?>: </span>
                            <span class="trace-value"><?php echo htmlspecialchars($value); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Cookie信息 -->
            <div class="trace-block">
                <div class="trace-block-title">Cookie</div>
                <div class="trace-block-container">
                    <?php if(empty($_COOKIE)): ?>
                        <div class="trace-row" style="font-style:italic;color:#666;">无Cookie数据</div>
                    <?php else: ?>
                        <?php foreach($_COOKIE as $key => $value): ?>
                        <div class="trace-row">
                            <span class="trace-label"><?php echo htmlspecialchars($key); ?>: </span>
                            <span class="trace-value"><?php echo htmlspecialchars($value); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- SQL查询面板 -->
        <div id="panel-sql" class="trace-panel">
            <div class="trace-block-container" style="max-height:250px;">
                <?php 
                // 只有在需要使用时才加载
                if (class_exists('startmvc\core\db\DbCore')) {
                    $sqlLogs = startmvc\core\db\DbCore::getSqlLogs();
                } else {
                    $sqlLogs = [];
                }
                if (!empty($sqlLogs)): 
                    $totalTime = 0;
                    foreach($sqlLogs as $index => $sql): 
                        // 提取执行时间数值部分（去掉ms后缀）
                        $timeValue = floatval(str_replace('ms', '', $sql['time']));
                        $totalTime += $timeValue;
                        // 设置时间显示的颜色，超过100ms为红色，超过50ms为黄色，低于50ms为绿色
                        $timeColor = $timeValue > 100 ? '#dc3545' : ($timeValue > 50 ? '#ffc107' : '#28a745');
                    ?>
                    <div class="trace-row">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:15px;">
                            <span style="color:#333;font-family:monospace;flex:1;word-break:break-all;white-space:pre-wrap;"><?php echo htmlspecialchars($sql['sql']); ?></span>
                            <span style="color:<?php echo $timeColor; ?>;white-space:nowrap;flex-shrink:0;font-weight:bold;"><?php echo $sql['time']; ?></span>
                        </div>
                        <?php if (!empty($sql['params'])): ?>
                        <div style="color:#666;font-size:12px;margin-top:4px;padding-left:20px;word-break:break-all;">
                            参数：<?php echo htmlspecialchars(json_encode($sql['params'], JSON_UNESCAPED_UNICODE)); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <div class="trace-stats">
                        <span>总计：<?php echo count($sqlLogs); ?> 条SQL语句</span>
                        <span style="float:right;color:#1a73e8;">总耗时：<?php echo number_format($totalTime, 2); ?> ms</span>
                    </div>
                <?php else: ?>
                    <div class="trace-row" style="font-style:italic;color:#666;">暂无SQL操作记录</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 加载文件面板 -->
        <div id="panel-file" class="trace-panel">
            <div class="trace-block-container" style="max-height:250px;">
                <?php 
                $files = get_included_files();
                foreach($files as $index => $file): ?>
                <div class="trace-row">
                    <span style="color:#666;display:inline-block;width:25px;"><?php echo ($index + 1) . '.'; ?></span>
                    <span style="color:#333;word-break:break-all;"><?php echo $file; ?></span>
                </div>
                <?php endforeach; ?>
                <div class="trace-stats">
                    总计：<?php echo count($files); ?> 个文件
                </div>
            </div>
        </div>
        
        <!-- 配置信息面板 -->
        <div id="panel-config" class="trace-panel">
            <div style="display:flex;flex-wrap:wrap;gap:12px;">
                <!-- 系统配置 -->
                <div style="flex:1;min-width:300px;">
                    <div class="trace-block-title">系统配置</div>
                    <div class="trace-block-container">
                        <?php
                        // 常见关键配置项
                        $keyConfigs = [
                            '运行环境' => defined('ENV') ? ENV : (defined('APP_ENV') ? APP_ENV : '未定义'),
                            '调试模式' => defined('DEBUG') ? (DEBUG ? '开启' : '关闭') : (config('debug') ? '开启' : '关闭'),
                            '时区设置' => date_default_timezone_get(),
                            '最大执行时间' => ini_get('max_execution_time').'秒',
                            '内存限制' => ini_get('memory_limit'),
                            '上传限制' => ini_get('upload_max_filesize'),
                            'POST限制' => ini_get('post_max_size'),
                            '字符集' => ini_get('default_charset') ?: (defined('CHARSET') ? CHARSET : 'UTF-8'),
                            '错误报告级别' => ini_get('error_reporting')
                        ];
                        
                        foreach($keyConfigs as $key => $value):
                        ?>
                        <div class="trace-row">
                            <span class="trace-label"><?php echo $key; ?>: </span>
                            <span style="color:#1a73e8;word-break:break-all;"><?php echo $value; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- 缓存状态 -->
                <div style="flex:1;min-width:300px;">
                    <div class="trace-block-title">缓存状态</div>
                    <div class="trace-block-container">
                        <?php
                        // 检查常见的缓存扩展
                        $cacheExtensions = [
                            'APC' => extension_loaded('apc') || extension_loaded('apcu'),
                            'Memcached' => extension_loaded('memcached'),
                            'Redis' => extension_loaded('redis'),
                            'OPcache' => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
                            'XCache' => extension_loaded('xcache'),
                            'File Cache' => true // 文件缓存总是可用
                        ];
                        
                        foreach($cacheExtensions as $cache => $available):
                            $color = $available ? '#28a745' : '#dc3545';
                            $status = $available ? '可用' : '不可用';
                        ?>
                        <div class="trace-row">
                            <span class="trace-label"><?php echo $cache; ?>: </span>
                            <span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status; ?></span>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- 自动加载信息 -->
                        <div class="trace-row">
                            <span class="trace-label">Autoload: </span>
                            <span style="color:#1a73e8;word-break:break-all;">
                                <?php 
                                $autoload = spl_autoload_functions();
                                echo is_array($autoload) ? count($autoload).' 个加载器' : '0 个加载器'; 
                                ?>
                            </span>
                        </div>
                        
                        <!-- 会话状态 -->
                        <div class="trace-row">
                            <span class="trace-label">Session: </span>
                            <span style="color:#1a73e8;word-break:break-all;">
                                <?php 
                                echo session_status() == PHP_SESSION_ACTIVE ? '已激活' : '未激活';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 框架配置 -->
            <div class="trace-block">
                <div class="trace-block-title">框架配置</div>
                <div class="trace-block-container">
                    <?php
                    // 获取可显示的配置信息
                    $configs = [];
                    if (function_exists('config')) {
                        // 尝试获取常见框架配置
                        $commonConfigs = [
                            'app', 'database', 'cache', 'session', 'log', 'trace', 
                            'debug', 'url', 'default_controller', 'default_action'
                        ];
                        
                        foreach ($commonConfigs as $key) {
                            $value = config($key);
                            if (!is_null($value)) {
                                if (is_array($value) || is_object($value)) {
                                    // 对于复杂结构，只显示键名和类型
                                    $configs[$key] = '[' . (is_array($value) ? 'Array' : get_class($value)) . ']';
                                } else {
                                    // 对于简单值，直接显示
                                    $configs[$key] = (string)$value;
                                }
                            }
                        }
                    }
                    
                    if (!empty($configs)): 
                        foreach($configs as $key => $value):
                    ?>
                    <div class="trace-row">
                        <span class="trace-label"><?php echo htmlspecialchars($key); ?>: </span>
                        <span class="trace-value"><?php echo htmlspecialchars($value); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="trace-row" style="font-style:italic;color:#666;">无法获取框架配置信息</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 错误信息面板 -->
        <div id="panel-error" class="trace-panel">
            <?php
            // 获取错误信息
            $hasErrors = false;
            ?>
            
            <!-- 捕获的异常 -->
            <div class="trace-block">
                <div class="trace-block-title" style="color:#dc3545;">异常信息</div>
                <div class="trace-block-container" style="max-height:90px;">
                    <?php 
                    // 检查是否有捕获的异常
                    $exceptions = isset(App::$trace['exceptions']) ? App::$trace['exceptions'] : [];
                    
                    if (!empty($exceptions)): 
                        $hasErrors = true;
                        foreach($exceptions as $index => $exception): 
                    ?>
                    <div class="trace-row">
                        <div style="color:#dc3545;font-weight:bold;margin-bottom:3px;"><?php echo get_class($exception); ?></div>
                        <div style="margin-bottom:3px;"><?php echo $exception->getMessage(); ?></div>
                        <div style="color:#666;font-size:12px;">
                            位于: <?php echo $exception->getFile(); ?>:<?php echo $exception->getLine(); ?>
                        </div>
                        <div style="margin-top:5px;font-family:monospace;font-size:12px;white-space:pre-wrap;color:#666;">
                            <?php 
                            // 显示简化的堆栈信息
                            $trace = $exception->getTrace();
                            $traceOutput = [];
                            foreach(array_slice($trace, 0, 5) as $t) {
                                $file = isset($t['file']) ? $t['file'] : '[内部函数]';
                                $line = isset($t['line']) ? $t['line'] : '';
                                $function = isset($t['function']) ? $t['function'] : '';
                                $class = isset($t['class']) ? $t['class'] . $t['type'] : '';
                                $traceOutput[] = "#" . count($traceOutput) . " " . $file . "(" . $line . "): " . $class . $function . "()";
                            }
                            echo implode("\n", $traceOutput);
                            if (count($trace) > 5) {
                                echo "\n... 更多 " . (count($trace) - 5) . " 行...";
                            }
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="trace-row" style="color:#28a745;font-style:italic;">未捕获到异常</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 错误信息 -->
            <div class="trace-block">
                <div class="trace-block-title" style="color:#dc3545;">错误信息</div>
                <div class="trace-block-container" style="max-height:90px;">
                    <?php
                    // 检查是否有错误信息
                    $errors = isset(App::$trace['errors']) ? App::$trace['errors'] : [];
                    
                    if (empty($errors) && function_exists('error_get_last')) {
                        $lastError = error_get_last();
                        if ($lastError) {
                            $errors[] = $lastError;
                        }
                    }
                    
                    if (!empty($errors)): 
                        $hasErrors = true;
                        foreach($errors as $index => $error): 
                            // 设置不同错误类型的显示样式
                            $errorType = isset($error['type']) ? $error['type'] : E_ERROR;
                            $errorTypeStr = '';
                            $errorColor = '#dc3545';
                            
                            switch($errorType) {
                                case E_ERROR:
                                case E_CORE_ERROR:
                                case E_COMPILE_ERROR:
                                case E_USER_ERROR:
                                    $errorTypeStr = '致命错误';
                                    $errorColor = '#dc3545';
                                    break;
                                case E_WARNING:
                                case E_CORE_WARNING:
                                case E_COMPILE_WARNING:
                                case E_USER_WARNING:
                                    $errorTypeStr = '警告';
                                    $errorColor = '#ffc107';
                                    break;
                                case E_NOTICE:
                                case E_USER_NOTICE:
                                    $errorTypeStr = '提示';
                                    $errorColor = '#1a73e8';
                                    break;
                                case E_STRICT:
                                case E_DEPRECATED:
                                case E_USER_DEPRECATED:
                                    $errorTypeStr = '建议修复';
                                    $errorColor = '#6c757d';
                                    break;
                                default:
                                    $errorTypeStr = '未知错误';
                            }
                    ?>
                    <div class="trace-row">
                        <div style="color:<?php echo $errorColor; ?>;font-weight:bold;margin-bottom:3px;">[<?php echo $errorTypeStr; ?>]</div>
                        <div style="margin-bottom:3px;"><?php echo isset($error['message']) ? $error['message'] : ''; ?></div>
                        <div style="color:#666;font-size:12px;">
                            位于: <?php echo isset($error['file']) ? $error['file'] : '未知文件'; ?>:<?php echo isset($error['line']) ? $error['line'] : ''; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="trace-row" style="color:#28a745;font-style:italic;">未发现错误</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$hasErrors): ?>
            <div style="text-align:center;margin-top:20px;color:#28a745;">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                </svg>
                <div style="margin-top:10px;font-size:16px;">应用运行正常，未发现错误或异常</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 触发按钮 -->
<div id="think_page_trace_btn">
    <div>
        <span style="margin-right:5px;">⚡</span>
        <?php echo isset(App::$trace['runtime']) ? App::$trace['runtime'] : '0.00ms'; ?>
    </div>
</div>

<!-- 交互脚本 -->
<script type="text/javascript">
(function(){
    // 获取DOM元素
    var btn = document.getElementById('think_page_trace_btn');
    var trace = document.getElementById('think_page_trace');
    var tabs = document.querySelectorAll('.trace-tab');
    var panels = document.querySelectorAll('.trace-panel');
    var isShow = false;
    
    // 初始状态
    trace.style.display = 'none';
    
    btn.onclick = function() {
        if(isShow) {
            trace.style.transform = 'translateY(100%)';
            setTimeout(function() {
                trace.style.display = 'none';
            }, 300);
            isShow = false;
        } else {
            trace.style.display = 'block';
            // 使用requestAnimationFrame确保在下一帧渲染前更新样式
            requestAnimationFrame(function() {
                trace.style.transform = 'translateY(0)';
            });
            isShow = true;
        }
    };
    
    // 标签切换功能
    tabs.forEach(function(tab) {
        tab.onclick = function() {
            // 移除所有活动状态
            tabs.forEach(function(t) {
                t.classList.remove('active');
            });
            
            // 设置当前标签为活动状态
            this.classList.add('active');
            
            // 隐藏所有面板
            panels.forEach(function(panel) {
                panel.classList.remove('trace-panel-active');
            });
            
            // 显示对应面板
            var panelId = 'panel-' + this.id.split('-')[1];
            document.getElementById(panelId).classList.add('trace-panel-active');
        };
    });
})();
</script>
<?php endif; ?>