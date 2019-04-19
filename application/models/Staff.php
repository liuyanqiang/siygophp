<?php
class  Staff extends ActiveRecord{

    public $table = 'hr_staff';
	public $primaryKey = 'sid';
	
	public function __construct(){
		parent::initConn();
		//$this->toArray();
	}
}