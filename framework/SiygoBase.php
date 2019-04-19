<?php
/**
 * 定义框架目录
 */
defined ( 'SP_PATH' ) or define ( 'SP_PATH', dirname ( __FILE__ ) );
// 模板目录
defined ( 'SP_TP' ) or define ( 'SP_TP', APP_PATH . 'views' . DIRECTORY_SEPARATOR );
/**
 * SiygoBase
 * 核心基础类
 * 
 * @author liuyanqiang
 */
class SiygoBase {
	public static $_aliases;
	private static $_app;
	public static function getVersion() {
		return '1.0.0';
	}
	public static function createWebApp($config = null) {
		return self::createApp ( 'CApplication', $config );
	}
	public static function createApp($class, $config = null) {
		return new $class ( $config );
	}
	public static function app() {
		return self::$_app;
	}
	public static function setApplication($app) {
		if (self::$_app === null || $app === null)
			self::$_app = $app;
		else
			return false;
	}
	
	/**
	 *
	 * @return string the path of the framework
	 */
	public static function getFrameworkPath() {
		return SP_PATH;
	}
	
	/**
	 * 自动加载类
	 *
	 * @return bool;
	 */
	public static function autoload($className) {
		// use include so that the error PHP file may appear
		if (isset ( self::$_coreClasses [$className] ))
			include (SP_PATH . self::$_coreClasses [$className]);
		elseif (is_array ( self::$_aliases )) {
			foreach ( self::$_aliases as $name ) {
				if (file_exists ( APP_PATH . $name . DIRECTORY_SEPARATOR . $className . '.php' ))
					include APP_PATH . $name . DIRECTORY_SEPARATOR . $className . '.php';
			}
		}
		return true;
	}
	
	/**
	 * 核心目录
	 */
	private static $_coreClasses = array (
			'CController' => '/base/CController.php',
			'CModel' => '/base/CModel.php',
			'CApplication' => '/base/CApplication.php',
			'ActiveRecord' => '/db/ActiveRecord.php',
			'CDbConnection' => '/db/CDbConnection.php' 
	);
}

spl_autoload_register ( array (
		'SiygoBase',
		'autoload' 
) );
