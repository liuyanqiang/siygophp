<?php
header ( 'content-type:text/html;charset=utf-8' );
date_default_timezone_set ( "PRC" );
//定义应用名称
define('APP_NAME','application');
// 站点根目录常量
define('APP_PATH', dirname(dirname(__FILE__)). DIRECTORY_SEPARATOR. APP_NAME. DIRECTORY_SEPARATOR);
// 框架目录，如果修改了目录请修改这里。指向框架目录即可
require '../framework/SP.php';
$config=APP_PATH.'./config/main.php';
SP::createWebApp($config);