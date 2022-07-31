<?php

namespace Tests\Feature;

use App\Models\Balance;
use App\Models\User;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $route = route('balance.add');

        $body = [
            'user_id' => $this->user1->id,
            'count' => 130.05,
        ];

        $response = $this->postJson($route, $body);

        $response->assertOk();

        $this->assertDatabaseHas('balances', [
            'user_id' => $this->user1->id,
            'balance' => 130.05,
        ]);
    }

    public function testAddForUser2(): void
    {
        $route = route('balance.add');

        $count = 130.05;

        $body = [
            'user_id' => $this->user2->id,
            'count' => $count,
        ];

        $response = $this->postJson($route, $body);

        $response->assertOk();

        $this->assertDatabaseHas('balances', [
            'user_id' => $this->user2->id,
            'balance' => $count + $this->user2Balance->balance,
        ]);
    }

    public function testWriteOffForUser1(): void
    {
        $route = route('balance.write_off');

        $count = 130.05;

        $body = [
            'user_id' => $this->user1->id,
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
        $route = route('balance.write_off');

        $body = [
            'user_id' => $this->user2->id,
            'count' => $count,
        ];

        $response = $this->postJson($route, $body);

        $response->assertStatus($statusCode);

        if ($statusCode === 200) {
            $this->assertDatabaseHas('balances', [
                'user_id' => $this->user2->id,
                'balance' => $this->user2Balance->balance - $count,
            ]);
        }
    }
}
