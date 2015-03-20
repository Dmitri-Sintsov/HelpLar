<?php namespace HelpLar;

/**
 * Selective debug logger: per-class turning log on/off.
 */
trait DbgTrait {

	protected $enableDebug = false;
	protected $debugStack = [];

	protected function dbgOn($enableDebug) {
		$this->debugStack[] = $this->enableDebug;
		$this->enableDebug = boolval($enableDebug);
	}
	
	protected function dbgOff() {
		$this->enableDebug = array_pop($this->debugStack);
	}
	
	protected function dbg($key, $val) {
		if ($this->enableDebug) {
			sdv_dbg($key, $val, 2);
		}
	}
	
}
