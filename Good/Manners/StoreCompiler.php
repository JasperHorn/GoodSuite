<?php

require_once dirname(__FILE__) . '/../Rolemodel/Visitor.php';


class GoodMannersStoreCompiler implements GoodRolemodelVisitor
{
	// Compiler level data
	private $outputDir;
	private $dataTypes = array();
	private $output = null;
	private $varName = null;
	private $firstDateType = true;
	private $dataType = null;
	
	private $resolver = null;
	private $resolverVisit = null;
	
	public function __construct($outputDir)
	{
		$this->outputDir = $outputDir;
	}
	
	public function visitDataModel($dataModel)
	{
		// Start off the class 
		$this->output  = "abstract class GoodMannersStore\n";
		$this->output .= "{\n";
		$this->output .= '	private $validationToken;' . "\n";
		$this->output .= "	\n";
		$this->output .= "	public function __construct()\n";
		$this->output .= "	{\n";
		$this->output .= '		$this->validationToken = new GoodMannersValidationToken();' . "\n";
		$this->output .= "	}\n";
		$this->output .= "	\n";
		$this->output .= "	public function __destruct()\n";
		$this->output .= "	{\n";
		$this->output .= '		$this->flush();' . "\n";
		$this->output .= "	}\n";
		$this->output .= "	\n";
		$this->output .= '	abstract public function visitReferenceProperty($name, ' .
								'$datatypeName, $dirty, $null, GoodMannersStorable $value = null);' . "\n";
		$this->output .= '	abstract public function visitTextProperty($name, $dirty, ' .
																	'$null, $value);' . "\n";
		$this->output .= '	abstract public function visitIntProperty($name, $dirty, ' .
																	'$null, $value);' . "\n";
		$this->output .= '	abstract public function visitFloatProperty($name, $dirty, ' .
																	'$null, $value);' . "\n";
		$this->output .= "	\n";
		$this->output .= '	protected function invalidate()' . "\n";
		$this->output .= "	{\n";
		$this->output .= '		$this->validationToken->invalidate();' . "\n";
		$this->output .= '		$this->validationToken = new GoodMannersValidationToken();' . "\n";
		$this->output .= "	}\n";
		$this->output .= "	\n";
	}
	
