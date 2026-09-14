<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$admin}</title>
<style>
    :root {
        --bg: #fbfcfd;
        --fg: #1c2024;
        --muted: #6b7280;
        --line: #e6e9ee;
        --card: #ffffff;
        --accent: #12a150;
        --accent-soft: #e9f8ef;
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --bg: #0f1115;
            --fg: #e6e8eb;
            --muted: #9aa2ad;
            --line: #232833;
            --card: #161a21;
            --accent: #3ddc84;
            --accent-soft: #17281f;
        }
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: var(--bg);
        color: var(--fg);
        font: 15px/1.7 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
        -webkit-font-smoothing: antialiased;
    }
    .panel {
        width: 100%;
        max-width: 420px;
        padding: 32px 28px;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 12px;
        text-align: center;
    }
    .mark {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: .04em;
        color: var(--accent);
        background: var(--accent-soft);
        border-radius: 999px;
        padding: 4px 12px;
    }
    .mark span { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }
    h1 { margin: 18px 0 6px; font-size: 22px; font-weight: 650; letter-spacing: -.01em; }
    p { margin: 0; color: var(--muted); font-size: 13px; }
</style>
</head>
<body>
<div class="panel">
    <div class="mark"><span></span>StartMVC Admin</div>
    <h1>{$admin}</h1>
    <p>后台模块已就绪，请在 app/admin 下创建您的控制器与视图。</p>
</div>
</body>
</html>
