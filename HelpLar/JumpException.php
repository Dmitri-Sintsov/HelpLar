<?php namespace HelpLar;

class JumpException extends \Exception {

	protected $data;
	
	public function setData($data) {
		$this->data = $data;
	}
	
	public function getData() {
		return $this->data;
	}
	
	public static function instance($msg = '', $data = null) {
		$e = new static($msg);
		$e->setData($data);
		return $e;
	}
	
	public static function raise($msg = '', $data = null) {
		throw static::instance($msg, $data);
	}
	
}
