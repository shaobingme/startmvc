<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
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
        padding: 12vh 24px 96px;
        background: var(--bg);
        color: var(--fg);
        font: 15px/1.7 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
        -webkit-font-smoothing: antialiased;
    }
    .wrap { max-width: 660px; margin: 0 auto; }
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
    h1 {
        margin: 20px 0 8px;
        font-size: 30px;
        font-weight: 650;
        letter-spacing: -.02em;
    }
    .sub { margin: 0 0 36px; color: var(--muted); }
    .links { display: grid; gap: 10px; }
    .links a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 18px;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 10px;
        color: inherit;
        text-decoration: none;
        transition: border-color .18s ease, transform .18s ease;
    }
    .links a:hover { border-color: var(--accent); transform: translateX(2px); }
    .links b { font-weight: 600; }
    .links em { display: block; font-style: normal; font-size: 13px; color: var(--muted); }
    .links i { color: var(--accent); font-style: normal; flex-shrink: 0; }
    footer {
        margin-top: 40px;
        padding-top: 18px;
        border-top: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
        display: flex;
        flex-wrap: wrap;
        gap: 6px 18px;
    }
    footer code { font-family: ui-monospace, Menlo, Consolas, monospace; color: var(--fg); }
</style>
</head>
<body>
<div class="wrap">
    <div class="mark"><span></span>StartMVC</div>
    <h1>{$content}</h1>
    <p class="sub">{$title}</p>

    <nav class="links">
        <a href="https://startmvc.com/doc/detail/1.html" target="_blank" rel="noopener">
            <span><b>官方文档</b><em>路由、模型、模板与中间件手册</em></span><i>&rarr;</i>
        </a>
        <a href="http://startmvc.com" target="_blank" rel="noopener">
            <span><b>官网与版本</b><em>查看最新版本与更新日志</em></span><i>&rarr;</i>
        </a>
        <a href="https://startmvc.com/topic" target="_blank" rel="noopener">
            <span><b>源码与反馈</b><em>提交问题或查看源码仓库</em></span><i>&rarr;</i>
        </a>
    </nav>

    <footer>
        <!-- {if SM_VERSION} -->
        <span>版本 <code>v<?=SM_VERSION?></code> (<?=SM_UPDATE?>)</span>
        <!-- {/if} -->
        <span>{lang('startmvc')}</span>
        <span>PHP <?=PHP_VERSION?></span>
    </footer>
</div>
</body>
</html>
