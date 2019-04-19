<?php
class CController {
	public $layout='main';
	public $var;
	public $template;
	public function render($template, array $var = array()) {
	   $this->template=$template;
	    $this->var=$var;
	    ob_end_clean (); // 关闭顶层的输出缓冲区内容
	    ob_start (); // 开始一个新的缓冲区
		$this->getlayout();
	 }
	/**
	 * 获取布局文件
	 * 视图渲染框架
	 */
	public function  getlayout(){
		$layoutfile=SP_TP.'layout/'.$this->layout.'.html';
		if(!empty($this->layout)&&file_exists($layoutfile)){
			require $layoutfile;
		}
	}
	/**
	 * 输出框架内容
	 * @return string
	 */
	public function  getContent(){
		if(is_array($this->var))
			extract($this->var,EXTR_PREFIX_SAME,'data');//抽取数组中的变量
		require SP_TP . $this->template . '.html'; // 加载视图view
		$content = ob_get_contents (); // 获得缓冲区的内容
		ob_end_clean (); // 关闭缓冲区
		ob_start (); // 开始新的缓冲区，给后面的程序用
		return $content;
	}
	/**
	 * 创建url 
	 */
	public function createUrl($route,$params=array(),$ampersand='&')
	{  
	    $route=trim($route,'/');
	    return SP::app()->createUrl($route,$params,$ampersand);
	}
}