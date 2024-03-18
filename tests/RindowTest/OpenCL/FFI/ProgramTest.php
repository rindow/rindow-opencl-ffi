<?php
namespace RindowTest\OpenCL\FFI\ProgramTest;

use PHPUnit\Framework\TestCase;
use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\OpenCL;
use Rindow\Math\Buffer\FFI\BufferFactory;
use Rindow\OpenCL\FFI\OpenCLFactory;

use Rindow\OpenCL\FFI\Program;
use RuntimeException;

class ProgramTest extends TestCase
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

    public function safestring($string)
    {
        $out = '';
        $string = str_split($string);
        $len = count($string);
        for($i=0;$i<$len;$i++) {
            $c = ord($string[$i]);
            if($c>=32&&$c<127) {
                $out .= chr($c);
            } elseif($c==10||$c==13) {
                $out .= "\n";
            } else {
                $out .= '($'.dechex($c).')';
            }
        }
        return $out;
    }

    public function compile_error($program,$e)
    {
        echo $e->getMessage();
        switch($e->getCode()) {
            case OpenCL::CL_BUILD_PROGRAM_FAILURE: {
                echo "CL_PROGRAM_BUILD_STATUS=".$program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_STATUS)."\n";
                echo "CL_PROGRAM_BUILD_OPTIONS=".safestring($program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_OPTIONS))."\n";
                echo "CL_PROGRAM_BUILD_LOG=".safestring($program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_LOG))."\n";
                echo "CL_PROGRAM_BINARY_TYPE=".safestring($program->getBuildInfo(OpenCL::CL_PROGRAM_BINARY_TYPE))."\n";
            }
            case OpenCL::CL_COMPILE_PROGRAM_FAILURE: {
                echo "CL_PROGRAM_BUILD_LOG=".safestring($program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_LOG))."\n";
            }
        }
        throw $e;
    }

    public function testIsAvailable()
    {
        $ocl = $this->newDriverFactory();
        $this->assertTrue($ocl->isAvailable());
    }

    /**
     * construct and build
     */
    public function testConstructAndBuild()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);
        $devices = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES);
        $dev_version = $devices->getInfo(0,OpenCL::CL_DEVICE_VERSION);
        //$dev_version = 'OpenCL 1.1 Mesa';
        $isOpenCL110 = strstr($dev_version,'OpenCL 1.1') !== false;
        
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

        try {
            $program->build();
        } catch(\RuntimeException $e) {
            $this->compile_error($program,$e);
            throw $e;
        }
        $this->assertEquals(0,$program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_STATUS));
    }

    /**
     * construct with null
     */
    public function testConstructWithNull()
    {
        $ocl = $this->newDriverFactory();
        $sources = [
            "__kernel void saxpy(const global float * x,\n".
            "                    __global float * y,\n".
            "                    const float a)\n".
            "{\n".
            "   uint gid = get_global_id(0);\n".
            "   y[gid] = a* x[gid] + y[gid];\n".
            "}\n"
        ];
        $context = $this->newContextFromType($ocl);
        $program = $ocl->Program($context,$sources,
            $mode=0,$devices=null,$options=null);
        try {
            $program->build($options=null,$devices=null);
        } catch(\RuntimeException $e) {
            $this->compile_error($program,$e);
        }
        $this->assertTrue(true);
    }

    /**
     * Compile and Link
     */
    public function testCompileAndLink()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);

        $devices = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES);
        $dev_version = $devices->getInfo(0,OpenCL::CL_DEVICE_VERSION);
        //$dev_version = 'OpenCL 1.1 Mesa';
        $isOpenCL110 = strstr($dev_version,'OpenCL 1.1') !== false;
        
        $header0 =
        "typedef int number_int_t;\n";
        $sources = [
            "#include \"const_zero.h\"\n".
            "__kernel void saxpy(const global float * x,\n".
            "                    __global float * y,\n".
            "                    const float a)\n".
            "{\n".
            "   uint gid = get_global_id(0);\n".
            "   y[gid] = a* x[gid] + y[gid];\n".
            "}\n"
        ];
        $sources0 = [
            "__kernel void saxpy(const global float * x,\n".
            "                    __global float * y,\n".
            "                    const float a)\n".
            "{\n".
            "   uint gid = get_global_id(0);\n".
            "   y[gid] = a* x[gid] + y[gid];\n".
            "}\n"
        ];

        // Construction sub-source
        $programSub = $ocl->Program($context,$header0);
        // Construction main-source
        $program = $ocl->Program($context,$sources);

        // Compiling
        try {
            $program->compile(['const_zero.h'=>$programSub]);
        } catch(\RuntimeException $e) {
            $this->compile_error($program,$e);
            throw $e;
        }
        $this->assertEquals(0,$program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_STATUS));

        // link program
        $linkedprogram = $ocl->Program($context,[$program],
            Program::TYPE_COMPILED_PROGRAM);

        $this->assertEquals(0,$linkedprogram->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_STATUS));


        //////////////////////////////////////////////
        // Compiling with null arguments
        $program = $ocl->Program($context,$sources0);
        try {
            $program->compile($headers=null,$options=null,$devices=null);
        } catch(\RuntimeException $e) {
            $this->compile_error($program,$e);
        }

        // linking with null arguments
        $linkedprogram = $ocl->Program($context,[$program],
            Program::TYPE_COMPILED_PROGRAM,
            $devices=null,$options=null);

        $this->assertTrue(true);

    }

    /**
     * get info
     */
    public function testGetInfo()
    {
        $ocl = $this->newDriverFactory();
        $context = $this->newContextFromType($ocl);

        $devices = $context->getInfo(OpenCL::CL_CONTEXT_DEVICES);
        $dev_version = $devices->getInfo(0,OpenCL::CL_DEVICE_VERSION);
        //$dev_version = 'OpenCL 1.1 Mesa';
        $isOpenCL110 = strstr($dev_version,'OpenCL 1.1') !== false;
        
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

        try {
            $program->build();
        } catch(\RuntimeException $e) {
            $this->compile_error($program,$e);
            throw $e;
        }
        if(!$isOpenCL110) {
            $this->assertTrue(null!==$program->getInfo(OpenCL::CL_PROGRAM_KERNEL_NAMES));
        }
        $this->assertTrue(true);
        if($this->skipDisplayInfo) {
            return;
        }

        echo "CL_PROGRAM_REFERENCE_COUNT=".$program->getInfo(OpenCL::CL_PROGRAM_REFERENCE_COUNT)."\n";
        // *** CAUTION *** 
        // *** Intel GPU has no info for CL_PROGRAM_CONTEXT ***
        // echo "CL_PROGRAM_CONTEXT=".$program->getInfo(OpenCL::CL_PROGRAM_CONTEXT)."\n";
        echo "CL_PROGRAM_NUM_DEVICES=".$program->getInfo(OpenCL::CL_PROGRAM_NUM_DEVICES)."\n";
        echo "CL_PROGRAM_DEVICES=\n";
        $devices = $program->getInfo(OpenCL::CL_PROGRAM_DEVICES);
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
        echo "CL_PROGRAM_SOURCE=\n";
        echo $program->getInfo(OpenCL::CL_PROGRAM_SOURCE)."\n";
        echo "CL_PROGRAM_BINARY_SIZES=[".implode(',',$program->getInfo(OpenCL::CL_PROGRAM_BINARY_SIZES))."]\n";
        #echo "CL_PROGRAM_BINARIES=".$program->getInfo(OpenCL::CL_PROGRAM_BINARIES)."\n";
        echo "CL_PROGRAM_NUM_KERNELS=".$program->getInfo(OpenCL::CL_PROGRAM_NUM_KERNELS)."\n";
        echo "CL_PROGRAM_KERNEL_NAMES=".$program->getInfo(OpenCL::CL_PROGRAM_KERNEL_NAMES)."\n";
        echo "============ build info ============\n";
        echo "CL_PROGRAM_BUILD_STATUS=".$program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_STATUS)."\n";
        echo "CL_PROGRAM_BUILD_OPTIONS=".$program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_OPTIONS)."\n";
        echo "CL_PROGRAM_BUILD_LOG=".$program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_LOG)."\n";
        echo "CL_PROGRAM_BINARY_TYPE=".$program->getBuildInfo(OpenCL::CL_PROGRAM_BINARY_TYPE)."\n";
    }

}
