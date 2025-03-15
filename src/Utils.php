<?php
namespace Rindow\OpenCL\FFI;

use Interop\Polite\Math\Matrix\OpenCL;
use InvalidArgumentException;
use FFI;

trait Utils
{
    protected int $CONSTRAINT_NONE = 0;
    protected int $CONSTRAINT_GREATER_OR_EQUAL_ZERO = 1;
    protected int $CONSTRAINT_GREATER_ZERO = 2;

    /**
     * @param array<int> $array
     * @return object
     */
    protected function array_to_integers(
        array $array, 
        int &$size, 
        int $constraint, 
        int &$errcode_ret,
        ?bool $no_throw=null,
        ) : object
    {
        $ffi = $this->ffi;
        $num_integers = count($array);
        if($size) {
            $integers = $ffi->new("size_t[$size]");
        } else {
            $integers = $ffi->new("size_t[$num_integers]");
        }
        FFI::memset($integers,0,FFI::sizeof($integers));
        $i = 0;
        foreach ($array as $key => $val) {
            if(!is_int($val)) {
                if($no_throw) {
                    $errcode_ret = -2;
                    return $integers;
                }
                throw new InvalidArgumentException("the array must be array of integer.", OpenCL::CL_INVALID_VALUE);
            }
            if($i<$num_integers) {
                if($constraint==$this->CONSTRAINT_GREATER_ZERO && $val<1) {
                    if($no_throw) {
                        $errcode_ret = -3;
                        return $integers;
                    }
                    throw new InvalidArgumentException("values must be greater zero.", OpenCL::CL_INVALID_VALUE);
                } elseif($constraint==$this->CONSTRAINT_GREATER_OR_EQUAL_ZERO && $val<0) {
                    if($no_throw) {
                        $errcode_ret = -3;
                        return $integers;
                    }
                    throw new InvalidArgumentException("values must be greater or equal zero.", OpenCL::CL_INVALID_VALUE);
                }
                $integers[$i] = $val;
                $i++;
            }
        }
        $errcode_ret = 0;
        $size = $num_integers;
        return $integers;
    }

    /**
     * @param array<string> $array_val
     * @return array{int,object,object,array<object>}
     */
    protected function array_to_strings(
        array $array_val,
        int $mode,
        ) : array 
    {
        $ffi = $this->ffi;
        $num_strings = count($array_val);
        $strings = $ffi->new("char*[$num_strings]");
        $lengths = $ffi->new("size_t[$num_strings]");
        $objs = [];
        $i = 0;
        foreach($array_val as $val) {
            if(!is_string($val)) {
                throw new InvalidArgumentException("the array must be array of string.", OpenCL::CL_INVALID_VALUE);
            }
            if($mode==Program::TYPE_SOURCE_CODE) {
                $val = $val."\0";
            }
            $len = strlen($val);
            $s = $ffi->new("char[$len]");
            FFI::memcpy($s,$val,$len);
            $objs[] = $s;
            $strings[$i] = $ffi->cast("char*",FFI::addr($s));
            $lengths[$i] = $len;
            $i++;
        }
        return [$num_strings,$strings,$lengths,$objs];
    }
    
    /**
     * @param array<string,object> $array_val
     * @return array<mixed>
     */
    protected function array_to_programs(
        array $array_val,
        ?bool $with_names=null,
        ) : array
    {
        $ffi = $this->ffi;
        $num_programs = count($array_val);
        $programs = $ffi->new("cl_program[$num_programs]");
        $index_names = null;
        if($with_names) {
            $index_names = $ffi->new("char*[$num_programs]");
        }
        $objs = [];

        $i = 0;
        foreach($array_val as $key => $val) {
            if(!($val instanceof Program)) {
                throw new InvalidArgumentException("array must be array of Program.", OpenCL::CL_INVALID_VALUE);
            }
            $programs[$i] = $val->_getId();
            if($with_names) {
                $len = strlen($key)+1;
                $s = $ffi->new("char[$len]");
                FFI::memcpy($s,$key."\0",$len);
                $objs[] = $s;
                $index_names[$i] = $ffi->cast("char*",FFI::addr($s));
            }
            $i++;
        }
        $results = [$num_programs,$programs,$objs];
        if($with_names) {
            $results[] = $index_names;
        }
        return $results;
    }
}