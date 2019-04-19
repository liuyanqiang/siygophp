<?php
class BaseController extends CController{
	public $db;
	public function __construct(){
		$this->db=SP::app()->db;
		
	}
	
}