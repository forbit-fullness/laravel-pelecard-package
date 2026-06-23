<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the schema created by laravel-pelecard < 2.0 with current Laravel
 * Cashier conventions (type / *_price / *_status / *_product).
 *
 * Every change is guarded by a column check, so on a fresh 2.x install — where
 * the base migration already created the new columns — this migration is a no-op.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (Schema::hasColumn('users', 'pelecard_id') && ! Schema::hasColumn('users', 'pelecard_token')) {
                    $table->string('pelecard_token')->nullable()->after('pelecard_id');
                }
                if (Schema::hasColumn('users', 'pm_last_four') && ! Schema::hasColumn('users', 'pm_exp_month')) {
                    $table->string('pm_exp_month', 2)->nullable()->after('pm_last_four');
                    $table->string('pm_exp_year', 4)->nullable()->after('pm_exp_month');
                }
            });
        }

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                if (Schema::hasColumn('subscriptions', 'name') && ! Schema::hasColumn('subscriptions', 'type')) {
                    $table->renameColumn('name', 'type');
                }
                if (Schema::hasColumn('subscriptions', 'pelecard_plan') && ! Schema::hasColumn('subscriptions', 'pelecard_price')) {
                    $table->renameColumn('pelecard_plan', 'pelecard_price');
                }
                if (! Schema::hasColumn('subscriptions', 'pelecard_status')) {
                    $table->string('pelecard_status')->nullable()->after('pelecard_subscription_id');
                }
            });
        }

        if (Schema::hasTable('subscription_items')) {
            Schema::table('subscription_items', function (Blueprint $table): void {
                if (Schema::hasColumn('subscription_items', 'pelecard_plan') && ! Schema::hasColumn('subscription_items', 'pelecard_price')) {
                    $table->renameColumn('pelecard_plan', 'pelecard_price');
                }
                if (! Schema::hasColumn('subscription_items', 'pelecard_product')) {
                    $table->string('pelecard_product')->nullable()->after('subscription_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscription_items')) {
            Schema::table('subscription_items', function (Blueprint $table): void {
                if (Schema::hasColumn('subscription_items', 'pelecard_product')) {
                    $table->dropColumn('pelecard_product');
                }
                if (Schema::hasColumn('subscription_items', 'pelecard_price') && ! Schema::hasColumn('subscription_items', 'pelecard_plan')) {
                    $table->renameColumn('pelecard_price', 'pelecard_plan');
                }
            });
        }

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                if (Schema::hasColumn('subscriptions', 'pelecard_status')) {
                    $table->dropColumn('pelecard_status');
                }
                if (Schema::hasColumn('subscriptions', 'pelecard_price') && ! Schema::hasColumn('subscriptions', 'pelecard_plan')) {
                    $table->renameColumn('pelecard_price', 'pelecard_plan');
                }
                if (Schema::hasColumn('subscriptions', 'type') && ! Schema::hasColumn('subscriptions', 'name')) {
                    $table->renameColumn('type', 'name');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                foreach (['pelecard_token', 'pm_exp_month', 'pm_exp_year'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
