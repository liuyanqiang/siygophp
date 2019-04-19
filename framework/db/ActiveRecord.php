<?php
/**
 * @author liuyanqiang
 * @email siygo@qq.com
 * @example
 * ActiveRecord 模型类对应关系型数据库
 * @var PDO static property to connect database.
 */
class ActiveRecord extends Base {
	
	/**
	 *
	 * @var 表前缀
	 */
	private $tablePrefix;
	/**
	 * 操作符
	 * 
	 * @var unknown
	 */
	public static $operators = array (
			'equal' => '=',
			'eq' => '=',
			'notequal' => '<>',
			'ne' => '<>',
			'greaterthan' => '>',
			'gt' => '>',
			'lessthan' => '<',
			'lt' => '<',
			'greaterthanorequal' => '>=',
			'ge' => '>=',
			'gte' => '>=',
			'lessthanorequal' => '<=',
			'le' => '<=',
			'lte' => '<=',
			'between' => 'BETWEEN',
			'like' => 'LIKE',
			'in' => 'IN',
			'notin' => 'NOT IN',
			'isnull' => 'IS NULL',
			'isnotnull' => 'IS NOT NULL',
			'notnull' => 'IS NOT NULL' 
	);
	
	/**
	 *
	 * @example $user->order('id desc', 'name asc')->limit(2,1);
	 *          explain to SQL:
	 *          ORDER BY id desc, name asc limit 2,1
	 */
	public static $sqlParts = array (
			'select' => 'SELECT',
			'from' => 'FROM',
			'set' => 'SET',
			'where' => 'WHERE',
			'group' => 'GROUP BY',
			'groupby' => 'GROUP BY',
			'having' => 'HAVING',
			'order' => 'ORDER BY',
			'orderby' => 'ORDER BY',
			'limit' => 'limit',
			'top' => 'TOP' 
	);
	/**
	 *
	 * @var array Static property to stored the default Sql Expressions values.
	 */
	public static $defaultSqlExpressions = array (
			'expressions' => array (),
			'wrap' => false,
			'select' => null,
			'insert' => null,
			'update' => null,
			'set' => null,
			'delete' => 'DELETE',
			'from' => null,
			'values' => null,
			'where' => null,
			'having' => null,
			'limit' => null,
			'order' => null,
			'group' => null 
	);
	/**
	 *
	 * @var 存储sql异常
	 */
	protected $sqlExpressions = array ();
	/**
	 *
	 * @var string The table name in database.
	 */
	public $table;
	/**
	 *
	 * @var 默认主键
	 */
	public $primaryKey = 'id';
	/**
	 *
	 * @var array 存储 （insert update）操作的语句
	 */
	public $dirty = array ();
	
	/**
	 *
	 * @var array 绑定验证
	 *      PDOStatement::execute(),
	 */
	private $params = array ();
	const BELONGS_TO = 'belongs_to';
	const HAS_MANY = 'has_many';
	const HAS_ONE = 'has_one';
	/**
	 * 连表多表操作
	 */
	public $relations = array ();
	/**
	 *
	 * @var 记录绑定验证的个数
	 */
	public static $count = 0;
	const PREFIX = ':ph';
	private static $toarray = false;
	private $_pdo;
	private $CDbConnection;
	
	/**
	 * 子类继承ar 必须实现的类
	 */
	public function initConn() {
		$this->CDbConnection = SP::app ()->db;
		// 设置表前缀
		$this->tablePrefix = $this->CDbConnection->tablePrefix;
	}
	/**
	 * 设置pdo对象
	 * 
	 * @param unknown $pdo        	
	 */
	public function setDb($pdo) {
		$this->_pdo = $pdo;
	}
	/**
	 * 获取pdo连接自动获取
	 * 
	 * @param unknown $sql        	
	 */
	public function getConnDB($sql, $isMaster = false) {
		$pdo = SP::app ()->db->getConnection ( $sql, $isMaster );
		if (! $pdo)
			exit ( ' mysql_connect Error!!!' );
		$this->setDb ( $pdo );
	}
	
