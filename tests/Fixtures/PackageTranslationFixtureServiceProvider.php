<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

final class PackageTranslationFixtureServiceProvider extends ServiceProvider
{
    public static string $name = 'capell-fixture-package';
}
