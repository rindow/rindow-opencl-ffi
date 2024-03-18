<?php
namespace RindowTest\OpenCL\FFI\ContextTest;

use PHPUnit\Framework\TestCase;
use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\OpenCL;
use Rindow\Math\Buffer\FFI\BufferFactory;
use Rindow\OpenCL\FFI\OpenCLFactory;

use Rindow\OpenCL\FFI\DeviceList;
use Rindow\OpenCL\FFI\Context;
use RuntimeException;

class ContextTest extends TestCase
{
    protected bool $skipDisplayInfo = true;

    public function newDriverFactory()
    {
        $factory = new OpenCLFactory();
        return $factory;
    }

    public function newHostBufferFactory()
    {
        $factory = new BufferFactory();
        return $factory;
    }

    public function testIsAvailable()
    {
        $ocl = $this->newDriverFactory();
        $this->assertTrue($ocl->isAvailable());
    }

    /**
     * construct by default
     */
    public function testConstructByDefault()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $devices = $ocl->DeviceList($platforms);
        $total_dev = $devices->count();
        $this->assertTrue($total_dev>=0);

        $context = $ocl->Context(OpenCL::CL_DEVICE_TYPE_DEFAULT);
        $this->assertInstanceof(Context::class,$context);
    }

    /**
     * construct context from type
     */
    public function testConstructFromType()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $devices = $ocl->DeviceList($platforms);
        $total_dev = $devices->count();
        $this->assertTrue($total_dev>=0);

        $count = 0;
        foreach([OpenCL::CL_DEVICE_TYPE_GPU,OpenCL::CL_DEVICE_TYPE_CPU] as $type) {
            try {
                $context = $ocl->Context($type);
                $this->assertInstanceof(Context::class,$context);
                //echo $context->getInfo(OpenCL::CL_CONTEXT_DEVICES)->getInfo(0,OpenCL::CL_DEVICE_NAME)."\n";
                $con_type = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES)->getInfo(0,OpenCL::CL_DEVICE_TYPE);
                $this->assertTrue(true==($con_type&$type));
                $count++;
            } catch(\RuntimeException $e) {
                ;
            }
        }
        $this->assertTrue($total_dev==$count);
    }

    /**
     * construct context from device_id
     */
    public function testConstructFromDeviceId()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $devices = $ocl->DeviceList($platforms);
        $total_dev = $devices->count();
        $this->assertTrue($total_dev>=0);

        $count = 0;
        foreach([OpenCL::CL_DEVICE_TYPE_GPU,OpenCL::CL_DEVICE_TYPE_CPU] as $type) {
            try {
                $devices = $ocl->DeviceList($platforms,0,$type);
                $context = $ocl->Context($devices);
                $this->assertInstanceof(Context::class,$context);
                //echo $context->getInfo(OpenCL::CL_CONTEXT_DEVICES)->getInfo(0,OpenCL::CL_DEVICE_NAME)."\n";
                $con_type = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES)->getInfo(0,OpenCL::CL_DEVICE_TYPE);
                $this->assertTrue(true==($con_type&$type));
                $count++;
            } catch(RuntimeException $e) {
                ;
            }
        }
        $this->assertTrue($total_dev==$count);
    }

    /**
     * get information
     */
    public function testGetInformation()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $devices = $ocl->DeviceList($platforms);
        $total_dev = $devices->count();
        $this->assertTrue($total_dev>=0);

        foreach([OpenCL::CL_DEVICE_TYPE_GPU,OpenCL::CL_DEVICE_TYPE_CPU] as $type) {
            try {
                $context = $ocl->Context($type);
            } catch(RuntimeException $e) {
                if(strpos('clCreateContextFromType',$e->getMessage())===null) {
                    throw $e;
                }
                $context = null;
            }
            if($context==null) {
                continue;
            }
            $this->assertTrue(1==$context->getInfo(OpenCL::CL_CONTEXT_REFERENCE_COUNT));

            $devices = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES);
            $this->assertTrue($devices instanceof DeviceList);
            $properties = $context->getInfo(OpenCL::CL_CONTEXT_PROPERTIES);
            $this->assertTrue(is_array($properties));
            if($this->skipDisplayInfo) {
                return;
            }
            echo "========\n";
            echo $context->getInfo(OpenCL::CL_CONTEXT_REFERENCE_COUNT)."\n";
            echo "CL_CONTEXT_REFERENCE_COUNT=".$context->getInfo(OpenCL::CL_CONTEXT_REFERENCE_COUNT)."\n";
            echo "CL_CONTEXT_NUM_DEVICES=".$context->getInfo(OpenCL::CL_CONTEXT_NUM_DEVICES)."\n";
            echo "CL_CONTEXT_DEVICES=";
            $devices = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES);
            echo "deivces(".$devices->count().")\n";
            for($i=0;$i<$devices->count();$i++) {
                echo "    CL_DEVICE_NAME=".$devices->getInfo($i,OpenCL::CL_DEVICE_NAME)."\n";
                echo "    CL_DEVICE_VENDOR=".$devices->getInfo($i,OpenCL::CL_DEVICE_VENDOR)."\n";
                echo "    CL_DEVICE_TYPE=(";
                $device_type = $devices->getInfo($i,OpenCL::CL_DEVICE_TYPE);
                if($device_type&OpenCL::CL_DEVICE_TYPE_CPU) { echo "CPU,"; }
                if($device_type&OpenCL::CL_DEVICE_TYPE_GPU) { echo "GPU,"; }
                if($device_type&OpenCL::CL_DEVICE_TYPE_ACCELERATOR) { echo "ACCEL,"; }
                if($device_type&OpenCL::CL_DEVICE_TYPE_CUSTOM) { echo "CUSTOM,"; }
                echo ")\n";
                echo "    CL_DRIVER_VERSION=".$devices->getInfo($i,OpenCL::CL_DRIVER_VERSION)."\n";
                echo "    CL_DEVICE_VERSION=".$devices->getInfo($i,OpenCL::CL_DEVICE_VERSION)."\n";
            }
            echo "CL_CONTEXT_PROPERTIES=(".implode(',',array_map(function($x){ return "0x".dechex($x);},
                $context->getInfo(OpenCL::CL_CONTEXT_PROPERTIES))).")\n";
        }
    }
}
