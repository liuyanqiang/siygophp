<?php
class  Jiayuan extends ActiveRecord{

    public $table = 'jiayuan';
	public $primaryKey = 'id';
	
	
	public $relations = array(
			'user' => array(self::BELONGS_TO, 'User', 'user_id'),
			
	);
	
	public function __construct(){
		parent::initConn();
	}
	
}