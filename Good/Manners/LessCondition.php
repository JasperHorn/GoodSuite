<?php

namespace Good\Manners;

require_once dirname(__FILE__) . '/Condition.php';
require_once 'ComparingStore.php';
require_once dirname(__FILE__) . '/Storable.php';

class LessCondition extends Condition
{
	private $store;
	private $to;

	public function __construct(ComparingStore $store, Storable $to)
	{
		parent::__construct($store);
		
		$this->store = $store;
		$this->to = $to;
	}
	
	protected function doProcess()
	{
		$this->store->processLessCondition($this->to);
	}
}

?>