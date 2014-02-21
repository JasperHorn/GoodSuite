<?php

namespace Good\Looking;

class Parser
{
    protected $factory;
    protected $inTextMode;
    private $grammar;
    
    public function __construct(Grammar $grammar, AbstractSyntax\Factory $factory)
    {
        $this->factory = $factory;
        $this->grammar = $grammar;
    }
    
    public function parseDocument($input)
    {
        $input = \preg_replace('/' . $this->grammar->comment . '/', '', $input);
        
        $this->inTextMode = true;
        
        $out = $this->factory->createAbstractDocument($this->parseStatementCollection($input));
        
        if ($input !== '')
        {
            throw new \Exception('Error: Could not parse document. Near: ' . substr($input, 0, 50));
        }
        
        return $out;
    }
    
    private function parseIfStructure($condition, &$input)
    {
        $statements = $this->parseStatementCollection($input);
        
        if (\preg_match('/^\\s*' . $this->grammar->controlStructureElse . '\\s*(?<terminator>' . 
                            $this->grammar->statementEnder . '|' .
                                $this->grammar->scriptDelimiterRight . '|$)/', $input, $matches) === 1)
        {
            $else = true;
            
            // remove the processed part
            $this->removeFromStart($input, $matches[0]);
            
            // determine next mode
            $this->determineIfNextModeIsText($matches['terminator']);
            
            $elseStatements = $this->parseStatementCollection($input);
        }
        else
        {
            $else = false;
        }
        
        if (\preg_match('/^\\s*' . $this->grammar->controlStructureEndIf . '\\s*(?<terminator>' . 
                            $this->grammar->statementEnder . '|' .
                                $this->grammar->scriptDelimiterRight . '|$)/', $input, $matches) === 1)
        {
            // remove the processed part
            $this->removeFromStart($input, $matches[0]);
            
            // determine next mode
            $this->determineIfNextModeIsText($matches['terminator']);
            
            if ($else)
            {
                return $this->factory->createIfElseStructure($condition, $statements, $elseStatements);
            }
            else
            {
                return $this->factory->createIfStructure($condition, $statements);
            }
        }
        
        if ($input == '')
        {
            throw new \Exception('Error: End of document found though there was still an "if" that needed to be closed.');
        }
        else if (\preg_match('/^\\s*(?<controlStructure>' . $this->grammar->controlStructureEndFor . '|' .
                                        $this->grammar->controlStructureEndForeach . ')\\s*$/', $input, $matched) === 1)
        {
            throw new \Exception('Error: Control structure mismatch, found <i>' . $matches['controlStructure'] . '</i> while parsing an if.');
        }
        else
        {
            throw new \Exception('Error: Unable to parse. Near: ' . \htmlentities(substr($input, 0, 50)));
        }
    }
    
    private function parseForStructure($from, $to, &$input)
    {
        $statements = $this->parseStatementCollection($input);
        
        if (\preg_match('/^\\s*' . $this->grammar->controlStructureEndFor . '\\s*(?<terminator>' . 
                            $this->grammar->statementEnder . '|' .
                                $this->grammar->scriptDelimiterRight . '|$)/', $input, $matches) === 1)
        {
            // remove the processed part
            $this->removeFromStart($input, $matches[0]);
            
            // determine next mode
            $this->determineIfNextModeIsText($matches['terminator']);
            
            return $this->factory->createForStructure($from, $to, $statements);
        }
        
        if ($input == '')
        {
            throw new \Exception('Error: End of document found though there was still a "for" that needed to be closed.');
        }
        else if (\preg_match('/^\\s*(?<controlStructure>' . $this->grammar->controlStructureEndIf . '|' .
                                        $this->grammar->controlStructureElse . '|' .
                                        $this->grammar->controlStructureEndForeach . ')\\s*$/', $input, $matched) === 1)
        {
            throw new \Exception('Error: Control structure mismatch, found <i>' . $matches['controlStructure'] . '</i> while parsing a for.');
        }
        else
        {
            throw new \Exception('Error: Unable to parse. Near: ' . \htmlentities(substr($input, 0, 20)));
        }
    }
    
