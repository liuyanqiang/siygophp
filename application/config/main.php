<?php
return Array (
		'app_name' => 'siygo应用',
		'importPath' => array (
				'controllers',
				'models',
				'extensions',
				'components' 
		),
		'defaultController' => 'index',
		'defaultAction' => 'index',
		'components' => array (
				'db' => array (
						'class' => 'CDbConnection',
						'masterConfig' => array (
								"connectionString" => "mysql:host=127.0.0.1;dbname=zg_project;port=3306",
								"username" => "root",
								"password" => "root" 
						),
						'slaveConfig' => array (
								"connectionString" => "mysql:host=211.151.57.99;dbname=zg_project;port=3306|mysql:host=211.151.57.99;dbname=zg_project;port=3306",
								"username" => "test",
								"password" => "ceshi"
						),
						'charset' => 'utf8',
						'tablePrefix' => 'zg_' 
				) 
		),
       'urlExt'=>'.html'
);