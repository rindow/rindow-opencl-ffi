<?php
namespace Rindow\OpenCL\FFI;

use Interop\Polite\Math\Matrix\OpenCL;
use InvalidArgumentException;
use RuntimeException;
use FFI;

class CommandQueue
{
    protected FFI $ffi;
    protected ?object $command_queue;
    protected Context $context;

    public function __construct(FFI $ffi,
        Context $context,
        object $device_id=null,
        object $properties=null,
        )
    {
        $this->ffi = $ffi;
        if($device_id===null) {
            $device = $context->_getDeviceIds();
            if($device==NULL) {
                throw new InvalidArgumentException("Context is not initialized");
            }
            $device = $device[0];
        } else {
            $device = $device_id;
        }
    
        $errcode_ret = $ffi->new('cl_int[1]');
        $command_queue = $ffi->clCreateCommandQueue(
            $context->_getId(),
            $device,
            $properties,
            $errcode_ret);
        if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clCreateCommandQueue Error errcode=".$errcode_ret[0]);
        }
        $this->command_queue = $command_queue;
        $this->context = $context;
    }

    public function __destruct()
    {
        if($this->command_queue) {
            $errcode_ret = $this->ffi->clReleaseCommandQueue($this->command_queue);
            $this->command_queue = null;
            if($errcode_ret!=0) {
                throw new RuntimeException("clReleaseCommandQueue Error errcode=".$errcode_ret);
            }
        }
    }

    public function _getId() : object
    {
        return $this->command_queue;
    }

    public function getContext() : Context
    {
        return $this->context;
    }

    public function flush() : void
    {
        $ffi = $this->ffi;
    
        $errcode_ret = $ffi->clFlush($this->command_queue);
        if($errcode_ret!=0) {
            throw new RuntimeException("clFlush Error errcode=".$errcode_ret);
        }
    }

    public function finish() : void
    {
        $ffi = $this->ffi;

        $errcode_ret = $ffi->clFinish($this->command_queue);
        if($errcode_ret!=0) {
            throw new RuntimeException("clFinish Error errcode=".$errcode_ret);
        }
    }

    public function getInfo(int $param_name) : mixed
    {
        $ffi = $this->ffi;

        $param_value_size_ret = $ffi->new("size_t[1]");
        $errcode_ret = $ffi->clGetCommandQueueInfo($this->command_queue,
                                $param_name,
                                0, NULL, $param_value_size_ret);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetCommandQueueInfo Error errcode=$errcode_ret");
        }
        switch($param_name) {
            case OpenCL::CL_QUEUE_CONTEXT: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_context[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetCommandQueueInfo illegal cl_context size=$size");
                }
                $errcode_ret = $ffi->clGetCommandQueueInfo($this->command_queue,
                        $param_name,
                        $size, $param_value_val, NULL);
                if($errcode_ret) {
                    throw new RuntimeException("clGetCommandQueueInfo Error2 errcode=$errcode_ret",$errcode_ret);
                }
                //return $param_value_val[0];
                return new Context($ffi,0,context:$param_value_val[0]);
            }
            case OpenCL::CL_QUEUE_DEVICE: {
                $size = $param_value_size_ret[0];
                $sizeofItem = $ffi::sizeof($ffi->new("cl_device_id[1]"));
                $items = $size/$sizeofItem;
                if(($size%$sizeofItem)!=0) {
                    throw new RuntimeException("clGetCommandQueueInfo illegal array<size_t> size=$size");
                }
                $device_ids = $ffi->new("cl_device_id[$items]");
                $errcode_ret = $ffi->clGetCommandQueueInfo($this->command_queue,
                            $param_name,
                            $size, $device_ids, NULL);
                if($errcode_ret) {
                    throw new RuntimeException("clGetCommandQueueInfo Error errcode=$errcode_ret",$errcode_ret);
                }
                // direct set to return_value
                $dummy = new PlatformList($ffi,$ffi->new("cl_platform_id[1]"));
                return new DeviceList($ffi,$dummy,devices:$device_ids);
            }
            case OpenCL::CL_QUEUE_PROPERTIES: {
                $size = $param_value_size_ret[0];
                if($size===0) {
                    return [];
                }
                $sizeofItem = $ffi::sizeof($ffi->new("cl_uint[1]"));
                $items = $size/$sizeofItem;
                if(($size%$sizeofItem)!=0) {
                    throw new RuntimeException("clGetCommandQueueInfo illegal array<cl_uint> size=$size");
                }
                $properties = $ffi->new("cl_context_properties[$items]");
                $errcode_ret = $ffi->clGetCommandQueueInfo($this->command_queue,
                            $param_name,
                            $size, $properties, NULL);
                if($errcode_ret) {
                    throw new RuntimeException("clGetCommandQueueInfo Error errcode=$errcode_ret",$errcode_ret);
                }
                // direct set to return_value
                $return_value = [];
                for($i=0; $i<$items; $i++) {
                    $return_value[] = $properties[$i];
                }
                return $return_value;
            }
#ifdef CL_VERSION_2_0
#           case OpenCL::CL_QUEUE_SIZE:
#endif
            case OpenCL::CL_QUEUE_REFERENCE_COUNT: {
                $size = $param_value_size_ret[0];
                $uint_result = $ffi->new("cl_uint[1]");
                if($size!=$ffi::sizeof($uint_result)) {
                    throw new RuntimeException("clGetCommandQueueInfo illegal cl_uint size=$size");
                }
                $errcode_ret = $ffi->clGetCommandQueueInfo($this->command_queue,
                        $param_name,
                        $size, $uint_result, NULL);
                if($errcode_ret) {
                    throw new RuntimeException("clGetCommandQueueInfo Error errcode=$errcode_ret",$errcode_ret);
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
