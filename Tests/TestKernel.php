<?php declare(strict_types=1);

namespace SwagGraphQL\Tests;

use Shopware\Development\Kernel;
use SwagGraphQL\SwagGraphQL;

class TestKernel extends Kernel
{
    protected function initializePlugins(): void
    {
        self::$plugins->add(new SwagGraphQL(true, __DIR__ . '/../'));
    }

    public function getProjectDir(): string
    {
        return parent::getProjectDir() . '/../../..';
    }
}
