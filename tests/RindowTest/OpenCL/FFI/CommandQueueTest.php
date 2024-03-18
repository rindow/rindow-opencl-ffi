<?php
namespace RindowTest\OpenCL\FFI\CommandQueueTest;

use PHPUnit\Framework\TestCase;
use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\OpenCL;
use Rindow\Math\Buffer\FFI\BufferFactory;
use Rindow\OpenCL\FFI\OpenCLFactory;

use Rindow\OpenCL\FFI\CommandQueue;
use RuntimeException;

class CommandQueueTest extends TestCase
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

    public function newHostBufferFactory()
    {
        $factory = new BufferFactory();
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
     * construct by default
     */
    public function testConstructByDefault()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $queue = $ocl->CommandQueue($context);
        $this->assertInstanceof(CommandQueue::class,$queue);
    }

    /**
     * flush and finish
     */
    public function testFlushAndFinish()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $queue = $ocl->CommandQueue($context);
        $this->assertInstanceof(CommandQueue::class,$queue);

        $hostBuffer = $this->newHostBufferFactory()
            ->Buffer(16,NDArray::float32);
        foreach(range(0,15) as $value) {
            $hostBuffer[$value] = $value;
        }
        $buffer = $ocl->Buffer($context,intval(16*32/8),
            OpenCL::CL_MEM_READ_WRITE);
        $buffer->write($queue,$hostBuffer,0,0,0,false);
        $queue->flush();
        $queue->finish();
        foreach(range(0,15) as $value) {
            $this->assertEquals($value,$hostBuffer[$value]);
        }
    }

    /**
     * get context
     */
    public function testGetContext()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $queue = $ocl->CommandQueue($context);
        
        $getContext = $queue->getContext();
        $this->assertEquals(spl_object_id($getContext),spl_object_id($context));
    }

    /**
     * get information
     */
    public function testGetInformation()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $queue = $ocl->CommandQueue($context);

        $this->assertTrue(1==$queue->getInfo(OpenCL::CL_QUEUE_REFERENCE_COUNT));
        if($this->skipDisplayInfo) {
            return;
        }

        echo "========\n";
        echo "CL_QUEUE_REFERENCE_COUNT=".$queue->getInfo(OpenCL::CL_QUEUE_REFERENCE_COUNT)."\n";
        $devices = $queue->getInfo(OpenCL::CL_QUEUE_DEVICE);
        echo "deivces(".$devices->count().")\n";
        for($i=0;$i<$devices->count();$i++) {
            echo "    CL_DEVICE_NAME=".$devices->getInfo($i,OpenCL::CL_DEVICE_NAME)."\n";
            echo "    CL_DEVICE_VENDOR=".$devices->getInfo($i,OpenCL::CL_DEVICE_VENDOR)."\n";
        }
        echo "CL_QUEUE_PROPERTIES=(".implode(',',array_map(function($x){ return "0x".dechex($x);},
            $queue->getInfo(OpenCL::CL_QUEUE_PROPERTIES))).")\n";
        // OpenCL 2.0
        // echo "CL_QUEUE_SIZE=".$queue->getInfo(OpenCL::CL_QUEUE_SIZE)."\n";

        echo "=======\n";
        echo "CL_CONTEXT_REFERENCE_COUNT=".$context->getInfo(OpenCL::CL_CONTEXT_REFERENCE_COUNT)."\n";
        $ctx2 = $queue->getInfo(OpenCL::CL_QUEUE_CONTEXT);
        var_dump($ctx2);
        echo "CL_CONTEXT_REFERENCE_COUNT1=".$context->getInfo(OpenCL::CL_CONTEXT_REFERENCE_COUNT)."\n";
        echo "CL_CONTEXT_REFERENCE_COUNT2=".$ctx2->getInfo(OpenCL::CL_CONTEXT_REFERENCE_COUNT)."\n";

    }
}
