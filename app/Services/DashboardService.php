<?php

namespace App\Services;

use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get aggregate dashboard metrics for the given user.
     *
     * @param User $user
     * @return array
     */
    public function getDashboardMetrics(User $user): array
    {
        $scanQuery = Scan::where('user_id', $user->id);

        $totalScans = (clone $scanQuery)->count();
        $queuedScans = (clone $scanQuery)->where('status', 'queued')->count();
        $runningScans = (clone $scanQuery)->whereIn('status', ['starting', 'running', 'crawling', 'passive_scanning', 'active_scanning', 'processing_results', 'generating_report'])->count();
        $completedScans = (clone $scanQuery)->where('status', 'completed')->count();
        $failedScans = (clone $scanQuery)->where('status', 'failed')->count();

        $recentScans = (clone $scanQuery)
            ->withCount('findings')
            ->latest()
            ->take(5)
            ->get();

        // Aggregate findings count by severity for user's scans
        $findingsCounts = Finding::whereHas('scan', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $findingsBySeverity = [
            'Critical' => $findingsCounts['Critical'] ?? 0,
            'High' => $findingsCounts['High'] ?? 0,
            'Medium' => $findingsCounts['Medium'] ?? 0,
            'Low' => $findingsCounts['Low'] ?? 0,
            'Informational' => $findingsCounts['Informational'] ?? 0,
        ];

        $totalFindings = array_sum($findingsBySeverity);

        return [
            'total_scans' => $totalScans,
            'queued_scans' => $queuedScans,
            'running_scans' => $runningScans,
            'completed_scans' => $completedScans,
            'failed_scans' => $failedScans,
            'recent_scans' => $recentScans,
            'findings_by_severity' => $findingsBySeverity,
            'total_findings' => $totalFindings,
        ];
    }
}
