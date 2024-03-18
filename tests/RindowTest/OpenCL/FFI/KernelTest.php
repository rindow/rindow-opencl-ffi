<?php
namespace RindowTest\OpenCL\FFI\KernelTest;

use PHPUnit\Framework\TestCase;
use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\OpenCL;
use Rindow\Math\Buffer\FFI\BufferFactory;
use Rindow\OpenCL\FFI\OpenCLFactory;
use RuntimeException;

class KernelTest extends TestCase
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
     * Kernel Simple
     */
    public function testKernelSimple()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $devices = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES);
        $dev_version = $devices->getInfo(0,OpenCL::CL_DEVICE_VERSION);
        // $dev_version = 'OpenCL 1.1 Mesa';
        $isOpenCL110 = strstr($dev_version,'OpenCL 1.1') !== false;
        $queue = $ocl->CommandQueue($context);
        $newHostBufferFactory = $this->newHostBufferFactory();

        $NWITEMS = 64;

        // Construction

        // array type source code
        $sources = [
            "__kernel void saxpy(const global float * x,\n".
            "                    __global float * y,\n".
            "                    const float a)\n".
            "{\n".
            "   uint gid = get_global_id(0);\n".
            "   y[gid] = a* x[gid] + y[gid];\n".
            "}\n"
        ];
        $program = $ocl->Program($context,$sources);
        $program->build();
        $kernel = $ocl->Kernel($program,"saxpy");
        
        // setArg

        $hostX = $newHostBufferFactory->Buffer(
            $NWITEMS,NDArray::float32
        );
        $hostY = $newHostBufferFactory->Buffer(
            $NWITEMS,NDArray::float32
        );
        
        for($i=0;$i<$NWITEMS;$i++) {
            $hostX[$i] = $i;
            $hostY[$i] = $NWITEMS-1-$i;
        }
        $a = 2.0;
        $bufX = $ocl->Buffer($context,intval($NWITEMS*32/8),
            OpenCL::CL_MEM_READ_ONLY|OpenCL::CL_MEM_COPY_HOST_PTR,
            $hostX);
        $bufY = $ocl->Buffer($context,intval($NWITEMS*32/8),
            OpenCL::CL_MEM_READ_WRITE|OpenCL::CL_MEM_COPY_HOST_PTR,
            $hostY);
        $kernel->setArg(0,$bufX);
        $kernel->setArg(1,$bufY);
        $kernel->setArg(2,$a,NDArray::float32);
        
        // enqueueNDRange
        $global_work_size = [$NWITEMS];
        $local_work_size = [1];
        $kernel->enqueueNDRange($queue,$global_work_size,$local_work_size);

        // complete kernel
        $queue->finish();

        $bufY->read($queue,$hostY);
 
        $trues = [
            63.0, 64.0, 65.0, 66.0, 67.0, 68.0, 69.0, 70.0,
            71.0, 72.0, 73.0, 74.0, 75.0, 76.0, 77.0, 78.0,
            79.0, 80.0, 81.0, 82.0, 83.0, 84.0, 85.0, 86.0,
            87.0, 88.0, 89.0, 90.0, 91.0, 92.0, 93.0, 94.0,
            95.0, 96.0, 97.0, 98.0, 99.0,100.0,101.0,102.0,
           103.0,104.0,105.0,106.0,107.0,108.0,109.0,110.0,
           111.0,112.0,113.0,114.0,115.0,116.0,117.0,118.0,
           119.0,120.0,121.0,122.0,123.0,124.0,125.0,126.0,
        ];
       
        for($i=0;$i<$NWITEMS;$i++) {
            $this->assertTrue($hostY[$i] == $trues[$i]);
        }
        
        // enqueueNDRange with null arguments

        $kernel->enqueueNDRange($queue,$global_work_size,
        $local_work_size=null,$global_work_offset=null,$events=null,$wait_events=null);
        $queue->finish();


    }

    /**
     * Kernel Group
     */
    public function testKernelGroup()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $devices = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES);
        $dev_version = $devices->getInfo(0,OpenCL::CL_DEVICE_VERSION);
        // $dev_version = 'OpenCL 1.1 Mesa';
        $isOpenCL110 = strstr($dev_version,'OpenCL 1.1') !== false;
        $queue = $ocl->CommandQueue($context);
        $newHostBufferFactory = $this->newHostBufferFactory();

        // Construction

        // single string source code
        $sources =
        "__kernel void reduce_sum(const global float * x,\n".
        "                    __global float * y,\n".
        "                    __local float * work_sum)\n".
        "{\n".
        "    int lid = get_local_id(0);\n".
        "    int group_size = get_local_size(0);\n".
        "    work_sum[lid] = x[get_global_id(0)];\n".
        "    barrier(CLK_LOCAL_MEM_FENCE);\n".
        "    for(int i = group_size/2; i>0; i >>= 1) {\n".
        "        if(lid < i) {\n".
        "            work_sum[lid] += work_sum[lid + i];\n".
        "        }\n".
        "        barrier(CLK_LOCAL_MEM_FENCE);\n".
        "    }\n".
        "    if(lid == 0) {\n".
        "        y[get_group_id(0)] = work_sum[0];\n".
        "    }\n".
        "}\n";
        $program = $ocl->Program($context,$sources);
        try {
            $program->build();
        } catch(\RuntimeException $e) {
            if($e->getCode() == OpenCL::CL_BUILD_PROGRAM_FAILURE) {
                echo "====BUILD_PROGRAM_FAILURE====\n";
                echo $program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_LOG);
                echo "==============================\n";
            }
            throw $e;
        }
        $kernel = $ocl->Kernel($program,"reduce_sum");
        
        // setArg

        $hostX = $newHostBufferFactory->Buffer(
            64,NDArray::float32
        );
        $hostY = $newHostBufferFactory->Buffer(
            8,NDArray::float32
        );

        for($i=0;$i<count($hostX);$i++) {
            $hostX[$i] = $i;
        }
        $bufX = $ocl->Buffer($context,intval(count($hostX)*32/8),
            OpenCL::CL_MEM_READ_ONLY|OpenCL::CL_MEM_COPY_HOST_PTR,
            $hostX);
        $bufY = $ocl->Buffer($context,intval(count($hostY)*32/8),
            OpenCL::CL_MEM_READ_WRITE|OpenCL::CL_MEM_COPY_HOST_PTR,
            $hostY);
        //$bufX->write($queue,$hostX);

        $groups = count($hostX)/count($hostY);
        
        $kernel->setArg(0,$bufX);
        $kernel->setArg(1,$bufY);
        $kernel->setArg(2,null,intval($bufX->bytes()/$groups));
        
        // enqueueNDRange
        $global_work_size = [count($hostX)];
        $local_work_size = [intval(count($hostX)/$groups)];
        $kernel->enqueueNDRange($queue,$global_work_size,$local_work_size);

        // complete kernel
        $queue->finish();

        $bufX->read($queue,$hostX);
        $bufY->read($queue,$hostY);

        $truesX = [
            0, 1, 2, 3, 4, 5, 6, 7,
            8, 9,10,11,12,13,14,15,
           16,17,18,19,20,21,22,23,
           24,25,26,27,28,29,30,31,
           32,33,34,35,36,37,38,39,
           40,41,42,43,44,45,46,47,
           48,49,50,51,52,53,54,55,
           56,57,58,59,60,61,62,63,
        ];
        $truesY = [
            28,92,156,220,284,348,412,476,
        ];
       
        for($i=0;$i<count($hostX);$i++) {
            $this->assertEquals($truesX[$i],$hostX[$i]);
        }
        for($i=0;$i<count($hostY);$i++) {
            $this->assertEquals($truesY[$i],$hostY[$i]);
        }
        
        // get Info

        $this->assertEquals("reduce_sum",$kernel->getInfo(OpenCL::CL_KERNEL_FUNCTION_NAME));
        $this->assertEquals(3,$kernel->getInfo(OpenCL::CL_KERNEL_NUM_ARGS));
        
        if($this->skipDisplayInfo) {
            return;
        }
        // getWorkGroupInfo\n";

        // intel gpu doesn't support attributes
        // echo "CL_KERNEL_ATTRIBUTES=".$kernel->getInfo(OpenCL::CL_KERNEL_ATTRIBUTES)."\n";

        $this->assertEquals([0,0,0],$kernel->getWorkGroupInfo(OpenCL::CL_KERNEL_COMPILE_WORK_GROUP_SIZE));

        echo "CL_KERNEL_WORK_GROUP_SIZE=".$kernel->getWorkGroupInfo(OpenCL::CL_KERNEL_WORK_GROUP_SIZE)."\n";
        echo "CL_KERNEL_PREFERRED_WORK_GROUP_SIZE_MULTIPLE=".$kernel->getWorkGroupInfo(OpenCL::CL_KERNEL_PREFERRED_WORK_GROUP_SIZE_MULTIPLE)."\n";
        echo "CL_KERNEL_LOCAL_MEM_SIZE=".$kernel->getWorkGroupInfo(OpenCL::CL_KERNEL_LOCAL_MEM_SIZE)."\n";
        echo "CL_KERNEL_PRIVATE_MEM_SIZE=".$kernel->getWorkGroupInfo(OpenCL::CL_KERNEL_PRIVATE_MEM_SIZE)."\n";
        echo "CL_KERNEL_COMPILE_WORK_GROUP_SIZE=[".implode(',',$kernel->getWorkGroupInfo(OpenCL::CL_KERNEL_COMPILE_WORK_GROUP_SIZE))."]\n";
        //try {
        //    echo "CL_KERNEL_GLOBAL_WORK_SIZE=[".implode(',',$kernel->getWorkGroupInfo(OpenCL::CL_KERNEL_GLOBAL_WORK_SIZE))."]\n";
        //} catch(\RuntimeException $e) {
        //    echo $e->getMessage()."\n";
        //}
    }

    /**
     * Kernel Multi gid
     */
    public function testKernelMultiGid()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $devices = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES);
        $dev_version = $devices->getInfo(0,OpenCL::CL_DEVICE_VERSION);
        // $dev_version = 'OpenCL 1.1 Mesa';
        $isOpenCL110 = strstr($dev_version,'OpenCL 1.1') !== false;
        $queue = $ocl->CommandQueue($context);
        $newHostBufferFactory = $this->newHostBufferFactory();

        // Construction

        // single string source code
        $sources =
        "__kernel void multi_gid(const global float * x,\n".
        "                    __global float * y)\n".
        "{\n".
        "    int gid0 = get_global_id(0);\n".
        "    int gid1 = get_global_id(1);\n".
        "    int gid2 = get_global_id(2);\n".
        "    int gsz0 = get_global_size(0);\n".
        "    int gsz1 = get_global_size(1);\n".
        "    int gsz2 = get_global_size(2);\n".
        "    y[gid0*gsz1*gsz2+gid1*gsz2+gid2] = gid0*100+gid1*10+gid2;\n".
        "}\n";
        $program = $ocl->Program($context,$sources);
        try {
            $program->build();
        } catch(\RuntimeException $e) {
            if($e->getCode() == OpenCL::CL_BUILD_PROGRAM_FAILURE) {
                echo "====BUILD_PROGRAM_FAILURE====\n";
                echo $program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_LOG);
                echo "==============================\n";
            }
            throw $e;
        }
        $kernel = $ocl->Kernel($program,"multi_gid");
        
        // setArg


        $hostX = $newHostBufferFactory->Buffer(
            24,NDArray::float32
        );
        $hostY = $newHostBufferFactory->Buffer(
            24,NDArray::float32
        );
        for($i=0;$i<count($hostX);$i++) {
            $hostX[$i] = $i;
            $hostY[$i] = 0;
        }
        $bufX = $ocl->Buffer($context,intval(count($hostX)*32/8),
            OpenCL::CL_MEM_READ_ONLY|OpenCL::CL_MEM_COPY_HOST_PTR,
            $hostX);
        $bufY = $ocl->Buffer($context,intval(count($hostY)*32/8),
            OpenCL::CL_MEM_READ_WRITE|OpenCL::CL_MEM_COPY_HOST_PTR,
            $hostY);
        //$bufX->write($queue,$hostX);
        //$bufY->write($queue,$hostY);

        $kernel = $ocl->Kernel($program,"multi_gid");
        $kernel->setArg(0,$bufX);
        $kernel->setArg(1,$bufY);

        // enqueueNDRange
        $global_work_size = [2,3,4];
        $kernel->enqueueNDRange($queue,$global_work_size);

        // complete kernel
        $queue->finish();

        $bufX->read($queue,$hostX);
        $bufY->read($queue,$hostY);

        $truesY = [
            0,  1,  2,  3,
            10, 11, 12, 13,
            20, 21, 22, 23,
           100,101,102,103,
           110,111,112,113,
           120,121,122,123,
        ];
      
        for($i=0;$i<count($hostY);$i++) {
            $this->assertEquals($truesY[$i],$hostY[$i]);
        }
                
    }

}
