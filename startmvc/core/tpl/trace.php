<!-- startmvc/core/tpl/trace.php -->
<?php
use startmvc\core\App;  // 添加命名空间引用
use startmvc\core\db\DbCore;  // 使用DbCore

if(config('trace')): ?>
<div id="think_page_trace" style="display:none;position:fixed;bottom:0;right:0;font-size:14px;width:100%;z-index:999999;color:#333;text-align:left;background:#f8f9fa;border-top:1px solid #e9ecef;box-shadow:0 -2px 4px rgba(0,0,0,.05);">
    <div style="padding:12px;">  <!-- 减小整体内边距 -->
        <div style="margin-bottom:12px;">  <!-- 减小板块间距 -->
            <h4 style="margin:0 0 8px 0;color:#1a73e8;font-size:16px;border-bottom:1px solid #e9ecef;padding-bottom:6px;">基础信息</h4>  <!-- 减小标题下边距 -->
            <div style="display:flex;flex-wrap:wrap;gap:12px;">  <!-- 减小卡片间距 -->
                <div style="background:#fff;padding:8px 15px;border-radius:4px;border:1px solid #e9ecef;">
                    <span style="color:#666;">请求方法：</span>
                    <span style="color:#1a73e8;font-weight:bold;"><?php echo $_SERVER['REQUEST_METHOD']; ?></span>
                </div>
                <div style="background:#fff;padding:8px 15px;border-radius:4px;border:1px solid #e9ecef;">
                    <span style="color:#666;">请求URI：</span>
                    <span style="color:#1a73e8;font-weight:bold;"><?php echo $_SERVER['REQUEST_URI']; ?></span>
                </div>
                <div style="background:#fff;padding:8px 15px;border-radius:4px;border:1px solid #e9ecef;">
                    <span style="color:#666;">运行时间：</span>
                    <span style="color:#28a745;font-weight:bold;"><?php echo isset(App::$trace['runtime']) ? App::$trace['runtime'] : '未记录'; ?></span>
                </div>
                <div style="background:#fff;padding:8px 15px;border-radius:4px;border:1px solid #e9ecef;">
                    <span style="color:#666;">内存使用：</span>
                    <span style="color:#28a745;font-weight:bold;"><?php echo isset(App::$trace['memory']) ? App::$trace['memory'] : '未记录'; ?></span>
                </div>
            </div>
        </div>
        
        <div style="margin-bottom:12px;">
            <h4 style="margin:0 0 8px 0;color:#1a73e8;font-size:16px;border-bottom:1px solid #e9ecef;padding-bottom:6px;">SQL操作记录</h4>
            <div style="background:#fff;border-radius:4px;border:1px solid #e9ecef;max-height:250px;overflow-y:auto;">
                <?php 
                $sqlLogs = DbCore::getSqlLogs();
                if (!empty($sqlLogs)): 
                    $totalTime = 0;
                    foreach($sqlLogs as $index => $sql): 
                        // 提取执行时间数值部分（去掉ms后缀）
                        $timeValue = floatval(str_replace('ms', '', $sql['time']));
                        $totalTime += $timeValue;
                        // 设置时间显示的颜色，超过100ms为红色，超过50ms为黄色，低于50ms为绿色
                        $timeColor = $timeValue > 100 ? '#dc3545' : ($timeValue > 50 ? '#ffc107' : '#28a745');
                    ?>
                    <div style="padding:6px 12px;<?php echo $index % 2 ? 'background:#f8f9fa;' : ''; ?> font-size:13px;line-height:1.5;border-bottom:1px solid #f0f0f0;">
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
                    <div style="padding:8px 12px;background:#f8f9fa;font-size:13px;border-top:1px solid #e9ecef;font-weight:bold;">
                        <span>总计：<?php echo count($sqlLogs); ?> 条SQL语句</span>
                        <span style="float:right;color:#1a73e8;">总耗时：<?php echo number_format($totalTime, 2); ?> ms</span>
                    </div>
                <?php else: ?>
                    <div style="padding:6px 12px;color:#666;font-style:italic;">暂无SQL操作记录</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div>
            <h4 style="margin:0 0 8px 0;color:#1a73e8;font-size:16px;border-bottom:1px solid #e9ecef;padding-bottom:6px;">加载的文件</h4>
            <div style="background:#fff;border-radius:4px;border:1px solid #e9ecef;max-height:150px;overflow-y:auto;">  <!-- 减小最大高度 -->
                <?php 
                $files = get_included_files();
                foreach($files as $index => $file): ?>
                <div style="padding:4px 12px;<?php echo $index % 2 ? 'background:#f8f9fa;' : ''; ?> font-size:13px;line-height:1.4;">  <!-- 减小文件列表行高和字体 -->
                    <span style="color:#666;display:inline-block;width:25px;"><?php echo ($index + 1) . '.'; ?></span>
                    <span style="color:#333;word-break:break-all;"><?php echo $file; ?></span>
                </div>
                <?php endforeach; ?>
                <div style="padding:8px 12px;background:#f8f9fa;font-size:13px;border-top:1px solid #e9ecef;font-weight:bold;">
                    总计：<?php echo count($files); ?> 个文件
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 添加一个触发按钮 -->
<div id="think_page_trace_btn" style="position:fixed;bottom:0;right:0;z-index:999999;">
    <div style="background:#1a73e8;color:#FFF;padding:6px 12px;cursor:pointer;font-size:14px;border-top-left-radius:4px;box-shadow:0 -2px 4px rgba(0,0,0,.1);">
        <span style="margin-right:5px;">⚡</span>
        <?php echo isset(App::$trace['runtime']) ? App::$trace['runtime'] : '0.00ms'; ?>
    </div>
</div>

<!-- 添加交互脚本 -->
<script type="text/javascript">
(function(){
    var btn = document.getElementById('think_page_trace_btn');
    var trace = document.getElementById('think_page_trace');
    var isShow = false;
    
    // 添加过渡效果
    trace.style.transition = 'all 0.3s ease';
    
    btn.onclick = function(){
        if(isShow){
            trace.style.transform = 'translateY(100%)';
            setTimeout(function() {
                trace.style.display = 'none';
            }, 300);
            isShow = false;
        } else {
            trace.style.display = 'block';
            setTimeout(function() {
                trace.style.transform = 'translateY(0)';
            }, 10);
            isShow = true;
        }
    };
})();
</script>
<?php endif; ?>