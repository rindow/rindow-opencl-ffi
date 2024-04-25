<?php
namespace Rindow\OpenCL\FFI;

use Interop\Polite\Math\Matrix\OpenCL;
use InvalidArgumentException;
use RuntimeException;
use FFI;

class Program
{
    use Utils;
    
    const TYPE_SOURCE_CODE      = 0;
    const TYPE_BINARY           = 1;
    const TYPE_BUILTIN_KERNEL   = 2;
    const TYPE_COMPILED_PROGRAM = 3;

    protected FFI $ffi;
    protected ?object $context;
    //protected int $num_devices;
    //protected object $devices;
    protected ?object $program;

    /**
     * @param string|array<string>|array<string,object> $source
     */
    public function __construct(FFI $ffi,
        Context $context,
        string|array $source,   // string or list of something
        int $mode=null,         // mode  0:source codes, 1:binary, 2:built-in kernel, 3:linker
        DeviceList $device_list=null,
        string $options=null,
    )
    {
        $this->ffi = $ffi;

        $mode = $mode ?? 0;
        $devices = null;
        $num_devices = 0;
        if($device_list!=NULL) {
            if(count($device_list)==0) {
                throw new InvalidArgumentException("devices is empty", OpenCL::CL_INVALID_DEVICE);
            }
            $devices = $device_list->_getIds();
            $num_devices = count($devices);
        }
    
        switch($mode) {
            case self::TYPE_SOURCE_CODE: // source mode
            case self::TYPE_BINARY: {// binary mode
                if(is_string($source)) {
                    $source = [$source];
                }
                if(!is_array($source)) {
                    throw new InvalidArgumentException("argument 2 must be source string or array of strings in source code mode.", OpenCL::CL_INVALID_VALUE);
                }
                [$num_strings, $strings, $lengths, $source_objs] = $this->array_to_strings($source,$mode);
                $errcode_ret = $ffi->new('cl_int[1]');
                if($mode==self::TYPE_SOURCE_CODE) {  // source mode
                    $program = $ffi->clCreateProgramWithSource(
                        $context->_getId(),
                        $num_strings,
                        $strings,
                        $lengths,
                        $errcode_ret);
                    if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
                        throw new RuntimeException("clCreateProgramWithSource Error errcode=".$errcode_ret[0]);
                    }
                } else {  // binary mode
                    $program = $ffi->clCreateProgramWithBinary(
                        $context->_getId(),
                        $num_strings,
                        $devices,
                        $lengths,
                        $strings,
                        NULL,                 // cl_int * binary_status,
                        $errcode_ret);
                    if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
                        throw new RuntimeException("clCreateProgramWithBinary Error errcode=".$errcode_ret[0]);
                    }
                }
                break;
            }
#ifdef CL_VERSION_1_2
            case self::TYPE_BUILTIN_KERNEL: { // built-in kernel mode
                if(!is_string($source)) {
                    throw new InvalidArgumentException("built-in kernel mode must be include kernel name string.", OpenCL::CL_INVALID_VALUE);
                }
                $kernel_names = $source;
                $errcode_ret = $ffi->new('cl_int[1]');
                $program = $ffi->clCreateProgramWithBuiltInKernels(
                    $context->_getId(),
                    $num_devices,
                    $devices,
                    $kernel_names,
                    $errcode_ret);
                if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clCreateProgramWithBuiltInKernels Error errcode=".$errcode_ret[0]);
                }
                break;
            }
            case self::TYPE_COMPILED_PROGRAM:{ // linker mode
                if(!is_array($source)) {
                    throw new InvalidArgumentException("link mode must be include array of programs.", OpenCL::CL_INVALID_VALUE);
                }
                $num_input_programs = [];
                [$num_input_programs,$input_programs,$objs] = $this->array_to_programs($source);
                $errcode_ret = $ffi->new('cl_int[1]');
                $program = $ffi->clLinkProgram(
                    $context->_getId(),
                    $num_devices,
                    $devices,
                    $options,
                    $num_input_programs,
                    $input_programs,
                    NULL,        // CL_CALLBACK *  pfn_notify
                    NULL,        // void * user_data
                    $errcode_ret
                );
                if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clLinkProgram Error errcode=".$errcode_ret[0]);
                }
                break;
            }
