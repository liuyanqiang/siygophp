<?php
class CApplication {
	public function __construct($config = null) {
		SP::setApplication ( $this );
		if (is_string ( $config ))
			$config = require ($config);
		$this->configure ( $config );
		if (is_array ( $this->importPath )) {
			SP::$_aliases = $this->importPath;
		}
		$this->components ();
		// 解析URL
		$_Url = $this->parseUrl ();
		// 路由转发
		$this->dispatch ( $_Url ['a'], $_Url ['c'] );
	}
	public function configure($config) {
		if (is_array ( $config )) {
			foreach ( $config as $key => $value )
				$this->$key = $value;
		}
	}
	/**
	 * 加载组件
	 */
	public function components() {
		if (empty ( $this->components ) && ! is_array ( $this->components ))
			return false;
		foreach ( $this->components as $key => $val ) {
			$this->$key = new $val ['class'] ();
			unset ( $val ['class'] );
			foreach ( $val as $name => $value ) {
				$this->$key->$name = $value;
			}
		}
	}
	public function parseUrl() {
		$REQUEST_URI = trim ( $_SERVER ['REQUEST_URI'], '/' );
		if (strpos ( $REQUEST_URI, "?" ) !== false) {
			$REQUEST_URI = substr ( $REQUEST_URI, 0, strpos ( $REQUEST_URI, "?" ) );
		}
	
		if (! empty ( $REQUEST_URI )) {
			$_url = explode ( "/", $REQUEST_URI );
			
			if (pathinfo ( $_url [0], PATHINFO_EXTENSION ) == 'php') {
				$Controller = ! empty ( $_url [1] ) ? $_url [1] : $this->defaultController;
				$Action = ! empty ( $_url [2] ) ? $_url [2] : $this->defaultAction;
			} else {
				$Controller = ! empty ( $_url [0] ) ? $_url [0] : $this->defaultController;
				$Action = ! empty ( $_url [1] ) ? $_url [1] : $this->defaultAction;
			}
		} else {
			$Controller = $this->defaultController;
			$Action = $this->defaultAction;
		}
		if(!empty($this->urlExt)){
		//判断acion是否携带扩展
		$urlExt=ltrim($this->urlExt,".");
		if (pathinfo ( $Action, PATHINFO_EXTENSION )==$urlExt) {
		   $urllen= strlen($this->urlExt);
		   $Action=substr($Action,0,-$urllen);
		   }
		}
		return array (
				'a' => $Controller,
				'c' => $Action 
		);
	}
	private function dispatch($Controller, $Action) {
		$ControllerClass = ucwords ( $Controller ) . 'Controller';
		$Action = ucwords ( $Action ) . 'Action';
		$C = new $ControllerClass ();
	    $C->$Action ();
	}
	
	public function createUrl($route,$params=array(),$ampersand='&'){
	   $urlParamsArr=array();
	    if(!empty($params)){
	       foreach ($params as $key=>$val){
	           $urlParamsArr[]="{$key}={$val}";
	       }
	    } 
	   $urlparam=implode($ampersand, $urlParamsArr);
	   return '/'.$route.$this->urlExt.'?'.$urlparam;
	}
}
