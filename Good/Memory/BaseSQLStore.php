<?php

namespace Good\Memory;

use Good\Manners\ComparingStore;
use Good\Manners\BasicLogicStore;
use Good\Manners\Storable;
use Good\Manners\Condition;
use Good\Manners\Resolver;

abstract class BaseSQLStore extends \GoodMannersStore // (generated so not namespaced)
							implements ComparingStore,
									   BasicLogicStore,
									   SQLStore
{
	protected $db;
	private $currentConditionWriter = null;
	private $currentPropertyVisitor = null;
	
	private $joins = array();
	private $joinsReverse = array();
	private $numberOfJoins = 0;
	
	private $postponed = array();
	
	public function __construct($db)
	{
		parent::__construct();
	
		$this->db = $db;
	}
	
	public function tableNamify($value)
	{
		return \strtolower($value);
	}
	
	public function fieldNamify($value)
	{
		return \strtolower($value);
	}
	
	public function parseInt($value)
	{
		return \intval($value);
	}
	
	public function parseFloat($value)
	{
		return \floatval($value);
	}
	
	public function parseDatetime($value)
	{
		// shouldn't be necessary when we do stricter type checking,
		// but let's just stick with it for now.
		if (!($value instanceof \DateTime))
		{
			// TODO: turn this into real error reporting
			throw new \Exception("Non-DateTime given for a DateTime field.");
		}
		
		return "'" . $value->format('Y-m-d H:i:s') . "'";
	}
	
	public function parseText($value)
	{
		return "'" . $this->db->escapeText($value) . "'";
	}
	
	protected function saveAnyDeletions($datatypeName, array $storables)
	{
		foreach ($storables as $storable)
		{
			$sql  = 'DELETE FROM ' . $this->tableNamify($datatypeName);
			$sql .= " WHERE id = " . intval($storable->getId());
			
			$this->db->query($sql);
		}
	}
	
	protected function saveAnyNew($datatypeName, array $storables)
	{
		$inserter = new SQLInserter($this, $this->db);
		
		foreach ($storables as $storable)
		{
			// We check again if it is new, as it might already be inserted when resolving dependencies
			// of another insert, in which case it is not new anymore.
			if ($storable->isNew())
			{
				$inserter->insert($datatypeName, $storable);
			}
		}
		
		foreach ($inserter->getPostponed() as $postponed)
		{
			$postponed->doNow();
		}
		
		$this->reflush();
	}
	
	protected function saveAnyModifications($datatypeName, array $storables)
	{
		$updater = new SQLSimpleUpdater($this, $this->db);
		
		foreach ($storables as $storable)
		{
			$updater->update($datatypeName, $storable);
			$storable->makeDirty(false);
		}
	}
	
	protected function doAnyGet($datatypeName, 
								Condition $condition, 
								Resolver $resolver)
	{
		$this->joins = array(0 => array());
		$this->numberOfJoins = 0;
		
		$selecter = new SQLSelecter($this, $this->db, 0);
		
		return $selecter->select($datatypeName, $condition, $resolver);
	}
	
	protected function doAnyModify($datatypeName,
								   Condition $condition,
								   Storable $modifications)
	{
		$this->joins = array(0 => array());
		$this->numberOfJoins = 0;
		
		$updater = new SQLAdvancedUpdater($this, $this->db, 0);
		
		$updater->update($datatypeName, $condition, $modifications);
	}
	
	public function getJoin($table, $property)
	{
		if (\array_key_exists($property, $this->joins[$table]))
		{
			return $this->joins[$table][$property]->tableNumberDestination;
		}
		else
		{
			return -1;
		}
	}
	
	public function getReverseJoin($tableNumber)
	{
		if (\array_key_exists($tableNumber, $this->joinsReverse))
		{
			return $this->joinsReverse[$tableNumber];
		}
		else
		{
			return null;
		}
	}
	
	public function getJoins()
	{
		return $this->joins;
	}
	
	public function createJoin($tableNumberOrigin, $fieldNameOrigin, $fieldNumberOrigin, $tableNameDestination)
	{
		// we start off with increment because joins index is numberOfJoins + 1 (index 0 is for base table)
		$this->numberOfJoins++;
		
		$join = new SQLJoin($tableNumberOrigin,
							$this->fieldNamify($fieldNameOrigin),
							$this->tableNamify($tableNameDestination),
							$this->numberOfJoins);
		
		$this->joins[$tableNumberOrigin][$fieldNumberOrigin] = $join;
		
		$this->joins[$this->numberOfJoins] = array();
		$this->joinsReverse[$this->numberOfJoins] = $join;
		
		return $this->numberOfJoins;
	}
	
	public function createEqualityCondition(\Good\Manners\Storable $to)
	{
		return new \Good\Manners\EqualityCondition($this, $to);
	}
	public function createInequalityCondition(\Good\Manners\Storable $to)
	{
		return new \Good\Manners\InequalityCondition($this, $to);
	}
	public function createGreaterCondition(\Good\Manners\Storable $to)
	{
		return new \Good\Manners\GreaterCondition($this, $to);
	}
	public function createGreaterOrEqualsCondition(\Good\Manners\Storable $to)
	{
		return new \Good\Manners\GreaterOrEqualsCondition($this, $to);
	}
	public function createLessCondition(\Good\Manners\Storable $to)
	{
		return new \Good\Manners\LessCondition($this, $to);
	}
	public function createLessOrEqualsCondition(\Good\Manners\Storable $to)
	{
		return new \Good\Manners\LessOrEqualsCondition($this, $to);
	}
	public function createAndCondition(\Good\Manners\Condition $condition1, \Good\Manners\Condition $condition2)
	{
		return new \Good\Manners\AndCondition($this, $condition1, $condition2);
	}
	public function createOrCondition(\Good\Manners\Condition $condition1, \Good\Manners\Condition $condition2)
	{
		return new \Good\Manners\OrCondition($this, $condition1, $condition2);
	}
	
	public function setCurrentConditionProcessor(ConditionProcessor $value)
	{
		$this->currentConditionWriter = $value;
	}
	
	public function processEqualityCondition(\Good\Manners\Storable $to)
	{
		$this->currentConditionWriter->processEqualityCondition($to);
	}
	public function processInequalityCondition(\Good\Manners\Storable $to)
	{
		$this->currentConditionWriter->processInequalityCondition($to);
	}
	public function processGreaterCondition(\Good\Manners\Storable $to)
	{
		$this->currentConditionWriter->processGreaterCondition($to);
	}
	public function processGreaterOrEqualsCondition(\Good\Manners\Storable $to)
	{
		$this->currentConditionWriter->processGreaterOrEqualsCondition($to);
	}
	public function processLessCondition(\Good\Manners\Storable $to)
	{
		$this->currentConditionWriter->processLessCondition($to);
	}
	public function processLessOrEqualsCondition(\Good\Manners\Storable $to)
	{
		$this->currentConditionWriter->processLessOrEqualsCondition($to);
	}
	public function processAndCondition(\Good\Manners\Condition $condition1, \Good\Manners\Condition $condition2)
	{
		$this->currentConditionWriter->processAndCondition($condition1, $condition2);
	}
	public function processOrCondition(\Good\Manners\Condition $condition1, \Good\Manners\Condition $condition2)
	{
		$this->currentConditionWriter->processOrCondition($condition1, $condition2);
	}
	
	public function setCurrentPropertyVisitor(PropertyVisitor $value)
	{
		$this->currentPropertyVisitor = $value;
	}
	
	public function visitReferenceProperty($name, $datatypeName, $dirty, $null, 
													\Good\Manners\Storable $value = null)
	{
		$this->currentPropertyVisitor->visitReferenceProperty($name, $datatypeName, $dirty, $null, $value);
	}
	public function visitTextProperty($name, $dirty, $null, $value)
	{
		$this->currentPropertyVisitor->visitTextProperty($name, $dirty, $null, $value);
	}
	public function visitIntProperty($name, $dirty, $null, $value)
	{
		$this->currentPropertyVisitor->visitIntProperty($name,$dirty,  $null, $value);
	}
	public function visitFloatProperty($name, $dirty, $null, $value)
	{
		$this->currentPropertyVisitor->visitFloatProperty($name, $dirty, $null, $value);
	}
	public function visitDatetimeProperty($name, $dirty, $null, $value)
	{
		$this->currentPropertyVisitor->visitDatetimeProperty($name, $dirty, $null, $value);
	}
}

?>