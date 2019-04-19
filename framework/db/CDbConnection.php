<?php
/**
 * mysql数据库读写分离类
 * 支持一主多从、预处理语句、事务
 * @author liuyanqiang
 * @version 2015-11-14
 * ================================
 * 配置文件：
 *	array(
 *		......
 *		'components'=>array(
 *			......
 *			'db'=>array(
 *				'class'=>'CDbConnection',
 *				'masterConfig'=> array(
 *        			'connectionString' => 'mysql:host=127.0.0.1;dbname=testdb;port=3306', //Master数据库DSN
 *        			"username"         => 用户名,
 *        			"password"         => 密码,
 *    			);
 * 				'slaveConfig' = array(
 *        			'connectionString' => 'mysql:host=127.0.0.2;dbname=testdb;port=3306|
 *								 mysql:host=127.0.0.3;dbname=testdb;port=3306|', //Slave1数据库DSN|Slave2数据库DSN|...
 *        			"username"         => 用户名,
 *        			"password"         => 密码,
 *    			);
 *				'charset'=>'utf8',
 *				'tablePrefix'=>'web_', 
 *			),
 *		),
 *	)
*/
class CDbConnection {
	/**
	 * 主数据库配置信息
	 */
	public $masterConfig = array ();
	/**
	 * 从数据库配置信息
	 */
	public $slaveConfig = array ();
	/**
	 * 初始化的时候是否要连接到数据库
	 */
	public $autoConnect = false;
	/**
	 * 是否查询出错的时候终止脚本执行
	 */
	public $isExit = false;
	/**
	 * 字符编码
	 */
	public $charset = 'utf8';
	/**
	 * 表前缀
	 */
	public $tablePrefix = '';
	/**
	 * 是否显示日志
	 */
	public $enableProfiling = false;
	/**
	 * Master数据库对应的CDbConnection对象
	 */
	private $_masterConnection = null;
	/**
	 * Slave数据库对应的CDbConnection对象
	 */
	private $_slaveConnections = array ();
	private $_filer_keyword = array (
			'sleep',
			'char' 
	);
	public $pdoClass = 'PDO';
	/**
	 * 对SQL语句应该在数据库建立连接执行数组列表。
	 */
	private $initSQLs;
	/**
	 * pdo模拟prepare
	 */
	private $emulatePrepare;
	private $_active = false;
	private $_slaveactive = false;
	/**
	 * pdo 设置额外参数
	 * 
	 * @var unknown
	 */
	private $_attributes = array ();
	/**
	 * 初始化函数
	 */
	public function init() {
		// 自动初始化连接（一般不推荐）
		if ($this->autoConnect) {
			$this->getMasterConnection ();
			$this->getSlaveConnection ();
		}
		register_shutdown_function ( array (
				$this,
				'close' 
		) );
	}
	
	/**
	 * 构造函数
	 * 
	 * @return void
	 */
	public function __construct($masterConfig = array(), $slaveConfig = array()) {
		$this->masterConfig = empty ( $masterConfig ) ? $this->masterConfig : $masterConfig;
		$slaveConfig = empty ( $slaveConfig ) ? $masterConfig : $slaveConfig;
		if ($slaveConfig)
			$this->slaveConfig = $this->slaveConfig;
		register_shutdown_function ( array (
				$this,
				'close' 
		) );
	}
	protected function open($cdn = '', $username = '', $password = '') {
		try {
			$_pdo = $this->createPdoInstance ( $cdn, $username, $password );
			if (! $_pdo)
				return false;
			$_pdo = $this->initConnection ( $_pdo );
		} catch ( Exception $e ) {
			return false;
		}
		
		return $_pdo;
	}
	/**
	 * Creates the PDO instance.
	 * When some functionalities are missing in the pdo driver, we may use
	 * an adapter class to provides them.
	 *
	 * @return PDO the pdo instance
	 */
	protected function createPdoInstance($cdn, $username, $password) {
		$pdoClass = $this->pdoClass;
		if (($pos = strpos ( $cdn, ':' )) !== false) {
			$driver = strtolower ( substr ( $cdn, 0, $pos ) );
			if ($driver === 'mssql' || $driver === 'dblib')
				$pdoClass = 'CMssqlPdoAdapter';
			elseif ($driver === 'sqlsrv')
				$pdoClass = 'CMssqlSqlsrvPdoAdapter';
		}
		try {
			$objpdo = @new $pdoClass ( $cdn, $username, $password, $this->_attributes );
		} catch ( Exception $e ) {
			return false;
		}
		return $objpdo;
	}
	
