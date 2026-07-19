<?php

namespace ArnaudDelgerie\TFSAppBundle\Kernel;

trait TFSAppKernelTrait
{
    public function getCacheDir(): string
    {
        return $_SERVER['APP_CACHE_DIR'] ?? parent::getCacheDir();
    }

    public function getBuildDir(): string
    {
        // Symfony's build/share dir defaults to inside the project, which is
        // read-only when the app is packaged under resources/. Honor an explicit
        // app-data path so nothing writes into the bundled sources at runtime.
        return $_SERVER['APP_BUILD_DIR'] ?? parent::getBuildDir();
    }

    public function getLogDir(): string
    {
        return $_SERVER['APP_LOG_DIR'] ?? parent::getLogDir();
    }
}
