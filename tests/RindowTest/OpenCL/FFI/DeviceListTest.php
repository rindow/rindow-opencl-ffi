<?php
namespace RindowTest\OpenCL\FFI\DeviceListTest;

use PHPUnit\Framework\TestCase;
use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\OpenCL;

use Rindow\OpenCL\FFI\OpenCLFactory;
use RuntimeException;

class DeviceListTest extends TestCase
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
        $devices = $ocl->DeviceList($platforms);
        #echo "count=".$devices->count()."\n";
        $total_dev = $devices->count();
        $this->assertTrue($devices->count()>=0);
    }

    /**
     * construct with device type
     */
    public function testConstructByDeviceType()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $devices = $ocl->DeviceList($platforms);
        $total_dev = $devices->count();
        $count = 0;

        foreach([OpenCL::CL_DEVICE_TYPE_CPU, OpenCL::CL_DEVICE_TYPE_GPU] as $type) {
            try {
                $devices = $ocl->DeviceList($platforms,0,$type);
                #echo "count=".$devices->count()."\n";
                $this->assertTrue($devices->count()>=0);
                $this->assertTrue(true==($devices->getInfo(0,OpenCL::CL_DEVICE_TYPE)&$type));
                $count += $devices->count();
            } catch(\RuntimeException $e) {
                ;
            }
        }
        $this->assertTrue($total_dev==$count);
    }

    /**
     * Construct with null
     * OpenCL::CL_DEVICE_TYPE_ALL
     */
    public function testConstructWithNull()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $devices = $ocl->DeviceList($platforms,0,0);
        #echo "count=".$devices->count()."\n";
        $this->assertTrue($devices->count()>=0);
    }

    /**
     * reduce num of devices
     */
    public function testReduceNumOfDevices()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $devices = $ocl->DeviceList($platforms);
        #echo "org=".$devices->count()."\n";
        $onedev = $devices->getOne(0);
        #echo $onedev->getInfo(0,OpenCL::CL_DEVICE_NAME)."\n";
        $this->assertTrue($onedev->count()==1);
    }

    /**
     * append device
     */
    public function testAppendDevice()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $devices = $ocl->DeviceList($platforms);
        $num = $devices->count();
        $onedev = $devices->getOne(0);
        $this->assertTrue($onedev->count()==1);
        $devices->append($onedev);
        $this->assertTrue($devices->count()==$num+1);
    }

    /**
     * get information
     */
    public function testGetInformation()
    {
        $ocl = $this->newDriverFactory();
        $platforms = $ocl->PlatformList();
        $devices = $ocl->DeviceList($platforms);
        $n = $devices->count();
        for($i=0;$i<$n;$i++) {
            $this->assertTrue(null!=$devices->getInfo($i,OpenCL::CL_DEVICE_NAME));
            if($this->skipDisplayInfo) {
                continue;
            }
            echo "\n";
            echo "device(".$i.")\n";
            echo "    CL_DEVICE_VENDOR_ID=".$devices->getInfo($i,OpenCL::CL_DEVICE_VENDOR_ID)."\n";
            echo "    CL_DEVICE_NAME=".$devices->getInfo($i,OpenCL::CL_DEVICE_NAME)."\n";
            echo "    CL_DEVICE_TYPE=(";
            $device_type = $devices->getInfo($i,OpenCL::CL_DEVICE_TYPE);
            if($device_type&OpenCL::CL_DEVICE_TYPE_CPU) { echo "CPU,"; }
            if($device_type&OpenCL::CL_DEVICE_TYPE_GPU) { echo "GPU,"; }
            if($device_type&OpenCL::CL_DEVICE_TYPE_ACCELERATOR) { echo "ACCEL,"; }
            if($device_type&OpenCL::CL_DEVICE_TYPE_CUSTOM) { echo "CUSTOM,"; }
            echo ")\n";
            echo "    CL_DEVICE_MAX_WORK_ITEM_SIZES=(".implode(',',$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_WORK_ITEM_SIZES)).")\n";
            echo "    CL_DEVICE_PARTITION_TYPE=(".implode(',',$devices->getInfo($i,OpenCL::CL_DEVICE_PARTITION_TYPE)).")\n";
            echo "    CL_DEVICE_PARTITION_PROPERTIES=(".implode(',',array_map(function($x){ return "0x".dechex($x);},
                $devices->getInfo($i,OpenCL::CL_DEVICE_PARTITION_PROPERTIES))).")\n";
            echo "    CL_DEVICE_VENDOR=".$devices->getInfo($i,OpenCL::CL_DEVICE_VENDOR)."\n";
            echo "    CL_DEVICE_BUILT_IN_KERNELS=".$devices->getInfo($i,OpenCL::CL_DEVICE_BUILT_IN_KERNELS)."\n";
            echo "    CL_DEVICE_PROFILE=".$devices->getInfo($i,OpenCL::CL_DEVICE_PROFILE)."\n";
            echo "    CL_DRIVER_VERSION=".$devices->getInfo($i,OpenCL::CL_DRIVER_VERSION)."\n";
            echo "    CL_DEVICE_VERSION=".$devices->getInfo($i,OpenCL::CL_DEVICE_VERSION)."\n";
            echo "    CL_DEVICE_OPENCL_C_VERSION=".$devices->getInfo($i,OpenCL::CL_DEVICE_OPENCL_C_VERSION)."\n";
            echo "    CL_DEVICE_EXTENSIONS=".$devices->getInfo($i,OpenCL::CL_DEVICE_EXTENSIONS)."\n";
            echo "    CL_DEVICE_MAX_COMPUTE_UNITS=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_COMPUTE_UNITS)."\n";
            echo "    CL_DEVICE_MAX_WORK_ITEM_DIMENSIONS=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_WORK_ITEM_DIMENSIONS)."\n";
            echo "    CL_DEVICE_MAX_CLOCK_FREQUENCY=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_CLOCK_FREQUENCY)."\n";
            echo "    CL_DEVICE_ADDRESS_BITS=".$devices->getInfo($i,OpenCL::CL_DEVICE_ADDRESS_BITS)."\n";
            echo "    CL_DEVICE_PREFERRED_VECTOR_WIDTH_CHAR=".$devices->getInfo($i,OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_CHAR)."\n";
            echo "    CL_DEVICE_PREFERRED_VECTOR_WIDTH_SHORT=".$devices->getInfo($i,OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_SHORT)."\n";
            echo "    CL_DEVICE_PREFERRED_VECTOR_WIDTH_INT=".$devices->getInfo($i,OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_INT)."\n";
            echo "    CL_DEVICE_PREFERRED_VECTOR_WIDTH_LONG=".$devices->getInfo($i,OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_LONG)."\n";
            echo "    CL_DEVICE_PREFERRED_VECTOR_WIDTH_FLOAT=".$devices->getInfo($i,OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_FLOAT)."\n";
            echo "    CL_DEVICE_PREFERRED_VECTOR_WIDTH_DOUBLE=".$devices->getInfo($i,OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_DOUBLE)."\n";
            echo "    CL_DEVICE_PREFERRED_VECTOR_WIDTH_HALF=".$devices->getInfo($i,OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_HALF)."\n";
            echo "    CL_DEVICE_NATIVE_VECTOR_WIDTH_CHAR=".$devices->getInfo($i,OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_CHAR)."\n";
            echo "    CL_DEVICE_NATIVE_VECTOR_WIDTH_SHORT=".$devices->getInfo($i,OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_SHORT)."\n";
            echo "    CL_DEVICE_NATIVE_VECTOR_WIDTH_INT=".$devices->getInfo($i,OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_INT)."\n";
            echo "    CL_DEVICE_NATIVE_VECTOR_WIDTH_LONG=".$devices->getInfo($i,OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_LONG)."\n";
            echo "    CL_DEVICE_NATIVE_VECTOR_WIDTH_FLOAT=".$devices->getInfo($i,OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_FLOAT)."\n";
            echo "    CL_DEVICE_NATIVE_VECTOR_WIDTH_DOUBLE=".$devices->getInfo($i,OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_DOUBLE)."\n";
            echo "    CL_DEVICE_NATIVE_VECTOR_WIDTH_HALF=".$devices->getInfo($i,OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_HALF)."\n";
            echo "    CL_DEVICE_MAX_READ_IMAGE_ARGS=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_READ_IMAGE_ARGS)."\n";
            echo "    CL_DEVICE_MAX_WRITE_IMAGE_ARGS=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_WRITE_IMAGE_ARGS)."\n";
            echo "    CL_DEVICE_MAX_SAMPLERS=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_SAMPLERS)."\n";
            echo "    CL_DEVICE_MEM_BASE_ADDR_ALIGN=".$devices->getInfo($i,OpenCL::CL_DEVICE_MEM_BASE_ADDR_ALIGN)."\n";
            echo "    CL_DEVICE_MIN_DATA_TYPE_ALIGN_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_MIN_DATA_TYPE_ALIGN_SIZE)."\n";
            echo "    CL_DEVICE_GLOBAL_MEM_CACHELINE_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_GLOBAL_MEM_CACHELINE_SIZE)."\n";
            echo "    CL_DEVICE_MAX_CONSTANT_ARGS=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_CONSTANT_ARGS)."\n";
            echo "    CL_DEVICE_PARTITION_MAX_SUB_DEVICES=".$devices->getInfo($i,OpenCL::CL_DEVICE_PARTITION_MAX_SUB_DEVICES)."\n";
            echo "    CL_DEVICE_REFERENCE_COUNT=".$devices->getInfo($i,OpenCL::CL_DEVICE_REFERENCE_COUNT)."\n";
            echo "    CL_DEVICE_GLOBAL_MEM_CACHE_TYPE=".$devices->getInfo($i,OpenCL::CL_DEVICE_GLOBAL_MEM_CACHE_TYPE)."\n";
            echo "    CL_DEVICE_LOCAL_MEM_TYPE=".$devices->getInfo($i,OpenCL::CL_DEVICE_LOCAL_MEM_TYPE)."\n";
            echo "    CL_DEVICE_MAX_MEM_ALLOC_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_MEM_ALLOC_SIZE)."\n";
            echo "    CL_DEVICE_GLOBAL_MEM_CACHE_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_GLOBAL_MEM_CACHE_SIZE)."\n";
            echo "    CL_DEVICE_GLOBAL_MEM_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_GLOBAL_MEM_SIZE)."\n";
            echo "    CL_DEVICE_MAX_CONSTANT_BUFFER_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_CONSTANT_BUFFER_SIZE)."\n";
            echo "    CL_DEVICE_LOCAL_MEM_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_LOCAL_MEM_SIZE)."\n";
            echo "    CL_DEVICE_IMAGE_SUPPORT=".$devices->getInfo($i,OpenCL::CL_DEVICE_IMAGE_SUPPORT)."\n";
            echo "    CL_DEVICE_ERROR_CORRECTION_SUPPORT=".$devices->getInfo($i,OpenCL::CL_DEVICE_ERROR_CORRECTION_SUPPORT)."\n";
            echo "    CL_DEVICE_HOST_UNIFIED_MEMORY=".$devices->getInfo($i,OpenCL::CL_DEVICE_HOST_UNIFIED_MEMORY)."\n";
            echo "    CL_DEVICE_ENDIAN_LITTLE=".$devices->getInfo($i,OpenCL::CL_DEVICE_ENDIAN_LITTLE)."\n";
            echo "    CL_DEVICE_AVAILABLE=".$devices->getInfo($i,OpenCL::CL_DEVICE_AVAILABLE)."\n";
            echo "    CL_DEVICE_COMPILER_AVAILABLE=".$devices->getInfo($i,OpenCL::CL_DEVICE_COMPILER_AVAILABLE)."\n";
            echo "    CL_DEVICE_LINKER_AVAILABLE=".$devices->getInfo($i,OpenCL::CL_DEVICE_LINKER_AVAILABLE)."\n";
            echo "    CL_DEVICE_PREFERRED_INTEROP_USER_SYNC=".$devices->getInfo($i,OpenCL::CL_DEVICE_PREFERRED_INTEROP_USER_SYNC)."\n";
            echo "    CL_DEVICE_MAX_WORK_GROUP_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_WORK_GROUP_SIZE)."\n";
            echo "    CL_DEVICE_IMAGE2D_MAX_WIDTH=".$devices->getInfo($i,OpenCL::CL_DEVICE_IMAGE2D_MAX_WIDTH)."\n";
            echo "    CL_DEVICE_IMAGE2D_MAX_HEIGHT=".$devices->getInfo($i,OpenCL::CL_DEVICE_IMAGE2D_MAX_HEIGHT)."\n";
            echo "    CL_DEVICE_IMAGE3D_MAX_WIDTH=".$devices->getInfo($i,OpenCL::CL_DEVICE_IMAGE3D_MAX_WIDTH)."\n";
            echo "    CL_DEVICE_IMAGE3D_MAX_HEIGHT=".$devices->getInfo($i,OpenCL::CL_DEVICE_IMAGE3D_MAX_HEIGHT)."\n";
            echo "    CL_DEVICE_IMAGE3D_MAX_DEPTH=".$devices->getInfo($i,OpenCL::CL_DEVICE_IMAGE3D_MAX_DEPTH)."\n";
            echo "    CL_DEVICE_IMAGE_MAX_BUFFER_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_IMAGE_MAX_BUFFER_SIZE)."\n";
            echo "    CL_DEVICE_IMAGE_MAX_ARRAY_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_IMAGE_MAX_ARRAY_SIZE)."\n";
            echo "    CL_DEVICE_MAX_PARAMETER_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_MAX_PARAMETER_SIZE)."\n";
            echo "    CL_DEVICE_PROFILING_TIMER_RESOLUTION=".$devices->getInfo($i,OpenCL::CL_DEVICE_PROFILING_TIMER_RESOLUTION)."\n";
            echo "    CL_DEVICE_PRINTF_BUFFER_SIZE=".$devices->getInfo($i,OpenCL::CL_DEVICE_PRINTF_BUFFER_SIZE)."\n";
            echo "    CL_DEVICE_SINGLE_FP_CONFIG=(";
            $config = $devices->getInfo($i,OpenCL::CL_DEVICE_SINGLE_FP_CONFIG);
            if($config&OpenCL::CL_FP_DENORM) { echo "DENORM,"; }
            if($config&OpenCL::CL_FP_INF_NAN) { echo "INF_NAN,"; }
            if($config&OpenCL::CL_FP_ROUND_TO_NEAREST) { echo "ROUND_TO_NEAREST,"; }
            if($config&OpenCL::CL_FP_ROUND_TO_ZERO) { echo "ROUND_TO_ZERO,"; }
            if($config&OpenCL::CL_FP_ROUND_TO_INF) { echo "ROUND_TO_INF,"; }
            if($config&OpenCL::CL_FP_FMA) { echo "FMA,"; }
            if($config&OpenCL::CL_FP_SOFT_FLOAT) { echo "SOFT_FLOAT,"; }
            if($config&OpenCL::CL_FP_CORRECTLY_ROUNDED_DIVIDE_SQRT) { echo "CORRECTLY_ROUNDED_DIVIDE_SQRT,"; }
            echo ")\n";
            echo "    CL_DEVICE_DOUBLE_FP_CONFIG=(";
            $config = $devices->getInfo($i,OpenCL::CL_DEVICE_DOUBLE_FP_CONFIG);
            if($config&OpenCL::CL_FP_DENORM) { echo "DENORM,"; }
            if($config&OpenCL::CL_FP_INF_NAN) { echo "INF_NAN,"; }
            if($config&OpenCL::CL_FP_ROUND_TO_NEAREST) { echo "ROUND_TO_NEAREST,"; }
            if($config&OpenCL::CL_FP_ROUND_TO_ZERO) { echo "ROUND_TO_ZERO,"; }
            if($config&OpenCL::CL_FP_ROUND_TO_INF) { echo "ROUND_TO_INF,"; }
            if($config&OpenCL::CL_FP_FMA) { echo "FMA,"; }
            if($config&OpenCL::CL_FP_SOFT_FLOAT) { echo "SOFT_FLOAT,"; }
            if($config&OpenCL::CL_FP_CORRECTLY_ROUNDED_DIVIDE_SQRT) { echo "CORRECTLY_ROUNDED_DIVIDE_SQRT,"; }
            echo ")\n";
            echo "    CL_DEVICE_EXECUTION_CAPABILITIES=(";
            $config = $devices->getInfo($i,OpenCL::CL_DEVICE_EXECUTION_CAPABILITIES);
            if($config&OpenCL::CL_EXEC_KERNEL) { echo "KERNEL,"; }
            if($config&OpenCL::CL_EXEC_NATIVE_KERNEL) { echo "NATIVE_KERNEL,"; }
            echo ")\n";
            echo "    CL_DEVICE_QUEUE_PROPERTIES=(";
            $config = $devices->getInfo($i,OpenCL::CL_DEVICE_QUEUE_PROPERTIES);
            if($config&OpenCL::CL_QUEUE_OUT_OF_ORDER_EXEC_MODE_ENABLE) { echo "OUT_OF_ORDER_EXEC_MODE_ENABLE,"; }
            if($config&OpenCL::CL_QUEUE_PROFILING_ENABLE) { echo "PROFILING_ENABLE,"; }
            echo ")\n";
            echo "    CL_DEVICE_PARENT_DEVICE=(";
            $parent_device = $devices->getInfo($i,OpenCL::CL_DEVICE_PARENT_DEVICE);
            if($parent_device) {
                echo "        deivces(".$parent_device->count().")\n";
                for($j=0;$j<$parent_device->count();$j++) {
                    echo "        CL_DEVICE_NAME=".$parent_device->getInfo($j,OpenCL::CL_DEVICE_NAME)."\n";
                    echo "        CL_DEVICE_VENDOR=".$parent_device->getInfo($j,OpenCL::CL_DEVICE_VENDOR)."\n";
                    echo "        CL_DEVICE_TYPE=(";
                    $device_type = $parent_device->getInfo($j,OpenCL::CL_DEVICE_TYPE);
                    if($device_type&OpenCL::CL_DEVICE_TYPE_CPU) { echo "CPU,"; }
                    if($device_type&OpenCL::CL_DEVICE_TYPE_GPU) { echo "GPU,"; }
                    if($device_type&OpenCL::CL_DEVICE_TYPE_ACCELERATOR) { echo "ACCEL,"; }
                    if($device_type&OpenCL::CL_DEVICE_TYPE_CUSTOM) { echo "CUSTOM,"; }
                    echo ")\n";
                }
            }
            echo ")\n";
            echo "    CL_DEVICE_PLATFORM=(\n";
            $device_platform = $devices->getInfo($i,OpenCL::CL_DEVICE_PLATFORM);
                echo "        platforms(".$device_platform->count().")\n";
                for($j=0;$j<$device_platform->count();$j++) {
                echo "            CL_PLATFORM_NAME=".$device_platform->getInfo($j,OpenCL::CL_PLATFORM_NAME)."\n";
                echo "            CL_PLATFORM_PROFILE=".$platforms->getInfo($j,OpenCL::CL_PLATFORM_PROFILE)."\n";
                echo "            CL_PLATFORM_VERSION=".$platforms->getInfo($j,OpenCL::CL_PLATFORM_VERSION)."\n";
                echo "            CL_PLATFORM_VENDOR=".$platforms->getInfo($j,OpenCL::CL_PLATFORM_VENDOR)."\n";
                echo "            CL_PLATFORM_EXTENSIONS=".$platforms->getInfo($j,OpenCL::CL_PLATFORM_EXTENSIONS)."\n";
                }
            echo "    )\n";
        
        }
    }

}
