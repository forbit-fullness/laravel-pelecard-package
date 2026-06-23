<?php

namespace Yousefkadah\Pelecard\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Yousefkadah\Pelecard\Subscription;
use Yousefkadah\Pelecard\Tests\Fixtures\User;
use Yousefkadah\Pelecard\Tests\TestCase;

class SubscriptionTest extends TestCase
{
    protected function makeUser(): User
    {
        return User::create(['name' => 'Test', 'email' => 'test@example.com']);
    }

    #[Test]
    public function it_creates_a_single_price_subscription(): void
    {
        $user = $this->makeUser();

        $subscription = $user->newSubscription('default', 'price_monthly')->create();

        $this->assertEquals('default', $subscription->type);
        $this->assertEquals('price_monthly', $subscription->pelecard_price);
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->pelecard_status);
        $this->assertTrue($subscription->hasSinglePrice());
        $this->assertTrue($user->subscribed('default'));
        $this->assertTrue($user->subscribed('default', 'price_monthly'));
        $this->assertFalse($user->subscribed('default', 'other_price'));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onTrial());

        // The subscription resolves its owner via the configured billable model.
        $this->assertTrue($subscription->owner()->is($user));
        $this->assertTrue($subscription->user()->is($user));
        $this->assertSame('user_id', $subscription->billableForeignKey());
    }

    #[Test]
    public function it_creates_a_trialing_subscription(): void
    {
        $user = $this->makeUser();

        $subscription = $user->newSubscription('default', 'price_monthly')
            ->trialDays(14)
            ->create();

        $this->assertEquals(Subscription::STATUS_TRIALING, $subscription->pelecard_status);
        $this->assertTrue($subscription->onTrial());
        $this->assertTrue($user->onTrial('default'));
        $this->assertTrue($subscription->valid());
    }

    #[Test]
    public function it_creates_a_multi_price_subscription(): void
    {
        $user = $this->makeUser();

        $subscription = $user->newSubscription('default', ['price_a', 'price_b'])->create();

        $this->assertTrue($subscription->hasMultiplePrices());
        $this->assertNull($subscription->pelecard_price);
        $this->assertCount(2, $subscription->items);
        $this->assertTrue($subscription->hasPrice('price_a'));
        $this->assertTrue($subscription->hasPrice('price_b'));
        $this->assertTrue($user->subscribedToPrice('price_a', 'default'));
        $this->assertTrue($user->subscribedToPrice(['price_b', 'price_x'], 'default'));
        $this->assertFalse($user->subscribedToPrice('price_x', 'default'));
    }

    #[Test]
    public function it_cancels_and_resumes_a_subscription(): void
    {
        $user = $this->makeUser();
        $subscription = $user->newSubscription('default', 'price_monthly')->create();

        $subscription->cancel();

        $this->assertTrue($subscription->canceled());
        $this->assertTrue($subscription->cancelled()); // deprecated alias
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Subscription::STATUS_CANCELED, $subscription->pelecard_status);

        $subscription->resume();

        $this->assertFalse($subscription->canceled());
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->pelecard_status);
    }

    #[Test]
    public function it_ends_a_subscription_immediately(): void
    {
        $user = $this->makeUser();
        $subscription = $user->newSubscription('default', 'price_monthly')->create();

        $subscription->cancelNow();

        $this->assertTrue($subscription->canceled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->ended());
        $this->assertFalse($user->subscribed('default'));
    }

    #[Test]
    public function it_swaps_prices(): void
    {
        $user = $this->makeUser();
        $subscription = $user->newSubscription('default', 'price_monthly')->create();

        $subscription->swap('price_yearly');

        $this->assertEquals('price_yearly', $subscription->pelecard_price);
        $this->assertTrue($subscription->hasPrice('price_yearly'));
        $this->assertFalse($subscription->hasPrice('price_monthly'));
    }

    #[Test]
    public function it_detects_incomplete_payments(): void
    {
        $user = $this->makeUser();
        $subscription = $user->newSubscription('default', 'price_monthly')->create();

        $subscription->update(['pelecard_status' => Subscription::STATUS_PAST_DUE]);

        $this->assertTrue($subscription->pastDue());
        $this->assertTrue($subscription->hasIncompletePayment());
        $this->assertTrue($user->hasIncompletePayment('default'));
        $this->assertFalse($subscription->active());
    }

    #[Test]
    public function it_updates_the_default_payment_method(): void
    {
        $user = $this->makeUser();

        $this->assertFalse($user->hasDefaultPaymentMethod());

        $user->updateDefaultPaymentMethod('tok_123', [
            'brand' => 'visa',
            'last_four' => '4242',
            'exp_month' => '12',
            'exp_year' => '2030',
        ]);

        $this->assertTrue($user->hasDefaultPaymentMethod());

        $pm = $user->defaultPaymentMethod();
        $this->assertEquals('tok_123', $pm->token);
        $this->assertEquals('visa', $pm->brand);
        $this->assertEquals('4242', $pm->last_four);

        $user->deletePaymentMethod();
        $this->assertFalse($user->hasDefaultPaymentMethod());
    }
}
