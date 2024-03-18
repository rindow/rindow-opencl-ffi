<?php
namespace Rindow\OpenCL\FFI;

use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\OpenCL;
use InvalidArgumentException;
use RuntimeException;
use FFI;

class Kernel
{
    use Utils;

    const CONSTRAINT_NONE = 0;
    const CONSTRAINT_GREATER_OR_EQUAL_ZERO = 1;
    const CONSTRAINT_GREATER_ZERO = 2;

    protected static $typeString = [
        NDArray::bool    => 'uint8_t',
        NDArray::int8    => 'int8_t',
        NDArray::int16   => 'int16_t',
        NDArray::int32   => 'int32_t',
        NDArray::int64   => 'int64_t',
        NDArray::uint8   => 'uint8_t',
        NDArray::uint16  => 'uint16_t',
        NDArray::uint32  => 'uint32_t',
        NDArray::uint64  => 'uint64_t',
        //NDArray::float8  => 'N/A',
        //NDArray::float16 => 'N/A',
        NDArray::float32 => 'float',
        NDArray::float64 => 'double',
    ];

    protected FFI $ffi;
    protected object $kernel;

    public function __construct(FFI $ffi,
        Program $program,
        string $kernel_name,
    )
    {
        $this->ffi = $ffi;

        $len = strlen($kernel_name)+1;
        $kernel_name_p = $ffi->new("char[$len]");
        FFI::memcpy($kernel_name_p,$kernel_name."\0",$len);
        $errcode_ret = $ffi->new('cl_int[1]');
        $kernel = $ffi->clCreateKernel(
            $program->_getId(),
            $kernel_name_p,
            $errcode_ret
        );
    
        if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clCreateKernel Error errcode=".$errcode_ret[0], $errcode_ret[0]);
        }
        $this->kernel = $kernel;
    }

    public function __destruct()
    {
        if($this->kernel) {
            $errcode_ret = $this->ffi->clReleaseKernel($this->kernel);
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                echo "WARNING: clReleaseKernel error=$errcode_ret\n";
            }
        }
    }

    public function setArg(
        int $arg_index,
        mixed $arg,    // long | double | opencl_buffer_ce | command_queue_ce
        int $dtype=null,
    )
    {
        $ffi = $this->ffi;
        $dtype = $dtype ?? 0;
    
        if(is_object($arg)) {
            if($arg instanceof Buffer) {
                $arg_value = FFI::addr($arg->_getId());
                $arg_size = FFI::sizeof($arg->_getId());
            } elseif($arg instanceof CommandQueue) {
                $arg_value = FFI::addr($arg->_getId());
                $arg_size = FFI::sizeof($arg->_getId());
            } else {
                throw new InvalidArgumentException("Unsupported argument type", OpenCL::CL_INVALID_VALUE);
            }
        } elseif(is_numeric($arg)) {
            if(!isset(self::$typeString[$dtype])) {
                throw new InvalidArgumentException("Unsuppored binding data type for integer or float:($dtype)", OpenCL::CL_INVALID_VALUE);
            }
            $arg_obj = $ffi->new(self::$typeString[$dtype]."[1]");
            $arg_obj[0] = $arg;
            $arg_value = FFI::addr($arg_obj);
            $arg_size = FFI::sizeof($arg_obj);
        } else if($arg===NULL) {
            $arg_value = NULL;
            $arg_size = $dtype;
        } else {
            throw new InvalidArgumentException("Invalid argument type", OpenCL::CL_INVALID_VALUE);
        }
    
        $errcode_ret = $ffi->clSetKernelArg(
            $this->kernel,
            $arg_index,
            $arg_size,
            $arg_value);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clSetKernelArg Error errcode=".$errcode_ret, $errcode_ret);
        }
    }

    public function enqueueNDRange(
        CommandQueue $command_queue,
        array $global_work_size,
        array $local_work_size=null,
        array $global_work_offset=null,
        EventList $events=null,
        EventList $wait_events=null,
    )
    {
        $ffi = $this->ffi;
        $errcode_ret = 0;

        $work_dim = 0;
        $global_work_size_p = $this->array_to_integers(
            $global_work_size, $work_dim,
            self::CONSTRAINT_GREATER_ZERO,
            $errcode_ret, no_throw:true,
        );
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new InvalidArgumentException("Invalid global work size. errcode=".$errcode_ret, $errcode_ret);
        }
        
        $local_work_size_p = null;
        if($local_work_size) {
            $tmp_dim=0;
            $local_work_size_p = $this->array_to_integers(
                $local_work_size, $tmp_dim,
                self::CONSTRAINT_GREATER_ZERO,
                $errcode_ret, no_throw:true,
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid local work size.", OpenCL::CL_INVALID_VALUE);
            }
            if($work_dim!=$tmp_dim) {
                throw new InvalidArgumentException("Unmatch number of dimensions between global work size and local work size.", OpenCL::CL_INVALID_VALUE);
            }
        }
    
        $global_work_offset_p = null;
        if($global_work_offset) {
            $tmp_dim=0;
            $global_work_offset_p = $this->array_to_integers(
                $global_work_offset, $tmp_dim,
                self::CONSTRAINT_GREATER_ZERO,
                $errcode_ret, no_throw:true,
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid local work size.", OpenCL::CL_INVALID_VALUE);
            }
            if($work_dim!=$tmp_dim) {
                throw new InvalidArgumentException("Unmatch number of dimensions between global work size and global work offset.", OpenCL::CL_INVALID_VALUE);
            }
        }
    
        $event_p = null;
        if($events) {
            $event_p = $ffi->new("cl_event[1]");
        }
    
        $num_events_in_wait_list = 0;
        $wait_events_p = null;
        if($wait_events) {
            $num_events_in_wait_list = count($wait_events);
            $wait_events_p = $wait_events->_getIds();
        }

        $errcode_ret = $ffi->clEnqueueNDRangeKernel(
            $command_queue->_getId(),
            $this->kernel,
            $work_dim,
            $global_work_offset_p,
            $global_work_size_p,
            $local_work_size_p,
            $num_events_in_wait_list,
            $wait_events_p,
            $event_p
        );
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clEnqueueNDRangeKernel Error errcode=".$errcode_ret, $errcode_ret);
        }
    
        // append event to events
        if($events) {
            $events->_move($event_p);
        }
    }
    
    public function getInfo(
        int $param_name,
        )
    {
        $ffi = $this->ffi;
    
        $param_value_size_ret = $ffi->new("size_t[1]");
        $errcode_ret = $ffi->clGetKernelInfo($this->kernel,
                            $param_name,
                            0, NULL, $param_value_size_ret);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetKernelInfo Error errcode=".$errcode_ret, $errcode_ret);
        }
    
        switch($param_name) {
            case OpenCL::CL_KERNEL_REFERENCE_COUNT:
            case OpenCL::CL_KERNEL_NUM_ARGS: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_uint[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal int size=$size");
                }
                $errcode_ret = $ffi->clGetKernelInfo($this->kernel,
                        $param_name,
                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetKernelInfo Error2 errcode=$errcode_ret",$errcode_ret);
                }
                return $param_value_val[0];
            }
