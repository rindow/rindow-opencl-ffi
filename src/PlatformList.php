<?php
namespace Rindow\OpenCL\FFI;

use Interop\Polite\Math\Matrix\OpenCL;
use InvalidArgumentException;
use RuntimeException;
use OutOfRangeException;
use FFI;
use Countable;

class PlatformList implements Countable
{
    protected FFI $ffi;
    protected int $num;
    protected object $platforms;

    public function __construct(FFI $ffi, object $platforms=NULL)
    {
        $this->ffi = $ffi;
        if($platforms!==NULL) {
            $num = count($platforms);
            $this->num = $num;
            $this->platforms = $platforms;
            return;
        }
        $numPlatforms = $ffi->new("cl_uint[1]");
        $errcode_ret = $ffi->clGetPlatformIDs(0, NULL, $numPlatforms);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetPlatformIDs Error errcode=$errcode_ret");
        }
        $num = $numPlatforms[0];
        $platforms = $ffi->new("cl_platform_id[$num]");
        $errcode_ret = $ffi->clGetPlatformIDs($num, $platforms, $numPlatforms);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetPlatformIDs Error2 errcode=$errcode_ret");
        }
        $this->num = $num;
        $this->platforms = $platforms;
    }

    public function __destruct()
    {
    }

    public function _getIds() : object
    {
        return $this->platforms;
    }

    public function count() : int
    {
        return $this->num;
    }

    public function getOne(int $offset) : self
    {
        $ffi= $this->ffi;
        if($offset<0 || $offset>=$this->num) {
            throw new OutOfRangeException("Invalid index of platforms: $offset");
        }
        $this->platforms[$offset];
        $platforms = $ffi->new("cl_platform_id[1]");
        $platforms[0] = $this->platforms[$offset];
        $obj = new self($ffi,$platforms);
        return $obj;
    }

    public function getInfo(int $offset, int $param_name) : mixed
    {
        $ffi= $this->ffi;
        if($offset<0 || $offset>=$this->num) {
            throw new OutOfRangeException("Invalid index of platforms: $offset");
        }
        $id = $this->platforms[$offset];
        $param_value_size_ret = $ffi->new("size_t[1]");
        $errcode_ret = $ffi->clGetPlatformInfo($id, $param_name,
                                0, NULL, $param_value_size_ret);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetPlatformInfo Error errcode=$errcode_ret");
        }
        switch($param_name) {
            case OpenCL::CL_PLATFORM_PROFILE:
            case OpenCL::CL_PLATFORM_VERSION:
            case OpenCL::CL_PLATFORM_NAME:
            case OpenCL::CL_PLATFORM_VENDOR:
            case OpenCL::CL_PLATFORM_EXTENSIONS: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_char[$size]");
                $errcode_ret = $ffi->clGetPlatformInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    throw new RuntimeException("clGetPlatformInfo Error errcode=$errcode_ret");
                }
                return FFI::string($param_value_val,$size-1);
            }
            default: {
                throw new InvalidArgumentException("invalid param name: $param_name");
            }
        }
    }
}