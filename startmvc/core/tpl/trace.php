<!-- startmvc/core/tpl/trace.php -->
<?php
use startmvc\core\App;  // 添加命名空间引用

if(config('trace')): ?>
<style>
/* ==========================================================================
   StartMVC 调试面板
   设计约束：轻量清爽 —— 浅色为底、单一强调色、以字阶与细线分区，不用大色块。
   全部类名带 smt- 前缀，并仅作用于面板根节点内部，避免影响宿主页样式。
   ========================================================================== */
#think_page_trace {
	--smt-fg: #1c2024;
	--smt-muted: #6b7280;
	--smt-faint: #9aa2ad;
	--smt-line: #e6e9ee;
	--smt-line-soft: #f1f3f6;
	--smt-card: #ffffff;
	--smt-bg: #fbfcfd;
	--smt-accent: #1a73e8;
	--smt-ok: #12a150;
	--smt-warn: #b7791f;
	--smt-err: #d93f3f;

	display: none;
	position: fixed;
	bottom: 0;
	right: 0;
	width: 100%;
	z-index: 999999;
	color: var(--smt-fg);
	background: var(--smt-bg);
	border-top: 1px solid var(--smt-line);
	box-shadow: 0 -6px 24px rgba(16, 24, 40, .07);
	text-align: left;
	font: 13px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
	-webkit-font-smoothing: antialiased;
	transition: transform .28s cubic-bezier(.4, 0, .2, 1);
	transform: translateY(100%);
}
#think_page_trace * { box-sizing: border-box; }

/* 面板外壳 */
.smt-inner { padding: 0 14px 12px; }

/* ---------- 标签导航 ---------- */
.smt-nav {
	display: flex;
	align-items: center;
	gap: 2px;
	border-bottom: 1px solid var(--smt-line);
	padding-top: 8px;
}
.smt-tab {
	padding: 7px 10px 9px;
	cursor: pointer;
	color: var(--smt-muted);
	border-bottom: 2px solid transparent;
	margin-bottom: -1px;
	transition: color .15s ease, border-color .15s ease;
	user-select: none;
}
.smt-tab:hover { color: var(--smt-fg); }
.smt-tab.active {
	color: var(--smt-accent);
	border-bottom-color: var(--smt-accent);
	font-weight: 600;
}
.smt-tab .smt-badge {
	display: inline-block;
	min-width: 16px;
	margin-left: 5px;
	padding: 0 4px;
	font-size: 11px;
	font-weight: 600;
	line-height: 15px;
	text-align: center;
	color: #fff;
	background: var(--smt-err);
	border-radius: 8px;
	vertical-align: 1px;
}
.smt-metrics {
	margin-left: auto;
	display: flex;
	align-items: center;
	gap: 14px;
	padding-right: 2px;
	color: var(--smt-muted);
	font-size: 12px;
}
.smt-metrics b { color: var(--smt-ok); font-weight: 600; font-variant-numeric: tabular-nums; }

/* ---------- 面板与滚动区 ---------- */
/* 面板高度随内容自适应并封顶，避免内容很少时留出大片空白 */
.smt-panel { display: none; max-height: 250px; overflow-y: auto; padding-top: 10px; }
.smt-panel.active { display: block; }
.smt-panel > .smt-cols + .smt-block { margin-top: 12px; }

