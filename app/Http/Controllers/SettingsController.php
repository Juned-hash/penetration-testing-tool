<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('settings.index', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Gracefully restart and scale all queue worker processes (Admin-only).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function restartQueue(Request $request): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        try {
            Artisan::call('queue:restart');

            $assessmentCount = env('ASSESSMENT_WORKERS', 3);
            $reportCount = env('REPORT_WORKERS', 1);

            if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                $cmd = "docker compose up -d --build --scale queue_assessment={$assessmentCount} --scale queue_reports={$reportCount} 2>&1";
                @exec($cmd);
            }

            Log::info('QUEUE_RESTART_REQUESTED: Queue worker restart & scale command executed by administrator.', [
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'assessment_count' => $assessmentCount,
                'report_count' => $reportCount,
            ]);

            return redirect()
                ->route('settings.index')
                ->with('success', 'Queue workers restart & scale signal transmitted successfully.');
        } catch (\Throwable $e) {
            Log::error('QUEUE_RESTART_FAILED: ' . $e->getMessage());
            return redirect()
                ->route('settings.index')
                ->with('error', 'Failed to restart queue workers: ' . $e->getMessage());
        }
    }

    /**
     * Run database migrations (Admin-only).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function migrate(Request $request): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);

            Log::info('MIGRATE_EXECUTED: Database migration executed by administrator.', [
                'user_id' => $request->user()->id,
            ]);

            return redirect()
                ->route('settings.index')
                ->with('success', 'Database migration executed successfully.');
        } catch (\Throwable $e) {
            Log::error('MIGRATE_FAILED: ' . $e->getMessage());
            return redirect()
                ->route('settings.index')
                ->with('error', 'Database migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear application configuration cache (Admin-only).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function configClear(Request $request): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        try {
            Artisan::call('config:clear');

            Log::info('CONFIG_CLEAR_EXECUTED: Configuration cache cleared by administrator.', [
                'user_id' => $request->user()->id,
            ]);

            return redirect()
                ->route('settings.index')
                ->with('success', 'Configuration cache cleared successfully.');
        } catch (\Throwable $e) {
            Log::error('CONFIG_CLEAR_FAILED: ' . $e->getMessage());
            return redirect()
                ->route('settings.index')
                ->with('error', 'Failed to clear config cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear application data cache (Admin-only).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function cacheClear(Request $request): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        try {
            Artisan::call('cache:clear');

            Log::info('CACHE_CLEAR_EXECUTED: Application cache cleared by administrator.', [
                'user_id' => $request->user()->id,
            ]);

            return redirect()
                ->route('settings.index')
                ->with('success', 'Application cache cleared successfully.');
        } catch (\Throwable $e) {
            Log::error('CACHE_CLEAR_FAILED: ' . $e->getMessage());
            return redirect()
                ->route('settings.index')
                ->with('error', 'Failed to clear application cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear route cache (Admin-only).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function routeClear(Request $request): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        try {
            Artisan::call('route:clear');

            Log::info('ROUTE_CLEAR_EXECUTED: Route cache cleared by administrator.', [
                'user_id' => $request->user()->id,
            ]);

            return redirect()
                ->route('settings.index')
                ->with('success', 'Route cache cleared successfully.');
        } catch (\Throwable $e) {
            Log::error('ROUTE_CLEAR_FAILED: ' . $e->getMessage());
            return redirect()
                ->route('settings.index')
                ->with('error', 'Failed to clear route cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear optimization caches (Admin-only).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function optimizeClear(Request $request): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        try {
            Artisan::call('optimize:clear');

            Log::info('OPTIMIZE_CLEAR_EXECUTED: Optimization cache cleared by administrator.', [
                'user_id' => $request->user()->id,
            ]);

            return redirect()
                ->route('settings.index')
                ->with('success', 'Optimization caches cleared successfully.');
        } catch (\Throwable $e) {
            Log::error('OPTIMIZE_CLEAR_FAILED: ' . $e->getMessage());
            return redirect()
                ->route('settings.index')
                ->with('error', 'Failed to clear optimization cache: ' . $e->getMessage());
        }
    }

    /**
     * Run database seeders (Authenticated users).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function dbSeed(Request $request): RedirectResponse
    {
        try {
            Artisan::call('db:seed', ['--force' => true]);

            Log::info('DB_SEED_EXECUTED: Database seeder executed.', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            return redirect()
                ->route('settings.index')
                ->with('success', 'Database seeders executed successfully.');
        } catch (\Throwable $e) {
            Log::error('DB_SEED_FAILED: ' . $e->getMessage());
            return redirect()
                ->route('settings.index')
                ->with('error', 'Database seeding failed: ' . $e->getMessage());
        }
    }
}