	/**
	 * Initializes the open db connection.
	 * This method is invoked right after the db connection is established.
	 * The default implementation is to set the charset for MySQL and PostgreSQL database connections.
	 *
	 * @param PDO $pdo
	 *        	the PDO instance
	 */
	protected function initConnection($pdo) {
		$pdo->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		if ($this->emulatePrepare !== null && constant ( 'PDO::ATTR_EMULATE_PREPARES' ))
			$pdo->setAttribute ( PDO::ATTR_EMULATE_PREPARES, $this->emulatePrepare );
		if ($this->charset !== null) {
			$driver = strtolower ( $pdo->getAttribute ( PDO::ATTR_DRIVER_NAME ) );
			if (in_array ( $driver, array (
					'pgsql',
					'mysql',
					'mysqli' 
			) ))
				$pdo->exec ( 'SET NAMES ' . $pdo->quote ( $this->charset ) );
		}
		
		if ($this->initSQLs !== null) {
			foreach ( $this->initSQLs as $sql )
				$pdo->exec ( $sql );
		}
		return $pdo;
	}
	
	/**
	 * 关闭数据库连接
	 * 
	 * @param CDbConnection $connection        	
	 * @param boolean $closeAll        	
	 * @return boolean
	 */
	public function close($connection = null, $closeAll = true) {
		// 关闭指定数据库连接
		if ($connection) {
			$this->_active = false;
			$this->_slaveactive = false;
			$connection = null;
		}
		// 关闭所有数据库连接
		if (! $connection && $closeAll) {
			if ($this->_masterConnection) {
				$this->_active = false;
				$this->_masterConnection = null;
			}
			if (is_array ( $this->_slaveConnections ) && ! empty ( $this->_slaveConnections )) {
				$this->_slaveactive = false;
				$this->_slaveConnections = array ();
			}
		}
		return true;
	}
	
	/**
	 * 获取Master主库连接对象
	 * 
	 * @return CDbConnection
	 */
	public function getMasterConnection() {
		// 判断是否已经连接
		if ($this->_masterConnection) {
			if (! $this->_active) {
				$this->_active = true;
			}
			return $this->_masterConnection;
		}
		// 没有连接则自行处理
		try {
			$this->_masterConnection = $this->open ( $this->masterConfig ['connectionString'], $this->masterConfig ['username'], $this->masterConfig ['password'] );
			$this->_active = true;
		} catch ( Expressions $e ) {
			return false;
		}
		return $this->_masterConnection;
	}
	
	/**
	 * 获取Slave从库连接
	 * 
	 * @return CDbConnection
	 */
	public function getSlaveConnection() {
		if (empty ( $this->slaveConfig ['connectionString'] )) {
			return $this->getMasterConnection ();
		}
		
		// 如果有可用的Slave连接，随机挑选一台Slave
		if (! empty ( $this->_slaveConnections )) {
			$key = array_rand ( $this->_slaveConnections );
			if (! $this->_slaveConnections [$key]) {
				try {
					$this->_slaveactive = true;
				} catch ( Expressions $e ) {
					unset ( $this->_slaveConnections [$key] );
					return $this->getSlaveConnection ();
				}
			}
			return $this->_slaveConnections [$key];
		}
		
		// 连接到所有Slave数据库，如果没有可用的Slave机则调用Master
		$arrDSN = explode ( "|", $this->slaveConfig ['connectionString'] );
		if (! is_array ( $arrDSN ) || empty ( $arrDSN )) {
			return $this->getMasterConnection ();
		}
		foreach ( $arrDSN as $tmpDSN ) {
			try {
				$connection = $this->open ( $tmpDSN, $this->slaveConfig ['username'], $this->slaveConfig ['password'] );
				if (! $connection)
					continue;
				$this->_slaveactive = true;
			} catch ( Expressions $e ) {
				continue;
			}
			$this->_slaveConnections [] = $connection;
		}
		// 如果没有一台可用的Slave则调用Master
		if (empty ( $this->_slaveConnections )) {
			return $this->getMasterConnection ();
		}
		// 随机在已连接的Slave机中选择一台
		$key = array_rand ( $this->_slaveConnections );
		if ($this->_slaveConnections [$key]) {
			return $this->_slaveConnections [$key];
		}
		// 如果选择的slave机器是无效的，并且可用的slave机器大于一台则循环遍历所有能用的slave机器
		unset ( $this->_slaveConnections [$key] );
		if (count ( $this->_slaveConnections ) > 1) {
			foreach ( $this->_slaveConnections as $connection ) {
				if ($connection) {
					return $connection;
				}
			}
		}
		// 如果没有可用的Slave连接，则继续使用Master连接
		return $this->getMasterConnection ();
	}
	
