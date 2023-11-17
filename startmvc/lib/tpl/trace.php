<style type="text/css">
.trace{width:100%;position:fixed;z-index:999;left:0;bottom:0;background-color:#fff;border-top:2px solid #ddd;display:none}
.trace-item{width:31%;float:left;overflow:hidden;padding:10px 1%}
.trace-item>.title{line-height:50px;font-size:15px;font-weight:bold;color:#666666;border-bottom:1px solid #E9E9E9}
.trace-item>.text{line-height:2.2em;font-size:13px;padding:20px 0;height:158px;overflow-y:auto;margin:8px 0}
.trace-item>.text .sql{border-bottom:1px dashed #E9E9E9;padding-bottom:10px;margin-bottom:10px}
.trace-item>.text .sql span{color:#888888;font-size:16px}
.trace-item>.text .sql i{color:#FF0036}
.trace-item>.text .sql a{font-size:13px;color:#3688FF}
.trace-item>.text .sql b{font-weight:700;font-size:13px}
.green{color:green}
.red{color:red}
.trace-small{padding:8px;background-color:#f5f5f5;border:2px solid #4B476D;border-right:none;position:fixed;right:0;bottom:300px;border-bottom-left-radius:30px;border-top-left-radius:30px;box-shadow:1px 1px 18px #ddd;cursor:pointer;overflow:hidden}
.trace-small img{float:left;width:38px;border-radius:38px}
.trace-small-msg{margin:0 8px;float:left;overflow:hidden;font-size:13px;line-height:20px;color:#333}
</style>

<?php
/**
 * 时间、内存开销计算
 * @return array(耗时[毫秒], 消耗内存[K])
 */
function cost()
{
    return array(
        round((microtime(true) - START_TIME) * 1000, 2),
        round((memory_get_usage() - START_MEMORY) / 1024, 2)
    );
}
$cost = cost();
?>
<div class="trace" id="trace">
    <div class="trace-item">
        <div class="title">运行信息</div>
        <div class="text">
            远程时间 : <?php echo date('Y-m-d H:i:s'); ?><br />
            运行耗时 : <?php echo $cost[0]; ?> 毫秒<br />
            内存消耗 : <?php echo $cost[1]; ?> k<br />
        </div>
    </div>
    <?php $includedFiles = get_included_files(); ?>
    <div class="trace-item">
        <div class="title">加载文件 [ <?= count($includedFiles); ?> ]</div>
        <div class="text">
            <?php
            foreach ($includedFiles as $k => $file) {
                echo ($k + 1) . '. ' . $file . '<br>';
            } ?>
        </div>
    </div>
    <div class="trace-item">
        <div class="title">SQL 运行日志</div>
        <div class="text">
            <?php foreach ($GLOBALS['traceSql'] as $k => $sql): ?>
                <div class="sql">
                    <p>记录 <?php echo $k + 1; ?></p>
                    结果：<?php if ($sql[0] == 'success') {
                                echo '<b class="green">' . $sql[0] . '</b>';
                            } else {
                                echo '<b class="red">' . $sql[0] . '</b>';
                            } ?> &nbsp;&nbsp;耗时：<?php echo $sql[2]; ?> 毫秒 <br />
                    语句：<?php echo $sql[1]; ?><br />
                    <?php if (!empty($sql[3])) {
                        echo '错误：<i>' . $sql[3] . '</i>&nbsp;&nbsp;&nbsp;[ <a href="https://fanyi.baidu.com/#en/zh/' . urlencode($sql[3]) . '" target="_blank">翻译一下</a> ]';
                    } ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
<div class="trace-small" onclick="showTrace()">
    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAMAAABHPGVmAAAAA3NCSVQICAjb4U/gAAAAk1BMVEVLR22PjKT///9VUXXFxNDg3+ZzcI6lo7atq71kYYLT0tvw8POXlau3tcWAfZhraIfOzdfV1N2dm69dWXv39vi9u8mqqLrm5eqSj6Z4dZLe1t5YVHdpZYWysMFua4qcnLXJx9N9epaZlqyGhJ26uMfx8fSTkadfW31ZVXjo6O21tb2hn7O/vctwbYvm3ube3t6Cf5qkpAY9AAAACXBIWXMAAAsSAAALEgHS3X78AAAAH3RFWHRTb2Z0d2FyZQBNYWNyb21lZGlhIEZpcmV3b3JrcyA4tWjSeAAAABZ0RVh0Q3JlYXRpb24gVGltZQAwNy8xOC8yM4iR64kAAAJESURBVGiB7Zhtc4IwDMehwkAURUUUnw6fpt7c3b7/p1vTQoWiHq315rb8X0wWQ36FpLWpZaFQKBQKhUL9Gc3tZTYhZJItt60nIeyMlOTYz2B8EEln84yTzCBkb5oxy+NmoeeFWSd/ZWYZCQsaiTxsObRrktFmIf2yacxMbYMQGwIGVdvO9KM4NF5HGvVhT42ZQQgMeikbz2A1x2ApSWVrajYpawi3kK0LsPYeCuxRFXG7EG4ue8zLmV+AvypjVE7DjQjlcSzB/6BKgdqJmrtH1D1WZbCqJeOm3h5RG1P5NuKvmvj22YiIck4sa1Jfdu9roM7g00BFtRpvIluNobmMzcPmCGekx6Bqbz32wx7cFHyb+bbWq7qIVVlt3SrEEqdRVf8GklI9GyJFfX2INN3vQ675IcQ4hP20cjVIvJAao0T75SWMkJeHTG9F7ZqA5DuqS48lQdjulOwe2tt7MZ/J2S1I3trHDzzMplguYrGVrkIOceGh3deVNl6b65DNxUPzgOLIzwfe4Y9oSquQQDiQ01ELMmQ3t/g5wfAaZAr/zHg3JDzU5MKttF3jI03rkAV/Ustawaerw+jBnQlcBfkjSRDWjPGmhB1YfGlA2Dzrw9UbT+26CsmTzhqYvu778iGb/JJnh3ifF0ifN0nFWgA1EmpAoAss+honr9NgyCHifC0fhTUgOt0cTSvdKorNevVETUzBwVVnbb25pKa9XtXe0bHWEYWPD72uVQUTNuqLdTDJOHLj2I12ybMQKBQKhUKhUD+gb0m5I9wiSEcWAAAAAElFTkSuQmCC" alt="">
    <div class="trace-small-msg">
        耗时 : <?php echo $cost[0]; ?> 毫秒<br />
        内存 : <?php echo $cost[1]; ?> k
    </div>
</div>
<script type="text/javascript">
    var graceTraceStatus = false;

    function showTrace() {
        var graceTrace = document.getElementById('trace');
        graceTraceStatus = !graceTraceStatus;
        if (graceTraceStatus) {
            graceTrace.style.display = 'block';
        } else {
            graceTrace.style.display = 'none';
        }
    }
</script>