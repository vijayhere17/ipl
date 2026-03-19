<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Withdrawal;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;

class WithdrawalController extends Controller
{
    public function requestWithdrawal(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10',
            'wallet_address' => 'required|string',
            'network' => 'required|string'
        ]);

        $user = Auth::user();

        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet || $wallet->winning_balance < $request->amount) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient balance'
            ]);
        }

        $wallet->winning_balance -= $request->amount;
        $wallet->save();

        Withdrawal::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'wallet_address' => $request->wallet_address,
            'network' => $request->network,
            'status' => 'pending'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Withdrawal request submitted'
        ]);
    }
}