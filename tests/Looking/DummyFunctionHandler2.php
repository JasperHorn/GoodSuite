<?php

class DummyFunctionHandler2 implements \Good\Looking\FunctionHandler
{
    function __construct()
    {
    }

    function getHandledFunctions()
    {
        return array('inc');
    }

    function handleFunction($f, \Good\Looking\FunctionHelper $helper, array $args)
    {
        if ($f == 'inc')
        {
            return $args[0] + 1;
        }
    }
}

?>
