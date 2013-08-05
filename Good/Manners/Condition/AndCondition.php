<?php

namespace Good\Manners\Condition;

use Good\Manners\Condition;
use Good\Manners\ConditionProcessor;
use Good\Manners\Store;

class AndCondition implements Condition
{
	private $condition1;
	private $condition2;

	public function __construct(Condition $condition1, 
								Condition $condition2)
	{
		$this->condition1 = $condition1;
		$this->condition2 = $condition2;
	}
	
	public function process(ConditionProcessor $store)
	{
		$store->processAndCondition($this->condition1, $this->condition2);
	}
}

?>