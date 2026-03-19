<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\EmailOtp;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{

    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $otp = rand(100000, 999999);

        EmailOtp::updateOrCreate(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5)
            ]
        );

        // Send OTP email
        Mail::raw("Your login OTP is: $otp", function ($message) use ($request) {
            $message->to($request->email)
                ->subject('Your Login OTP');
        });

        return response()->json([
            'status' => true,
            'message' => 'OTP sent successfully'
        ]);
    }


    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'referral_code' => 'nullable|string'
        ]);

        $otpRecord = EmailOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP'
            ]);
        }

        if (now()->gt($otpRecord->expires_at)) {
            return response()->json([
                'status' => false,
                'message' => 'OTP expired'
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {

            // Generate unique username
            do {
                $username = 'user' . rand(10000, 99999);
            } while (User::where('username', $username)->exists());

            // Generate unique referral code
            do {
                $referralCode = strtoupper(Str::random(6));
            } while (User::where('referral_code', $referralCode)->exists());

            $referredBy = null;

            if ($request->referral_code) {
                $referrer = User::where('referral_code', $request->referral_code)->first();

                if ($referrer) {
                    $referredBy = $referrer->id;
                }
            }

            $user = User::create([
                'username' => $username,
                'email' => $request->email,
                'password' => bcrypt(Str::random(10)),
                'referral_code' => $referralCode,
                'referred_by' => $referredBy
            ]);
        }

        // delete OTP after successful login
        $otpRecord->delete();

        // generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'referral_code' => $user->referral_code
            ]
        ]);
    }

    public function profile(Request $request)
{
    $user = $request->user();

    return response()->json([
        'status' => true,
        'data' => [
            'id' => $user->id,
            'username' => $user->username,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'wallet_address' => $user->wallet_address,
            'referral_code' => $user->referral_code
        ]
    ]);
}

public function updateProfile(Request $request)
{
    $request->validate([
        'username' => 'nullable|string|max:50|unique:users,username,' . $request->user()->id,
        'email' => 'nullable|email',
        'mobile' => 'nullable|string|max:15',
        'wallet_address' => 'nullable|string|max:255'
    ]);

    $user = $request->user();

    if ($request->username) {
        $user->username = $request->username;
    }

    if ($request->email) {
        $user->email = $request->email;
    }

    if ($request->mobile) {
        $user->mobile = $request->mobile;
    }

    if ($request->wallet_address) {
        $user->wallet_address = $request->wallet_address;
    }

    $user->save();

    return response()->json([
        'status' => true,
        'message' => 'Profile updated successfully',
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'wallet_address' => $user->wallet_address,
            'referral_code' => $user->referral_code
        ]
    ]);
}
}