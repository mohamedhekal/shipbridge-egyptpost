<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\EgyptPost;

use Hekal\ShipBridge\EgyptPost\Support\PayloadFactory;
use Hekal\ShipBridge\Facades\ShipBridge;
use Hekal\ShipBridge\Support\StatusNormalizer;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class EgyptPostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/egyptpost.php', 'shipbridge.drivers.egyptpost');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/egyptpost.php' => config_path('shipbridge-egyptpost.php'),
        ], 'shipbridge-egyptpost-config');

        ShipBridge::extend('egyptpost', function ($app, array $config): EgyptPostDriver {
            /** @var array<string, string> $aliases */
            $aliases = config('shipbridge.status_aliases', []);
            /** @var array<string, string> $driverMap */
            $driverMap = $config['status_map'] ?? [];

            return new EgyptPostDriver(
                client: new EgyptPostClient($app->make(HttpFactory::class), $config),
                payloads: new PayloadFactory($config),
                normalizer: new StatusNormalizer(array_merge($aliases, $driverMap)),
            );
        });
    }
}
