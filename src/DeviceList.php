<?php
namespace Rindow\OpenCL\FFI;

use Interop\Polite\Math\Matrix\OpenCL;
use InvalidArgumentException;
use RuntimeException;
use OutOfRangeException;
use FFI;
use Countable;

class DeviceList implements Countable
{
    protected FFI $ffi;
    protected int $num;
    protected object $devices;

    public function __construct(FFI $ffi,
        PlatformList $platforms,
        ?int $index=NULL,
        ?int $device_type=NULL,
        ?object $devices=NULL,
        )
    {
        $this->ffi = $ffi;
        $index = $index ?? 0;
        $device_type = $device_type ?? OpenCL::CL_DEVICE_TYPE_ALL;
        if($devices!==NULL) {
            $num = count($devices);
            $this->num = $num;
            $this->devices = $devices;
            return;
        }
        if($index<0 || $index>=count($platforms)) {
            throw new OutOfRangeException("Invalid index of platforms: $index");
        }
        if($device_type==0) {
            $device_type = OpenCL::CL_DEVICE_TYPE_ALL;
        }
        $platforms = $platforms->_getIds();

        $numDevices = $ffi->new("unsigned int[1]");
        $errcode_ret = $ffi->clGetDeviceIDs(
                                $platforms[0],
                                $device_type,
                                0,
                                NULL,
                                $numDevices);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetDeviceIDs Error errcode=$errcode_ret");
        }
        $num = $numDevices[0];
        $devices = $ffi->new("cl_device_id[$num]");
        $errcode_ret = $ffi->clGetDeviceIDs(
                                $platforms[0],
                                $device_type,
                                $num,
                                $devices,
                                $numDevices);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetDeviceIDs Error2 errcode=$errcode_ret");
        }
        $this->num = $num;
        $this->devices = $devices;
    }

    public function __destruct()
    {
    }

    public function _getIds() : object
    {
        return $this->devices;
    }

    public function count() : int
    {
        return $this->num;
    }

    public function getOne(int $offset) : self
    {
        $ffi= $this->ffi;
        if($offset<0 || $offset>=$this->num) {
            throw new OutOfRangeException("Invalid index of devices: $offset");
        }
        $devices = $ffi->new("cl_device_id[1]");
        $devices[0] = $this->devices[$offset];
        $dummy = new PlatformList($ffi,$ffi->new("cl_platform_id[1]"));
        $obj = new self($ffi,$dummy,devices:$devices);
        return $obj;
    }

    public function append(self $devices) : void
    {
        $ffi= $this->ffi;
        $devices = $devices->_getIds();
        $num = count($devices);
        $sum = $this->num + $num;
        $newDevices = $ffi->new("cl_device_id[$sum]");
        FFI::memcpy($newDevices,$this->devices,FFI::sizeof($this->devices));
        FFI::memcpy(FFI::addr($newDevices[$this->num]),$devices,FFI::sizeof($devices));
        $this->devices = $newDevices;
        $this->num = $sum;
    }

    public function getInfo(int $offset, int $param_name) : mixed
    {
        $ffi= $this->ffi;
        if($offset<0 || $offset>=$this->num) {
            throw new OutOfRangeException("Invalid index of devices: $offset");
        }
        $id = $this->devices[$offset];
        $param_value_size_ret = $ffi->new("size_t[1]");
        $errcode_ret = $ffi->clGetDeviceInfo($id,
                                $param_name,
                                0,
                                NULL,
                                $param_value_size_ret);
                
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetDeviceInfo Error errcode=$errcode_ret");
        }
        switch($param_name) {
            case OpenCL::CL_DEVICE_NAME:
            case OpenCL::CL_DEVICE_VENDOR:
            case OpenCL::CL_DRIVER_VERSION:
            case OpenCL::CL_DEVICE_PROFILE:
            case OpenCL::CL_DEVICE_VERSION:
            case OpenCL::CL_DEVICE_OPENCL_C_VERSION:
            case OpenCL::CL_DEVICE_EXTENSIONS:
            case OpenCL::CL_DEVICE_BUILT_IN_KERNELS: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_char[$size]");
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                return FFI::string($param_value_val,$size-1);
            }
            case OpenCL::CL_DEVICE_VENDOR_ID:
            case OpenCL::CL_DEVICE_MAX_COMPUTE_UNITS:
            case OpenCL::CL_DEVICE_MAX_WORK_ITEM_DIMENSIONS:
            case OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_CHAR:
            case OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_SHORT:
            case OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_INT:
            case OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_LONG:
            case OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_FLOAT:
            case OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_DOUBLE:
            case OpenCL::CL_DEVICE_PREFERRED_VECTOR_WIDTH_HALF:
            case OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_CHAR:
            case OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_SHORT:
            case OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_INT:
            case OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_LONG:
            case OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_FLOAT:
            case OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_DOUBLE:
            case OpenCL::CL_DEVICE_NATIVE_VECTOR_WIDTH_HALF:
            case OpenCL::CL_DEVICE_MAX_CLOCK_FREQUENCY:
            case OpenCL::CL_DEVICE_ADDRESS_BITS:
            case OpenCL::CL_DEVICE_MAX_READ_IMAGE_ARGS:
            case OpenCL::CL_DEVICE_MAX_WRITE_IMAGE_ARGS:
            case OpenCL::CL_DEVICE_MAX_SAMPLERS:
            case OpenCL::CL_DEVICE_MEM_BASE_ADDR_ALIGN:
            case OpenCL::CL_DEVICE_MIN_DATA_TYPE_ALIGN_SIZE:
            case OpenCL::CL_DEVICE_GLOBAL_MEM_CACHELINE_SIZE:
            case OpenCL::CL_DEVICE_MAX_CONSTANT_ARGS:
            case OpenCL::CL_DEVICE_GLOBAL_MEM_CACHE_TYPE:
            case OpenCL::CL_DEVICE_LOCAL_MEM_TYPE:
            case OpenCL::CL_DEVICE_PARTITION_MAX_SUB_DEVICES:
            case OpenCL::CL_DEVICE_REFERENCE_COUNT:{
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_uint[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal int size=$size");
                }
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
            case OpenCL::CL_DEVICE_MAX_MEM_ALLOC_SIZE:
            case OpenCL::CL_DEVICE_GLOBAL_MEM_CACHE_SIZE:
            case OpenCL::CL_DEVICE_GLOBAL_MEM_SIZE:
            case OpenCL::CL_DEVICE_MAX_CONSTANT_BUFFER_SIZE:
            case OpenCL::CL_DEVICE_LOCAL_MEM_SIZE:{
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_ulong[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal long size=$size");
                }
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
            case OpenCL::CL_DEVICE_IMAGE_SUPPORT:
            case OpenCL::CL_DEVICE_ERROR_CORRECTION_SUPPORT:
            case OpenCL::CL_DEVICE_HOST_UNIFIED_MEMORY:
            case OpenCL::CL_DEVICE_ENDIAN_LITTLE:
            case OpenCL::CL_DEVICE_AVAILABLE:
            case OpenCL::CL_DEVICE_COMPILER_AVAILABLE:
            case OpenCL::CL_DEVICE_LINKER_AVAILABLE:
            case OpenCL::CL_DEVICE_PREFERRED_INTEROP_USER_SYNC:{
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_bool[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal bool size=$size");
                }
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
            case OpenCL::CL_DEVICE_MAX_WORK_GROUP_SIZE:
            case OpenCL::CL_DEVICE_IMAGE2D_MAX_WIDTH:
            case OpenCL::CL_DEVICE_IMAGE2D_MAX_HEIGHT:
            case OpenCL::CL_DEVICE_IMAGE3D_MAX_WIDTH:
            case OpenCL::CL_DEVICE_IMAGE3D_MAX_HEIGHT:
            case OpenCL::CL_DEVICE_IMAGE3D_MAX_DEPTH:
            case OpenCL::CL_DEVICE_MAX_PARAMETER_SIZE:
            case OpenCL::CL_DEVICE_PROFILING_TIMER_RESOLUTION:
            case OpenCL::CL_DEVICE_IMAGE_MAX_BUFFER_SIZE:
            case OpenCL::CL_DEVICE_IMAGE_MAX_ARRAY_SIZE:
            case OpenCL::CL_DEVICE_PRINTF_BUFFER_SIZE:{
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("size_t[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal size_t size=$size");
                }
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
            case OpenCL::CL_DEVICE_TYPE:
            case OpenCL::CL_DEVICE_SINGLE_FP_CONFIG:
            case OpenCL::CL_DEVICE_DOUBLE_FP_CONFIG:
            case OpenCL::CL_DEVICE_EXECUTION_CAPABILITIES:
            case OpenCL::CL_DEVICE_QUEUE_PROPERTIES:{
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_bitfield[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal cl_bitfield size=$size");
                }
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
            case OpenCL::CL_DEVICE_PLATFORM: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_platform_id[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal cl_platform_id size=$size");
                }
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                return new PlatformList($this->ffi, $param_value_val);
            }
            case OpenCL::CL_DEVICE_PARENT_DEVICE:{
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_device_id[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal device_id size=$size");
                }
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                if($param_value_val[0]===NULL) {
                    return NULL;
                }
                $dummy = new PlatformList($ffi,$ffi->new("cl_platform_id[1]"));
                $obj = new self($ffi,$dummy,devices:$param_value_val);
                return $obj;
            }
            case OpenCL::CL_DEVICE_MAX_WORK_ITEM_SIZES: {
                $size = $param_value_size_ret[0];
                if($size===0) {
                    return [];
                }
                $sizeofItem = $ffi::sizeof($ffi->new("size_t[1]"));
                if(($size%$sizeofItem)!=0) {
                    throw new RuntimeException("clGetDeviceInfo illegal array<size_t> size=$size");
                }
                $items = $size/$sizeofItem;
                $param_value_val = $ffi->new("size_t[$items]");
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                $results = [];
                for($i=0; $i<$items; $i++) {
                    $results[] = $param_value_val[$i];
                }
                return $results;
            }
            case OpenCL::CL_DEVICE_PARTITION_PROPERTIES:
            case OpenCL::CL_DEVICE_PARTITION_TYPE: {
                $size = $param_value_size_ret[0];
                if($size===0) {
                    return [];
                }
                $sizeofItem = $ffi::sizeof($ffi->new("cl_device_partition_property[1]"));
                if(($size%$sizeofItem)!=0) {
                    throw new RuntimeException("clGetDeviceInfo illegal array<cl_device_partition_property> size=$size");
                }
                $items = $size/$sizeofItem;
                $param_value_val = $ffi->new("cl_device_partition_property[$items]");
                $errcode_ret = $ffi->clGetDeviceInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetDeviceInfo Error2 errcode=$errcode_ret");
                }
                $results = [];
                for($i=0; $i<$items; $i++) {
                    $results[] = $param_value_val[$i];
                }
                return $results;
            }
            default: {
                throw new InvalidArgumentException("invalid param name: $param_name");
            }
        }
    }
}