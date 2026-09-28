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
     * Gracefully restart all queue worker processes (Admin-only).
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function restartQueue(Request $request): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        Artisan::call('queue:restart');

        Log::info('QUEUE_RESTART_REQUESTED: Queue worker restart requested by administrator.', [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
        ]);

        return redirect()
            ->route('settings.index')
            ->with('success', 'Queue worker restart signal transmitted successfully.');
    }
}
