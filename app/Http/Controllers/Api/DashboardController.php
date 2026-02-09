<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaystackTransaction;
use App\Models\Subscription;
use App\Models\School;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
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
}