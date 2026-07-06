<?php

declare(strict_types=1);

namespace Akrista\BladeIslands\Tests;

use Akrista\BladeIslands\BladeIslandsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BladeIslandsServiceProvider::class];
    }
}
