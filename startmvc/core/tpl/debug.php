<style>
    .exception{margin:150px auto 0 auto;padding:2rem 2rem 1rem 2rem;width:800px;background-color:#fff;border-top:5px solid #669933;word-break:break-word;box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15)}
    .exception h1{padding:0;margin:0 0 4px 0;font-size:1.5rem;font-weight:normal;color:#666}
    .e-text{margin-bottom:1.5rem;font-size:1.2rem;line-height:1.25;font-weight:500;color:#332F51}
    .e-list{padding:1.5rem 0 0 0;border-top:1px solid #ddd;line-height:2}
    .e-list dt{float:left;margin-right:1rem;color:#666}
</style>

<div class="exception">
    <h1>DEBUG</h1>
    <p class="e-text"><?= $exception->getMessage(); ?></p>
    <dl class="e-list">
        <dt>相关文件</dt>
        <dd><?= trim(realpath($exception->getFile()))?></dd>

        <dt>错误位置</dt>
        <dd><?= $exception->getLine() ?> 行</dd>
    </dl>
	<div><a href="https://www.startmvc.com" title="StartMVC框架" target="_blank" rel="noopenner noreferrer">StartMVC框架</a></div>
</div>