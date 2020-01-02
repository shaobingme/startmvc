<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?=$title?></title>
    <style>
        .container {
            width: 60%;
            margin: 10% auto 0;
            background-color: #f0f0f0;
            padding: 2% 5%;
            border-radius: 10px
        }

        ul {
            padding-left: 20px;
        }

            ul li {
                line-height: 2.3
            }

        a {
            color: #20a53a
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $content;?></h1>
        <h3><?=$title?></h3>
        <ul>
            <li>更多功能了解，请查看<a href="http://startmvc.com" target="_blank">Startmvc</a></li>
        </ul>
    </div>
</body>
</html>