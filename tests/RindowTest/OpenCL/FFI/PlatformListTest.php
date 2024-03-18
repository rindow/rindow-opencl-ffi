<?php
namespace RindowTest\OpenCL\FFI\PlatformListTest;

use PHPUnit\Framework\TestCase;
use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\OpenCL;

use Rindow\OpenCL\FFI\OpenCLFactory;
use RuntimeException;

class PlatformListTest extends TestCase
{
    protected bool $skipDisplayInfo = true;

    public function newDriverFactory()
    {
        $factory = new OpenCLFactory();
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
        #echo "count=".$platforms->count()."\n";
        $this->assertTrue($platforms->count()>=0);
    }

    /**
     * get one
     */
    public function testGetOne()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $num = $platforms->count();
        $one = $platforms->getOne(0);
        $this->assertTrue($one->count()==1);
        #echo "CL_PLATFORM_NAME=".$one->getInfo(0,OpenCL::CL_PLATFORM_NAME)."\n";
    }

    /**
     * get one
     */
    public function testGetInfo()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $n = $platforms->count();

        for($i=0;$i<$n;$i++) {
            $this->assertTrue(null!=$platforms->getInfo($i,OpenCL::CL_PLATFORM_NAME));
            if($this->skipDisplayInfo) {
                continue;
            }
            echo "\n";
            echo "platform(".$i.")\n";
            echo "    CL_PLATFORM_NAME=".$platforms->getInfo($i,OpenCL::CL_PLATFORM_NAME)."\n";
            echo "    CL_PLATFORM_PROFILE=".$platforms->getInfo($i,OpenCL::CL_PLATFORM_PROFILE)."\n";
            echo "    CL_PLATFORM_VERSION=".$platforms->getInfo($i,OpenCL::CL_PLATFORM_VERSION)."\n";
            echo "    CL_PLATFORM_VENDOR=".$platforms->getInfo($i,OpenCL::CL_PLATFORM_VENDOR)."\n";
            echo "    CL_PLATFORM_EXTENSIONS=".$platforms->getInfo($i,OpenCL::CL_PLATFORM_EXTENSIONS)."\n";
        }
    }

}
