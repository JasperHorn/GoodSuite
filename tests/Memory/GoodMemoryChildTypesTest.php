<?php

require_once dirname(__FILE__) . '/../Manners/GoodMannersChildTypesTest.php';

/** 
 * @runTestsInSeparateProcesses
 */
class GoodMemoryChildTypesTest extends GoodMannersChildTypesTest
{
    public function getNewDb()
    {
        require dirname(__FILE__) . '/dbconfig.php';
        
        return new \Good\Memory\Database\MySQL($dbconfig['name'], $dbconfig['host'], 
                                    $dbconfig['port'], $dbconfig['user'], $dbconfig['pass'], '');
    }
    
    public function getNewStorage()
    {
        return new \Good\Memory\SQLStorage($this->getNewDb());
    }
}