#endif
            default: {
                throw new InvalidArgumentException("invalid mode.", OpenCL::CL_INVALID_VALUE);
            }
        } // end switch
        $this->program = $program;
    }

    public function __destruct()
    {
        if($this->program) {
            $errcode_ret = $this->ffi->clReleaseProgram($this->program);
            $this->program = null;
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                echo "WARNING: clReleaseProgram error=$errcode_ret\n";
            }
        }
    }

    public function _getId() : object
    {
        return $this->program;
    }

    public function build(
        string $options=NULL,
        DeviceList $device_list=NULL,
    ) : void
    {
        $ffi = $this->ffi;
        $num_devices = 0;
        $devices = NULL;
        if($device_list) {
            $devices = $device_list->_getIds();
            $num_devices = count($devices);
        }
        $options_obj = null;
        if($options) {
            $len = strlen($options)+1;
            $options_obj = $ffi->new("char[$len]");
            FFI::memcpy($options_obj,$options."\0",$len);
        }
        $errcode_ret = $ffi->clBuildProgram(
            $this->program,
            $num_devices,
            $devices,
            $options_obj,
            NULL,        // CL_CALLBACK *  pfn_notify
            NULL         // void * user_data
        );
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clBuildProgram Error errcode=".$errcode_ret,$errcode_ret);
        }
    }

#ifdef CL_VERSION_1_2
    /**
     * @param array<string,object> $headers
     */
    public function compile(
        array $headers=null,        // ArrayHash<Program> Key:file path Value:program
        string $options=null,       // string
        DeviceList $device_list=null,// DeviceList
        ) : void
    {
        $ffi = $this->ffi;
        $num_input_headers = 0;
        $input_headers = null;
        $header_include_names = null;
        if($headers) {
            [$num_input_headers,$input_headers,$objs,$header_include_names] = $this->array_to_programs($headers, with_names:true);
        }
        $devices = null;
        $num_devices = 0;
        if($device_list) {
            $devices = $device_list->_getIds();
            $num_devices = count($devices);
        }
        $errcode_ret = $ffi->clCompileProgram(
            $this->program,
            $num_devices,
            $devices,
            $options,
            $num_input_headers,
            $input_headers,
            $header_include_names,
            NULL,        // CL_CALLBACK *  pfn_notify
            NULL         // void * user_data
        );
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clCompileProgram Error errcode=".$errcode_ret,$errcode_ret);
        }
    }
