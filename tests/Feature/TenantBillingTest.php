<?php

namespace Yousefkadah\Pelecard\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\Attributes\Test;
use Yousefkadah\Pelecard\PelecardServiceProvider;
use Yousefkadah\Pelecard\Tests\Fixtures\Tenant;

/**
 * Proves the package can be bound to a non-User billable model (here a Tenant)
 * purely via the pelecard.model config value.
 */
class TenantBillingTest extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [PelecardServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Bind billing to the Tenant model instead of User.
        $app['config']->set('pelecard.model', Tenant::class);
    }

    #[Test]
    public function it_binds_billing_to_the_configured_tenant_model(): void
    {
        // Migration used the tenant table + tenant_id foreign key.
        $this->assertTrue(Schema::hasColumn('tenants', 'pelecard_id'));
        $this->assertTrue(Schema::hasColumn('subscriptions', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('subscriptions', 'user_id'));
        $this->assertTrue(Schema::hasColumn('pelecard_transactions', 'tenant_id'));

        $tenant = Tenant::create(['name' => 'Acme']);
        $subscription = $tenant->newSubscription('default', 'price_monthly')->create();

        $this->assertSame('tenant_id', $subscription->billableForeignKey());
        $this->assertSame($tenant->id, $subscription->getAttribute('tenant_id'));
        $this->assertTrue($subscription->owner()->is($tenant));
        $this->assertTrue($tenant->subscribed('default'));
    }
}
