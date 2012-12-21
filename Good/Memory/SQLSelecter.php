<?php

require_once dirname(__FILE__) . '/../Manners/ResolverVisitor.php';
require_once dirname(__FILE__) . '/../Manners/Resolver.php';

require_once dirname(__FILE__) . '/SQLConditionWriter.php';

class GoodMemorySQLSelecter implements GoodMannersResolverVisitor
{
	private $db;
	private $store;
	
	private $subquery;
	
	private $sql;
	private $currentTable;
	private $currentReference;
	
	private $order = array();
	
	public function __construct(GoodMemorySQLStore $store, GoodMemoryDatabase $db, $currentTable)
	{
		$this->db = $db;
		$this->store = $store;
		$this->currentTable = $currentTable;
	}
	
	
	public function select($datatypeName, GoodMannersCondition $condition, GoodMannersResolver $resolver)
	{
		$this->sql = "SELECT t0.id AS t0_id";
		
		$this->currentReference = 0;
		$resolver->resolverAccept($this);
		
		$this->sql .= $this->writeQueryWithoutSelect($datatypeName, $condition);
		
		$this->db->query($this->sql);
		
		return $this->db->getResult();
	}
	
	public function writeQueryWithoutSelect($datatypeName, 
											GoodMannersCondition $condition)
	{
		$sql  = " FROM " . $this->store->tableNamify($datatypeName) . " AS t0";
		
		$conditionWriter = new GoodMemorySQLConditionWriter($this->store, 0);
		$conditionWriter->writeCondition($condition);
		
		foreach ($this->store->getJoins() as $somejoins)
		{
			foreach ($somejoins as $join)
			{
				$sql .= ' JOIN ' . $this->store->tableNamify($join->tableNameDestination) . 
															' AS t' . $join->tableNumberDestination;
				$sql .= ' ON t' . $join->tableNumberOrigin . '.' . 
											$this->store->fieldNamify($join->fieldNameOrigin);
				$sql .= ' = t' . $join->tableNumberDestination . '.id';
			}
		}
		
		$sql .= ' WHERE ' . $conditionWriter->getCondition();
		
		// Code below can't simply be replaced by a foreach or implode,
		// because that will happen in the order the entries are created
		// and we want to use the numerical indices as order.
		// One could use "ksort", but I believe this is more efficient
		// in most cases.
		for ($i = 0; $i < count($this->order); $i++)
		{
			if ($i == 0)
			{
				$sql .= ' ORDER BY ' . $this->order[$i];
			}
			else
			{
				$sql .= ', ' . $this->order[$i];
			}
		}
		
		return $sql;
	}
	
	public function resolverVisitResolvedReferenceProperty($name, $datatypeName, GoodMannersResolver $resolver)
	{
		if ($resolver == null)
		{
			// resolver should only be null if resolved is false
			// just checking here (maybe this should throw an error,
			// but I'd say it's only a flaw in Good not outside it 
			// that can trigger this)
		}
		
		$this->sql .= ', ';
		$this->sql .= 't' . $this->currentTable . '.' . $name;
		$this->sql .= ' AS t' . $this->currentTable . '_' . $name;
	
		$join = $this->store->getJoin($this->currentTable, $this->currentReference);
		
		if ($join == -1)
		{
			$join = $this->store->createJoin($this->currentTable,
											 $name, 
											 $this->currentReference, 
											 $datatypeName);
		}
				
		$this->sql .= ', ';
		$this->sql .= 't' . $join . '.id AS t' . $join . '_id';
		
		$currentTable = $this->currentTable;
		$this->currentTable = $join;
		$resolver->resolverAccept($this);
		$this->currentTable = $currentTable;
		
		$this->currentReference++;
	}
	
	public function resolverVisitUnresolvedReferenceProperty($name)
	{
		$this->currentReference++;
	}
	
	public function resolverVisitNonReferenceProperty($name)
	{
		$this->sql .= ', ';
		
		$this->sql .= 't' . $this->currentTable . '.' . $this->store->fieldNamify($name);
		$this->sql .= ' AS t' . $this->currentTable . '_' . $this->store->fieldNamify($name);

	}
	
	public function resolverVisitOrderAsc($number, $name)
	{
		$this->order[$number] = 't' . $this->currentTable . '_' . 
						$this->store->fieldnamify($name) . ' ASC';
	}
	
	public function resolverVisitOrderDesc($number, $name)
	{
		$this->order[$number] = 't' . $this->currentTable . '_' . 
						$this->store->fieldnamify($name) . ' DESC';
	}
}

?>