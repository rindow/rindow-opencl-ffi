<?php
namespace RindowTest\OpenCL\FFI\ReleaseTest;

use PHPUnit\Framework\TestCase;
use Rindow\OpenCL\FFI\OpenCLFactory;
use Rindow\OpenCL\FFI\PlatformList;
use FFI;

class ReleaseTest extends TestCase
{
    public function testFFINotLoaded()
    {
        $factory = new OpenCLFactory();
        if(extension_loaded('ffi')) {
            if($factory->isAvailable()) {
                $platforms = $factory->PlatformList();
                $this->assertInstanceof(PlatformList::class,$platforms);
            } else {
                $this->assertFalse($factory->isAvailable());
            }
        } else {
            $this->assertFalse($factory->isAvailable());
        }
    }
}