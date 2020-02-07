<?php

/**
 * @runTestsInSeparateProcesses
 */
abstract class GoodMannersGetTest extends \PHPUnit\Framework\TestCase
{
    private $storage;

    abstract public function getNewStorage();
    // this function should be removed, but is used for clearing the database at the moment
    abstract public function getNewDb();
    abstract public function truncateTable($table);

    // This could be done just once for all the tests and it would even be necessary
    // to run the tests in this class in a single process.
    // However, since we can't run these tests in the same process as those from other
    // classes (we would have namespace collisions for Storage and SQLStorage)
    // we have to run every test in different class, and setUpBeforeClass doesn't
    // play well with that. As such, we'll have to call this function from
    // setUp instead of having PHPUnit do its magic.
    public static function _setUpBeforeClass()
    {
        // Garbage collector causes segmentation fault, so we disable
        // for the duration of the test case
        gc_disable();
        file_put_contents(dirname(__FILE__) . '/../testInputFiles/MyGetType.datatype',
                                                                            "datatype MyGetType\n" .
                                                                            "{" .
                                                                            "   int myInt;\n" .
                                                                            "   float myFloat;\n".
                                                                            "   text myText;\n" .
                                                                            "   datetime myDatetime;\n" .
                                                                            '   "OtherType" myOtherType;' . "\n" .
                                                                            '   "MyGetType" myCircular;' . "\n" .
                                                                            "}\n");

        file_put_contents(dirname(__FILE__) . '/../testInputFiles/OtherType.datatype',
                                                                            "datatype OtherType { int yourInt; }");

        $rolemodel = new \Good\Rolemodel\Rolemodel();
        $schema = $rolemodel->createSchema(array(dirname(__FILE__) . '/../testInputFiles/MyGetType.datatype',
                                                   dirname(__FILE__) . '/../testInputFiles/OtherType.datatype'));

        $service = new \Good\Service\Service();
        $service->compile(array(new \Good\Manners\Modifier\Storable()), $schema, dirname(__FILE__) . '/../generated/');

        require dirname(__FILE__) . '/../generated/MyGetType.datatype.php';
        require dirname(__FILE__) . '/../generated/OtherType.datatype.php';

        require dirname(__FILE__) . '/../generated/MyGetTypeResolver.php';
        require dirname(__FILE__) . '/../generated/OtherTypeResolver.php';
    }

    public static function _tearDownAfterClass()
    {
        unlink(dirname(__FILE__) . '/../testInputFiles/MyGetType.datatype');
        unlink(dirname(__FILE__) . '/../testInputFiles/OtherType.datatype');
        unlink(dirname(__FILE__) . '/../generated/MyGetType.datatype.php');
        unlink(dirname(__FILE__) . '/../generated/OtherType.datatype.php');
        unlink(dirname(__FILE__) . '/../generated/MyGetTypeResolver.php');
        unlink(dirname(__FILE__) . '/../generated/OtherTypeResolver.php');
        unlink(dirname(__FILE__) . '/../generated/GeneratedBaseClass.php');

        if (ini_get('zend.enable_gc'))
        {
            gc_enable();
        }
    }

    public function setUp(): void
    {
        $this->_setUpBeforeClass();

        // just doubling this up (from tearDown) to be sure
        // this should be handled natively once that is implemented
        $this->truncateTable('mygettype');
        $this->truncateTable('othertype');

        $storage = $this->getNewStorage();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $storage->insert($ins);

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $storage->insert($ins);

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $storage->insert($ins);

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $storage->insert($ins);

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $storage->insert($ins);

        $storage->flush();

        // new Storage, so communication will have to go through data storage
        $this->storage = $this->getNewStorage();
    }

    public function tearDown(): void
    {
        // Just doing this already to make sure the deconstructor will hasve
        // side-effects at an unspecified moment...
        // (at which point the database will probably be in a wrong state for this)
        $this->storage->flush();

        // this should be handled through the GoodManners API once that is implemented
        $this->truncateTable('mygettype');
        $this->truncateTable('othertype');

        $this->_tearDownAfterClass();
    }

