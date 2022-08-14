<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;

class TransactionService
{
    public function commit(TransactionType $type, float $count, $info): Transaction
    {
        $transaction = Transaction::create([
            'type' => $type,
            'count' => $count,
            'info' => $info,
        ]);

        if ($type === TransactionType::SendTo) {
            $senderId = $info['sender_balance']['user_id'];
            $recipientId = $info['recipient_balance']['user_id'];

            $transaction->users()->attach([$senderId, $recipientId]);
        } else {
            $transaction->users()->attach($info['user_id']);
        }

        return $transaction;
    }
}
