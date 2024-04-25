<?php
namespace Rindow\OpenCL\FFI;

use Interop\Polite\Math\Matrix\DeviceBuffer;
use Interop\Polite\Math\Matrix\LinearBuffer as HostBuffer;
use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\OpenCL;
use InvalidArgumentException;
use RuntimeException;
use LogicException;
use FFI;

class Buffer implements DeviceBuffer
{
    use Utils;
    
    /** @var array<int,int> $valueSize */
    protected static $valueSize = [
        NDArray::bool    => 1,
        NDArray::int8    => 1,
        NDArray::int16   => 2,
        NDArray::int32   => 4,
        NDArray::int64   => 8,
        NDArray::uint8   => 1,
        NDArray::uint16  => 2,
        NDArray::uint32  => 4,
        NDArray::uint64  => 8,
        NDArray::float8  => 1,
        NDArray::float16 => 2,
        NDArray::float32 => 4,
        NDArray::float64 => 8,
        NDArray::complex16 => 2,
        NDArray::complex32 => 4,
        NDArray::complex64 => 8,
        NDArray::complex128=> 16,
    ];

    protected FFI $ffi;
    protected ?object $buffer;  // cl_mem
    protected int $size;       // size_t
    protected int $dtype=0;      // int
    protected int $value_size=0; // size_t
    protected HostBuffer $host_buffer; // Buffer for FFI

