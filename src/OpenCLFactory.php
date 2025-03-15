<?php
namespace Rindow\OpenCL\FFI;

use FFI;
//use FFI\Env\Runtime as FFIEnvRuntime;
//use FFI\Env\Status as FFIEnvStatus;
//use FFI\Location\Locator as FFIEnvLocator;
use Interop\Polite\Math\Matrix\LinearBuffer as HostBuffer;
use FFI\Exception as FFIException;
use RuntimeException;

class OpenCLFactory
{
    private static ?FFI $ffi = null;
    /** @var array<string> $libs_win */
    protected array $libs_win = ['OpenCL.dll'];
    /** @var array<string> $libs_linux */
    protected array $libs_linux = ['libOpenCL.so.1'];

    /**
     * @param array<string> $libFiles
     */
    public function __construct(
        ?string $headerFile=null,
        ?array $libFiles=null,
        )
    {
        if(self::$ffi!==null) {
            return;
        }
        if(!extension_loaded('ffi')) {
            return;
        }
        $headerFile = $headerFile ?? __DIR__ . "/opencl.h";
        if($libFiles==null) {
            if(PHP_OS=='Linux') {
                $libFiles = $this->libs_linux;
            } elseif(PHP_OS=='WINNT') {
                $libFiles = $this->libs_win;
            } else {
                throw new RuntimeException('Unknown operating system: "'.PHP_OS.'"');
            }
        }
        //$ffi = FFI::load($headerFile);
        $code = file_get_contents($headerFile);
        // ***************************************************************
        // FFI Locator is incompletely implemented. It is often not found.
        // ***************************************************************
        //$pathname = FFIEnvLocator::resolve(...$libFiles);
        //if($pathname) {
        //    $ffi = FFI::cdef($code,$pathname);
        //    self::$ffi = $ffi;
        //}
        foreach ($libFiles as $filename) {
            try {
                $ffi = FFI::cdef($code,$filename);
            } catch(FFIException $e) {
                continue;
            }
            self::$ffi = $ffi;
            break;
        }
    }

    public function isAvailable() : bool
    {
        return self::$ffi!==null;
        //$isAvailable = FFIEnvRuntime::isAvailable();
        //if(!$isAvailable) {
        //    return false;
        //}
        //$pathname = FFIEnvLocator::resolve(...$this->libs);
        //return $pathname!==null;
    }

    public function PlatformList() : PlatformList
    {
        if(self::$ffi==null) {
            throw new RuntimeException('opencl library not loaded.');
        }
        return new PlatformList(self::$ffi);
    }

    public function DeviceList(
        PlatformList $platforms,
        ?int $index=NULL,
        ?int $deviceType=NULL,
    ) : DeviceList
    {
        if(self::$ffi==null) {
            throw new RuntimeException('opencl library not loaded.');
        }
        return new DeviceList(self::$ffi,$platforms,$index,$deviceType);
    }

    public function Context(
        DeviceList|int $arg
    ) : Context
    {
        if(self::$ffi==null) {
            throw new RuntimeException('opencl library not loaded.');
        }
        return new Context(self::$ffi,$arg);
    }

    public function EventList(
        ?Context $context=null
    ) : EventList
    {
        if(self::$ffi==null) {
            throw new RuntimeException('opencl library not loaded.');
        }
        return new EventList(self::$ffi, $context);
    }

    public function CommandQueue(
        Context $context,
        ?object $deviceId=null,
        ?object $properties=null,
    ) : CommandQueue
    {
        if(self::$ffi==null) {
            throw new RuntimeException('opencl library not loaded.');
        }
        return new CommandQueue(self::$ffi, $context, $deviceId, $properties);
    }

    /**
     * @param string|array<string>|array<string,object> $source
     */
    public function Program(
        Context $context,
        string|array $source,   // string or list of something
        ?int $mode=null,         // mode  0:source codes, 1:binary, 2:built-in kernel, 3:linker
        ?DeviceList $deviceList=null,
        ?string $options=null,
        ) : Program
    {
        if(self::$ffi==null) {
            throw new RuntimeException('opencl library not loaded.');
        }
        return new Program(self::$ffi, $context, $source, $mode, $deviceList, $options);
    }

    public function Buffer(
        Context $context,
        int $size,
        ?int $flags=null,
        ?HostBuffer $hostBuffer=null,
        ?int $hostOffset=null,
        ?int $dtype=null,
        ) : Buffer
    {
        if(self::$ffi==null) {
            throw new RuntimeException('opencl library not loaded.');
        }
        return new Buffer(self::$ffi, $context, $size, $flags, $hostBuffer, $hostOffset, $dtype);
    }

    public function Kernel
    (
        Program $program,
        string $kernelName,
        ) : Kernel
    {
        if(self::$ffi==null) {
            throw new RuntimeException('opencl library not loaded.');
        }
        return new Kernel(self::$ffi, $program, $kernelName);
    }
}
