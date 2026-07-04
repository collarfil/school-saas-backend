<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Send password reset link to email
     */
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ], [
            'email.exists' => 'We cannot find a user with that email address.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $email = $request->email;
            $user = User::where('email', $email)->first();

            // Generate token
            $token = Str::random(64);
            
            // Delete old tokens
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            
            // Store new token
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => $token,
                'created_at' => Carbon::now()
            ]);

            // Create reset URL
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            $resetUrl = $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($email);

            // Send email (you can customize this)
            try {
                Mail::send('emails.password-reset', ['resetUrl' => $resetUrl, 'user' => $user], function ($message) use ($email, $user) {
                    $message->to($email, $user->name)
                            ->subject('Reset Your Password - School Management System');
                });
            } catch (\Exception $mailError) {
                Log::warning('Mail not configured, returning token for testing: ' . $mailError->getMessage());
            }

            Log::info('Password reset link generated for: ' . $email);

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset link sent to your email address.',
                'reset_url' => $resetUrl // Remove in production
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send reset link: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send reset link. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify reset token and reset password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $email = $request->email;
            $token = $request->token;

            // Verify token
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->where('token', $token)
                ->first();

            if (!$resetRecord) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired reset token.'
                ], 400);
            }

            // Check if token is expired (24 hours)
            $tokenCreatedAt = Carbon::parse($resetRecord->created_at);
            if ($tokenCreatedAt->diffInHours(Carbon::now()) > 24) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reset token has expired. Please request a new one.'
                ], 400);
            }

            // Update user password
            $user = User::where('email', $email)->first();
            $user->password = Hash::make($request->password);
            $user->must_change_password = false; // Reset the flag
            $user->save();

            // Delete used token
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            Log::info('Password reset successfully for: ' . $email);

            return response()->json([
                'status' => 'success',
                'message' => 'Password has been reset successfully. You can now login with your new password.'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reset password: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reset password. Please try again.'
            ], 500);
        }
    }

    /**
     * Validate reset token (check if valid)
     */
    public function validateToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'status' => 'error',
                'valid' => false,
                'message' => 'Invalid reset token.'
            ], 400);
        }

        // Check if token is expired (24 hours)
        $tokenCreatedAt = Carbon::parse($resetRecord->created_at);
        if ($tokenCreatedAt->diffInHours(Carbon::now()) > 24) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            
            return response()->json([
                'status' => 'error',
                'valid' => false,
                'message' => 'Reset token has expired.'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'valid' => true,
            'message' => 'Token is valid.'
        ]);
    }
}