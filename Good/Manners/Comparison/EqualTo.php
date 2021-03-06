<?php

namespace Good\Manners\Comparison;

use Good\Manners\EqualityComparisonProcessor;

class EqualTo implements EqualityComparison
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function processComparison(EqualityComparisonProcessor $processor)
    {
        $processor->processEqualToComparison($this->value);
    }
}

?>
