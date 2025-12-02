<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>页面跳转...</title>
	<style>
		* {
			margin: 0;
			padding: 0;
		}

		body {
			width:1000px;
			margin:250px auto;
			background-color: #fff;
			color: #333;
			font-family: 'Microsoft YaHei';
			text-align: center;
		}


		a {
			color: #333;
		}

		.tips {
			font-size: 40px;
		}

		.tips span {
			font-size: 80px;
		}
		
		.link {
			line-height: 40px;
		}
	</style>
</head>

<body>

		<div class="tips">
			<span>
				<?php if($code == 0){ ?>
				:(
				<?php }else{ ?>
				:)
				<?php } ?>
			</span>
			<?php echo $msg; ?>
		</div>
		<div class="link">
	        <a href="<?php if($url == ''){ ?>javascript:window.history.go(-1);<?php }else{ echo $url;} ?>">
				<span class="sec"></span> 秒后返回上页，点击链接直接跳转...</a>或者，你可以
			<a href="/">返回首页</a>
		</div>


</body>

</html>
<script>
	(function() {
		var sec = 3;
		var timerElement = document.querySelector('.sec');
		
		function jump() {
			<?php if ($url == '') { ?>
				<?php if($code == 1){ ?>
				// 成功：返回上一页并刷新（通常用于表单提交后）
				window.location.href = document.referrer;
				<?php } else { ?>
				// 失败：返回上一页（保留表单数据）
				window.history.go(-1);
				<?php } ?>
			<?php } else { ?>
				// 指定跳转地址
				window.location.href = '<?php echo $url; ?>';
			<?php } ?>
		}

		function time() {
			timerElement.innerHTML = sec;
			if (sec <= 0) {
				jump();
				return;
			}
			sec--;
			setTimeout(time, 1000);
		}
		
		time();
	})();
</script>