	public function visitDataType($dataType)
	{
		if ($this->firstDateType)
		{
			$this->firstDateType = false;
		}
		else
		{
			$this->finishDataType();
		}
		$name = $dataType->getName();
		$this->dataType = $name;
		$this->dataTypes[] = $name;
		
		// ucfirst: upper case first (php builtin)
		$this->output .= '	abstract protected function doModifyAny' . ucfirst($name) . 
							'(GoodMannersCondition $condition, ' . $name . ' $modifications);' . "\n";
		$this->output .= '	abstract protected function doGet' . ucfirst($name) .
							'Collection(GoodMannersCondition $condition, ' . $name . 
															'Resolver $resolver);' . "\n";
		$this->output .= "	\n";
		$this->output .= '	abstract protected function saveNew' . ucfirst($name) . 
																's(array $entries);' . "\n";
		$this->output .= '	abstract protected function save' . ucfirst($name) . 
															'Modifications(array $entries);' . "\n";
		$this->output .= '	abstract protected function save' . ucfirst($name) . 
															'Deletions(array $entries);' . "\n";
		$this->output .= "	\n";
		
		$this->output .= '	private $dirty' . ucfirst($name) . 's = array();' . "\n";
		$this->output .= "	\n";
		$this->output .= '	public function dirty' . ucfirst($name) . 
												'(' . $name . ' $storable)' . "\n";
		$this->output .= "	{\n";
		$this->output .= '		$this->dirty' . ucfirst($name) . 's[] = $storable;' . "\n";
		$this->output .= "	}\n";
		$this->output .= "	\n";
		$this->output .= '	public function insert' . ucfirst($name) . 
												'(' . $name . ' $storable)' . "\n";
		$this->output .= "	{\n";
		$this->output .= '		$storable->setStore($this);' . "\n";
		$this->output .= '		$storable->setValidationToken($this->validationToken);' . "\n";
		$this->output .= "		\n";
		$this->output .= '		$this->dirty' . ucfirst($name) . 's[] = $storable;' . "\n";
		$this->output .= "	}\n";
		$this->output .= "\n";
		$this->output .= '	public function modifyAny' . ucfirst($name) .'(GoodMannersCondition ' .
													'$condition, ' . $name . ' $modifications)' . "\n";
		$this->output .= "	{\n";
		$this->output .= '		$this->invalidate();' . "\n";
		$this->output .= "		\n";
		$this->output .= '		$this->doModifyAny' . ucfirst($name) .'($condition, $modifications);' . "\n";
		$this->output .= "	}\n";
		$this->output .= "	\n";
		$this->output .= '	public function get' . ucfirst($name) . 'Collection(GoodMannersCondition ' .
													 '$condition, ' . $name . 'Resolver $resolver)' . "\n";
		$this->output .= "	{\n";
		$this->output .= '		$this->flush();' . "\n";
		$this->output .= '		return $this->doGet' . ucfirst($name) . 
													'Collection($condition, $resolver);' . "\n";
		$this->output .= "	}\n";
		$this->output .= "	\n";
		
		$this->resolver  = "<?php\n";
		$this->resolver .= "\n";
		$this->resolver .= 'require_once $good->getGoodPath() . "/Manners/AbstractResolver.php";' . "\n";
		$this->resolver .= "\n";
		$this->resolver .= 'class ' . $name . 'Resolver extends GoodMannersAbstractResolver' . "\n";
		$this->resolver .= "{\n";
		
		$this->resolverVisit  = '	public function resolverAccept' . 
												'(GoodMannersResolverVisitor $visitor)' . "\n";
		$this->resolverVisit .= "	{\n";
	}
	
