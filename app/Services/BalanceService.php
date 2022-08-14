<?php

namespace App\Services;

use App\Exceptions\BalanceServiceException;
use App\Models\Balance;
use App\Models\User;

class BalanceService
{
    public const APP_CURRENCY = 'RUB';

    public function add(User $user, float $count): Balance
    {
        $balance = Balance::firstOrNew([
            'user_id' => $user->id,
        ]);

        $balance->balance += $count;

        $balance->save();

        return $balance;
    }

    public function writeOff(User $user, float $count): Balance
    {
        $balance = Balance::firstOrNew([
            'user_id' => $user->id,
        ]);

        $balance->balance -= $count;

        if ($balance->balance < 0) {
            throw new BalanceServiceException(__('Insufficient funds.'));
        }

        $balance->save();

        return $balance;
    }

    public function sendTo(User $sender, User $recipient, float $count): array
    {
        $senderBalance = $this->writeOff($sender, $count);

        $recipientBalance = $this->add($recipient, $count);

        return [
            'sender_balance' => $senderBalance,
            'recipient_balance' => $recipientBalance,
        ];
    }
}
