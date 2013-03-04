<?php

namespace Good\Rolemodel;

include_once 'DataMember.php';
include_once 'DataType.php';
include_once 'DataModel.php';

class Rolemodel
{
	public function createDataModel($input)
	{
		$dataTypes = array();
		
		foreach ($input as $name => $file)
		{
			$dataTypes[] = $this->fileToDataType($name, $file);
		}
		
		return new DataModel($dataTypes);
	}
	
	private function fileToDataType($name, $file)
	{
		// read the file
		$input = \file_get_contents($file);
		
		// Cutting out php tags out if they are there
		// (they are allowed as an additional method to make the content unaccesible)
		
		if (substr($input, 0, 5) == '<?php')
		{
			$input = \substr($input, 5);
		}
		
		if (substr($input, -2) == '?>')
		{
			$input = \substr($input, 0, -2);
		}
		
		// And now we start parsing the file
		// line by line
		$inputLines = \explode("\r\n", $input);
		
		// building a complicated regex just once
		// (so outside the for loop)
		$regexAttributeSeperator = '(\\s*,\\s*|\\s+)';
		$regexAttributes = '\\[(?P<attributes>[a-zA-Z0-9_]+(' . $regexAttributeSeperator . '[a-zA-Z0-9_]+)*\\s*)?\\]';
		$regexType = '(?P<type>([a-zA-Z_][a-zA-Z0-9_]*|"[a-zA-Z_][a-zA-Z0-9_]*"))';
		$regexName = '(?P<name>[a-zA-Z_][a-zA-Z0-9_]*)';
		$regexDataDefinition = '^\\s*(' . $regexAttributes . '\\s*)?' . $regexType . '\\s+' . $regexName . '\\s*$';
		
		// We'll use this array to build the result in
		$members = array();
		
		for ($i = 0; $i < \count($inputLines); $i++)
		{
			// if the line is only whitespace, we just move on to the next
			if (\preg_match('/^\\s*$/', $inputLines[$i]) != 0)
				continue;
				
			
			if (\preg_match('/' . $regexDataDefinition . '/', $inputLines[$i], $matches) != 0)
			{
				$type = $matches['type'];
				$varName = $matches['name'];
				if ($matches['attributes'] != '')
				{
					$attributes = \preg_split('/' . $regexAttributeSeperator . '/', $matches['attributes']);
				}
				else
				{
					$attributes = array();
				}
				
				$members[] = new DataMember($attributes, $type, $varName);
			}
			else
			{
				// TODO: better error handling outputting, et al
				die("Malformed Datamodel file: " . $file);
			}
		}
		
		return new DataType($file, $name, $members);
	}
}

?>