    public function __construct(FFI $ffi,
        Context $context,
        int $size,
        int $flags=null,
        HostBuffer $host_buffer=null,
        int $host_offset=null,
        int $dtype=null,
        )
    {
        $flags = $flags ?? 0;
        $host_offset = $host_offset ?? 0;
        $this->ffi = $ffi;

        $host_ptr = null;
        if($host_buffer) {
            if((($host_buffer->count() - $host_offset) * $host_buffer->value_size())<$size) {
                throw new InvalidArgumentException("Host buffer is too small.", OpenCL::CL_INVALID_VALUE);
            }
            $host_ptr = $host_buffer->addr($host_offset);
        }
    
        $errcode_ret = $ffi->new('cl_int[1]');
        $buffer = $ffi->clCreateBuffer(
            $context->_getId(),
            $flags,
            $size,
            $host_ptr,
            $errcode_ret);
        if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clCreateBuffer Error errcode=".$errcode_ret[0], $errcode_ret[0]);
        }
        $this->buffer = $buffer;
        $this->size = $size;
        if($flags&(OpenCL::CL_MEM_COPY_HOST_PTR|OpenCL::CL_MEM_USE_HOST_PTR)) {
            $this->dtype = $host_buffer->dtype();
            $this->value_size = $host_buffer->value_size();
        }
        if($dtype) {
            $this->dtype = $dtype;
            if(!array_key_exists($dtype,self::$valueSize)) {
                throw new InvalidArgumentException("unknown dtype: ".$dtype);
            }
            $this->value_size = self::$valueSize[$dtype];
        }
        if(($flags&OpenCL::CL_MEM_USE_HOST_PTR) && $host_buffer!=NULL) {
            $this->host_buffer = $host_buffer;
        }
    }

    public function __destruct()
    {
        if($this->buffer) {
            $errcode_ret = $this->ffi->clReleaseMemObject($this->buffer);
            $this->buffer = null;
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                echo "WARNING: clReleaseMemObject error=$errcode_ret\n";
            }
        }
    }
    
    public function dtype() : int
    {
        return $this->dtype;
    }

    public function value_size() : int
    {
        return $this->value_size;
    }

    public function bytes() : int
    {
        return $this->size;
    }

    public function _getId() : object
    {
        return $this->buffer;
    }

    public function read(
        CommandQueue $command_queue,
        HostBuffer $host_buffer,      
        int $size=null,
        int $offset=null,
        int $host_offset=null,
        bool $blocking_read=null,
        EventList $events=null,
        EventList $wait_events=null,
    ) : void
    {
        $size = $size ?? 0;
        $offset = $offset ?? 0;
        $host_offset = $host_offset ?? 0;
        $blocking_read = $blocking_read ?? true;
        $blocking_read = $blocking_read ? 1:0;

        $ffi = $this->ffi;
        if($size==0) {
            $size = $this->size;
        }
        if($size+$offset > $this->size) {
            throw new InvalidArgumentException("size is too large.", OpenCL::CL_INVALID_VALUE);
        }
        if(((count($host_buffer) - $host_offset) * $host_buffer->value_size())<$size) {
            throw new InvalidArgumentException("Host buffer is too small.", OpenCL::CL_INVALID_VALUE);
        }
        $host_ptr = $host_buffer->addr($host_offset);
    
        $event_p = null;
        if($events) {
            $event_p = $ffi->new("cl_event[1]");
        }
    
        $wait_events_p = null;
        $num_events_in_wait_list = 0;
        if($wait_events) {
            $num_events_in_wait_list = count($wait_events);
            $wait_events_p = $wait_events->_getIds();
        }
    
        $errcode_ret = $ffi->clEnqueueReadBuffer(
            $command_queue->_getId(),
            $this->buffer,
            $blocking_read,
            $offset,
            $size,
            $host_ptr,
            $num_events_in_wait_list,
            $wait_events_p,
            $event_p);
    
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clEnqueueReadBuffer Error errcode=".$errcode_ret, $errcode_ret);
        }
    
        // append event to events
        if($events) {
            $events->_move($event_p);
        }
    }

    /**
     * @param array<int> $region
     * @param array<int> $buffer_offset
     * @param array<int> $host_offset
     */
    public function readRect(
        CommandQueue $command_queue,
        HostBuffer $host_buffer,      
        array $region,
        int $host_buffer_offset=NULL,
        array $buffer_offset=NULL,
        array $host_offset=NULL,
        int $buffer_row_pitch=NULL,
        int $buffer_slice_pitch=NULL,
        int $host_row_pitch=NULL,
        int $host_slice_pitch=NULL,
        bool $blocking_read=NULL,
        EventList $events=NULL,
        EventList $wait_events=NULL,
    ) : void
    {
        $host_buffer_offset = $host_buffer_offset ?? 0;
        $buffer_row_pitch = $buffer_row_pitch ?? 0;
        $buffer_slice_pitch = $buffer_slice_pitch ?? 0;
        $host_row_pitch = $host_row_pitch ?? 0;
        $host_slice_pitch = $host_slice_pitch ?? 0;
        $blocking_read = $blocking_read ?? true;
        $blocking_read = $blocking_read ? 1:0;

        $ffi = $this->ffi;
        $errcode_ret = 0;
        $tmp_dim = 3;
        $region = $this->array_to_integers(
            $region, $tmp_dim, 
            $this->CONSTRAINT_GREATER_ZERO,
            $errcode_ret, no_throw:true);

        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new InvalidArgumentException("Invalid region size. errcode=$errcode_ret", $errcode_ret);
        }
        for($i=0;$i<3;$i++) {
            if($region[$i]==0) {
                $region[$i] = 1;
            }
        }

        if($buffer_offset) {
            $tmp_dim = 3;
            $buffer_offsets = $this->array_to_integers(
                $buffer_offset, $tmp_dim,
                $this->CONSTRAINT_GREATER_OR_EQUAL_ZERO,
                $errcode_ret, no_throw:true
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid buffer_offsets. errcode=$errcode_ret", $errcode_ret);
            }
        } else {
            $buffer_offsets = $ffi->new("size_t[3]");
            FFI::memset($buffer_offsets,0,FFI::sizeof($buffer_offsets));
        }
    
        if($host_offset) {
            $tmp_dim = 3;
            $host_offsets = $this->array_to_integers(
                $host_offset, $tmp_dim,
                $this->CONSTRAINT_GREATER_OR_EQUAL_ZERO,
                $errcode_ret, no_throw:true
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid host_offsets. errcode=$errcode_ret", $errcode_ret);
            }
        } else {
            $host_offsets = $ffi->new("size_t[3]");
            FFI::memset($host_offsets,0,FFI::sizeof($host_offsets));
        }
    
        if($buffer_row_pitch<0) {
            throw new InvalidArgumentException("buffer_row_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } else if($buffer_row_pitch==0) {
            $buffer_row_pitch = $region[0];
        }
    
        if($buffer_slice_pitch<0) {
            throw new InvalidArgumentException("buffer_slice_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } elseif($buffer_slice_pitch==0) {
            $buffer_slice_pitch = $region[1]*$buffer_row_pitch;
        }
    
        if($host_row_pitch<0) {
            throw new InvalidArgumentException("host_row_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } else if($host_row_pitch==0) {
            $host_row_pitch = $region[0];
        }
    
        if($host_slice_pitch<0) {
            throw new InvalidArgumentException("host_slice_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } else if($host_slice_pitch==0) {
            $host_slice_pitch = $region[1]*$host_row_pitch;
        }
    
        {
            $pos_max
                = ($host_offsets[2]+$region[2]-1)*$host_slice_pitch
                + ($host_offsets[1]+$region[1]-1)*$host_row_pitch
                + ($host_offsets[0]+$region[0]-1);
            if($pos_max >= ((count($host_buffer) - $host_buffer_offset) * $host_buffer->value_size())) {
                throw new InvalidArgumentException("Host buffer is too small.", OpenCL::CL_INVALID_VALUE);
            }
        }
        $host_ptr = $host_buffer->addr($host_buffer_offset);
    
        {
            $pos_max
                = ($buffer_offsets[2]+$region[2]-1)*$buffer_slice_pitch
                + ($buffer_offsets[1]+$region[1]-1)*$buffer_row_pitch
                + ($buffer_offsets[0]+$region[0]-1);
            if($pos_max >= $this->size) {
                throw new InvalidArgumentException("buffer is too small.", OpenCL::CL_INVALID_VALUE);
            }
        }
    
        $event_p = null;
        if($events) {
            $event_p = $ffi->new("cl_event[1]");
        }
    
        $wait_event_p = null;
        $num_events_in_wait_list = 0;
        if($wait_events) {
            $num_events_in_wait_list = count($wait_events);
            $wait_events_p  = $wait_events->_getIds();
        }
    
        $errcode_ret = $ffi->clEnqueueReadBufferRect(
            $command_queue->_getId(),
            $this->buffer,
            $blocking_read,
            $buffer_offsets,
            $host_offsets,
            $region,
            $buffer_row_pitch,
            $buffer_slice_pitch,
            $host_row_pitch,
            $host_slice_pitch,
            $host_ptr,
            $num_events_in_wait_list,
            $wait_event_p,
            $event_p);
    
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clEnqueueReadBufferRect Error errcode=".$errcode_ret, $errcode_ret);
        }
    
        // append event to events
        if($events) {
            $events->_move($event_p);
        }
    }
    
    public function write(
        CommandQueue $command_queue,
        HostBuffer $host_buffer,
        int $size=null,
        int $offset=null,
        int $host_offset=null,
        bool $blocking_write=null,
        EventList $events=null,
        EventList $wait_events=null,
    ) : void
    {
        $size = $size ?? 0;
        $offset = $offset ?? 0;
        $host_offset = $host_offset ?? 0;
        $blocking_write = $blocking_write ?? true;
        $blocking_write = $blocking_write ? 1:0;

        $ffi = $this->ffi;
        if($size==0) {
            $size = $this->size;
        }
        if($size+$offset > $this->size) {
            throw new InvalidArgumentException("size is too large.", OpenCL::CL_INVALID_VALUE);
        }
        if(((count($host_buffer) - $host_offset) * $host_buffer->value_size())<$size) {
            throw new InvalidArgumentException("Host buffer is too small.", OpenCL::CL_INVALID_VALUE);
        }
        $host_ptr = $host_buffer->addr($host_offset);
    
        $event_p = null;
        if($events) {
            $event_p = $ffi->new("cl_event[1]");
        }
    
        $wait_events_p = null;
        $num_events_in_wait_list = 0;
        if($wait_events) {
            $num_events_in_wait_list = count($wait_events);
            $wait_events_p = $wait_events->_getIds();
        }
    
        $errcode_ret = $ffi->clEnqueueWriteBuffer(
            $command_queue->_getId(),
            $this->buffer,
            $blocking_write,
            $offset,
            $size,
            $host_ptr,
            $num_events_in_wait_list,
            $wait_events_p,
            $event_p);
    
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clEnqueueWriteBuffer Error errcode=".$errcode_ret, $errcode_ret);
        }
        $this->dtype = $host_buffer->dtype();
        $this->value_size = $host_buffer->value_size();
    
        // append event to events
        if($events) {
            $events->_move($event_p);
        }
    }

    /**
     * @param array<int> $region
     * @param array<int> $buffer_offset
     * @param array<int> $host_offset
     */
    public function writeRect(
        CommandQueue $command_queue,
        HostBuffer $host_buffer,
        array $region,
        int $host_buffer_offset=null,
        array $buffer_offset=null,
        array $host_offset=null,
        int $buffer_row_pitch=null,
        int $buffer_slice_pitch=null,
        int $host_row_pitch=null,
        int $host_slice_pitch=null,
        bool $blocking_write=null,
        EventList $events=null,
        EventList $wait_events=null,
    ) : void
    {
        $host_buffer_offset = $host_buffer_offset ?? 0;
        $buffer_row_pitch = $buffer_row_pitch ?? 0;
        $buffer_slice_pitch = $buffer_slice_pitch ?? 0;
        $host_row_pitch = $host_row_pitch ?? 0;
        $host_slice_pitch = $host_slice_pitch ?? 0;
        $blocking_write = $blocking_write ?? true;
        $blocking_write = $blocking_write ? 1:0;
    
        $ffi = $this->ffi;
        $errcode_ret = 0;
        {
            $tmp_dim = 3;
            $region = $this->array_to_integers(
                $region, $tmp_dim,
                $this->CONSTRAINT_GREATER_ZERO,
                $errcode_ret, no_throw:true
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid region size. errcode=$errcode_ret", $errcode_ret);
            }
            for($i=0;$i<3;$i++) {
                if($region[$i]==0) {
                    $region[$i] = 1;
                }
            }
        }
    
        if($buffer_offset) {
            $tmp_dim = 3;
            $buffer_offsets = $this->array_to_integers(
                $buffer_offset, $tmp_dim,
                $this->CONSTRAINT_GREATER_OR_EQUAL_ZERO,
                $errcode_ret, no_throw:true
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid buffer_offsets. errcode=$errcode_ret", $errcode_ret);
            }
        } else {
            $buffer_offsets = $ffi->new("size_t[3]");
            FFI::memset($buffer_offsets,0,FFI::sizeof($buffer_offsets));
        }
    
        if($host_offset) {
            $tmp_dim = 3;
            $host_offsets = $this->array_to_integers(
                $host_offset, $tmp_dim,
                $this->CONSTRAINT_GREATER_OR_EQUAL_ZERO,
                $errcode_ret, no_throw:true
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid host_offsets. errcode=$errcode_ret", $errcode_ret);
            }
        } else {
            $host_offsets = $ffi->new("size_t[3]");
            FFI::memset($host_offsets,0,FFI::sizeof($host_offsets));
        }
    
        if($buffer_row_pitch<0) {
            throw new InvalidArgumentException("buffer_row_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } elseif($buffer_row_pitch==0) {
            $buffer_row_pitch = $region[0];
        }
    
        if($buffer_slice_pitch<0) {
            throw new InvalidArgumentException("buffer_slice_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } elseif($buffer_slice_pitch==0) {
            $buffer_slice_pitch = $region[1]*$buffer_row_pitch;
        }
    
        if($host_row_pitch<0) {
            throw new InvalidArgumentException("host_row_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } elseif($host_row_pitch==0) {
            $host_row_pitch = $region[0];
        }
    
        if($host_slice_pitch<0) {
            throw new InvalidArgumentException("host_slice_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } else if($host_slice_pitch==0) {
            $host_slice_pitch = $region[1]*$host_row_pitch;
        }
    
        {
            $pos_max
                = ($host_offsets[2]+$region[2]-1)*$host_slice_pitch
                + ($host_offsets[1]+$region[1]-1)*$host_row_pitch
                + ($host_offsets[0]+$region[0]-1);
            if($pos_max >= ((count($host_buffer) - $host_buffer_offset) * $host_buffer->value_size())) {
                throw new InvalidArgumentException("Host buffer is too small.", OpenCL::CL_INVALID_VALUE);
            }
        }
        $host_ptr = $host_buffer->addr($host_buffer_offset);
    
        {
            $pos_max
                = ($buffer_offsets[2]+$region[2]-1)*$buffer_slice_pitch
                + ($buffer_offsets[1]+$region[1]-1)*$buffer_row_pitch
                + ($buffer_offsets[0]+$region[0]-1);
            if($pos_max >= $this->size) {
                throw new InvalidArgumentException("buffer is too small.", OpenCL::CL_INVALID_VALUE);
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
    
        $errcode_ret = $ffi->clEnqueueWriteBufferRect(
            $command_queue->_getId(),
            $this->buffer,
            $blocking_write,
            $buffer_offsets,
            $host_offsets,
            $region,
            $buffer_row_pitch,
            $buffer_slice_pitch,
            $host_row_pitch,
            $host_slice_pitch,
            $host_ptr,
            $num_events_in_wait_list,
            $wait_events_p,
            $event_p);
    
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clEnqueueReadBufferRect Error errcode=".$errcode_ret, $errcode_ret);
        }
    
        // append event to events
        if($events) {
            $events->_move($event_p);
        }
    }

#ifdef CL_VERSION_1_2
    public function fill(
        CommandQueue $command_queue,
        HostBuffer $pattern_buffer,
        int $size=null,
        int $offset=null,
        int $pattern_size=null,
        int $pattern_offset=null,
        EventList $events=null,
        EventList $wait_events=null,
    ) : void
    {
        $size = $size ?? 0;
        $offset = $offset ?? 0;
        $pattern_size = $pattern_size ?? 0;
        $pattern_offset = $pattern_offset ?? 0;
    
        $ffi = $this->ffi;
        $errcode_ret = 0;
    
        if($pattern_size==0) {
            $pattern_size = count($pattern_buffer);
        }
        if(count($pattern_buffer) - $pattern_offset < $pattern_size) {
            throw new InvalidArgumentException("Host buffer is too small.", OpenCL::CL_INVALID_VALUE);
        }
        $pattern_ptr = $pattern_buffer->addr($pattern_offset);
    
        if($size==0) {
            $size = $this->size;
        }
    
        $event_p = null;
        if($events) {
            $event_p = $ffi->new("cl_event[1]");
        }
        $num_events_in_wait_list = 0;
        $wait_events_p= null;
        if($wait_events) {
            $num_events_in_wait_list = count($wait_events);
            $wait_events_p = $wait_events->_getIds();
        }
    
        //if(1) {
        //    zend_throw_exception_ex(spl_ce_RuntimeException, errcode_ret, 
        //        "debug=%d,offset=%d,size=%d,pattern_size=%d,pattern_value_size=%d",
        //        3,(int)offset,(int)size,(int)pattern_size,(int)(pattern_buffer_obj->value_size));
        //    return;
        //}
        //php_printf("debug=%d,offset=%d,size=%d,pattern_size=%d,pattern_value_size=%d,sizeof(cl_float)=%d\n",
        //        3,(int)offset,(int)size,(int)pattern_size,(int)(pattern_buffer_obj->value_size),(int)sizeof(cl_float));
        //if(num_events_in_wait_list!=0 || event_wait_list!=NULL) {
        //    zend_throw_exception_ex(spl_ce_RuntimeException, errcode_ret, 
        //    "event_wait_list is not null");
        //    return;
        //}
        $errcode_ret = $ffi->clEnqueueFillBuffer(
            $command_queue->_getId(),
            $this->buffer,
            $pattern_ptr,
            ($pattern_size*($pattern_buffer->value_size())),
            $offset,
            $size,
            $num_events_in_wait_list,
            $wait_events_p,
            $event_p);
    
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clEnqueueFillBuffer Error errcode=".$errcode_ret, $errcode_ret);
        }
        $this->dtype = $pattern_buffer->dtype();
        $this->value_size = $pattern_buffer->value_size();
    
        // append event to events
        if($events) {
            $events->_move($event_p);
        }
    }
#endif

    public function copy(
        CommandQueue $command_queue,
        self $src_buffer,
        int $size=null,
        int $src_offset=null,
        int $dst_offset=null,
        EventList $events=null,
        EventList $wait_events=null,
    ) : void
    {
        $size = $size ?? 0;
        $src_offset = $src_offset ?? 0;
        $dst_offset = $dst_offset ?? 0;

        $ffi = $this->ffi;
        if($size==0) {
            $size = $src_buffer->bytes();
        }
    
        $event_p = null;
        if($events) {
            $event_p = $ffi->new("cl_event[1]");
        }
        $num_events_in_wait_list = 0;
        $wait_events_p= null;
        if($wait_events) {
            $num_events_in_wait_list = count($wait_events);
            $wait_events_p = $wait_events->_getIds();
        }
    
        $errcode_ret = $ffi->clEnqueueCopyBuffer(
            $command_queue->_getId(),
            $src_buffer->_getId(),
            $this->buffer,
            $src_offset,
            $dst_offset,
            $size,
            $num_events_in_wait_list,
            $wait_events_p,
            $event_p);
    
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clEnqueueWriteBuffer Error errcode=".$errcode_ret, $errcode_ret);
        }
        if($this->dtype==0) {
            $this->dtype = $src_buffer->dtype();
            $this->value_size = $src_buffer->value_size();
        }
    
        // append event to events
        if($events) {
            $events->_move($event_p);
        }
    }

    /**
     * @param array<int> $region
     * @param array<int> $src_origin
     * @param array<int> $dst_origin
     */
    public function copyRect(
        CommandQueue $command_queue,
        Buffer $src_buffer,
        array $region,
        array $src_origin=null,
        array $dst_origin=null,
        int $src_row_pitch=null,
        int $src_slice_pitch=null,
        int $dst_row_pitch=null,
        int $dst_slice_pitch=null,
        EventList $events=null,
        EventList $wait_events=null,
    ) : void
    {
        $src_row_pitch = $src_row_pitch ?? 0;
        $src_slice_pitch = $src_slice_pitch ?? 0;
        $dst_row_pitch = $dst_row_pitch ?? 0;
        $dst_slice_pitch = $dst_slice_pitch ?? 0;
    
        $ffi = $this->ffi;
        $errcode_ret = 0;
        {
            $tmp_dim = 3;
            $region = $this->array_to_integers(
                $region, $tmp_dim,
                $this->CONSTRAINT_GREATER_ZERO,
                $errcode_ret, no_throw:true
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid region size. errcode=$errcode_ret", $errcode_ret);
            }
            for($i=0;$i<3;$i++) {
                if($region[$i]==0) {
                    $region[$i] = 1;
                }
            }
        }
    
        if($src_origin) {
            $tmp_dim = 3;
            $src_origins = $this->array_to_integers(
                $src_origin, $tmp_dim,
                $this->CONSTRAINT_GREATER_OR_EQUAL_ZERO,
                $errcode_ret, no_throw:true
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid source origin. errcode=$errcode_ret", $errcode_ret);
            }
        } else {
            $src_origins = $ffi->new("size_t[3]");
            FFI::memset($src_origins,0,FFI::sizeof($src_origins));
        }
    
        if($dst_origin) {
            $tmp_dim = 3;
            $dst_origins = $this->array_to_integers(
                $dst_origin, $tmp_dim,
                $this->CONSTRAINT_GREATER_OR_EQUAL_ZERO,
                $errcode_ret, no_throw:true
            );
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                throw new InvalidArgumentException("Invalid destination origin. errcode=$errcode_ret", $errcode_ret);
            }
        } else {
            $dst_origins = $ffi->new("size_t[3]");
            FFI::memset($dst_origins,0,FFI::sizeof($dst_origins));
        }
    
        if($src_row_pitch<0) {
            throw new InvalidArgumentException("src_row_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } else if($src_row_pitch==0) {
            $src_row_pitch = $region[0];
        }
    
        if($src_slice_pitch<0) {
            throw new InvalidArgumentException("src_slice_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } else if($src_slice_pitch==0) {
            $src_slice_pitch = $region[1]*$src_row_pitch;
        }
    
        if($dst_row_pitch<0) {
            throw new InvalidArgumentException("dst_row_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } else if($dst_row_pitch==0) {
            $dst_row_pitch = $region[0];
        }
    
        if($dst_slice_pitch<0) {
            throw new InvalidArgumentException("dst_slice_pitch must be greater then or equal zero.", OpenCL::CL_INVALID_VALUE);
        } else if($dst_slice_pitch==0) {
            $dst_slice_pitch = $region[1]*$dst_row_pitch;
        }
    
        {
            $pos_max
                = ($src_origins[2]+$region[2]-1)*$src_slice_pitch
                + ($src_origins[1]+$region[1]-1)*$src_row_pitch
                + ($src_origins[0]+$region[0]-1);
            if($pos_max >= $src_buffer->bytes()) {
                throw new InvalidArgumentException("Source buffer is too small.", OpenCL::CL_INVALID_VALUE);
            }
        }
    
        {
            $pos_max
                = ($dst_origins[2]+$region[2]-1)*$dst_slice_pitch
                + ($dst_origins[1]+$region[1]-1)*$dst_row_pitch
                + ($dst_origins[0]+$region[0]-1);
            if($pos_max >= $this->size) {
                throw new InvalidArgumentException("destination buffer is too small.", OpenCL::CL_INVALID_VALUE);
            }
        }
    
        $event_p = null;
        if($events) {
            $event_p = $ffi->new("cl_event[1]");
        }
        $num_events_in_wait_list = 0;
        $wait_events_p= null;
        if($wait_events) {
            $num_events_in_wait_list = count($wait_events);
            $wait_events_p = $wait_events->_getIds();
        }
    
        $errcode_ret = $ffi->clEnqueueCopyBufferRect(
            $command_queue->_getId(),
            $src_buffer->_getId(),
            $this->buffer,
            $src_origins,
            $dst_origins,
            $region,
            $src_row_pitch,
            $src_slice_pitch,
            $dst_row_pitch,
            $dst_slice_pitch,
            $num_events_in_wait_list,
            $wait_events_p,
            $event_p);
    
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clEnqueueCopyBufferRect Error errcode=".$errcode_ret, $errcode_ret);
        }
    
        // append event to events
        if($events) {
            $events->_move($event_p);
        }
    }

    public function getInfo(
        int $param_name,
        ) : mixed
    {
        $ffi = $this->ffi;
        $id = $this->buffer;
    
        $param_value_size_ret = $ffi->new("size_t[1]");
        $errcode_ret = $ffi->clGetMemObjectInfo($id,
                            $param_name,
                            0, NULL, $param_value_size_ret);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clGetMemObjectInfo Error errcode=".$errcode_ret, $errcode_ret);
        }
    
        switch($param_name) {
            case OpenCL::CL_MEM_TYPE:
            case OpenCL::CL_MEM_MAP_COUNT:
            case OpenCL::CL_MEM_REFERENCE_COUNT: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_uint[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetDeviceInfo illegal int size=$size");
                }
                $errcode_ret = $ffi->clGetMemObjectInfo($id,
                        $param_name,
                        $size, $param_value_val, NULL);
                if($errcode_ret) {
                    throw new RuntimeException("clGetMemObjectInfo Error2 errcode=$errcode_ret",$errcode_ret);
                }
                return $param_value_val[0];
            }
            case OpenCL::CL_MEM_SIZE:
            case OpenCL::CL_MEM_OFFSET: {
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("size_t[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetMemObjectInfo illegal size_t size=$size");
                }
                $errcode_ret = $ffi->clGetMemObjectInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret) {
                    throw new RuntimeException("clGetMemObjectInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
            case OpenCL::CL_MEM_FLAGS:{
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_bitfield[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetMemObjectInfo illegal cl_bitfield size=$size");
                }
                $errcode_ret = $ffi->clGetMemObjectInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret) {
                    throw new RuntimeException("clGetMemObjectInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
/*  OpenCL 2.0
            case OpenCL::CL_MEM_USES_SVM_POINTER:{
                $size = $param_value_size_ret[0];
                $param_value_val = $ffi->new("cl_bool[1]");
                if($size!=$ffi::sizeof($param_value_val)) {
                    throw new RuntimeException("clGetMemObjectInfo illegal bool size=$size");
                }
                $errcode_ret = $ffi->clGetMemObjectInfo($id,
                                        $param_name,
                                        $size, $param_value_val, NULL);
                if($errcode_ret) {
                    throw new RuntimeException("clGetMemObjectInfo Error2 errcode=$errcode_ret");
                }
                return $param_value_val[0];
            }
*/
            //case OpenCL::CL_MEM_CONTEXT: {
            //    $size = $param_value_size_ret[0];
            //    $param_value_val = $ffi->new("cl_context[1]");
            //    if($size!=$ffi::sizeof($param_value_val)) {
            //        throw new RuntimeException("clGetMemObjectInfo illegal cl_context size=$size");
            //    }
            //    $errcode_ret = $ffi->clGetMemObjectInfo($id,
            //            $param_name,
            //            $size, $param_value_val, NULL);
            //    if($errcode_ret) {
            //        throw new RuntimeException("clGetMemObjectInfo Error2 errcode=$errcode_ret",$errcode_ret);
            //    }
            //    return $param_value_val[0];
            //}
            //case OpenCL::CL_MEM_ASSOCIATED_MEMOBJECT: {
            //    $size = $param_value_size_ret[0];
            //    $param_value_val = $ffi->new("cl_mem[1]");
            //    if($size!=$ffi::sizeof($param_value_val)) {
            //        throw new RuntimeException("clGetMemObjectInfo illegal cl_mem size=$size");
            //    }
            //    $errcode_ret = $ffi->clGetMemObjectInfo($id,
            //            $param_name,
            //            $size, $param_value_val, NULL);
            //    if($errcode_ret) {
            //        throw new RuntimeException("clGetMemObjectInfo Error2 errcode=$errcode_ret",$errcode_ret);
            //    }
            //    return $param_value_val[0];
            //}
            default:{
                throw new InvalidArgumentException("invalid param name: $param_name");
            }
        }
    }
    
    public function count() : int
    {
        return $this->bytes()/$this->value_size();
    }

    public function offsetExists( $offset ) : bool
    {
        throw new LogicException("Unsuppored Operation");
    }

    public function offsetGet( $offset ) : mixed
    {
        throw new LogicException("Unsuppored Operation");
    }

    public function offsetSet( $offset , $value ) : void
    {
        throw new LogicException("Unsuppored Operation");
    }

    public function offsetUnset( $offset ) : void
    {
        throw new LogicException("Unsuppored Operation");
    }
}
