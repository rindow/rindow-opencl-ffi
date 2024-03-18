<?php
namespace RindowTest\OpenCL\FFI\EventListTest;

use PHPUnit\Framework\TestCase;
use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\OpenCL;
use Rindow\OpenCL\FFI\OpenCLFactory;
use RuntimeException;

class EventListTest extends TestCase
{
    protected bool $skipDisplayInfo = true;
    //protected int $default_device_type = OpenCL::CL_DEVICE_TYPE_DEFAULT;
    //protected int $default_device_type = OpenCL::CL_DEVICE_TYPE_GPU;
    static protected int $default_device_type = OpenCL::CL_DEVICE_TYPE_GPU;

    public function newDriverFactory()
    {
        $factory = new OpenCLFactory();
        return $factory;
    }

    public function newContextFromType($ocl)
    {
        try {
            $context = $ocl->Context(self::$default_device_type);
        } catch(RuntimeException $e) {
            if(strpos('clCreateContextFromType',$e->getMessage())===null) {
                throw $e;
            }
            self::$default_device_type = OpenCL::CL_DEVICE_TYPE_DEFAULT;
            $context = $ocl->Context(self::$default_device_type);
        }
        return $context;
    }

    public function testIsAvailable()
    {
        $ocl = $this->newDriverFactory();
        $this->assertTrue($ocl->isAvailable());
    }

    /**
     * construct empty
     */
    public function testConstructEmpty()
    {
        $ocl = $this->newDriverFactory();
        $events1 = $ocl->EventList();
        $this->assertTrue(count($events1)==0);
    }

    /**
     * construct user event
     */
    public function testConstructUserEvent()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $events2 = $ocl->EventList($context);
        $this->assertTrue(count($events2)==1);
    }

    /**
     * move and copy event
     */
    public function testMoveAndCopy()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $events1 = $ocl->EventList();
        $events2 = $ocl->EventList($context);
        $events3 = $ocl->EventList($context);

        $this->assertTrue(count($events3)==1);
        #  move ev2 to ev1
        $events1->move($events2);
        #  copy ev3 to ev1
        $events1->copy($events3);
        $this->assertTrue(count($events1)==2);
        $this->assertTrue(count($events2)==0);
        $this->assertTrue(count($events3)==1);
    }

    /**
     * setStatus to event
     */
    public function testSetStatus()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $events3 = $ocl->EventList($context);

        $this->assertTrue(count($events3)==1);
        $events3->setStatus(OpenCL::CL_COMPLETE);
        $this->assertTrue(true);
    }

    /**
     * SUCCESS construct events with null arguments
     */
    public function testConstructWithNull()
    {
        $ocl = $this->newDriverFactory();
        $events = $ocl->EventList(null);
        $this->assertTrue(count($events)==0);
    }

}
