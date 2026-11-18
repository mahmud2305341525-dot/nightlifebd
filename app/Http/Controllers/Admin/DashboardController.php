<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\User;
use App\Models\Venue;
use App\Services\IncidentEvaluationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, IncidentEvaluationService $evaluator)
    {
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sevenDaysAgo = $now->copy()->subDays(7);

        // --- Production AI vs Moderator Evaluation ---
        $evalFrom = $request->input('eval_from');
        $evalTo = $request->input('eval_to');
        $evalCategory = $request->input('eval_category');
        $evalSource = $request->input('eval_source');
        $evaluation = $evaluator->evaluateProductionDecisions($evalFrom, $evalTo, $evalCategory, $evalSource);

        // --- Report statistics ---
        $reportCounts = Report::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalReports = array_sum($reportCounts);

        // Reports by category (verified only, last 30 days)
        $reportsByCategory = Report::verified()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->pluck('total', 'category')
            ->toArray();

        // Reports trend (last 30 days, per day)
        $reportsTrend = Report::query()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();

        // Fill missing days with 0
        $trendFilled = [];
        for ($d = $thirtyDaysAgo->copy(); $d->lte($now); $d->addDay()) {
            $key = $d->toDateString();
            $trendFilled[$key] = $reportsTrend[$key] ?? 0;
        }

        // --- Venue statistics ---
        $venueCount = Venue::approved()->count();
        $pendingVenues = Venue::where('status', 'pending')->count();

        // Safety score distribution (histogram: 0-20, 20-40, 40-60, 60-80, 80-100)
        $scoreDistribution = Venue::approved()
            ->whereNotNull('safety_score')
            ->selectRaw("
                CASE
                    WHEN safety_score < 20 THEN '0-19'
                    WHEN safety_score < 40 THEN '20-39'
                    WHEN safety_score < 60 THEN '40-59'
                    WHEN safety_score < 80 THEN '60-79'
                    ELSE '80-100'
                END as bracket,
                COUNT(*) as total
            ")
            ->groupBy('bracket')
            ->pluck('total', 'bracket')
            ->toArray();

        // Ensure all brackets are present
        foreach (['0-19', '20-39', '40-59', '60-79', '80-100'] as $b) {
            $scoreDistribution[$b] = $scoreDistribution[$b] ?? 0;
        }

        // Top 5 hotspot venues (most verified incident reports in 30 days)
        $hotspots = Venue::approved()
            ->withCount(['reports as incident_count' => function ($q) use ($thirtyDaysAgo) {
                $q->verified()->where('kind', 'incident')->where('created_at', '>=', $thirtyDaysAgo);
            }])
            ->orderByDesc('incident_count')
            ->limit(5)
            ->get()
            ->filter(fn ($v) => $v->incident_count > 0);

        // --- User statistics ---
        $totalUsers = User::count();
        $activeUsersWeek = User::where('updated_at', '>=', $sevenDaysAgo)->count();
        $newUsersWeek = User::where('created_at', '>=', $sevenDaysAgo)->count();
        $bannedUsers = User::where('is_banned', true)->count();

        // --- Moderation stats ---
        $pendingReports = $reportCounts['pending'] ?? 0;
        $driver = DB::connection()->getDriverName();
        $rawAvgExpr = $driver === 'sqlite'
            ? 'AVG((julianday(verified_at) - julianday(created_at)) * 24) as avg_hours'
            : 'AVG(TIMESTAMPDIFF(HOUR, created_at, verified_at)) as avg_hours';

        $avgResolutionHours = Report::whereNotNull('verified_at')
            ->where('verified_at', '>=', $thirtyDaysAgo)
            ->selectRaw($rawAvgExpr)
            ->value('avg_hours');
        $avgResolutionHours = $avgResolutionHours !== null ? round((float) $avgResolutionHours, 1) : null;

        // Recent moderation actions
        $recentActions = AuditLog::whereIn('action', ['report.verified', 'report.rejected'])
            ->with('user:id,name')
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact(
            'reportCounts', 'totalReports', 'reportsByCategory', 'trendFilled',
            'venueCount', 'pendingVenues', 'scoreDistribution', 'hotspots',
            'totalUsers', 'activeUsersWeek', 'newUsersWeek', 'bannedUsers',
            'pendingReports', 'avgResolutionHours', 'recentActions',
            'evaluation', 'evalFrom', 'evalTo', 'evalCategory', 'evalSource'
        ));
    }
}