	/**
	 * 重置参数
	 * 
	 * @return ActiveRecord
	 */
	public function reset() {
		$this->params = array ();
		$this->sqlExpressions = array ();
		return $this;
	}
	/**
	 * function to SET or RESET the dirty data.
	 *
	 * @param array $dirty
	 *        	The dirty data will be set, or empty array to reset the dirty data.
	 * @return ActiveRecord return $this, can using chain method calls.
	 */
	public function dirty($dirty = array()) {
		$this->data = array_merge ( $this->data, $this->dirty = $dirty );
		return $this;
	}
	
	/**
	 * function to find one record and assign in to current object.
	 *
	 * @param int $id
	 *        	If call this function using this param, will find record by using this id. If not set, just find the first record in database.
	 * @return bool ActiveRecord find record, assign in to current object and return it, other wise return "false".
	 */
	public function find($id = null) {
		if ($id)
			$this->reset ()->eq ( $this->primaryKey, $id );
		return $this->query ( $this->limit ( 1 )->_buildSql ( array (
				'select',
				'from',
				'where',
				'group',
				'having',
				'order',
				'limit' 
		) ), $this->params, $this->reset () );
	}
	/**
	 * function to find all records in database.
	 *
	 * @return array return array of ActiveRecord
	 */
	public function findAll() {
		return $this->queryAll ( $this->_buildSql ( array (
				'select',
				'from',
				'where',
				'group',
				'having',
				'order',
				'limit' 
		) ), $this->params, $this->reset () );
	}
	/**
	 * function to delete current record in database.
	 *
	 * @return bool
	 */
	public function delete() {
		return $this->execute ( $this->eq ( $this->primaryKey, $this->{$this->primaryKey} )->_buildSql ( array (
				'delete',
				'from',
				'where' 
		) ), $this->params );
	}
	/**
	 * function to build update SQL, and update current record in database, just write the dirty data into database.
	 *
	 * @return bool ActiveRecord update success return current object, other wise return false.
	 */
	public function update() {
		if (count ( $this->dirty ) == 0)
			return true;
		foreach ( $this->dirty as $field => $value )
			$this->addCondition ( $field, '=', $value, ',', 'set' );
		if (! $this->{$this->primaryKey})
			return false;
		if ($this->execute ( $this->eq ( $this->primaryKey, $this->{$this->primaryKey} )->_buildSql ( array (
				'update',
				'set',
				'where' 
		) ), $this->params ))
			return $this->dirty ()->reset ();
		return false;
	}
	/**
	 * 返回count
	 * 
	 * @param string $id        	
	 * @return Ambigous <boolean, mixed>
	 */
	public function count($id = null) {
		if ($id)
			$this->reset ()->eq ( $this->primaryKey, $id );
		
		$rows = $this->select ( 'count(*) as rowcount' )->query ( $this->limit ( 1 )->_buildSql ( array (
				'select',
				'from',
				'where',
				'group',
				'having',
				'order',
				'limit' 
		) ), $this->params, $this->reset () );
		
		if (self::$toarray == true) {
			$rowCount = $rows ['rowcount'];
		} else {
			$rowCount = $rows->rowcount;
		}
		return $rowCount;
	}
	
	/**
	 * function to build insert SQL, and insert current record into database.
	 *
	 * @return bool ActiveRecord insert success return current object, other wise return false.
	 */
	public function insert() {
		if (count ( $this->dirty ) == 0)
			return true;
		$value = $this->_filterParam ( $this->dirty );
		
		$this->insert = new Expressions ( array (
				'operator' => 'INSERT INTO ' . $this->tablePrefix . $this->table,
				'target' => new WrapExpressions ( array (
						'target' => array_keys ( $value ) 
				) ) 
		) );
		
		$this->values = new Expressions ( array (
				'operator' => 'values',
				'target' => new WrapExpressions ( array (
						'target' => array_values ( $value ) 
				) ) 
		) );
		
		if ($this->execute ( $this->_buildSql ( array (
				'insert',
				'values' 
		) ), $this->params )) {
			$this->id = $this->_pdo->lastInsertId ();
			return $this->dirty ()->reset ();
		}
		return false;
	}
	/**
	 * helper function to exec sql.
	 *
	 * @param string $sql
	 *        	The SQL need to be execute.
	 * @param array $param
	 *        	The param will be bind to PDOStatement.
	 * @return bool
	 */
	public function execute($sql, $param = array()) {
		// 设置主库连接
		$this->getConnDB ( $sql );
		return (($sth = $this->_pdo->prepare ( $sql )) && $sth->execute ( $param ));
	}
	/**
	 * helper function to query record from db.
	 *
	 * @param PDOStatement $sth
	 *        	the PDOStatement instance
	 * @param ActiveRecord $obj
	 *        	The object, if find record in database, will assign the attributes in to this object.
	 * @return bool
	 */
	protected static function _queryCallback($sth, $obj) {
		if (self::$toarray == true) {
			$obj = $sth->fetch ( PDO::FETCH_ASSOC );
		} else {
			$obj = $sth->fetch ( PDO::FETCH_INTO );
		}
		return $obj;
	}
	/**
	 * helper function to query one record by sql and params.
	 *
	 * @param string $sql
	 *        	The SQL to find record.
	 * @param array $param
	 *        	The param will be bind to PDOStatement.
	 * @param ActiveRecord $obj
	 *        	The object, if find record in database, will assign the attributes in to this object.
	 * @return bool ActiveRecord
	 */
	public function query($sql, $param = array(), $obj = null) {
		return $this->callbackQuery ( 'ActiveRecord::_queryCallback', $sql, $param, $obj );
	}
	/**
	 * 是否转成数组
	 */
	public function toArray() {
		self::$toarray = true;
		return $this;
	}
	