	/**
	 * 根据sql获取数据库连接
	 * 
	 * @param string $sql        	
	 * @return CDbConnection
	 */
	public function getConnection($sql, $isMaster = false) {
		if (! $this->exitsleep ( $sql )) {
			return false;
		}
		if ($isMaster) {
			return $this->getMasterConnection ();
		}
		$temp = explode ( " ", ltrim ( $sql ) );
		$optType = trim ( strtolower ( array_shift ( $temp ) ) );
		unset ( $temp );
		
		if ($optType != "select") {
			return $this->getMasterConnection ();
		} else {
			return $this->getSlaveConnection ();
		}
	}
	/**
	 *
	 *
	 * 过滤特殊慢日志字符
	 * 
	 * @param unknown $sql        	
	 * @return boolean
	 */
	private function exitsleep($sql) {
		$hassleep1 = strpos ( $sql, 'sleep' );
		$hassleep2 = strpos ( $sql, 'SLEEP' );
		$hassleep3 = strpos ( $sql, 'CHAR' );
		$hassleep4 = strpos ( $sql, 'char' );
		if ($hassleep1 === false && $hassleep2 === false) {
			return true;
		} else {
			return false;
		}
	}
	/**
	 * 执行sql查询语句并返回所有行（二维数组）
	 * 
	 * @param string $sql        	
	 * @param blooean $isMaster        	
	 * @return array
	 */
	public function queryAll($sql, $isMaster = false) {
		return $this->setArDb ( $this->getConnection ( $sql, $isMaster ) )->toArray ()->queryAll ( $sql );
	}
	/**
	 * 默认插入 使用主库
	 * 
	 * @param unknown $sql        	
	 * @param string $isMaster        	
	 * @return boolean
	 */
	public function execute($sql, $isMaster = true) {
		return $this->setArDb ( $this->getConnection ( $sql, $isMaster ) )->execute ( $sql );
	}
	/**
	 * 默认查询从库
	 * 
	 * @param unknown $sql        	
	 * @param string $isMaster        	
	 * @return Ambigous <boolean, ActiveRecord, mixed>
	 */
	public function query($sql, $isMaster = false) {
		return $this->setArDb ( $this->getConnection ( $sql, $isMaster ) )->toArray ()->query ( $sql );
	}
	
	/**
	 *
	 * @example $transaction=Siygo::app()->db->beginTransaction();
	 *          try
	 *          {
	 *          Siygo::app()->db->execute();
	 *          Siygo::app()->db->->execute();
	 *          //.... other SQL executions
	 *          Siygo::app()->db->commit();
	 *          }
	 *          catch(Exception $e)
	 *          {
	 *          Siygo::app()->db->rollBack();
	 *          }
	 *          开启事物
	 * @return $_pdo 对象
	 */
	public function beginTransaction() {
		$_pdo = $this->getMasterConnection ();
		$_pdo->beginTransaction ();
		return $_pdo;
	}
	
	/**
	 * 调取AR；类
	 *
	 * @return ActiveRecord
	 */
	public function setArDb($pdo) {
		if (! $pdo)
			exit ( 'mysql_connect Error!!!' );
		$ar = new ActiveRecord ();
		$ar->setDb ( $pdo );
		return $ar;
	}
}
