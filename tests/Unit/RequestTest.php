<?php

namespace Yousefkadah\Pelecard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Yousefkadah\Pelecard\Exceptions\ValidationException;
use Yousefkadah\Pelecard\Http\Request;
use Yousefkadah\Pelecard\Tests\TestCase;

class RequestTest extends TestCase
{
    #[Test]
    public function it_can_create_a_request_with_data(): void
    {
        $request = Request::make(['amount' => 1000, 'currency' => 'ILS']);

        $this->assertEquals(1000, $request->get('amount'));
        $this->assertEquals('ILS', $request->get('currency'));
    }

    #[Test]
    public function it_can_set_and_get_data(): void
    {
        $request = new Request;
        $request->set('amount', 5000);

        $this->assertEquals(5000, $request->get('amount'));
    }

    #[Test]
    public function it_maps_fields_to_pelecard_services_names(): void
    {
        $request = Request::make([
            'terminal' => '0962210',
            'amount' => 1000,
            'card_number' => '4580000000000000',
            'cvv' => '123',
            'param_x' => 'order_1',
        ]);

        $formatted = $request->toPelecardFormat();

        $this->assertArrayHasKey('terminalNumber', $formatted);
        $this->assertArrayHasKey('total', $formatted);
        $this->assertArrayHasKey('creditCard', $formatted);
        $this->assertArrayHasKey('cvv2', $formatted);
        $this->assertArrayHasKey('paramX', $formatted);
        $this->assertSame(1000, $formatted['total']);
        $this->assertArrayNotHasKey('Amount', $formatted);
        $this->assertArrayNotHasKey('CardNumber', $formatted);
    }

    #[Test]
    public function it_combines_expiry_into_mmyy(): void
    {
        $request = Request::make([
            'expiry_month' => '5',
            'expiry_year' => '2026',
        ]);

        $formatted = $request->toPelecardFormat();

        $this->assertSame('0526', $formatted['creditCardDateMmYy']);
        $this->assertArrayNotHasKey('expiryMonth', $formatted);
        $this->assertArrayNotHasKey('expiryYear', $formatted);
    }

    #[Test]
    public function it_converts_currency_to_numeric_code(): void
    {
        $request = Request::make(['amount' => 1000, 'currency' => 'ILS']);

        $formatted = $request->toPelecardFormat();

        $this->assertSame(1, $formatted['currency']);
    }

    #[Test]
    public function it_leaves_numeric_currency_untouched(): void
    {
        $request = Request::make(['currency' => 2]);

        $formatted = $request->toPelecardFormat();

        $this->assertSame(2, $formatted['currency']);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        $request = Request::make(['amount' => 1000]);
        $request->setRequiredFields(['amount', 'currency']);
        $request->validate();
    }
}
