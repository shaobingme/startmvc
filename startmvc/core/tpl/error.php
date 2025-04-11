<!DOCTYPE html>
<html>
<head>
    <title>系统错误</title>
    <meta charset="utf-8">
    <style>
        body { 
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f5f5f5;
        }
        .error-container {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        h1 { 
            color: #e74c3c;
            margin-top: 0;
        }
        .error-message {
            color: #666;
            line-height: 1.6;
        }
        .error-trace {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>系统错误</h1>
        <div class="error-message">
            <?php if (config('debug', true)): ?>
                <p><strong>错误信息：</strong><?php echo htmlspecialchars($e->getMessage()); ?></p>
                <p><strong>文件位置：</strong><?php echo htmlspecialchars($e->getFile()); ?> 行号：<?php echo $e->getLine(); ?></p>
                <div class="error-trace">
                    <strong>堆栈跟踪：</strong><br>
                    <?php echo nl2br(htmlspecialchars($e->getTraceAsString())); ?>
                </div>
            <?php else: ?>
                <p>抱歉，系统遇到了一些问题。请稍后再试。</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>