<?php
class TestController extends BaseController{
	
	 public function TestAction(){
	 	$user=new User();
	 	$aa=$user->get();
	 	var_dump($aa);die;
	 	
	 	$this->render("index/index",array('name'=>'111'));
	 }
	
}