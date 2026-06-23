<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Pelecard columns to users table
        if (! Schema::hasColumn('users', 'pelecard_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('pelecard_id')->nullable()->index();
                $table->string('pelecard_token')->nullable(); // default payment method (card token)
                $table->string('pm_type')->nullable(); // card brand, e.g. "visa"
                $table->string('pm_last_four', 4)->nullable(); // last 4 digits
                $table->string('pm_exp_month', 2)->nullable();
                $table->string('pm_exp_year', 4)->nullable();
                $table->timestamp('trial_ends_at')->nullable();
            });
        }

        // Create pelecard_credentials table for multi-tenancy
        Schema::create('pelecard_credentials', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner'); // owner_type, owner_id
            $table->string('terminal');
            $table->string('user');
            $table->text('password'); // encrypted
            $table->string('environment')->default('sandbox'); // sandbox or production
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'is_active']);
        });

        // Create subscriptions table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // subscription type (e.g., 'default', 'premium')
            $table->string('pelecard_subscription_id')->nullable();
            $table->string('pelecard_status')->nullable(); // active, trialing, canceled, past_due, incomplete
            $table->string('pelecard_price')->nullable(); // price identifier (null for multi-price subscriptions)
            $table->integer('quantity')->nullable()->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable(); // cancellation date
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        // Create subscription_items table
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('pelecard_product')->nullable(); // product identifier
            $table->string('pelecard_price'); // price identifier
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(['subscription_id', 'pelecard_price']);
        });

        // Create pelecard_transactions table for logging
        Schema::create('pelecard_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pelecard_transaction_id')->nullable()->index();
            $table->string('type'); // charge, refund, authorize, etc.
            $table->integer('amount'); // in agorot/cents
            $table->string('currency', 3)->default('ILS');
            $table->string('status'); // completed, failed, pending
            $table->json('metadata')->nullable(); // full API response
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelecard_transactions');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('pelecard_credentials');

        if (Schema::hasColumn('users', 'pelecard_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Drop the index before the column it references; otherwise
                // SQLite (and stricter drivers) error on the dangling index.
                $table->dropIndex(['pelecard_id']);

                // Drop only columns that still exist — the companion alignment
                // migration may already have removed some of them on rollback.
                $columns = array_filter([
                    'pelecard_id',
                    'pelecard_token',
                    'pm_type',
                    'pm_last_four',
                    'pm_exp_month',
                    'pm_exp_year',
                    'trial_ends_at',
                ], fn (string $column): bool => Schema::hasColumn('users', $column));

                $table->dropColumn($columns);
            });
        }
    }
};
