<?php namespace HelpLar;

trait SharedContextTrait {
	
	protected $owner;
	protected $context;

	/**
	 * Should be overriden to add ->context properties.
	 */
	public function initContext() {
		$this->context = new \stdClass();
	}
	
	public function getContext() {
		return $this->context;
	}
	
	public static function shareContext($owner) {
		$self = new static();
		$this->context = $owner->getContext();
		$this->owner = $owner;
		return $self;
	}
	
}
