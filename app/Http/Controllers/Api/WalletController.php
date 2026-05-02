<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;

class WalletController extends Controller
{
    public function wallet()
    {
        $user = auth()->user();

        $wallet = Wallet::where('user_id', $user->id)->first();

        return response()->json([
            'status' => true,
            'data' => [
                'deposit_balance' => $wallet->deposit_balance ?? 0,
                'winning_balance' => $wallet->winning_balance ?? 0,
                'bonus_balance'   => $wallet->bonus_balance ?? 0,
                'total_balance'   => ($wallet->deposit_balance ?? 0) + ($wallet->winning_balance ?? 0)
            ]
        ]);
    }

    public function createWalletAddress(Request $request)
{
    $user = auth()->user();

    if ($user->wallet_address) {
        return response()->json([
            'status' => false,
            'message' => 'Wallet already created'
        ]);
    }

    $request->validate([
        'wallet_address' => 'required|unique:users,wallet_address'
    ]);

    $user->wallet_address = $request->wallet_address;
    $user->save();

    return response()->json([
        'status' => true,
        'message' => 'Wallet address created'
    ]);
}

  public function addMoney(Request $request)
{
    $user = auth()->user();

    $request->validate([
        'amount' => 'required|numeric|min:1'
    ]);

    $wallet = Wallet::firstOrCreate(
        ['user_id' => $user->id],
        [
            'deposit_balance' => 0,
            'bonus_balance' => 0,
            'winning_balance' => 0
        ]
    );

    // Add deposit money
    $wallet->deposit_balance += $request->amount;
    $wallet->save();

    // Log deposit
    WalletTransaction::create([
        'user_id' => $user->id,
        'type' => 'add_money',
        'amount' => $request->amount,
        'wallet_type' => 'deposit',
        'description' => 'Money added'
    ]);

    // 🔥 REFERRAL BONUS LOGIC
    if ($user->referred_by && !$user->referral_reward_given) {

        $referrer = User::find($user->referred_by);

        if ($referrer) {

            $bonus = $request->amount * 0.10;

            $refWallet = Wallet::firstOrCreate(
                ['user_id' => $referrer->id],
                [
                    'deposit_balance' => 0,
                    'bonus_balance' => 0,
                    'winning_balance' => 0
                ]
            );

            // Add bonus to referrer
            $refWallet->bonus_balance += $bonus;
            $refWallet->save();

            // Mark as given (only first time)
            $user->referral_reward_given = 1;
            $user->save();

            // Log referral bonus
            WalletTransaction::create([
                'user_id' => $referrer->id,
                'type' => 'referral_bonus',
                'amount' => $bonus,
                'wallet_type' => 'bonus',
                'description' => 'Referral bonus received'
            ]);
        }
    }

    return response()->json([
        'status' => true,
        'message' => 'Money added successfully'
    ]);
}

    public function transfer(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'receiver' => 'required',
            'amount' => 'required|numeric|min:1'
        ]);

        $receiver = User::where('username', $request->receiver)->first();


        if (!$receiver) {
            return response()->json([
                'status' => false,
                'message' => 'Receiver not found'
            ]);
        }

        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet || $wallet->deposit_balance < $request->amount) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient balance'
            ]);
        }

        // sender debit
        $wallet->decrement('deposit_balance', $request->amount);

        // receiver credit
        Wallet::firstOrCreate(['user_id' => $receiver->id])
            ->increment('deposit_balance', $request->amount);

        // logs
        WalletTransaction::create([
            'user_id' => $user->id,
            'type' => 'transfer',
            'amount' => $request->amount,
            'wallet_type' => 'deposit',
            'description' => 'Transfer sent'
        ]);

        WalletTransaction::create([
            'user_id' => $receiver->id,
            'type' => 'transfer',
            'amount' => $request->amount,
            'wallet_type' => 'deposit',
            'description' => 'Transfer received'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Transfer successful'
        ]);
    }

    public function withdraw(Request $request)
{
    $user = auth()->user();

    if (!$user->wallet_address) {
        return response()->json([
            'status' => false,
            'message' => 'Please create wallet address first'
        ]);
    }

    $request->validate([
        'amount' => 'required|numeric|min:1'
    ]);

    $wallet = Wallet::where('user_id', $user->id)->first();

    if (!$wallet || $wallet->winning_balance < $request->amount) {
        return response()->json([
            'status' => false,
            'message' => 'Insufficient winning balance'
        ]);
    }

    // Deduct balance
    $wallet->winning_balance -= $request->amount;
    $wallet->save();

    // Save withdrawal request
    Withdrawal::create([
        'user_id' => $user->id,
        'amount' => $request->amount,
        'wallet_address' => $user->wallet_address,
        'status' => 'pending'
    ]);

    // Log transaction
    WalletTransaction::create([
        'user_id' => $user->id,
        'type' => 'withdraw',
        'amount' => $request->amount,
        'wallet_type' => 'winning',
        'description' => 'Withdraw request'
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Withdraw request placed'
    ]);
}
}