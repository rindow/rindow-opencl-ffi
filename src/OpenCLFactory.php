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
    const STAUTS_OK = 0;
    const STAUTS_LIBRARY_NOT_LOADED = -1;
    const STATUS_CONFIGURATION_NOT_COMPLETE = -2;
    const STATUS_DEVICE_NOT_FOUND = -3;
    
    private static ?FFI $ffi = null;
    private static ?string $statusMessage = null;
    private static int $status = 0;

    /** @var array<string> $libs_win */
    protected array $libs_win = ['OpenCL.dll'];
    /** @var array<string> $libs_linux */
    protected array $libs_linux = ['libOpenCL.so.1'];
    /** @var array<string> $libs_mac */
    protected array $libs_mac = ['/System/Library/Frameworks/OpenCL.framework/OpenCL'];

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
            } elseif(PHP_OS=='Darwin') {
                $libFiles = $this->libs_mac;
            } else {
                throw new RuntimeException('Unknown operating system: "'.PHP_OS.'"');
            }
        }
        $code = file_get_contents($headerFile);
        foreach ($libFiles as $filename) {
            try {
                $ffi = FFI::cdef($code,$filename);
            } catch(FFIException $e) {
                if(self::$status>self::STAUTS_LIBRARY_NOT_LOADED) {
                    self::$statusMessage = 'OpenCL library not loaded.';
                }
                continue;
            }
            $platforms = null;
            try {
                $platforms = new PlatformList($ffi);
            } catch(RuntimeException $e) {
                if(self::$status>self::STATUS_CONFIGURATION_NOT_COMPLETE) {
                    self::$statusMessage = 'OpenCL configuration is not complete.';
                }
                continue;
            }
            try {
                $dmy = new DeviceList($ffi,$platforms);
            } catch(RuntimeException $e) {
                if(self::$status>self::STATUS_DEVICE_NOT_FOUND) {
                    self::$statusMessage = 'OpenCL device is not found.';
                }
                continue;
            }
            self::$ffi = $ffi;
            self::$status = self::STAUTS_OK;
            break;
        }
    }

    public function getStatus() : int
    {
        return self::$status;
    }

    public function getStatusMessage() : string
    {
        return self::$statusMessage??'';
    }

    public function isAvailable() : bool
    {
        return self::$ffi!==null;
    }

    public function PlatformList() : PlatformList
    {
        if(self::$ffi==null) {
            throw new RuntimeException($this->getStatusMessage());
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
            throw new RuntimeException($this->getStatusMessage());
        }
        return new DeviceList(self::$ffi,$platforms,$index,$deviceType);
    }

    public function Context(
        DeviceList|int $arg
    ) : Context
    {
        if(self::$ffi==null) {
            throw new RuntimeException($this->getStatusMessage());
        }
        return new Context(self::$ffi,$arg);
    }

    public function EventList(
        ?Context $context=null
    ) : EventList
    {
        if(self::$ffi==null) {
            throw new RuntimeException($this->getStatusMessage());
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
            throw new RuntimeException($this->getStatusMessage());
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
            throw new RuntimeException($this->getStatusMessage());
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
            throw new RuntimeException($this->getStatusMessage());
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
            throw new RuntimeException($this->getStatusMessage());
        }
        return new Kernel(self::$ffi, $program, $kernelName);
    }
}
