The Interface of The OpenCL for FFI on PHP
==========================================

Status:
[![Build Status](https://github.com/rindow/rindow-opencl-ffi/workflows/tests/badge.svg)](https://github.com/rindow/rindow-opencl-ffi/actions)
[![Downloads](https://img.shields.io/packagist/dt/rindow/rindow-opencl-ffi)](https://packagist.org/packages/rindow/rindow-opencl-ffi)
[![Latest Stable Version](https://img.shields.io/packagist/v/rindow/rindow-opencl-ffi)](https://packagist.org/packages/rindow/rindow-opencl-ffi)
[![License](https://img.shields.io/packagist/l/rindow/rindow-opencl-ffi)](https://packagist.org/packages/rindow/rindow-opencl-ffi)

You can use OpenCL on PHP.
The version of OpenCL is limited to version 1.2(1.1 with restrictions), and we are considering porting to a wide range of environments.

Since our goal is to use it with the Rindow Neural Network Library, we currently only have the minimum required functionality. It will be expanded in the future.

Please see the documents about Buffer objects on [Rindow Mathematics](https://rindow.github.io/mathematics/acceleration/opencl.html#rindow-clblast-ffi) web pages.


Requirements
============

- PHP 8.1 or PHP8.2 or PHP8.3 or PHP8.4
- interop-phpobjects/polite-math 1.0.5 or later
- FFI-Buffer in the Interop php objects for Math. (ex. rindow/rindow-math-buffer-ffi)
- OpenCL 1.2 ICL loader and OpenCL 1.1/1.2 drivers
- Windows 10/11, Ubuntu(Recommendation 22.04-)/Debian (Recommendation 12-)

AMD GPU/APU and Intel integrated GPU drivers for Windows are including OpenCL drivers.
If you want to use it on Linux, you need to explicitly install the OpenCL driver.

How to setup
============

### Setup OpenCL
On Windows, you can use OpenCL without doing anything.

On Linux, install ICL Loader and the driver appropriate for your hardware.

For example, in the case of Linux standard AMD driver, install as follows
```shell
$ sudo apt install clinfo
$ sudo apt install mesa-opencl-icd
```
Linux standard OpenCL drivers include:
- mesa-opencl-icd
- beignet-opencl-icd
- intel-opencl-icd
- nvidia-opencl-icd-xxx
- pocl-opencl-icd

### Setup Rindow OpenCL-FFI
Install using composer.

```shell
$ composer require rindow/rindow-opencl-ffi
```

How to use
==========
Let's run the sample program.

### Sample for Display OpenCL Information
```php
<?php
include __DIR__.'/vendor/autoload.php';

use Interop\Polite\Math\Matrix\OpenCL;
use Rindow\OpenCL\FFI\OpenCLFactory;

$ocl = new OpenCLFactory();
$platforms = $ocl->PlatformList();
$m = $platforms->count();
for($p=0;$p<$m;$p++) {
    echo "platform(".$p.")\n";
    echo "    CL_PLATFORM_NAME=".$platforms->getInfo($p,OpenCL::CL_PLATFORM_NAME)."\n";
    echo "    CL_PLATFORM_PROFILE=".$platforms->getInfo($p,OpenCL::CL_PLATFORM_PROFILE)."\n";
    echo "    CL_PLATFORM_VERSION=".$platforms->getInfo($p,OpenCL::CL_PLATFORM_VERSION)."\n";
    echo "    CL_PLATFORM_VENDOR=".$platforms->getInfo($p,OpenCL::CL_PLATFORM_VENDOR)."\n";
    $devices = $ocl->DeviceList($platforms,index:$p);
    $n = $devices->count();
    for($i=0;$i<$n;$i++) {
        echo "    device(".$i.")\n";
        echo "        CL_DEVICE_VENDOR_ID=".$devices->getInfo($i,OpenCL::CL_DEVICE_VENDOR_ID)."\n";
        echo "        CL_DEVICE_NAME=".$devices->getInfo($i,OpenCL::CL_DEVICE_NAME)."\n";
        echo "        CL_DEVICE_TYPE=(";
        $device_type = $devices->getInfo($i,OpenCL::CL_DEVICE_TYPE);
        if($device_type&OpenCL::CL_DEVICE_TYPE_CPU) { echo "CPU,"; }
        if($device_type&OpenCL::CL_DEVICE_TYPE_GPU) { echo "GPU,"; }
        if($device_type&OpenCL::CL_DEVICE_TYPE_ACCELERATOR) { echo "ACCEL,"; }
        if($device_type&OpenCL::CL_DEVICE_TYPE_CUSTOM) { echo "CUSTOM,"; }
        echo ")\n";
        echo "        CL_DEVICE_VENDOR=".$devices->getInfo($i,OpenCL::CL_DEVICE_VENDOR)."\n";
        echo "        CL_DEVICE_PROFILE=".$devices->getInfo($i,OpenCL::CL_DEVICE_PROFILE)."\n";
        echo "        CL_DEVICE_VERSION=".$devices->getInfo($i,OpenCL::CL_DEVICE_VERSION)."\n";
        echo "        CL_DEVICE_OPENCL_C_VERSION=".$devices->getInfo($i,OpenCL::CL_DEVICE_OPENCL_C_VERSION)."\n";
    }
}
```

### Sample for Compile and Run OpenCL Program

```shell
$ composer require rindow/rindow-opencl-ffi
$ composer require rindow/rindow-math-buffer-ffi
```

```php
<?php
include __DIR__.'/vendor/autoload.php';

use Interop\Polite\Math\Matrix\OpenCL;
use Interop\Polite\Math\Matrix\NDArray;
use Rindow\OpenCL\FFI\OpenCLFactory;
use Rindow\Math\Buffer\FFI\BufferFactory;

$ocl = new OpenCLFactory();
$hostBufferFactory = new BufferFactory();
$NWITEMS = 64;

$context = $ocl->Context(OpenCL::CL_DEVICE_TYPE_DEFAULT);
$queue = $ocl->CommandQueue($context);
$sources = [
    "__kernel void saxpy(const global float * x,\n".
    "                    __global float * y,\n".
    "                    const float a)\n".
    "{\n".
    "   uint gid = get_global_id(0);\n".
    "   y[gid] = a* x[gid] + y[gid];\n".
    "}\n"
];
$program = $ocl->Program($context,$sources);

try {
    $program->build();
} catch(\RuntimeException $e) {
    echo $e->getMessage()."\n";
    switch($e->getCode()) {
        case OpenCL::CL_BUILD_PROGRAM_FAILURE: {
            echo "CL_PROGRAM_BUILD_STATUS=".$program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_STATUS)."\n";
            echo "CL_PROGRAM_BUILD_OPTIONS=".safestring($program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_OPTIONS))."\n";
            echo "CL_PROGRAM_BUILD_LOG=".safestring($program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_LOG))."\n";
            echo "CL_PROGRAM_BINARY_TYPE=".safestring($program->getBuildInfo(OpenCL::CL_PROGRAM_BINARY_TYPE))."\n";
        }
        case OpenCL::CL_COMPILE_PROGRAM_FAILURE: {
            echo "CL_PROGRAM_BUILD_LOG=".safestring($program->getBuildInfo(OpenCL::CL_PROGRAM_BUILD_LOG))."\n";
        }
    }
    throw $e;
}
$kernel = $ocl->Kernel($program,"saxpy");
$hostX = $hostBufferFactory->Buffer(
    $NWITEMS,NDArray::float32
);
$hostY = $hostBufferFactory->Buffer(
    $NWITEMS,NDArray::float32
);

for($i=0;$i<$NWITEMS;$i++) {
    $hostX[$i] = $i;
    $hostY[$i] = $NWITEMS-1-$i;
}
$a = 2.0;
$bufX = $ocl->Buffer($context,intval($NWITEMS*32/8),
    OpenCL::CL_MEM_READ_ONLY|OpenCL::CL_MEM_COPY_HOST_PTR,
    $hostX);
$bufY = $ocl->Buffer($context,intval($NWITEMS*32/8),
    OpenCL::CL_MEM_READ_WRITE|OpenCL::CL_MEM_COPY_HOST_PTR,
    $hostY);
$kernel->setArg(0,$bufX);
$kernel->setArg(1,$bufY);
$kernel->setArg(2,$a,NDArray::float32);

// enqueueNDRange
$global_work_size = [$NWITEMS];
$local_work_size = [1];
$kernel->enqueueNDRange($queue,$global_work_size,$local_work_size);

// complete kernel
$queue->finish();

$bufY->read($queue,$hostY);

for($i=0;$i<$NWITEMS;$i++) {
    echo $hostY[$i].",";
}
echo "\n";
```

To Do
=====
We have never verified OpenCL functionality on macOS. As OpenCL is not the recommended approach on macOS, it's a lower priority, but we welcome assistance from anyone who can test in a macOS environment.
