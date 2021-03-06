<?php

namespace Modules\Transactions\Models\Transactions;

use Illuminate\Database\Eloquent\Builder;

class Sales extends \Modules\Transactions\Models\Transactions
{
    protected $attributes = [
        'type' => 'sales',
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('type', function (Builder $builder) { $builder->where('type', 'sales'); });
    }

    public function reject($id)
    {
        // 1. Get transaction
        $transaction = self::where('id', $id)->whereIn('status', ['pending', 'new', 'processed', 'sent'])->firstOrFail();

        // 2. Foreach transaction details, then return to stock
        if ($transaction->transactionDetails) {
            foreach ($transaction->transactionDetails as $transactionDetail) {
                if ($transactionDetail->product) {
                    $transactionDetail->product->postProduct->stock += $transactionDetail->quantity;
                    $transactionDetail->product->postProduct->save();
                }
            }
        }

        // 3.1 Return balance to user balance
        if ($user = \Modules\Users\Models\Users::find($transaction->receiver_id)) {

            // 3.2 If status in new, processed, sent, then return balance, grand total to user balance
            if (in_array($transaction->status, ['new', 'processed', 'sent'])) {
                $user->balance += $transaction->balance;
                $user->balance += $transaction->grand_total;

                // 3.3 Insert user_balance_histories, update user
                $user->userBalanceHistoryCreate(['type' => 'transaction_returned', 'reference_id' => $transaction->id]);
                $user->save();
            }
        }

        // 4. Update transaction set status = 'returned'
        $transaction->status = 'returned';
        $transaction->save();

        return $transaction;
    }
}
