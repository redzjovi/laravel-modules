<?php

namespace Modules\Ravintola\Http\Controllers\Api\v1\Voucher;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Options\Models\Options;
use Modules\Ravintola\Models\RavintolaUserVouchers;

class ValidateVoucherController extends Controller
{
    /**
     * Store a newly created resource in storage.
     * @param  Request $request
     * $_POST =
     * [
     *      'transaction_deductible' => 1,  // nilai voucher yang digunakan
     * ]
     * @return Response
     */
    public function store(Request $request)
    {
        // 1. Validation
        $validator = \Validator::make($request->input(), [
            'pos_id' => ['required', 'between:0,20'],
            'outlet_code' => ['required', 'between:0,10'],
            'verification_number' => [
                'required', 'between:0,12',
                new \Modules\Users\Rules\PhoneNumberVerificationCodeCheck(['phone_number' => $request->input('phone_number')]),
            ],
            'phone_number' => [
                'required', 'between:0,20', 'exists:users,phone_number',
                new \Modules\Ravintola\Rules\VoucherNewVerificationNumberPhoneNumberCheck($request->input()),
            ],
            'transaction_amount' => ['required', 'integer'],
            'transaction_deductible' => [
                'required', 'integer',
                new \Modules\Ravintola\Rules\TransactionDeductibleCheck($request->input()),
            ],
            'signature' => [
                'required', 'between:0,64',
                new \Modules\Ravintola\Rules\SignatureCheck($request->input()),
            ],
        ]);

        // 2. If validation false
        if ($validator->fails()) {
            return response()->json(
                [
                    'response_status' => 'error',
                    'code' => 422,
                    'msg' => $validator->errors()->first(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // 3. Select user
        $user = \Modules\Users\Models\Users::where(['phone_number' => $request->input('phone_number'), 'verification_code' => $request->input('verification_number')])->firstOrFail();

        // 4. Update ravintola_user_voucher
        $ravintolaUserVoucher = RavintolaUserVouchers::where('user_id', $user->id)->where('status', 'new')->firstOrFail();
        $ravintolaUserVoucher->fill($request->input());
        $ravintolaUserVoucher->value = $user->balance - $request->input('transaction_deductible');
        $ravintolaUserVoucher->used_time = date('Y-m-d H:i:s');
        // $ravintolaUserVoucher->used_outlet = $request->input('outlet_code');
        $ravintolaUserVoucher->status = 'used';
        // $ravintolaUserVoucher->transaction_deductible = $request->input('transaction_deductible');
        $ravintolaUserVoucher->transaction_remaining_amount = $request->input('transaction_amount') - $request->input('transaction_deductible');
        $ravintolaUserVoucher->data = json_encode($request->input());
        $ravintolaUserVoucher->save();

        // 5. Insert user_balance_histories, update users.balance
        $user->balance = $user->balance - $request->input('transaction_deductible');
        $user->userBalanceHistoryCreate(['type' => 'ravintola_voucher', 'reference_id' => $ravintolaUserVoucher->id]);
        $user->save();

        // 6. Update users.game_token
        $user->game_token +=
            (int) (
                (int) $ravintolaUserVoucher->transaction_remaining_amount / (int) Options::firstByName('amount_ratio_game_token')->value
            )
            * $user->getGameTokenMultiple();
        $user->userGameTokenHistoryCreate(['type' => 'ravintola_user_voucher', 'reference_id' => $ravintolaUserVoucher->id]);
        $user->save();

        // 7. Response json ok
        return response()->json([
            'status' => 'ok',
            'voucher' => new \Modules\Ravintola\Http\Resources\Api\v1\VoucherResource($ravintolaUserVoucher),
            'transaction_deductible' => $ravintolaUserVoucher->transaction_deductible,
        ]);
    }
}