    private function parseForeachStructure($array, $varName, &$input)
    {
        $statements = $this->parseStatementCollection($input);
        
        if (\preg_match('/^\\s*' . $this->grammar->controlStructureEndForeach . '\\s*(?<terminator>' . 
                            $this->grammar->statementEnder . '|' .
                                $this->grammar->scriptDelimiterRight . '|$)/', $input, $matches) === 1)
        {
            // remove the processed part
            $this->removeFromStart($input, $matches[0]);
            
            // determine next mode
            $this->determineIfNextModeIsText($matches['terminator']);
            
            return $this->factory->createForeachStructure($array, $varName, $statements);
        }
        
        if ($input == '')
        {
            throw new \Exception('Error: End of document found though there was still a "foreach" that needed to be closed.');
        }
        else if (\preg_match('/^\\s*(?<controlStructure>' . $this->grammar->controlStructureEndIf . '|' .
                                        $this->grammar->controlStructureElse . '|' .
                                        $this->grammar->controlStructureEndFor . ')\\s*$/', $input, $matched) === 1)
        {
            throw new \Exception('Error: Control structure mismatch, found <i>' . $matches['controlStructure'] . '</i> while parsing a foreach.');
        }
        else
        {
            throw new \Exception('Error: Unable to parse. Near: ' . \htmlentities(substr($input, 0, 20)));
        }
    }
    
    private function removeFromStart(&$input, $needle)
    {
        if (strlen($input) > strlen($needle))
        {
            $input = substr($input, strlen($needle));
        }
        else
        {
            $input = '';
        }
    }
        
    private function determineIfNextModeIsText($terminator)
    {
        if (\preg_match('/' . $this->grammar->scriptDelimiterRight . '/', $terminator) == 1)
        {
            $this->inTextMode = true;
        }
    }
    
    private function parseStatementCollection(&$input)
    {
        $statements = array();
        
        // regex for: finding the end of a statement:
        // a statement ender (;), script delimiter (:>) or the end of the string
        $regexTerminator = '(?<terminator>' . $this->grammar->statementEnder . 
                                        '|'. $this->grammar->scriptDelimiterRight . '|$)';
        
        $parseable = true;
        
        while ($parseable && $input != '')
        {
            while ($input !== '' && ($this->inTextMode ||
                      \preg_match('/^\\s*(?:' . $this->grammar->expression . '|\\s*)' . $regexTerminator . '/', $input, $matches) === 1))
            {
                if ($this->inTextMode)
                {
                    // Everthing before delimiter is text
                    // Everything after is for the next iteration of parsing
                    $parts = \preg_split('/' . $this->grammar->scriptDelimiterLeft . '/', $input, 2);
                    
                    if ($parts[0] != '')
                    {
                        $statements[] = $this->factory->createTextBlock($parts[0]);
                    }
                    
                    if (\array_key_exists(1, $parts))
                    {
                        $input = $parts[1];
                    }
                    else
                    {
                        $input = '';
                    }
                    
                    // Since we only stop at a script delimiter or end of input
                    // we always drop out of text a text block
                    $this->inTextMode = false;
                }
                else
                {
                    // if $matches['expression'] does not exist, this is
                    // an empty statement (only whitespace), which means we only have to discard
                    if (array_key_exists('expression', $matches))
                    {
                        // Make a statment out of entire match except the terminating symbol 
                        // (= statement ender, script delimiter or end of input)
                        $statements[] = $this->factory->createStatement(
                                    \substr($matches[0], 0, -1 * strlen($matches['terminator'])));
                        
                        // determine next mode
                        $this->determineIfNextModeIsText($matches['terminator']);
                    }
                    
                    // remove the processed part
                    $this->removeFromStart($input, $matches[0]);
                }
            }
            
            if ($input != '' && preg_match('/^\\s*' . $this->grammar->controlStructureIf . '\\s*' . $regexTerminator . '/', $input, $matches) === 1)
            {
                // remove the processed part and determine next mode
                $this->removeFromStart($input, $matches[0]);
                $this->determineIfNextModeIsText($matches['terminator']);
                
                $statements[] = $this->parseIfStructure($matches['condition'], $input);
            }
            else if ($input != '' && preg_match('/^\\s*' . $this->grammar->controlStructureFor . '\\s*' . $regexTerminator . '/', $input, $matches) === 1)
            {
                // remove the processed part and determine next mode
                $this->removeFromStart($input, $matches[0]);
                $this->determineIfNextModeIsText($matches['terminator']);
                
                $statements[] = $this->parseForStructure($matches['from'], $matches['to'], $input);
            }
            else if ($input != '' && preg_match('/^\\s*' . $this->grammar->controlStructureForeach . '\\s*' . $regexTerminator . '/', $input, $matches) === 1)
            {
                // remove the processed part and determine next mode
                $this->removeFromStart($input, $matches[0]);
                $this->determineIfNextModeIsText($matches['terminator']);
                
                $statements[] = $this->parseForeachStructure($matches['foreachArray'], $matches['foreachVariable'], $input);
            }
            else
            {
                $parseable = false;
            }
        }
        
        return $statements;
    }
}

?>