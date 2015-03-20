<?php namespace HelpLar;

trait LoggingTrait {
	
	protected $logger;
	
	public function setLogger(callable $logger) {
		$this->logger = $logger;
	}
	
	protected function log($str) {
		if (is_callable($this->logger)) {
			call_user_func($this->logger, $str);
		}
	}
	
}