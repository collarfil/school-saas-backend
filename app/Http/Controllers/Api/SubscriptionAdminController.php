<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaystackTransaction;
use App\Models\Subscription;
use App\Models\School;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionAdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_revenue' => PaystackTransaction::successful()->sum('amount'),
            'active_subscriptions' => Subscription::active()->count(),
            'pending_payments' => Subscription::pending()->count(),
            'expired_subscriptions' => Subscription::where('valid_until', '<', now())->count(),
            'today_revenue' => PaystackTransaction::successful()
                ->whereDate('created_at', today())
                ->sum('amount')
        ];

        $recentTransactions = PaystackTransaction::with(['school', 'subscription'])
            ->latest()
            ->take(10)
            ->get();

        $expiringSubscriptions = Subscription::with('school')
            ->where('valid_until', '<=', now()->addDays(7))
            ->where('status', 'active')
            ->orderBy('valid_until')
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_transactions' => $recentTransactions,
            'expiring_soon' => $expiringSubscriptions
        ]);
    }

    public function unlockSchool(School $school)
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $school->update(['is_unlocked' => true]);

        // Optionally notify school admin
        $admin = $school->users()->where('role','admin')->first();
        if ($admin) {
            try {
                \Mail::to($admin->email)->send(new \App\Mail\SchoolUnlockedMail($school));
            } catch (\Exception $e) {
                \Log::warning('Failed to send unlock email: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'School unlocked successfully', 'school' => $school]);
    }

    // ADD THE MISSING lockSchool METHOD
    public function lockSchool(School $school)
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $school->update(['is_unlocked' => false]);

        return response()->json(['message' => 'School locked successfully', 'school' => $school]);
    }

    public function transactions(Request $request)
    {
        $query = PaystackTransaction::with(['school', 'subscription']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        $transactions = $query->latest()->paginate(50);

        return response()->json($transactions);
    }

    public function subscriptions(Request $request)
    {
        $query = Subscription::with(['school', 'paystackTransactions']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $subscriptions = $query->latest()->paginate(50);

        return response()->json($subscriptions);
    }

    public function generateRevenueReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->subMonth());
        $endDate = $request->get('end_date', now());

        $revenueData = PaystackTransaction::successful()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $planBreakdown = Subscription::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                plan_type,
                COUNT(*) as count,
                SUM(amount) as total_amount
            ')
            ->groupBy('plan_type')
            ->get();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'revenue_trend' => $revenueData,
            'plan_breakdown' => $planBreakdown,
            'summary' => [
                'total_revenue' => $revenueData->sum('total_amount'),
                'total_transactions' => $revenueData->sum('transaction_count'),
                'average_transaction' => $revenueData->avg('total_amount')
            ]
        ]);
    }

    public function getSchoolSubscriptions(School $school)
    {
        $subscriptions = $school->subscriptions()
            ->with('paystackTransactions')
            ->latest()
            ->paginate(20);

        return response()->json($subscriptions);
    }

    public function createManualSubscription(Request $request, School $school)
    {
        $request->validate([
            'plan_type' => 'required|in:termly,yearly',
            'term' => 'required_if:plan_type,termly',
            'school_session' => 'required_if:plan_type,yearly',
            'student_capacity' => 'required|integer',
            'amount' => 'required|numeric',
            'valid_until' => 'required|date'
        ]);

        $subscription = Subscription::create([
            'school_id' => $school->id,
            'plan_type' => $request->plan_type,
            'term' => $request->term,
            'school_session' => $request->school_session,
            'student_capacity' => $request->student_capacity,
            'amount' => $request->amount,
            'payment_status' => 'paid',
            'payment_date' => now(),
            'valid_from' => now(),
            'valid_until' => $request->valid_until,
            'status' => 'active'
        ]);

        // Deactivate old subscriptions
        Subscription::where('school_id', $school->id)
            ->where('id', '!=', $subscription->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        return response()->json([
            'message' => 'Manual subscription created successfully',
            'subscription' => $subscription
        ]);
    }
}