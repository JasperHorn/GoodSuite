<?php

namespace Good\Memory\SQL;

use Good\Memory\Database as Database;

use Good\Memory\SQLStore;
use Good\Memory\PropertyVisitor;
use Good\Manners\Storable;
use Good\Manners\Condition;

class AdvancedUpdater implements PropertyVisitor
{
	private $db;
	private $store;
	
	private $subquery;
	
	private $sql;
	private $first;
	private $currentTable;
	private $currentReference;
	
	private $condition;
	private $rootTableName;
	
	public function __construct(SQLStore $store, Database\Database $db, $currentTable)
	{
		$this->db = $db;
		$this->store = $store;
		$this->currentTable = $currentTable;
	}
	
	public function update($datatypeName, Condition $condition, 
							Storable $value)
	{
		$this->updateWithRootTableName($datatypeName, $condition, $value, $datatypeName);
	}
	
	public function updateWithRootTableName($datatypeName, Condition $condition, 
													Storable $value, $rootTableName)
	{
		$this->condition = $condition;
		$this->rootTableName = $rootTableName;
	
		$joinDiscoverer = new JoinDiscoverer($this->store, 0);
		$joinDiscoverer->discoverJoins($value);
		
		$this->sql = 'UPDATE ' . $this->store->tableNamify($datatypeName);
		$this->sql .= ' SET ';
		
		$this->first = true;
		$this->currentReference = 0;
		$this->store->setCurrentPropertyVisitor($this);
		$value->acceptStore($this->store);
		
		// if we haven't got a single entry to update, we don't do anything
		// (there is no reason for alarm, though, it may just be that this
		//  table is only used in the ON clause)
		if (!$this->first)
		{
			$conditionWriter = new UpdateConditionWriter($this->store, 0);
			$conditionWriter->writeCondition($condition, $rootTableName, $this->currentTable, $datatypeName);
			
			$this->sql .= ' WHERE ' . $conditionWriter->getCondition();
			
			
			$this->db->query($this->sql);
		}
	}

	private function comma()
	{
		if ($this->first)
		{
			$this->first = false;
		}
		else
		{
			$this->sql .= ', ';
		}
	}
	
	public function visitReferenceProperty($name, $datatypeName, $dirty, $null, 
														Storable $value = null)
	{
		if ($dirty)
		{
			if (!$null && $value->isNew())
			{
				$join = $this->store->getJoin($this->currentTable, $this->currentReference);
				
				$updater = new AdvancedUpdater($this->store, $this->db, $join);
				$updater->updateWithRootTableName($datatypeName, $this->condition, 
																$value, $this->rootTableName);
				$this->store->setCurrentPropertyVisitor($this);
			}
			else
			{
				$this->comma();
				
				$this->sql .= $this->store->fieldNamify($name);
				$this->sql .= ' = ';
			
				if ($null)
				{
					$this->sql .= 'NULL';
				}
				else
				{
					$this->sql .= intval($value->getId());
				}
			}
		}
		
		$this->currentReference++;
	}
	
	public function visitTextProperty($name, $dirty, $null, $value)
	{
		if ($dirty)
		{
			$this->comma();
			
			$this->sql .= $this->store->fieldNamify($name);
			$this->sql .= ' = ';
			
			if ($null)
			{
				$this->sql .= 'NULL';
			}
			else
			{
				$this->sql .= $this->store->parseText($value);
			}
		}
	}
	
	public function visitIntProperty($name, $dirty, $null, $value)
	{
		if ($dirty)
		{
			$this->comma();
			
			$this->sql .= $this->store->fieldNamify($name);
			$this->sql .= ' = ';
			
			if ($null)
			{
				$this->sql .= 'NULL';
			}
			else
			{
				$this->sql .= $this->store->parseInt($value);
			}
		}
	}
	
	public function visitFloatProperty($name, $dirty, $null, $value)
	{
		
		if ($dirty)
		{
			$this->comma();
			
			$this->sql .= $this->store->fieldNamify($name);
			$this->sql .= ' = ';
			
			if ($null)
			{
				$this->sql .= 'NULL';
			}
			else
			{
				$this->sql .= $this->store->parseFloat($value);
			}
		}
	}
	
	public function visitDatetimeProperty($name, $dirty, $null, $value)
	{
		if ($dirty)
		{
			$this->comma();
			
			$this->sql .= $this->store->fieldNamify($name);
			$this->sql .= ' = ';
			
			if ($null)
			{
				$this->sql .= 'NULL';
			}
			else
			{
				$this->sql .= $this->store->parseDatetime($value);
			}
		}
	}
}

?>