    private function array_search_specific($needle, $haystack)
    {
        // this is sort of a array_search
        // (it has to ignore any additional fields, though)
        foreach ($haystack as $key => $hay)
        {
            // I wanted to do strict checking here, but at the moment
            // all the values from the database are strings, so that's
            // not very useful.
            // I hope one day this'll be fixed, though.
            if ($hay->myInt == $needle->myInt &&
                $hay->myFloat == $needle->myFloat &&
                $hay->myText == $needle->myText &&
                $hay->myDatetime == $needle->myDatetime &&
                // they are both null
                (($hay->myOtherType === null && $needle->myOtherType === null) ||
                // or neither is null (so we won't be calling functions on null)
                // and they are the same
                 ($hay->myOtherType !== null && $needle->myOtherType !== null &&
                  $hay->myOtherType->yourInt == $needle->myOtherType->yourInt)) &&
                // they are both null
                (($hay->myCircular === null && $needle->myCircular === null) ||
                // or neither is null (so we won't be calling functions on null)
                // and they are the same
                 ($hay->myCircular !== null && $needle->myCircular !== null &&
                  $hay->myCircular->myInt == $needle->myCircular->myInt &&
                  $hay->myCircular->myFloat == $needle->myCircular->myFloat &&
                  $hay->myCircular->myText == $needle->myCircular->myText &&
                  $hay->myCircular->myDatetime == $needle->myCircular->myDatetime)))
            {
                return $key;
            }
        }

        return false;
    }

    private function assertContainsAndReturnIndex_specific($needle, $haystack)
    {
        $pos = $this->array_search_specific($needle, $haystack);

        if ($pos === false)

        {
            // this will always fail
            // basically, we tested before with a couple less restirctions
            // and now we just use the general function to get nice ouput
            // it'll contain some differences that don't matter, but that's
            // a small price to pay
            $this->assertContains($needle, $haystack);
        }

        return $pos;
    }

