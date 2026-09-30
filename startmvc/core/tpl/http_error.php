<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?php echo (int)$exception->getStatusCode(); ?> - <?php echo htmlspecialchars($exception->getMessage()); ?></title>
    <style>
        body { font-family: "Microsoft YaHei", sans-serif; text-align: center; padding-top: 80px; color: #333; }
        h1 { font-size: 72px; margin: 0; color: #e74c3c; }
        p { color: #888; }
        a { color: #2563eb; }
    </style>
</head>
<body>
    <h1><?php echo (int)$exception->getStatusCode(); ?></h1>
    <p><?php echo htmlspecialchars($exception->getMessage()); ?></p>
    <p><a href="/">返回首页</a></p>
</body>
</html>
