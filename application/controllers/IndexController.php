<?php
class IndexController extends BaseController{
	
	 public function IndexAction(){
	 
	 	 $data=$this->db->query("select * from zg_hr_staff limit 1");
	     $this->render("index/index",array('data'=>$data));
	 }
	 
     public function TestAction(){
	 	 $data=$this->db->query("select * from zg_hr_staff limit 1");
	 	 
	 	
	 	//  print_r($_GET);die;
	 	//var_dump($url);die;
	 	 $this->render("index/index",array('data'=>$data));
	 }
	
}