/* 细滚动条，避免默认样式过于抢眼 */
.smt-panel::-webkit-scrollbar,
.smt-box::-webkit-scrollbar { width: 8px; height: 8px; }
.smt-panel::-webkit-scrollbar-thumb,
.smt-box::-webkit-scrollbar-thumb { background: #dfe3e8; border-radius: 4px; }
.smt-panel::-webkit-scrollbar-thumb:hover,
.smt-box::-webkit-scrollbar-thumb:hover { background: #cbd2da; }

/* ---------- 键值卡片 ---------- */
.smt-cols { display: flex; flex-wrap: wrap; gap: 8px; }
.smt-col { flex: 1; min-width: 300px; }
.smt-item {
	background: var(--smt-card);
	border: 1px solid var(--smt-line);
	border-radius: 6px;
	padding: 7px 12px;
	flex: 1 1 200px;
	min-width: 200px;
}
.smt-item-title { color: var(--smt-muted); }
.smt-item-content { color: var(--smt-fg); font-weight: 600; }

/* ---------- 数据块 ---------- */
.smt-block { margin-bottom: 12px; }
.smt-block-title {
	font-size: 12px;
	font-weight: 600;
	color: var(--smt-muted);
	letter-spacing: .02em;
	padding-bottom: 6px;
	margin-bottom: 6px;
	border-bottom: 1px solid var(--smt-line);
}
.smt-block-title.smt-err { color: var(--smt-err); }
.smt-box {
	background: var(--smt-card);
	border: 1px solid var(--smt-line);
	border-radius: 6px;
	max-height: 200px;
	overflow-y: auto;
}
.smt-box-lg { max-height: 220px; }
.smt-box-md { max-height: 160px; }
.smt-box-sm { max-height: 96px; }

/* ---------- 数据行 ---------- */
.smt-row {
	padding: 5px 12px;
	font-size: 12.5px;
	border-bottom: 1px solid var(--smt-line-soft);
	display: flex;
	gap: 8px;
	align-items: baseline;
}
.smt-row:last-child { border-bottom: 0; }
.smt-block .smt-row:not(.smt-row-plain) { flex-direction: column; gap: 0; }
.smt-label { color: var(--smt-muted); flex-shrink: 0; }
.smt-label::after { content: "："; }
.smt-value { word-break: break-all; min-width: 0; }
.smt-key { color: var(--smt-accent); word-break: break-all; min-width: 0; }
.smt-empty { font-style: normal; color: var(--smt-faint); }
.smt-idx { color: var(--smt-faint); display: inline-block; min-width: 26px; font-variant-numeric: tabular-nums; }
.smt-mono {
	font-family: ui-monospace, Menlo, Consolas, monospace;
	font-size: 12px;
	word-break: break-all;
	white-space: pre-wrap;
	color: var(--smt-fg);
}
.smt-detail { color: var(--smt-muted); font-size: 11.5px; }
.smt-pad { margin-bottom: 3px; }

/* ---------- 状态文字 ---------- */
.smt-ok { color: var(--smt-ok); font-weight: 600; }
.smt-ok-plain { color: var(--smt-ok); }
.smt-err-text { color: var(--smt-err); }

/* ---------- 统计栏 ---------- */
.smt-stats {
	padding: 7px 12px;
	font-size: 12px;
	font-weight: 600;
	color: var(--smt-muted);
	background: var(--smt-bg);
	border-top: 1px solid var(--smt-line);
	display: flex;
	justify-content: space-between;
	gap: 12px;
}
.smt-stats .smt-accent { color: var(--smt-accent); }

/* ---------- 空状态 ---------- */
.smt-allclear {
	text-align: center;
	padding: 24px 0 8px;
	color: var(--smt-ok);
}
.smt-allclear svg { width: 40px; height: 40px; opacity: .85; }
.smt-allclear p { margin: 8px 0 0; font-size: 13px; }

/* ---------- 触发按钮 ---------- */
#think_page_trace_btn {
	position: fixed;
	bottom: 0;
	right: 0;
	z-index: 999999;
	display: flex;
	align-items: stretch;
	font: 12px/1 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
}
#think_page_trace_btn .smt-trigger {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 7px 12px;
	color: #fff;
	background: #252a31;
	cursor: pointer;
	border-top-left-radius: 6px;
	box-shadow: 0 -2px 8px rgba(16, 24, 40, .18);
	transition: background .15s ease;
	font-variant-numeric: tabular-nums;
}
#think_page_trace_btn .smt-trigger:hover { background: #1a1f26; }
#think_page_trace_btn .smt-bolt { color: #ffd166; font-size: 13px; }
#think_page_trace_btn .smt-close {
	display: none;
	align-items: center;
	justify-content: center;
	width: 30px;
	color: #fff;
	background: #252a31;
	cursor: pointer;
	border-left: 1px solid rgba(255, 255, 255, .14);
	transition: background .15s ease;
}
#think_page_trace_btn .smt-close:hover { background: #1a1f26; }
#think_page_trace_btn.smt-open .smt-close { display: flex; }
</style>