#endif

    public function getInfo(
        int $param_name
    ) : mixed
    {
        $ffi = $this->ffi;
        $param_value_size_ret = $ffi->new("size_t[1]");
        $errcode_ret = $ffi->clGetProgramInfo($this->program,
                                $param_name,
                                0,
                                NULL,
                                $param_value_size_ret);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetProgramInfo Error errcode=$errcode_ret");
        }
        switch($param_name) {
            case OpenCL::CL_PROGRAM_REFERENCE_COUNT:
            case OpenCL::CL_PROGRAM_NUM_DEVICES: {
                $size = $param_value_size_ret[0];
                $uint_result = $ffi->new("cl_uint[1]");
                if($size!=$ffi::sizeof($uint_result)) {
                    throw new RuntimeException("clGetProgramInfo illegal uint size=$size");
                }
                $errcode_ret = $ffi->clGetProgramInfo($this->program,
                                        $param_name,
                                        $size, $uint_result, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetProgramInfo Error2 errcode=$errcode_ret");
                }
                return $uint_result[0];
            }
#ifdef CL_VERSION_1_2
            case OpenCL::CL_PROGRAM_NUM_KERNELS: {
                $size = $param_value_size_ret[0];
                if($size==4) {
                    $size_t_result = $ffi->new("cl_uint[1]");
                } else {
                    $size_t_result = $ffi->new("size_t[1]");
                }
                if($size!=$ffi::sizeof($size_t_result)) {
                    throw new RuntimeException("clGetProgramInfo illegal uint or size_t size=$size");
                }
                $errcode_ret = $ffi->clGetProgramInfo($this->program,
                                        $param_name,
                                        $size, $size_t_result, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetProgramInfo Error2 errcode=$errcode_ret");
                }
                return $size_t_result[0];
            }
#endif
#ifdef CL_VERSION_1_2
            case OpenCL::CL_PROGRAM_KERNEL_NAMES:
#endif
            case OpenCL::CL_PROGRAM_SOURCE: {
                $size = $param_value_size_ret[0];
                $param_value = $ffi->new("char[$size]");
                $errcode_ret = $ffi->clGetProgramInfo($this->program,
                                    $param_name,
                                    $size, $param_value, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetProgramInfo Error errcode=$errcode_ret");
                }
                $param_value_val = FFI::string($param_value,$size-1);
                return $param_value_val;
            }
            case OpenCL::CL_PROGRAM_DEVICES: {
                $size = $param_value_size_ret[0];
                $sizeofItem = $ffi::sizeof($ffi->new("cl_device_id[1]"));
                $items = $size/$sizeofItem;
                if(($size%$sizeofItem)!=0) {
                    throw new RuntimeException("clGetProgramInfo illegal array<cl_device_id> size=$size");
                }
                $device_ids = $ffi->new("cl_device_id[$items]");
                $errcode_ret = $ffi->clGetProgramInfo($this->program,
                            $param_name,
                            $size, $device_ids, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetProgramInfo Error errcode=$errcode_ret");
                }
                // direct set to return_value
                $dummy = new PlatformList($ffi,$ffi->new("cl_platform_id[1]"));
                return new DeviceList($ffi,$dummy,devices:$device_ids);
            }
            case OpenCL::CL_PROGRAM_BINARY_SIZES: {
                $size = $param_value_size_ret[0];
                if($size===0) {
                    return [];
                }
                $sizeofItem = $ffi::sizeof($ffi->new("size_t[1]"));
                if(($size%$sizeofItem)!=0) {
                    throw new RuntimeException("clGetProgramInfo illegal array<size_t> size=$size");
                }
                $items = $size/$sizeofItem;
                $param_value_val = $ffi->new("size_t[$items]");
                $errcode_ret = $ffi->clGetProgramInfo($this->program,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetProgramInfo Error errcode=$errcode_ret");
                }
                $results = [];
                for($i=0; $i<$items; $i++) {
                    $results[] = $param_value_val[$i];
                }
                return $results;
            }
            default:{
                throw new InvalidArgumentException("invalid param name: $param_name");
            }
        }
    }

    public function getBuildInfo(
        int $param_name,
        DeviceList $device_list=null,
        int $device_index=null,
    ) : mixed
    {
        $ffi = $this->ffi;
        if($device_list===null) {
            $device_list = $this->getInfo(OpenCL::CL_PROGRAM_DEVICES);
        }
        $device_index = $device_index ?? 0;

        $devices = $device_list->_getIds();
        if($devices===NULL) {
            throw new InvalidArgumentException("devices is empty.", OpenCL::CL_INVALID_DEVICE);
        }
        if($device_index<0 || $device_index >= count($devices)) {
            throw new InvalidArgumentException("invalid device index.", OpenCL::CL_INVALID_DEVICE);
        }
        $device = $devices[$device_index];
    
        $param_value_size_ret = $ffi->new("size_t[1]");
        $errcode_ret = $ffi->clGetProgramBuildInfo($this->program,
                                $device,
                                $param_name,
                                0, NULL, $param_value_size_ret);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetProgramBuildInfo Error errcode=$errcode_ret");
        }
    
        switch($param_name) {
            case OpenCL::CL_PROGRAM_BUILD_STATUS: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_int[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetProgramBuildInfo illegal int size=$size");
                }
                $errcode_ret = $ffi->clGetProgramBuildInfo($this->program,
                                        $device,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetProgramBuildInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
#ifdef CL_VERSION_1_2
            case OpenCL::CL_PROGRAM_BINARY_TYPE: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_uint[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetProgramBuildInfo illegal uint size=$size");
                }
                $errcode_ret = $ffi->clGetProgramBuildInfo($this->program,
                                        $device,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetProgramBuildInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
#endif
            case OpenCL::CL_PROGRAM_BUILD_OPTIONS:
            case OpenCL::CL_PROGRAM_BUILD_LOG: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_char[$size]");
                $errcode_ret = $ffi->clGetProgramBuildInfo($this->program,
                                        $device,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetProgramBuildInfo Error2 errcode=$errcode_ret");
                }
                return FFI::string($param_value_val,$size-1);
            }
            default:{
                throw new InvalidArgumentException("invalid param name: $param_name");
            }
        }
    }
}
