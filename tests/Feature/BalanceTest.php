<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Balance;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\CurrencyConverterService;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BalanceTest extends TestCase
{
    use RefreshDatabase;

    private $user1;
    private $user2;
    private $user2Balance;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();

        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();

        $this->user2Balance = Balance::factory()
            ->for($this->user2)
            ->create(['balance' => 100.0]);

        DB::commit();
    }

    public function testAddForUser1(): void
    {
        $route = route('balance.add', $this->user1);

        $body = [
            'count' => 130.05,
        ];

        $response = $this->postJson($route, $body);

        $response->assertCreated();

        $this->assertDatabaseHas('balances', [
            'user_id' => $this->user1->id,
            'balance' => 130.05,
        ]);

        $this->assertDatabaseHas('transactions', [
            'type' => TransactionType::Add,
            'count' => $body['count'],
            'info->user_id' => $this->user1->id,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function testAddForUser2(): void
    {
        $route = route('balance.add', $this->user2);

        $count = 130.05;

        $body = [
            'count' => $count,
        ];

        $response = $this->postJson($route, $body);

        $response->assertOk();

        $this->assertDatabaseHas('balances', [
            'user_id' => $this->user2->id,
            'balance' => $count + $this->user2Balance->balance,
        ]);

        $this->assertDatabaseHas('transactions', [
            'type' => TransactionType::Add,
            'count' => $count,
            'info->user_id' => $this->user2->id,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function testWriteOffForUser1(): void
    {
        $route = route('balance.write_off', $this->user1);

        $count = 130.05;

        $body = [
            'count' => $count,
        ];

        $response = $this->postJson($route, $body);

        $response->assertStatus(400);
    }

    public function writeOffUser2Provider(): array
    {
        return [
            [50.0, 200],
            [100.0, 200],
            [150.0, 400],
        ];
    }

    /**
     * @dataProvider writeOffUser2Provider
     */
    public function testWriteOffForUser2(float $count, int $statusCode): void
    {
        $route = route('balance.write_off', $this->user2);

        $body = [
            'count' => $count,
        ];

        $response = $this->postJson($route, $body);

        $response->assertStatus($statusCode);

        if ($statusCode === 200) {
            $this->assertDatabaseHas('balances', [
                'user_id' => $this->user2->id,
                'balance' => $this->user2Balance->balance - $count,
            ]);

            $this->assertDatabaseHas('transactions', [
                'type' => TransactionType::WriteOff,
                'count' => $count,
                'info->user_id' => $this->user2->id,
                'created_at' => now()->toDateTimeString(),
            ]);
        }
    }

    public function testShowForUser1(): void
    {
        $route = route('balance.show', $this->user1);

        $response = $this->getJson($route);

        $response->assertOk();

        $response->assertJsonFragment([
            'user_id' => $this->user1->id,
            'balance' => 0,
        ]);
    }

    public function testShowForUser2(): void
    {
        $route = route('balance.show', $this->user2);

        $response = $this->getJson($route);

        $response->assertOk();

        $response->assertJsonFragment([
            'user_id' => $this->user2->id,
            'balance' => $this->user2Balance->balance,
        ]);
    }


    public function sendToUserProvider(): array
    {
        return [
            [50.0, 200],
            [100.0, 200],
            [150.0, 400],
        ];
    }

    /**
     * @dataProvider sendToUserProvider
     */
    public function testSendToUser(float $count, int $statusCode): void
    {
        $route = route('balance.send_to', [
            'sender' => $this->user2,
            'recipient' => $this->user1,
        ]);

        $body = [
            'count' => $count,
        ];

        $response = $this->postJson($route, $body);

        $response->assertStatus($statusCode);

        if ($statusCode === 200) {
            $this->assertDatabaseHas('balances', [
                'user_id' => $this->user1->id,
                'balance' => $count,
            ]);
            $this->assertDatabaseHas('balances', [
                'user_id' => $this->user2->id,
                'balance' => $this->user2Balance->balance - $count,
            ]);

            $this->assertDatabaseHas('transactions', [
                'type' => TransactionType::SendTo,
                'count' => $count,
                'info->sender_balance->user_id' => $this->user2->id,
                'info->recipient_balance->user_id' => $this->user1->id,
                'created_at' => now()->toDateTimeString(),
            ]);
        }
    }

    public function testShowWithCurrency(): void
    {
        $currency = 'USD';
        $rate = 60.0;

        $this->instance(
            CurrencyConverterService::class,
            Mockery::mock(
                CurrencyConverterService::class,
                function (MockInterface $mock) use ($currency, $rate) {
                    $rate = 60.0;
                    $mock->shouldReceive('convert')
                        ->once()
                        ->andReturn([
                            'from_currency' => BalanceService::APP_CURRENCY,
                            'from_amount' => $this->user2Balance->balance,

                            'to_currency' => $currency,
                            'to_amount' => $this->user2Balance->balance / $rate,

                            'rate' => $rate,
                        ]);
                }
            )
        );

        $route = route('balance.show', [
            'user' => $this->user2,
            'currency' => $currency,
        ]);

        $response = $this->getJson($route);

        $response->assertOk();

        $response->assertJsonFragment([
            'user_id' => $this->user2->id,
            'balance' => $this->user2Balance->balance / $rate,
            'currency' => $currency,
        ]);
    }
}
