<?php
class  User extends ActiveRecord{

    public $table = 'user';
	public $primaryKey = 'id';
	public $relations = array(
			'contacts' => array(self::HAS_MANY, 'Jiayuan', 'user_id'),
			'contact' => array(self::HAS_ONE, 'Jiayuan', 'user_id', 'where' => '1', 'order' => 'id desc'),
	);
	
	
	
	public function __construct(){
		parent::initConn();
	}
	
	
	
	

}