	public function visitEnd()
	{
		$this->finishDataType();
	
		$this->output .= '	private $flushes = 0;' . "\n";
		$this->output .= "	\n";
		$this->output .= '	public function flush()' . "\n";
		$this->output .= "	{\n";
		$this->output .= '		$this->flushes++;' . "\n";
		
		foreach ($this->dataTypes as $type)
		{
			$this->output .= '		// Flush all the ' . $type . ' objects' . "\n";
			$this->output .= '		$deleted = array();' . "\n";
			$this->output .= '		$modified = array();' . "\n";
			$this->output .= '		$new = array();' . "\n";
			$this->output .= "		\n";
			// ucfirst: Make first letter uppercase (it's a part of php)
			$this->output .= '		foreach ($this->dirty' . ucfirst($type) . 's as $dirty)' . "\n";
			$this->output .= "		{\n";
			$this->output .= '			if ($dirty->isDeleted() && !$dirty->isNew())' . "\n";
			$this->output .= "			{\n";
			$this->output .= '				$deleted[] = $dirty;' . "\n";
			$this->output .= "			}\n";
			$this->output .= '			else if ($dirty->isNew() && !$dirty->isDeleted())' . "\n";
			$this->output .= "			{\n";
			$this->output .= '				$new[] = $dirty;' . "\n";
			$this->output .= "			}\n";
			$this->output .= '			else if (!$dirty->isNew())' . "\n";
			$this->output .= "			{\n";
			$this->output .= '				$modified[] = $dirty;' . "\n";
			$this->output .= "			}\n";
			$this->output .= "		}\n";
			$this->output .= "		\n";
			$this->output .= '		if (count($new) > 0)' . "\n";
			$this->output .= "		{\n";
			$this->output .= '			$this->saveNew' . ucfirst($type) . 's($new);' . "\n";
			$this->output .= "		}\n";
			$this->output .= "		\n";
			$this->output .= '		if (count($modified) > 0)' . "\n";
			$this->output .= "		{\n";
			$this->output .= '			$this->save' . ucfirst($type) . 'Modifications($modified);' . "\n";
			$this->output .= "		}\n";
			$this->output .= "		\n";
			$this->output .= '		if (count($deleted) > 0)' . "\n";
			$this->output .= "		{\n";
			$this->output .= '			$this->save' . ucfirst($type) . 'Deletions($deleted);' . "\n";
			$this->output .= "		}\n";
			$this->output .= "		\n";
		}
		
		$this->output .= '		$this->flushes--;' . "\n";
		$this->output .= "		\n";
		$this->output .= '		if ($this->flushes == 0 && $this->reflush == true)' . "\n";
		$this->output .= "		{\n";
		$this->output .= '			$this->reflush = false;' . "\n";
		$this->output .= '			$this->flush();' . "\n";
		$this->output .= "		}\n";
		$this->output .= "	}\n";
		$this->output .= "	\n";
		$this->output .= '	private $reflush = false;' . "\n";
		$this->output .= "	\n";
		$this->output .= '	public function reflush()' . "\n";
		$this->output .= "	{\n";
		$this->output .= '		if ($this->flushes == 0)' . "\n";
		$this->output .= "		{\n";
		$this->output .= '			$this->reflush = false;' . "\n";
		$this->output .= '			$this->flush();' . "\n";
		$this->output .= "		}\n";
		$this->output .= '		else' . "\n";
		$this->output .= "		{\n";
		$this->output .= '			$this->reflush = true;' . "\n";
		$this->output .= "		}\n";
		$this->output .= "	}\n";
		$this->output .= "	\n";
		
		
		// neatly start the file
		$top  = "<?php\n";
		$top .= "\n";
		
		$top .= 'require_once $good->getGoodPath() . "/Manners/Condition.php";' . "\n";
		$top .= 'require_once $good->getGoodPath() . "/Manners/ValidationToken.php";' . "\n";
		$top .= 'require_once $good->getGoodPath() . "/Manners/Resolver.php";' . "\n";
		$top .= "\n";
		foreach ($this->dataTypes as $className)
		{
			// TODO: Either make this work in some way, or remove it.
			//$top .= "require_once '" . $className . ".datatype.php';\n";
		}
		
		$top .= "\n";
		
		$this->output = $top . $this->output;
		
		// close the file off
		$this->output .= "}\n";
		$this->output .= "\n";
		$this->output .= "?>";
		
		file_put_contents($this->outputDir . 'Store.php', $this->output);
	}
	
	public function visitDataMember($dataMember)
	{
		$this->varName = $dataMember->getName();
	}
	public function visitTypeReference($type)
	{
		$this->resolver .= '	private $resolved' . ucfirst($this->varName) . ' = null;' . "\n"; 
		$this->resolver .= "	\n";
		$this->resolver .= '	public function resolve' . ucfirst($this->varName) . '()' . "\n"; 
		$this->resolver .= "	{\n";
		$this->resolver .= '		$this->resolved' . ucfirst($this->varName) . ' = ' .
										'new ' . $type->getReferencedType() . 
																'Resolver($this->root);' . "\n";
		$this->resolver .= "		\n";
		$this->resolver .= '		return $this->resolved' . ucfirst($this->varName) . ';' . "\n"; 
		$this->resolver .= "	}\n";
		$this->resolver .= "	\n";
		$this->resolver .= '	public function get' . ucfirst($this->varName) . '()' . "\n"; 
		$this->resolver .= "	{\n";
		$this->resolver .= '		return $this->resolved' . ucfirst($this->varName) . ';' . "\n"; 
		$this->resolver .= "	}\n";
		$this->resolver .= "	\n";
		
		$this->resolverVisit .= '		if ($this->resolved' . ucfirst($this->varName) . ' != null)' . "\n";
		$this->resolverVisit .= "		{\n";
		$this->resolverVisit .= '			$visitor->resolverVisitResolvedReferenceProperty("' .
											$this->varName . '", "' . $type->getReferencedType() . 
											'", ' . '$this->resolved' . ucfirst($this->varName) . 
											');' . "\n";
		$this->resolverVisit .= "		}\n";
		$this->resolverVisit .= '		else' . "\n";
		$this->resolverVisit .= "		{\n";
		$this->resolverVisit .= '			$visitor->resolverVisitUnresolvedReferenceProperty(' . 
											'"' . $this->varName . '");' . "\n";
		$this->resolverVisit .= "		}\n";
	}
	public function visitTypePrimitiveText($type)
	{
		$this->visitNonReference();
	}
	public function visitTypePrimitiveInt($type)
	{
		$this->visitNonReference();
	}
	public function visitTypePrimitiveFloat($type)
	{
		$this->visitNonReference();
	}
	