#ifdef CL_VERSION_1_2
            case OpenCL::CL_KERNEL_ATTRIBUTES:
#endif
            case OpenCL::CL_KERNEL_FUNCTION_NAME: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_char[$size]");
                $errcode_ret = $ffi->clGetKernelInfo($this->kernel,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetKernelInfo Error2 errcode=$errcode_ret",$errcode_ret);
                }
                return FFI::string($param_value_val,$size-1);
            }
            //case OpenCL::CL_KERNEL_CONTEXT: {
            //    $size = $param_value_size_ret[0];
            //    $param_value_val = $ffi->new("cl_context[1]");
            //    if($size!=$ffi::sizeof($param_value_val)) {
            //        throw new RuntimeException("clGetDeviceInfo illegal cl_context size=$size");
            //    }
            //    $errcode_ret = $ffi->clGetKernelInfo($this->kernel,
            //            $param_name,
            //            $size, $param_value_val, NULL);
            //    if($errcode_ret!=OpenCL::CL_SUCCESS) {
            //        throw new RuntimeException("clGetKernelInfo Error2 errcode=$errcode_ret",$errcode_ret);
            //    }
            //    return $param_value_val[0];
            //}
            case OpenCL::CL_KERNEL_PROGRAM: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_program[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal cl_program size=$size");
                }
                $errcode_ret = $ffi->clGetKernelInfo($this->kernel,
                        $param_name,
                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetKernelInfo Error2 errcode=$errcode_ret",$errcode_ret);
                }
                return $param_value_val[0];
            }
            default:{
                throw new InvalidArgumentException("invalid param name: $param_name");
            }
        }
    }
    
    public function getWorkGroupInfo(
        int $param_name,
        DeviceList $device_list=null,
    )
    {
        $ffi = $this->ffi;
    
        $param_value_size_ret = $ffi->new("size_t[1]");
        if($device_list) {
            if(count($device_list)<1) {
                throw new InvalidArgumentException("device list is empty",OpenCL::CL_INVALID_VALUE);
            }
            $device_id = $device_list->_getIds()[0];
        } else {
            $program = $ffi->new("cl_program[1]");
            $size = FFI::sizeof($program);
            $errcode_ret = $ffi->clGetKernelInfo($this->kernel,
                        OpenCL::CL_KERNEL_PROGRAM,
                        $size, $program, NULL);
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new RuntimeException("clGetKernelInfo(CL_KERNEL_PROGRAM) Error2 errcode=$errcode_ret",$errcode_ret);
            }
            $errcode_ret = $ffi->clGetProgramInfo($program[0],
                                OpenCL::CL_PROGRAM_DEVICES,
                                0, NULL, $param_value_size_ret);
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new RuntimeException("clGetProgramInfo(CL_PROGRAM_DEVICES) Error2 errcode=$errcode_ret",$errcode_ret);
            }
            $size=$param_value_size_ret[0];
            if($size===0) {
                throw new RuntimeException("clGetProgramInfo(CL_PROGRAM_DEVICES) size is 0");
            }
            $sizeofItem = $ffi::sizeof($ffi->new("cl_device_id[1]"));
            if(($size%$sizeofItem)!=0) {
                throw new RuntimeException("clGetProgramInfo(CL_PROGRAM_DEVICES) illegal array<cl_device_id> size=$size");
            }
            $items = $size/$sizeofItem;
            $device_ids = $ffi->new("cl_device_id[$items]");
            $errcode_ret = $ffi->clGetProgramInfo($program[0],
                        OpenCL::CL_PROGRAM_DEVICES,
                        $size, $device_ids, NULL);
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new RuntimeException("clGetProgramInfo(CL_PROGRAM_DEVICES) Error2 errcode=$errcode_ret",$errcode_ret);
            }
            $device_id = $device_ids[0];
        }
    
        $errcode_ret = $ffi->clGetKernelWorkGroupInfo($this->kernel,
                            $device_id,
                            $param_name,
                            0, NULL, $param_value_size_ret);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetKernelWorkGroupInfo Error errcode=$errcode_ret",$errcode_ret);
        }
    
        switch($param_name) {
            case OpenCL::CL_KERNEL_WORK_GROUP_SIZE:
            case OpenCL::CL_KERNEL_PREFERRED_WORK_GROUP_SIZE_MULTIPLE: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("size_t[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetKernelWorkGroupInfo illegal size_t size=$size");
                }
                $errcode_ret = $ffi->clGetKernelWorkGroupInfo($this->kernel,
                        $device_id,
                        $param_name,
                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetKernelWorkGroupInfo Error errcode=$errcode_ret",$errcode_ret);
                }
                return $param_value_val[0];
            }
            case OpenCL::CL_KERNEL_LOCAL_MEM_SIZE:
            case OpenCL::CL_KERNEL_PRIVATE_MEM_SIZE: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_ulong[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetKernelWorkGroupInfo illegal size_t size=$size");
                }
                $errcode_ret = $ffi->clGetKernelWorkGroupInfo($this->kernel,
                        $device_id,
                        $param_name,
                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetKernelWorkGroupInfo Error2 errcode=$errcode_ret",$errcode_ret);
                }
                return $param_value_val[0];
            }
#ifdef CL_VERSION_1_2
            case OpenCL::CL_KERNEL_GLOBAL_WORK_SIZE: 
#endif
            case OpenCL::CL_KERNEL_COMPILE_WORK_GROUP_SIZE: {
                $size=$param_value_size_ret[0];
                if($size===0) {
                    return [];
                }
                $sizeofItem = $ffi::sizeof($ffi->new("size_t[1]"));
                if(($size%$sizeofItem)!=0) {
                    throw new RuntimeException("clGetKernelWorkGroupInfo illegal array<size_t> size=$size");
                }
                $items = $size/$sizeofItem;
                $param_value_val = $ffi->new("size_t[$items]");
                $errcode_ret = $ffi->clGetKernelWorkGroupInfo($this->kernel,
                            $device_id,
                            $param_name,
                            $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetKernelWorkGroupInfo Error2 errcode=$errcode_ret",$errcode_ret);
                }
                // direct set to return_value
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