<div id="think_page_trace">
	<div class="smt-inner">
		<!-- 标签导航 -->
		<div class="smt-nav">
			<div id="tab-base" class="smt-tab active">基础信息</div>
			<div id="tab-request" class="smt-tab">请求</div>
			<div id="tab-sql" class="smt-tab">SQL</div>
			<div id="tab-file" class="smt-tab">文件</div>
			<div id="tab-config" class="smt-tab">配置</div>
			<?php
			// 错误数用于标签上的角标，让闭着面板也能看出有没有问题
			$smtErrorCount = count(isset(App::$trace['exceptions']) ? App::$trace['exceptions'] : []);
			$smtErrorCount += count(isset(App::$trace['errors']) ? App::$trace['errors'] : []);
			?>
			<div id="tab-error" class="smt-tab">错误<?php if ($smtErrorCount > 0): ?><span class="smt-badge"><?php echo $smtErrorCount; ?></span><?php endif; ?></div>
			<div class="smt-metrics">
				<span>耗时 <b><?php echo isset(App::$trace['runtime']) ? App::$trace['runtime'] : '—'; ?></b></span>
				<span>内存 <b><?php echo isset(App::$trace['memory']) ? App::$trace['memory'] : '—'; ?></b></span>
			</div>
		</div>

		<!-- 基础信息面板 -->
		<div id="panel-base" class="smt-panel active">
			<div class="smt-cols">
				<div class="smt-item">
					<span class="smt-item-title">请求方法</span>
					<span class="smt-item-content" style="margin-left:6px;"><?php echo htmlspecialchars(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : ''); ?></span>
				</div>
				<div class="smt-item">
					<span class="smt-item-title">请求URI</span>
					<span class="smt-item-content" style="margin-left:6px;word-break:break-all;"><?php echo htmlspecialchars(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''); ?></span>
				</div>
				<div class="smt-item">
					<span class="smt-item-title">控制器</span>
					<span class="smt-item-content" style="margin-left:6px;"><?php echo htmlspecialchars(isset($_GET['c']) ? $_GET['c'] : 'index'); ?></span>
				</div>
				<div class="smt-item">
					<span class="smt-item-title">方法</span>
					<span class="smt-item-content" style="margin-left:6px;"><?php echo htmlspecialchars(isset($_GET['a']) ? $_GET['a'] : 'index'); ?></span>
				</div>
				<div class="smt-item">
					<span class="smt-item-title">PHP版本</span>
					<span class="smt-item-content" style="margin-left:6px;"><?php echo PHP_VERSION; ?></span>
				</div>
				<div class="smt-item">
					<span class="smt-item-title">服务器</span>
					<span class="smt-item-content" style="margin-left:6px;"><?php echo htmlspecialchars(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : ''); ?></span>
				</div>
			</div>
		</div>

		<!-- 请求信息面板 -->
		<div id="panel-request" class="smt-panel">
			<div class="smt-cols">
				<!-- GET参数 -->
				<div class="smt-col">
					<div class="smt-block-title">GET 参数</div>
					<div class="smt-box">
						<?php if(empty($_GET)): ?>
							<div class="smt-row smt-row-plain smt-empty">无 GET 参数</div>
						<?php else: ?>
							<?php foreach($_GET as $key => $value): ?>
							<div class="smt-row smt-row-plain">
								<span class="smt-label"><?php echo htmlspecialchars($key); ?></span>
								<span class="smt-value">
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
				<div class="smt-col">
					<div class="smt-block-title">POST 参数</div>
					<div class="smt-box">
						<?php if(empty($_POST)): ?>
							<div class="smt-row smt-row-plain smt-empty">无 POST 参数</div>
						<?php else: ?>
							<?php foreach($_POST as $key => $value): ?>
							<div class="smt-row smt-row-plain">
								<span class="smt-label"><?php echo htmlspecialchars($key); ?></span>
								<span class="smt-value">
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
			<div class="smt-block">
				<div class="smt-block-title">请求头</div>
				<div class="smt-box">
					<?php 
					$headers = function_exists('getallheaders') ? getallheaders() : [];
					if(empty($headers)): ?>
						<div class="smt-row smt-row-plain smt-empty">无法获取请求头信息</div>
					<?php else: ?>
						<?php foreach($headers as $key => $value): ?>
						<div class="smt-row smt-row-plain">
							<span class="smt-label"><?php echo htmlspecialchars($key); ?></span>
							<span class="smt-value"><?php echo htmlspecialchars($value); ?></span>
						</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Cookie信息 -->
			<div class="smt-block">
				<div class="smt-block-title">Cookie</div>
				<div class="smt-box">
					<?php if(empty($_COOKIE)): ?>
						<div class="smt-row smt-row-plain smt-empty">无 Cookie 数据</div>
					<?php else: ?>
						<?php foreach($_COOKIE as $key => $value): ?>
						<div class="smt-row smt-row-plain">
							<span class="smt-label"><?php echo htmlspecialchars($key); ?></span>
							<span class="smt-value"><?php echo htmlspecialchars($value); ?></span>
						</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- SQL查询面板 -->
		<div id="panel-sql" class="smt-panel">
			<div class="smt-box smt-box-lg">
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
						// 耗时分档着色：>100ms 红、>50ms 黄、其余绿
						$timeClass = $timeValue > 100 ? 'smt-err-text' : ($timeValue > 50 ? 'smt-warn' : 'smt-ok');
					?>
					<div class="smt-row">
						<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:15px;width:100%;">
							<span class="smt-mono"><?php echo htmlspecialchars($sql['sql']); ?></span>
							<span class="<?php echo $timeClass; ?>" style="white-space:nowrap;flex-shrink:0;font-weight:600;font-variant-numeric:tabular-nums;"><?php echo $sql['time']; ?></span>
						</div>
						<?php if (!empty($sql['params'])): ?>
						<div class="smt-detail" style="margin-top:3px;word-break:break-all;">
							参数 <?php echo htmlspecialchars(json_encode($sql['params'], JSON_UNESCAPED_UNICODE)); ?>
						</div>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
					<div class="smt-stats">
						<span>共 <?php echo count($sqlLogs); ?> 条语句</span>
						<span class="smt-accent">耗时合计 <?php echo number_format($totalTime, 2); ?> ms</span>
					</div>
				<?php else: ?>
					<div class="smt-row smt-row-plain smt-empty">暂无 SQL 操作记录</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- 加载文件面板 -->
		<div id="panel-file" class="smt-panel">
			<div class="smt-box smt-box-lg">
				<?php 
				$files = get_included_files();
				foreach($files as $index => $file): ?>
				<div class="smt-row smt-row-plain">
					<span class="smt-idx"><?php echo ($index + 1) . '.'; ?></span>
					<span class="smt-mono" style="font-size:11.5px;"><?php echo $file; ?></span>
				</div>
				<?php endforeach; ?>
				<div class="smt-stats">
					<span>共 <?php echo count($files); ?> 个文件</span>
				</div>
			</div>
		</div>

		<!-- 配置信息面板 -->
		<div id="panel-config" class="smt-panel">
			<div class="smt-cols">
				<!-- 系统配置 -->
				<div class="smt-col">
					<div class="smt-block-title">系统配置</div>
					<div class="smt-box">
						<?php
						// 常见关键配置项
						$keyConfigs = [
							'运行环境' => defined('ENV') ? ENV : (defined('APP_ENV') ? APP_ENV : '未定义'),
							'调试模式' => defined('DEBUG') ? (DEBUG ? '开启' : '关闭') : (config('debug') ? '开启' : '关闭'),
							'时区设置' => date_default_timezone_get(),
							'最大执行时间' => ini_get('max_execution_time') . '秒',
							'内存限制' => ini_get('memory_limit'),
							'上传限制' => ini_get('upload_max_filesize'),
							'POST限制' => ini_get('post_max_size'),
							'字符集' => ini_get('default_charset') ?: (defined('CHARSET') ? CHARSET : 'UTF-8'),
							'错误报告级别' => ini_get('error_reporting'),
						];
						foreach($keyConfigs as $key => $value):
						?>
						<div class="smt-row smt-row-plain">
							<span class="smt-label"><?php echo $key; ?></span>
							<span class="smt-key"><?php echo $value; ?></span>
						</div>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- 缓存状态 -->
				<div class="smt-col">
					<div class="smt-block-title">缓存与运行状态</div>
					<div class="smt-box">
						<?php
						// 检查常见的缓存扩展
						$cacheExtensions = [
							'APC' => extension_loaded('apc') || extension_loaded('apcu'),
							'Memcached' => extension_loaded('memcached'),
							'Redis' => extension_loaded('redis'),
							'OPcache' => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
							'XCache' => extension_loaded('xcache'),
							'File Cache' => true, // 文件缓存总是可用
						];
						foreach($cacheExtensions as $cache => $available):
						?>
						<div class="smt-row smt-row-plain">
							<span class="smt-label"><?php echo $cache; ?></span>
							<span class="<?php echo $available ? 'smt-ok' : 'smt-err-text'; ?>"><?php echo $available ? '可用' : '不可用'; ?></span>
						</div>
						<?php endforeach; ?>

						<!-- 自动加载信息 -->
						<div class="smt-row smt-row-plain">
							<span class="smt-label">Autoload</span>
							<span class="smt-key">
								<?php 
								$autoload = spl_autoload_functions();
								echo is_array($autoload) ? count($autoload).' 个加载器' : '0 个加载器'; 
								?>
							</span>
						</div>

						<!-- 会话状态 -->
						<div class="smt-row smt-row-plain">
							<span class="smt-label">Session</span>
							<span class="smt-key">
								<?php 
								echo session_status() == PHP_SESSION_ACTIVE ? '已激活' : '未激活';
								?>
							</span>
						</div>
					</div>
				</div>
			</div>

			<!-- 框架配置 -->
			<div class="smt-block">
				<div class="smt-block-title">框架配置</div>
				<div class="smt-box">
					<?php
					// 获取可显示的配置信息
					$configs = [];
					if (function_exists('config')) {
						// 尝试获取常见框架配置
						$commonConfigs = [
							'app', 'database', 'cache', 'session', 'log', 'trace',
							'debug', 'url', 'default_controller', 'default_action',
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
					<div class="smt-row smt-row-plain">
						<span class="smt-label"><?php echo htmlspecialchars($key); ?></span>
						<span class="smt-value"><?php echo htmlspecialchars($value); ?></span>
					</div>
					<?php endforeach; ?>
					<?php else: ?>
					<div class="smt-row smt-row-plain smt-empty">无法获取框架配置信息</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- 错误信息面板 -->
		<div id="panel-error" class="smt-panel">
			<?php
			// 获取错误信息
			$hasErrors = false;
			?>

			<!-- 捕获的异常 -->
			<div class="smt-block">
				<div class="smt-block-title smt-err">异常信息</div>
				<div class="smt-box smt-box-sm">
					<?php 
					// 检查是否有捕获的异常
					$exceptions = isset(App::$trace['exceptions']) ? App::$trace['exceptions'] : [];
					
					if (!empty($exceptions)): 
						$hasErrors = true;
						foreach($exceptions as $index => $exception): 
					?>
					<div class="smt-row">
						<div class="smt-err-text smt-pad" style="font-weight:600;"><?php echo get_class($exception); ?></div>
						<div class="smt-pad"><?php echo $exception->getMessage(); ?></div>
						<div class="smt-detail">
							<?php echo $exception->getFile(); ?>:<?php echo $exception->getLine(); ?>
						</div>
						<div class="smt-detail smt-mono" style="margin-top:4px;">
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
					<div class="smt-row smt-row-plain smt-empty">未捕获到异常</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- 错误信息 -->
			<div class="smt-block">
				<div class="smt-block-title smt-err">错误信息</div>
				<div class="smt-box smt-box-sm">
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
							$errorClass = 'smt-err-text';
							
							switch($errorType) {
								case E_ERROR:
								case E_CORE_ERROR:
								case E_COMPILE_ERROR:
								case E_USER_ERROR:
									$errorTypeStr = '致命错误';
									$errorClass = 'smt-err-text';
									break;
								case E_WARNING:
								case E_CORE_WARNING:
								case E_COMPILE_WARNING:
								case E_USER_WARNING:
									$errorTypeStr = '警告';
									$errorClass = 'smt-warn';
									break;
								case E_NOTICE:
								case E_USER_NOTICE:
									$errorTypeStr = '提示';
									$errorClass = 'smt-key';
									break;
								case E_STRICT:
								case E_DEPRECATED:
								case E_USER_DEPRECATED:
									$errorTypeStr = '建议修复';
									$errorClass = 'smt-detail';
									break;
								default:
									$errorTypeStr = '未知错误';
							}
					?>
					<div class="smt-row">
						<div class="<?php echo $errorClass; ?> smt-pad" style="font-weight:600;"><?php echo $errorTypeStr; ?></div>
						<div class="smt-pad"><?php echo isset($error['message']) ? $error['message'] : ''; ?></div>
						<div class="smt-detail">
							<?php echo isset($error['file']) ? $error['file'] : '未知文件'; ?>:<?php echo isset($error['line']) ? $error['line'] : ''; ?>
						</div>
					</div>
					<?php endforeach; ?>
					<?php else: ?>
					<div class="smt-row smt-row-plain smt-empty">未发现错误</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if (!$hasErrors): ?>
			<div class="smt-allclear">
				<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
					<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
				</svg>
				<p>应用运行正常，未发现错误或异常</p>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- 触发按钮：左半是耗时，展开后右侧多出一个关闭按钮 -->
<div id="think_page_trace_btn">
	<div class="smt-trigger">
		<span class="smt-bolt">&#9889;</span>
		<?php echo isset(App::$trace['runtime']) ? App::$trace['runtime'] : '0.00ms'; ?>
	</div>
	<div class="smt-close" title="关闭">&times;</div>
</div>

<!-- 交互脚本 -->
<script type="text/javascript">
(function(){
	var root = document.getElementById('think_page_trace');
	var bar = document.getElementById('think_page_trace_btn');
	if (!root || !bar) { return; }

	var trigger = bar.querySelector('.smt-trigger');
	var closeBtn = bar.querySelector('.smt-close');
	var tabs = root.querySelectorAll('.smt-tab');
	var panels = root.querySelectorAll('.smt-panel');
	var isShow = false;

	// 关闭：先滑出再隐藏，动画结束后复位 transform 以便下次展开
	function hide() {
		root.style.transform = 'translateY(100%)';
		bar.classList.remove('smt-open');
		isShow = false;
		setTimeout(function(){
			if (!isShow) { root.style.display = 'none'; }
		}, 280);
	}

	// 展开：先恢复显示，再于下一帧上滑，保证过渡动画生效
	function show() {
		root.style.display = 'block';
		bar.classList.add('smt-open');
		isShow = true;
		requestAnimationFrame(function(){
			root.style.transform = 'translateY(0)';
		});
	}

	trigger.onclick = function(){ isShow ? hide() : show(); };
	closeBtn.onclick = function(){
		var openTab = root.querySelector('.smt-tab.active');
		if (openTab) { openTab.click(); } // 关闭即回到基础信息页
		hide();
	};

	// 标签切换
	tabs.forEach(function(tab){
		tab.onclick = function(){
			tabs.forEach(function(t){ t.classList.remove('active'); });
			this.classList.add('active');
			panels.forEach(function(p){ p.classList.remove('active'); });
			var panel = document.getElementById('panel-' + this.id.split('-')[1]);
			if (panel) { panel.classList.add('active'); }
		};
	});
})();
</script>
<?php endif; ?>
