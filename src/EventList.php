<?php
namespace Rindow\OpenCL\FFI;

use Interop\Polite\Math\Matrix\OpenCL;
use RuntimeException;
use OutOfRangeException;
use FFI;
use Countable;

class EventList implements Countable
{
    protected FFI $ffi;
    protected int $num=0;
    protected ?object $events=null;

    public function __construct(FFI $ffi,
        Context $context=NULL
        )
    {
        $this->ffi = $ffi;
        if($context===null) {
            $this->num = 0;
            $this->events = null;
            return;
        }
        $errcode_ret = $ffi->new('cl_int[1]');
        $event = $ffi->clCreateUserEvent(
            $context->_getId(),
            $errcode_ret);
        if($errcode_ret[0]!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clCreateUserEvent Error errcode=".$errcode_ret[0]);
        }
        $this->num = 1;
        $events = $ffi->new('cl_event[1]');
        $events[0] = $event;
        $this->events = $events;
    }

    public function __destruct()
    {
        $this->clear();
    }

    public function _ffi() : FFI
    {
        return $this->ffi;
    }

    public function _getIds(bool $move=null) : object
    {
        $events = $this->events;
        if($move) {
            $this->events = null;
            $this->num = 0;
        }
        return $events;
    }

    public function count() : int
    {
        return $this->num;
    }

    public function wait() : void
    {
        $ffi = $this->ffi;
        if($this->events===NULL) {
            throw new RuntimeException("EventList is not initialized");
        }
        $errcode_ret = $ffi->clWaitForEvents($this->num,$this->events);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clWaitForEvents Error errcode=".$errcode_ret);
        }
    }

    public function clear() : void
    {
        $ffi = $this->ffi;
        if($this->events) {
            for($i=0;$i<$this->num;$i++) {
                $errcode_ret = $ffi->clReleaseEvent($this->events[$i]);
                if($errcode_ret!=OpenCL::CL_SUCCESS) {
                    echo "WARNING: clReleaseEvent error=$errcode_ret\n";
                }
            }
        }
        $this->events = null;
        $this->num = 0;
    }

    public function _move(object $events) : void
    {
        $ffi = $this->ffi;
        if($this->num==0) {
            $this->events = $events;
            $this->num = count($events);
            return;
        }
        $num = $this->num + count($events);
        $newEvnets = $ffi->new("cl_event[$num]");
        FFI::memcpy($newEvnets,$this->events,FFI::sizeof($this->events));
        FFI::memcpy(FFI::addr($newEvnets[$this->num]),$events,FFI::sizeof($events));
        $this->events = $newEvnets;
        $this->num = $num;
    }

    public function move(self $events) : void
    {
        $ffi = $this->ffi;
        $count = count($events);
        if($count==0) {
            return;
        }
        $eventItems = $events->_getIds(move:true);
        $this->_move($eventItems);
    }

    public function copy(self $events) : void
    {
        $ffi = $this->ffi;
        $count = count($events);
        if($count==0) {
            return;
        }
        $num = $this->num + $count;
        $newEvnets = $ffi->new("cl_event[$num]");
        $eventItems = $events->_getIds();
        if($this->num!=0) {
            FFI::memcpy($newEvnets,$this->events,FFI::sizeof($this->events));
        }
        FFI::memcpy(FFI::addr($newEvnets[$this->num]),$eventItems,FFI::sizeof($eventItems));
        for($i=0;$i<$count;$i++) {
            $errcode_ret = $ffi->clRetainEvent($eventItems[$i]);
            if($errcode_ret!=OpenCL::CL_SUCCESS) {
                echo "WARNING: clRetainEvent error=$errcode_ret\n";
            }
        }
        $this->events = $newEvnets;
        $this->num = $num;
    }

    public function setStatus(int $execution_status, int $index=null) : void
    {
        $ffi = $this->ffi;
        $index = $index ?? 0;
        if($index<0 || $index>=$this->num) {
            throw new OutOfRangeException("event index is out of range");
        }
    
        $errcode_ret = $ffi->clSetUserEventStatus(
            $this->events[$index],
            $execution_status);
        if($errcode_ret!=OpenCL::CL_SUCCESS) {
            throw new RuntimeException("clSetUserEventStatus Error errcode=".$errcode_ret);
        }
    }
}
