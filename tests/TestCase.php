<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\EgyptPost\Tests;

use Hekal\ShipBridge\EgyptPost\EgyptPostServiceProvider;
use Hekal\ShipBridge\ShipBridgeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ShipBridgeServiceProvider::class,
            EgyptPostServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('shipbridge.default', 'egyptpost');
        $app['config']->set('shipbridge.drivers.egyptpost.base_url', 'https://egyptpost.test/v1');
        $app['config']->set('shipbridge.drivers.egyptpost.token', 'test-token');
    }
}