	private function visitNonReference()
	{
		$this->resolver .= '	private $orderNumber' . ucfirst($this->varName) . ' = -1;' . "\n";
		$this->resolver .= '	private $orderDirection' . ucfirst($this->varName) . ' = -1;' . "\n";
		$this->resolver .= "	\n";
		$this->resolver .= '	public function orderBy' . ucfirst($this->varName) . 'Asc()' . "\n";
		$this->resolver .= "	{\n";
		$this->resolver .= '		$this->orderNumber' . ucfirst($this->varName) .
														' = $this->drawOrderTicket();' . "\n";
		$this->resolver .= '		$this->orderDirection' . ucfirst($this->varName) . 
														' = self::ORDER_DIRECTION_ASC;' . "\n";
		$this->resolver .= "	}\n";
		$this->resolver .= "	\n";
		$this->resolver .= '	public function orderBy' . ucfirst($this->varName) . 'Desc()' . "\n";
		$this->resolver .= "	{\n";
		$this->resolver .= '		$this->orderNumber' . ucfirst($this->varName) .
														' = $this->drawOrderTicket();' . "\n";
		$this->resolver .= '		$this->orderDirection' . ucfirst($this->varName) . 
														' = self::ORDER_DIRECTION_DESC;' . "\n";
		$this->resolver .= "	}\n";
		$this->resolver .= "	\n";
		
		$this->resolverVisit .= '		$visitor->resolverVisitNonReferenceProperty("' .
															$this->varName . '");' . "\n";
		$this->resolverVisit .= '		if ($this->orderNumber' . ucfirst($this->varName) . ' != -1)' . "\n";
		$this->resolverVisit .= "		{\n";
		$this->resolverVisit .= '			if ($this->orderDirection' . ucfirst($this->varName) . 
														'== self::ORDER_DIRECTION_ASC)' . "\n";
		$this->resolverVisit .= "			{\n";
		$this->resolverVisit .= '				$visitor->resolverVisitOrderAsc($this->orderNumber' 
													. ucfirst($this->varName) . ', "'
													. $this->varName . '");' . "\n";
		$this->resolverVisit .= "			}\n";
		$this->resolverVisit .= '			else' . "\n";
		$this->resolverVisit .= "			{\n";
		$this->resolverVisit .= '				$visitor->resolverVisitOrderDesc($this->orderNumber' 
													. ucfirst($this->varName) . ', "'
													. $this->varName . '");' . "\n";
		$this->resolverVisit .= "			}\n";
		$this->resolverVisit .= "		}\n";
	}
	
	private function finishDataType()
	{
		$this->resolverVisit .= "	}\n";
		$this->resolverVisit .= "	\n";
		
		$this->resolver .= $this->resolverVisit;
		
		$this->resolver .= "}\n";
		$this->resolver .= "\n";
		$this->resolver .= "?>";
		
		file_put_contents($this->outputDir . $this->dataType . 'Resolver.php', $this->resolver);
	}
}

?>