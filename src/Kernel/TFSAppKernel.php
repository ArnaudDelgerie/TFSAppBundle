<?php

namespace ArnaudDelgerie\TFSAppBundle\Kernel;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel;

abstract class TFSAppKernel extends Kernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        return $_SERVER['APP_CACHE_DIR'] ?? parent::getCacheDir();
    }

    public function getBuildDir(): string
    {
        // Symfony's build/share dir defaults to inside the project, which is
        // the installed snapshot — replaced wholesale by the next update
        // (CONTRACT.md §1). Honor an explicit app-data path so nothing writes
        // into it at runtime.
        return $_SERVER['APP_BUILD_DIR'] ?? parent::getBuildDir();
    }

    public function getLogDir(): string
    {
        return $_SERVER['APP_LOG_DIR'] ?? parent::getLogDir();
    }
}
