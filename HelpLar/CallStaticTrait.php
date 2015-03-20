<?php namespace HelpLar;

trait CallStaticTrait {
	
	protected static $disableCallStaticTrait = false;
	
	/**
	  * Handle dynamic, static calls to the object.
	  *
	  * @param  string  $method
	  * @param  array   $args
	  * @return mixed
	  */
	public static function __callStatic($method, $args) {
		if (static::$disableCallStaticTrait) {
			throw new \Exception("Undefined {$method} in " . get_called_class());
		}
		$instance = new static();
		// sdv_dbg('method',$method);
		if (substr($method, 0, 2) === 's_') {
			$method = substr($method, 2);
		}
		switch (count($args)) {
		case 0:
			return $instance->$method();
		case 1:
			return $instance->$method($args[0]);
		case 2:
			return $instance->$method($args[0], $args[1]);
		case 3:
			return $instance->$method($args[0], $args[1], $args[2]);
		case 4:
			return $instance->$method($args[0], $args[1], $args[2], $args[3]);
		default:
			return call_user_func_array(array($instance, $method), $args);
		}
	}

}