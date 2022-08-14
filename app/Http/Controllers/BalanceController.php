<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\BalanceRequest;
use App\Http\Resources\BalanceResource;
use App\Models\Balance;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\CurrencyConverterService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    private BalanceService $balanceService;
    private CurrencyConverterService $currencyConverterService;
    private TransactionService $transactionService;

    public function __construct(
        BalanceService $balanceService,
        CurrencyConverterService $currencyConverterService,
        TransactionService $transactionService
    ) {
        $this->balanceService = $balanceService;
        $this->currencyConverterService = $currencyConverterService;
        $this->transactionService = $transactionService;
    }

    public function add(BalanceRequest $request, User $user): BalanceResource
    {
        $count = (float)$request->get('count');

        $balance = DB::transaction(function () use ($user, $count) {
            $balance = $this->balanceService->add($user, $count);

            $this->transactionService->commit(TransactionType::Add, $count, $balance);

            return $balance;
        });

        return new BalanceResource($balance);
    }

    public function writeOff(BalanceRequest $request, User $user): BalanceResource
    {
        $count = (float)$request->get('count');

        $balance = DB::transaction(function () use ($user, $count) {
            $balance = $this->balanceService->writeOff($user, $count);

            $this->transactionService->commit(TransactionType::WriteOff, $count, $balance);

            return $balance;
        });

        return new BalanceResource($balance);
    }

    public function show(Request $request, User $user): BalanceResource
    {
        $request->validate(['currency' => 'string']);

        $balance = Balance::firstOrNew([
            'user_id' => $user->id,
        ]);

        $resource = new BalanceResource($balance);

        if ($request->has('currency')) {
            $convertedBalance = $this->currencyConverterService->convert(
                BalanceService::APP_CURRENCY,
                $request->get('currency'),
                $balance->balance
            );

            $resource->setConvertedBalance($convertedBalance['to_amount']);
            $resource->setCurrency($convertedBalance['to_currency']);
        }

        return $resource;
    }

    public function sendTo(BalanceRequest $request, User $sender, User $recipient): AnonymousResourceCollection
    {
        $count = (float)$request->get('count');

        $result = DB::transaction(function () use ($sender, $recipient, $count) {
            $result = $this->balanceService->sendTo($sender, $recipient, $count);

            $this->transactionService->commit(TransactionType::SendTo, $count, $result);

            return $result;
        });

        return BalanceResource::collection($result);
    }
}