    public function testGetAll()
    {
        // At the moment we don't have a proper api to get any,
        // but this trick does do the same
        $type = new MyGetType();
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    public function testGetLessThan()
    {
        $type = new MyGetType();
        $type->myInt = 5;
        $any = new \Good\Manners\Condition\LessThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    public function testGetLessOrEqual()
    {
        $type = new MyGetType();
        $type->myInt = 5;
        $any = new \Good\Manners\Condition\LessOrEqual($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    public function testGetGreaterThan()
    {
        $type = new MyGetType();
        $type->myInt = 5;
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    public function testGetGreaterOrEqual()
    {
        $type = new MyGetType();
        $type->myInt = 5;
        $any = new \Good\Manners\Condition\GreaterOrEqual($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    public function testGetEqualTo()
    {
        $type = new MyGetType();
        $type->myInt = 5;
        $any = new \Good\Manners\Condition\EqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    public function testGetNotEqualTo()
    {
        $type = new MyGetType();
        $type->myInt = 5;
        $any = new \Good\Manners\Condition\NotEqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetEqualTo
     */
    public function testGetEqualToReference()
    {
        // First, we get the referenced object
        // (we have to do this to get the id of the object,
        //  which is used when we compare to the specific reference,
        //  which is exactly what the whole point of this test is)
        $otherType = new OtherType();
        $otherType->yourInt = 80;

        $any = new \Good\Manners\Condition\EqualTo($otherType);

        $collection = $this->storage->getCollection($any);

        $referenced = $collection->getNext();

        // Then, we get the result with that reference
        $type = new MyGetType();
        $type->myOtherType = $referenced;
        $any = new \Good\Manners\Condition\EqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetLessThan
     * @depends testGetGreaterThan
     */
    public function testGetAnd()
    {
        $type = new MyGetType();
        $type->myInt = 4;
        $greater = new \Good\Manners\Condition\GreaterThan($type);

        $type = new MyGetType();
        $type->myInt = 10;
        $less = new \Good\Manners\Condition\LessThan($type);

        $and = new \Good\Manners\Condition\AndCondition($less, $greater);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($and, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetLessThan
     * @depends testGetGreaterThan
     */
    public function testGetOr()
    {
        $type = new MyGetType();
        $type->myInt = 5;
        $greater = new \Good\Manners\Condition\GreaterThan($type);

        $less = new \Good\Manners\Condition\LessThan($type);

        $type = new MyGetType();
        $type->myInt = 8;
        $greater = new \Good\Manners\Condition\GreaterThan($type);

        $and = new \Good\Manners\Condition\OrCondition($less, $greater);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($and, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    public function testGetReferenceIsNull()
    {
        $type = new MyGetType();
        $type->myOtherType = null;
        $any = new \Good\Manners\Condition\EqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    public function testGetReferenceIsNotNull()
    {
        $type = new MyGetType();
        $type->myOtherType = null;
        $any = new \Good\Manners\Condition\NotEqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetLessThan
     */
    public function testGetByPropertyOfReference()
    {
        $type = new MyGetType();
        $type->myOtherType = new OtherType();
        $type->myOtherType->yourInt = 85;
        $any = new \Good\Manners\Condition\LessThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetGreaterThan
     * @depends testGetByPropertyOfReference
     */
    public function testGetByTwoValuesInOneCondition()
    {
        // At the moment we don't have a proper api to get any,
        // but this trick does do the same
        $type = new MyGetType();
        $type->myInt = 4;
        $type->myOtherType = new OtherType();
        $type->myOtherType->yourInt = 45;
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetGreaterThan
     */
    public function testGetByFloat()
    {
        // Ints are already tested as we use them above everywhere
        $type = new MyGetType();
        $type->myFloat = 6.0;
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetEqualTo
     */
    public function testGetByText()
    {
        $type = new MyGetType();
        $type->myText = "Twenty";
        $any = new \Good\Manners\Condition\EqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetGreaterThan
     */
    public function testGetByDatetime()
    {
        $type = new MyGetType();
        $type->myDatetime = new DateTimeImmutable('2006-06-06');
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetEqualTo
     */
    public function testGetByIntIsNull()
    {
        $type = new MyGetType();
        $type->myInt = null;
        $any = new \Good\Manners\Condition\EqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetEqualTo
     */
    public function testGetByFloatIsNull()
    {
        $type = new MyGetType();
        $type->myFloat = null;
        $any = new \Good\Manners\Condition\EqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetEqualTo
     */
    public function testGetByTextIsNull()
    {
        $type = new MyGetType();
        $type->myText = null;
        $any = new \Good\Manners\Condition\EqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetEqualTo
     */
    public function testGetByDatetimeIsNull()
    {
        $type = new MyGetType();
        $type->myDatetime = null;
        $any = new \Good\Manners\Condition\EqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetNotEqualTo
     */
    public function testGetByIntIsNotNull()
    {
        // At the moment we don't have a proper api to get any,
        // but this trick does do the same
        $type = new MyGetType();
        $type->myInt = null;
        $any = new \Good\Manners\Condition\NotEqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetNotEqualTo
     */
    public function testGetByFloatIsNotNull()
    {
        $type = new MyGetType();
        $type->myFloat = null;
        $any = new \Good\Manners\Condition\NotEqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetNotEqualTo
     */
    public function testGetByTextIsNotNull()
    {
        $type = new MyGetType();
        $type->myText = null;
        $any = new \Good\Manners\Condition\NotEqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetNotEqualTo
     */
    public function testGetByDatetimeIsNotNull()
    {
        $type = new MyGetType();
        $type->myDatetime = null;
        $any = new \Good\Manners\Condition\NotEqualTo($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetAll
     */
    public function testGetSortedAscending()
    {
        // Still the get any trick
        $type = new MyGetType();
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $resolver->orderByMyIntAsc();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $this->assertSame(null, $collection->getNext());
    }

    /**
     * @depends testGetAll
     */
    public function testGetSortedDescending()
    {
        // Still the get any trick
        $type = new MyGetType();
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $resolver->orderByMyIntDesc();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $this->assertSame(null, $collection->getNext());
    }

    /**
     * @depends testGetAll
     * @depends testGetSortedAscending
     * @depends testGetSortedDescending
     */
    public function testGetDoubleSorted()
    {
        // Still the get any trick
        $type = new MyGetType();
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $resolver->orderByMyFloatAsc();
        $resolver->orderByMyIntDesc();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $type = $collection->getNext();
        $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

        $expectedResults = array();

        $this->assertSame(null, $collection->getNext());
    }

    /**
     * @depends testGetAll
     */
    public function testGetAllUnresolvedReference()
    {
        // same ol' trick for getting any
        $type = new MyGetType();
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            // do an isResolved check here
            // and then set to null (because you can't access unresolved properties)
            // However, the first isn't possible yet and the second isn't necessary yet

            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetAll
     */
    public function testGetCircularReference()
    {
        // First, we need to create a circular reference:
        $type = new MyGetType();
        $any = new \Good\Manners\Condition\GreaterThan($type);
        $resolver = new MyGetTypeResolver();
        $resolver->orderByMyIntAsc();
        $collection = $this->storage->getCollection($any, $resolver);
        foreach ($collection as $type)
        {
            if ($type->myInt == 4)
            {
                $ref = $type;
            }
            else if ($type->myInt == 10)
            {
                $ref->myCircular = $type;
                $type->myCircular = $ref;
            }
        }
        $this->storage->flush();

        // same ol' trick for getting any
        $type = new MyGetType();
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyCircular();

        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ins->myOtherType = null;
        $expectedResults[] = $ins;
        $int4 = $ins;

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = $int4;
        $expectedResults[] = $ins;
        $int4->myCircular = $ins;

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        foreach ($collection as $type)
        {
            // do an isResolved check here
            // and then set to null (because you can't access unresolved properties)
            // However, the first isn't possible yet and the second isn't necessary yet

            $pos = $this->assertContainsAndReturnIndex_specific($type, $expectedResults);

            array_splice($expectedResults, $pos, 1);
        }

        $this->assertSame(array(), $expectedResults);
    }

    /**
     * @depends testGetAll
     */
    public function testNestedForeachOnStorableCollection()
    {
        // Still the get any trick
        $type = new MyGetType();
        $any = new \Good\Manners\Condition\GreaterThan($type);

        $resolver = new MyGetTypeResolver();
        $resolver->resolveMyOtherType();
        $resolver->orderByMyIntDesc();
        $collection = $this->storage->getCollection($any, $resolver);

        $expectedResults = array();

        $ins = new MyGetType();
        $ins->myInt = 10;
        $ins->myFloat = 10.10;
        $ins->myText = "Ten";
        $ins->myDatetime = new \DateTimeImmutable('2010-10-10');
        $ins->myOtherType = null;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 8;
        $ins->myFloat = 10.10;
        $ins->myText = null;
        $ins->myDatetime = new \DateTimeImmutable('2008-08-08');
        $ref = new OtherType();
        $ref->yourInt = 40;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 5;
        $ins->myFloat = null;
        $ins->myText = "Five";
        $ins->myDatetime = new \DateTimeImmutable('2005-05-05');
        $ref = new OtherType();
        $ref->yourInt = 80;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = 4;
        $ins->myFloat = 4.4;
        $ins->myText = "Four";
        $ins->myDatetime = new \DateTimeImmutable('2004-04-04');
        $ref = new OtherType();
        $ref->yourInt = 90;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $ins = new MyGetType();
        $ins->myInt = null;
        $ins->myFloat = 20.20;
        $ins->myText = "Twenty";
        $ins->myDatetime = null;
        $ref = new OtherType();
        $ref->yourInt = 5;
        $ins->myOtherType = $ref;
        $ins->myCircular = null;
        $expectedResults[] = $ins;

        $exp1 = $expectedResults;

        foreach ($collection as $type)
        {
            $pos = $this->assertContainsAndReturnIndex_specific($type, $exp1);

            array_splice($exp1, $pos, 1);

            $exp2 = $expectedResults;

            foreach ($collection as $type)
            {
                $pos = $this->assertContainsAndReturnIndex_specific($type, $exp2);

                array_splice($exp2, $pos, 1);
            }

            $this->assertSame(array(), $exp2);
        }

        $this->assertSame(array(), $exp1);
    }
}

?>
