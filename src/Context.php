<?php
namespace Rindow\OpenCL\FFI;

use Interop\Polite\Math\Matrix\OpenCL;
use InvalidArgumentException;
use RuntimeException;
use FFI;

class Context
{
    protected FFI $ffi;
    protected ?object $context;
    protected int $num_devices;
    protected object $devices;

    public function __construct(FFI $ffi,
        DeviceList|int $arg,
        ?object $context=null)
    {
        $this->ffi = $ffi;
        if($context!=null) {
            $num_devices=0;
            $errcode_ret=0;
            $devices = $this->get_devices(
                $context,
                $num_devices,
                $errcode_ret
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new RuntimeException("clGetContextInfo Error errcode=".$errcode_ret);
            }
            $errcode_ret = $ffi->clRetainContext($context);
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new RuntimeException("clRetainContext Error errcode=".$errcode_ret);
            }
            $this->context = $context;
            $this->num_devices = $num_devices;
            $this->devices = $devices;
            return;
        }
        if($arg instanceof DeviceList) {
            $devices = $arg;
            unset($arg);
            $ids = $devices->_getIds();
            $num = count($ids);
            $errcode_ret = $ffi->new('cl_int[1]');
            $context = $ffi->clCreateContext(
                NULL,       // const cl_context_properties * properties,
                $num,       // cl_uint  num_devices,
                $ids,       // const cl_device_id * devices,
                NULL,       // CL_CALLBACK * pfn_notify,
                NULL,       // void *user_data,
                $errcode_ret  // cl_int *errcode_ret
            );
            if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
                throw new RuntimeException("clCreateContext Error errcode=".$errcode_ret[0]);
            }
        } elseif(is_int($arg)) {
            // *** CAUTHON ***
            // When it call clCreateContextFromType without properties,
            // it throw the CL_INVALID_PLATFORM.
            // Probably the clCreateContextFromType needs flatform in cl_context_properties.
            //
            $device_type = $arg;
            unset($arg);
            $properties = $ffi->new('cl_context_properties[3]');
            $properties[0] = OpenCL::CL_CONTEXT_PLATFORM;
            $properties[1] = 0;
            $properties[2] = 0;
            $platform = $ffi->new('cl_platform_id[1]');
            $num_platforms = $ffi->new('cl_uint[1]');
    
            $errcode_ret = $ffi->clGetPlatformIDs( 1,   // cl_uint num_entries
                                $platform,              // cl_platform_id * platforms
                                $num_platforms );       // cl_uint * num_platforms
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new RuntimeException("clGetPlatformIDs Error errcode=".$errcode_ret);
            }
            $int64 = $ffi->new('size_t[1]');
            FFI::memcpy($int64,$platform,FFI::sizeof($int64));
            $properties[1] = $int64[0];
            $errcode_ret = $ffi->new('cl_int[1]');
            $context = $ffi->clCreateContextFromType(
                $properties,    // const cl_context_properties * properties,
                $device_type,   // cl_device_type      device_type,
                NULL,           // CL_CALLBACK * pfn_notify
                NULL,           // void *user_data,
                $errcode_ret    // cl_int *errcode_ret
            );
            if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
                // static char message[128];
                // sprintf(message,"clCreateContextFromType Error: device_type=%lld, error=%d",device_type,errcode_ret);
                throw new RuntimeException("clCreateContextFromType Error errcode=".$errcode_ret[0]);
            }
        } else {
            throw new RuntimeException("devices must be integer of device-type or the DeviceList", OpenCL::CL_INVALID_DEVICE);
        }
    
        $errcode_ret = 0;
        $num_devices = 0;
        $devices = $this->get_devices(
            $context,
            $num_devices,
            $errcode_ret
        );
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetContextInfo Error errcode=".$errcode_ret);
        }
    
        $this->context = $context;
        $this->num_devices = $num_devices;
        $this->devices = $devices;
    }

    public function __destruct()
    {
        if($this->context) {
            $errcode_ret = $this->ffi->clReleaseContext($this->context);
            $this->context = null;
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                echo "WARNING: clReleaseContext error=$errcode_ret\n";
            }
        }
    }

    public function _getId() : object
    {
        return $this->context;
    }

    public function _getNumDevices() : int
    {
        return $this->num_devices;
    }

    public function _getDeviceIds() : object
    {
        return $this->devices;
    }

    protected function get_devices(
        object $context,
        int &$num_of_device,
        int &$errcode_ret
    ) : ?object // cl_device_id* 
    {
        $ffi = $this->ffi;
        $n_device = $ffi->new('cl_int[1]');
        $errcode = $ffi->clGetContextInfo(
            $context,
            OpenCL::CL_CONTEXT_NUM_DEVICES,
            FFI::sizeof($n_device),
            $n_device,
            NULL);
        if($errcode!=OpenCL::CL_SUCCESS) {
            $errcode_ret = $errcode;
            return NULL;
        }
        $n_device = $n_device[0];
        $devices = $ffi->new("cl_device_id[$n_device]");
        if($devices==NULL) {
            $errcode_ret = OpenCL::CL_OUT_OF_RESOURCES;
            return NULL;
        }
        $cl_device_id = $ffi->new('cl_device_id[1]');
        $errcode = $ffi->clGetContextInfo(
            $context,
            OpenCL::CL_CONTEXT_DEVICES,
            $n_device*FFI::sizeof($cl_device_id),
            $devices,
            NULL);
        if($errcode!=OpenCL::CL_SUCCESS) {
            $errcode_ret = $errcode;
            return NULL;
        }
        $num_of_device = $n_device;

        return $devices;
    }

    public function getInfo(int $param_name) : mixed
    {
        $ffi = $this->ffi;

        $param_value_size_ret = $ffi->new("size_t[1]");
        $errcode_ret = $ffi->clGetContextInfo($this->context,
                                $param_name,
                                0, NULL, $param_value_size_ret);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetContextInfo Error errcode=$errcode_ret");
        }
        switch($param_name) {
            case OpenCL::CL_CONTEXT_DEVICES: {
                $size = $param_value_size_ret[0];
                $sizeofItem = $ffi::sizeof($ffi->new("cl_device_id[1]"));
                $items = $size/$sizeofItem;
                if(($size%$sizeofItem)!=0) {
                    throw new RuntimeException("clGetContextInfo illegal array<size_t> size=$size");
                }
                $device_ids = $ffi->new("cl_device_id[$items]");
                $errcode_ret = $ffi->clGetContextInfo($this->context,
                            $param_name,
                            $size, $device_ids, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetContextInfo Error errcode=".$errcode_ret);
                }
                // direct set to return_value
                $dummy = new PlatformList($ffi,$ffi->new("cl_platform_id[1]"));
                return new DeviceList($ffi,$dummy,devices:$device_ids);
            }
            case OpenCL::CL_CONTEXT_PROPERTIES: {
                $size = $param_value_size_ret[0];
                if($size===0) {
                    return [];
                }
                $sizeofItem = $ffi::sizeof($ffi->new("cl_uint[1]"));
                $items = $size/$sizeofItem;
                if(($size%$sizeofItem)!=0) {
                    throw new RuntimeException("clGetContextInfo illegal array<cl_uint> size=$size");
                }
                $properties = $ffi->new("cl_context_properties[$items]");
                $errcode_ret = $ffi->clGetContextInfo($this->context,
                            $param_name,
                            $size, $properties, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetContextInfo Error errcode=".$errcode_ret);
                }
                // direct set to return_value
                $return_value = [];
                for($i=0; $i<$items; $i++) {
                    $return_value[] = $properties[$i];
                }
                return $return_value;
            }
            case OpenCL::CL_CONTEXT_REFERENCE_COUNT:
            case OpenCL::CL_CONTEXT_NUM_DEVICES: {
                $size = $param_value_size_ret[0];
                $uint_result = $ffi->new("cl_uint[1]");
                if($size!=$ffi::sizeof($uint_result)) {
                    throw new RuntimeException("clGetContextInfo illegal cl_uint size=$size");
                }
                $errcode_ret = $ffi->clGetContextInfo($this->context,
                        $param_name,
                        $size, $uint_result, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetContextInfo Error errcode=".$errcode_ret);
                }
                $result = $uint_result[0];
                return $result;
            }
            default:{
                throw new InvalidArgumentException("invalid param name: $param_name");
            }
        }
    }
}