	/**
	 * helper function to execute sql with callback, can using this call back to fetch data.
	 *
	 * @param callable $cb
	 *        	Callback function will call after find records in database.
	 * @param string $sql
	 *        	The SQL to find record.
	 * @param array $param
	 *        	The param will be bind to PDOStatement.
	 * @param ActiveRecord $obj
	 *        	The object, if find record in database, will assign the attributes in to this object.
	 * @return mixed if success to exec SQL, return the return value of callback, other wise return false.
	 */
	public function callbackQuery($cb, $sql, $param = array(), $obj = null) {
		$this->getConnDB ( $sql );
		if ($sth = $this->_pdo->prepare ( $sql )) {
			$sth->setFetchMode ( PDO::FETCH_INTO, ($obj ? $obj : $this) );
			$sth->execute ( $param );
			return call_user_func ( $cb, $sth, $obj );
		}
		return false;
	}
	/**
	 * helper function to query record from db.
	 *
	 * @param PDOStatement $sth
	 *        	the PDOStatement instance
	 * @param ActiveRecord $obj
	 *        	The object, if find record in database, will assign the attributes in to this object.
	 * @return bool
	 * @see _queryCallback
	 */
	protected static function _queryAllCallback($sth, $obj) {
		$result = array ();
		if (self::$toarray == true) {
			while ( $obj = $sth->fetch ( PDO::FETCH_ASSOC ) )
				$result [] = $obj;
		} else {
			while ( $obj = $sth->fetch ( PDO::FETCH_INTO ) )
				$result [] = clone $obj;
		}
		return $result;
	}
	/**
	 * helper function to find all records by SQL.
	 *
	 * @param string $sql
	 *        	The SQL to find record.
	 * @param array $param
	 *        	The param will be bind to PDOStatement.
	 * @param ActiveRecord $obj
	 *        	The object, if find record in database, will assign the attributes in to this object.
	 * @return mixed if success to exec SQL, return array of ActiveRecord object, other wise return false.
	 */
	public function queryAll($sql, $param = array(), $obj = null) {
		return $this->callbackQuery ( 'ActiveRecord::_queryAllCallback', $sql, $param, $obj );
	}
	/**
	 * helper function to get relation of this object.
	 * There was three types of relations: {BELONGS_TO, HAS_ONE, HAS_MANY}
	 *
	 * @param string $name
	 *        	The name of the relation, the array key when defind the relation.
	 * @return mixed
	 */
	protected function &getRelation($name) {
		$relation = $this->relations [$name];
		if ($relation instanceof self || (is_array ( $relation ) && $relation [0] instanceof self))
			return $relation;
		$obj = new $relation [1] ();
		if ((! $relation instanceof self) && self::HAS_ONE == $relation [0])
			$this->relations [$name] = $obj->eq ( $relation [2], $this->{$this->primaryKey} )->find ();
		elseif (is_array ( $relation ) && self::HAS_MANY == $relation [0])
			$this->relations [$name] = $obj->eq ( $relation [2], $this->{$this->primaryKey} )->findAll ();
		elseif ((! $relation instanceof self) && self::BELONGS_TO == $relation [0])
			$this->relations [$name] = $obj->find ( $this->{$relation [2]} );
		else
			throw new Exception ( "Relation $name not found." );
		return $this->relations [$name];
	}
	/**
	 * helper function to build SQL with sql parts.
	 *
	 * @param string $n
	 *        	The SQL part will be build.
	 * @param int $i
	 *        	The index of $n in $sqls array.
	 * @param ActiveRecord $o
	 *        	The refrence to $this
	 * @return string
	 */
	private function _buildSqlCallback(&$n, $i, $o) {
		if ('select' === $n && null == $o->$n)
			$n = strtoupper ( $n ) . ' ' . $this->tablePrefix . $o->table . '.*';
		elseif (('update' === $n || 'from' === $n) && null == $o->$n)
			$n = strtoupper ( $n ) . ' ' . $this->tablePrefix . $o->table;
		elseif ('delete' === $n)
			$n = strtoupper ( $n ) . ' ';
			// elseif('values'===$n) $n= strtoupper($n). ' ';
		else
			$n = (null !== $o->$n) ? $o->$n . ' ' : '';
	}
	/**
	 * helper function to build SQL with sql parts.
	 *
	 * @param array $sqls
	 *        	The SQL part will be build.
	 * @return string
	 */
	protected function _buildSql($sqls = array()) {
		array_walk ( $sqls, array (
				$this,
				'_buildSqlCallback' 
		), $this );
		// this code to debug info.
		// echo 'SQL: ', implode(' ', $sqls), "\n", "PARAMS: ", implode(', ', $this->params), "\n";
		return implode ( ' ', $sqls );
	}
	/**
	 * magic function to make calls witch in function mapping stored in $operators and $sqlPart.
	 * also can call function of PDO object.
	 *
	 * @param string $name
	 *        	function name
	 * @param array $args
	 *        	The arguments of the function.
	 * @return mixed Return the result of callback or the current object to make chain method calls.
	 */
	public function __call($name, $args) {
		if (is_callable ( $callback = array (
				$this->_pdo,
				$name 
		) ))
			return call_user_func_array ( $callback, $args );
		if (in_array ( $name = strtolower ( $name ), array_keys ( self::$operators ) ))
			$this->addCondition ( $args [0], self::$operators [$name], isset ( $args [1] ) ? $args [1] : null, (is_string ( end ( $args ) ) && 'or' === strtolower ( end ( $args ) )) ? 'OR' : 'AND' );
		else if (in_array ( $name = str_replace ( 'by', '', $name ), array_keys ( self::$sqlParts ) ))
			$this->$name = new Expressions ( array (
					'operator' => self::$sqlParts [$name],
					'target' => implode ( ', ', $args ) 
			) );
		else
			throw new Exception ( "Method $name not exist." );
		return $this;
	}
	/**
	 * make wrap when build the SQL expressions of WHWRE.
	 *
	 * @param string $op
	 *        	If give this param will build one WrapExpressions include the stored expressions add into WHWRE. otherwise wil stored the expressions into array.
	 * @return ActiveRecord return $this, can using chain method calls.
	 */
	public function wrap($op = null) {
		if (1 === func_num_args ()) {
			$this->wrap = false;
			if (is_array ( $this->expressions ) && count ( $this->expressions ) > 0)
				$this->_addCondition ( new WrapExpressions ( array (
						'delimiter' => ' ',
						'target' => $this->expressions 
				) ), 'or' === strtolower ( $op ) ? 'OR' : 'AND' );
			$this->expressions = array ();
		} else
			$this->wrap = true;
		return $this;
	}
	/**
	 * helper function to build place holder when make SQL expressions.
	 *
	 * @param mixed $value
	 *        	the value will bind to SQL, just store it in $this->params.
	 * @return mixed $value
	 */
	protected function _filterParam($value) {
		if (is_array ( $value ))
			foreach ( $value as $key => $val )
				$this->params [$value [$key] = self::PREFIX . ++ self::$count] = $val;
		else if (is_string ( $value )) {
			$this->params [$ph = self::PREFIX . ++ self::$count] = $value;
			$value = $ph;
		}
		return $value;
	}
	/**
	 * helper function to add condition into WHERE.
	 *
	 * create the SQL Expressions.
	 *
	 * @param string $field
	 *        	The field name, the source of Expressions
	 * @param string $operator        	
	 * @param mixed $value
	 *        	the target of the Expressions
	 * @param string $op
	 *        	the operator to concat this Expressions into WHERE or SET statment.
	 * @param string $name
	 *        	The Expression will contact to.
	 */
	public function addCondition($field, $operator, $value, $op = 'AND', $name = 'where') {
		$value = $this->_filterParam ( $value );
		if ($exp = new Expressions ( array (
				'source' => ('where' == $name ? $this->tablePrefix . $this->table . '.' : '') . $field,
				'operator' => $operator,
				'target' => (is_array ( $value ) ? new WrapExpressions ( array (
						'target' => $value 
				) ) : $value) 
		) )) {
			if (! $this->wrap)
				$this->_addCondition ( $exp, $op, $name );
			else
				$this->_addExpression ( $exp, $op );
		}
	}
	/**
	 * helper function to make wrapper.
	 * Stored the expression in to array.
	 *
	 * @param Expressions $exp
	 *        	The expression will be stored.
	 * @param string $operator
	 *        	The operator to concat this Expressions into WHERE statment.
	 */
	protected function _addExpression($exp, $operator) {
		if (! is_array ( $this->expressions ) || count ( $this->expressions ) == 0)
			$this->expressions = array (
					$exp 
			);
		else
			$this->expressions [] = new Expressions ( array (
					'operator' => $operator,
					'target' => $exp 
			) );
	}
	/**
	 * helper function to add condition into WHERE.
	 *
	 * @param Expressions $exp
	 *        	The expression will be concat into WHERE or SET statment.
	 * @param string $operator
	 *        	the operator to concat this Expressions into WHERE or SET statment.
	 * @param string $name
	 *        	The Expression will contact to.
	 */
	protected function _addCondition($exp, $operator, $name = 'where') {
		if (! $this->$name)
			$this->$name = new Expressions ( array (
					'operator' => strtoupper ( $name ),
					'target' => $exp 
			) );
		else
			$this->$name->target = new Expressions ( array (
					'source' => $this->$name->target,
					'operator' => $operator,
					'target' => $exp 
			) );
	}
	/**
	 * magic function to SET values of the current object.
	 */
	public function __set($var, $val) {
		if (array_key_exists ( $var, $this->sqlExpressions ) || array_key_exists ( $var, self::$defaultSqlExpressions ))
			$this->sqlExpressions [$var] = $val;
		$this->dirty [$var] = $this->data [$var] = $val;
	}
	/**
	 * magic function to UNSET values of the current object.
	 */
	public function __unset($var) {
		if (array_key_exists ( $var, $this->sqlExpressions ))
			unset ( $this->sqlExpressions [$var] );
		if (isset ( $this->data [$var] ))
			unset ( $this->data [$var] );
		if (isset ( $this->dirty [$var] ))
			unset ( $this->dirty [$var] );
	}
	/**
	 * magic function to GET the values of current object.
	 */
	public function &__get($var) {
		if (array_key_exists ( $var, $this->sqlExpressions ))
			return $this->sqlExpressions [$var];
		else if (array_key_exists ( $var, $this->relations ))
			return $this->getRelation ( $var );
		else
			return parent::__get ( $var );
	}
}
/**
 * base class to stord attributes in one array.
 */
abstract class Base {
	/**
	 *
	 * @var array Stored the attributes of the current object
	 */
	public $data = array ();
	public function __construct($config = array()) {
		foreach ( $config as $key => $val )
			$this->$key = $val;
	}
	public function __set($var, $val) {
		$this->data [$var] = $val;
	}
	public function &__get($var) {
		$result = isset ( $this->data [$var] ) ? $this->data [$var] : null;
		return $result;
	}
}
/**
 * 拼接SQL
 */
class Expressions extends Base {
	public function __toString() {
		return $this->source . ' ' . $this->operator . ' ' . $this->target;
	}
}
/**
 * Class 拼接sql
 */
class WrapExpressions extends Expressions {
	public function __toString() {
		return ($this->start ? $this->start : '(') . implode ( ($this->delimiter ? $this->delimiter : ','), $this->target ) . ($this->end ? $this->end : ')');
	}
}
