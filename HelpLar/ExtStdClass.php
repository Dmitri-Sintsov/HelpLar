<?php namespace HelpLar;

class ExtStdClass extends \stdClass {

	protected $_original;
	
	function __construct($original) {
		if (is_array($original)) {
			$this->_original = $original;
		} else if (is_object($original)) {
			foreach ($original as $propName => $val) {
				if (property_exists($this, $propName)) {
					JumpException::raise("Bugcheck: original has conflicting property {$propName}", $original);
				}
				$this->{$propName} = $original->{$propName};
			}
		} else {
			JumpException::raise('Original data must be either array or object.', $original);
		}
	}

	public function has($propName) {
		if (is_null($this->_original)) {
			return property_exists($this, $propName);
		} else {
			return array_key_exists($propName, $this->_original);
		}
	}
	
	public function get($propName, $defVal = null) {
		if (is_null($this->_original)) {
			return property_exists($this, $propName) ? $this->{$propName} : $defVal;
		} else {
			return array_key_exists($propName, $this->_original) ? $this->_original[$propName] : $defVal;
		}
	}
	
	public function set($propName, $val) {
		if ($propName === '_original') {
			throw new Exception('Bugcheck.');
		}
		if (is_null($this->_original)) {
			$this->{$propName} = $val;
		} else {
			$this->_original[$propName] = $val;
		}
	}

	public function delete($propName) {
		if ($propName === '_original') {
			throw new Exception('Bugcheck.');
		}
		if (is_null($this->_original) && property_exists($this, $propName)) {
			unset($this->{$propName});
		} elseif (array_key_exists($propName, $this->_original)) {
			unset($this->_original[$propName]);
		}
	}
	
	protected static $propNames;
	protected static $defVal;
	protected static $found;
	protected static $apply;
	protected static function _getNested(&$obj, $idx = 0) {
		if (count(static::$propNames) === $idx) {
			return $obj;
		}
		if (is_array($obj) && array_key_exists(static::$propNames[$idx], $obj)) {
			if (count(static::$propNames) - 1=== $idx) {
				static::$found = true;
				if (is_callable(static::$apply)) {
					return call_user_func(static::$apply, $obj, static::$propNames[$idx]);
				}
			}
			return static::_getNested($obj[static::$propNames[$idx]], $idx + 1);
		} elseif (is_object($obj) && property_exists($obj, static::$propNames[$idx])) {
			if (count(static::$propNames) - 1 === $idx) {
				static::$found = true;
				if (is_callable(static::$apply)) {
					return call_user_func(static::$apply, $obj, static::$propNames[$idx]);
				}
			}
			return static::_getNested($obj->{static::$propNames[$idx]}, $idx + 1);
		} else {
			return static::$defVal;
		}
	}

	public static function s_hasNested($obj, array $propNames, $defVal = null) {
		static::$propNames = $propNames;
		static::$defVal = $defVal;
		static::$found = false;
		static::$apply = null;
		static::_getNested($obj);
		return static::$found;
	}

	public static function s_getNested($obj, array $propNames, $defVal = null) {
		static::$propNames = $propNames;
		static::$defVal = $defVal;
		static::$found = false;
		static::$apply = null;
		return static::_getNested($obj);
	}

	public static function s_setNested($obj, array $propNames, $val) {
		static::$propNames = $propNames;
		static::$defVal = null;
		static::$found = false;
		static::$apply = function(&$obj, $propName) use ($val) {
			// sdv_dbg('obj',$obj);
			// sdv_dbg('propname',$propName);
			if (is_array($obj)) {
				$obj[$propName] = $val;
			} else {
				$obj->{$propName} = $val;
			}
		};
		static::_getNested($obj);
		return static::$found;
	}

	public static function s_deleteNested($obj, array $propNames) {
		static::$propNames = $propNames;
		static::$defVal = null;
		static::$found = false;
		static::$apply = function(&$obj, $propName) {
			// sdv_dbg('obj',$obj);
			// sdv_dbg('propname',$propName);
			if (is_array($obj)) {
				unset($obj[$propName]);
			} else {
				unset($obj->{$propName});
			}
		};
		return static::_getNested($obj);
	}

	public function hasNested(array $propNames) {
		return static::s_hasNested(is_null($this->_original) ? $this : $this->_original, $propNames);
	}
	
	public function getNested(array $propNames, $defVal = null) {
		return static::s_getNested(is_null($this->_original) ? $this : $this->_original, $propNames, $defVal);
	}

	public function setNested(array $propNames, $val = null) {
		return static::s_setNested(is_null($this->_original) ? $this : $this->_original, $propNames, $val);
	}

	public function deleteNested(array $propNames) {
		return static::s_deleteNested(is_null($this->_original) ? $this : $this->_original, $propNames);
	}
	
}
