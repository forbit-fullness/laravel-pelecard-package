<?php

namespace Yousefkadah\Pelecard\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Yousefkadah\Pelecard\PelecardServiceProvider;
use Yousefkadah\Pelecard\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // The package migration alters the host application's "users" table,
        // so it must exist before the package migrations run.
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamps();
            });
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            PelecardServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup Pelecard config
        $app['config']->set('pelecard.model', User::class);
        $app['config']->set('pelecard.terminal', 'test_terminal');
        $app['config']->set('pelecard.user', 'test_user');
        $app['config']->set('pelecard.password', 'test_password');
        $app['config']->set('pelecard.environment', 'sandbox');
    }
}
