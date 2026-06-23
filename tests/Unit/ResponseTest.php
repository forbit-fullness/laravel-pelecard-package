<?php

namespace Yousefkadah\Pelecard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Yousefkadah\Pelecard\Exceptions\PaymentException;
use Yousefkadah\Pelecard\Http\Response;
use Yousefkadah\Pelecard\Tests\TestCase;

class ResponseTest extends TestCase
{
    #[Test]
    public function it_detects_successful_response(): void
    {
        $response = new Response(['StatusCode' => '000'], 200);

        $this->assertTrue($response->successful());
        $this->assertFalse($response->failed());
    }

    #[Test]
    public function it_detects_failed_response(): void
    {
        $response = new Response(['StatusCode' => '001', 'ErrorMessage' => 'Payment declined'], 200);

        $this->assertTrue($response->failed());
        $this->assertFalse($response->successful());
    }

    #[Test]
    public function it_extracts_transaction_id(): void
    {
        $response = new Response(['PelecardTransactionId' => '123456'], 200);

        $this->assertEquals('123456', $response->getTransactionId());
    }

    #[Test]
    public function it_extracts_error_message(): void
    {
        $response = new Response(['ErrorMessage' => 'Invalid card'], 200);

        $this->assertEquals('Invalid card', $response->getErrorMessage());
    }

    #[Test]
    public function it_throws_on_failed_response(): void
    {
        $this->expectException(PaymentException::class);

        $response = new Response(['StatusCode' => '001', 'ErrorMessage' => 'Failed'], 200);
        $response->throw();
    }
}
