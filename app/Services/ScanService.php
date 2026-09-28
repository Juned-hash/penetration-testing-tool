<?php

namespace App\Services;

use App\Jobs\RunAssessment;
use App\Models\AuthenticationConfiguration;
use App\Models\Scan;
use App\Models\ScanConfiguration;
use App\Models\ScanLog;
use App\Models\ScanScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScanService
{
    /**
     * Create a new security assessment scan with scope and authentication.
     *
     * @param User $user
     * @param array $data
     * @return Scan
     */
    public function createScan(User $user, array $data): Scan
    {
        return DB::transaction(function () use ($user, $data) {
            $scan = Scan::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'target_url' => rtrim($data['target_url'], '/'),
                'environment' => $data['environment'],
                'status' => 'draft',
            ]);

            ScanConfiguration::create([
                'scan_id' => $scan->id,
                'spider_enabled' => true,
                'ajax_spider_enabled' => false,
                'passive_scan_enabled' => true,
                'active_scan_enabled' => true,
                'authentication_enabled' => ($data['auth_mode'] ?? 'none') !== 'none',
            ]);

            $includedPaths = array_filter(array_map('trim', explode("\n", $data['included_paths'] ?? '')));
            if (empty($includedPaths)) {
                $includedPaths = ['/*'];
            }
            foreach ($includedPaths as $path) {
                if (!empty($path)) {
                    ScanScope::create([
                        'scan_id' => $scan->id,
                        'type' => 'include',
                        'path' => $path,
                    ]);
                }
            }

            $excludedPaths = array_filter(array_map('trim', explode("\n", $data['excluded_paths'] ?? '')));
            foreach ($excludedPaths as $path) {
                if (!empty($path)) {
                    ScanScope::create([
                        'scan_id' => $scan->id,
                        'type' => 'exclude',
                        'path' => $path,
                    ]);
                }
            }

            AuthenticationConfiguration::create([
                'scan_id' => $scan->id,
                'mode' => $data['auth_mode'] ?? 'none',
                'login_url' => $data['login_url'] ?? null,
                'username_field' => $data['username_field'] ?? null,
                'password_field' => $data['password_field'] ?? null,
                'username' => $data['username'] ?? null,
                'password' => $data['password'] ?? null,
                'token_name' => $data['token_name'] ?? null,
                'token_value' => $data['token_value'] ?? null,
                'login_button_selector' => $data['login_button_selector'] ?? null,
                'logged_in_indicator' => $data['logged_in_indicator'] ?? null,
                'logged_out_indicator' => $data['logged_out_indicator'] ?? null,
                'authenticated_url' => $data['authenticated_url'] ?? null,
            ]);

            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => 'info',
                'phase' => 'draft',
                'message' => 'Assessment created and configured.',
            ]);

            return $scan;
        });
    }

    /**
     * Mark explicit authorization confirmation for the assessment.
     *
     * @param Scan $scan
     * @return Scan
     */
    public function confirmAuthorization(Scan $scan): Scan
    {
        $scan->update([
            'authorization_confirmed_at' => now(),
        ]);

        ScanLog::create([
            'scan_id' => $scan->id,
            'level' => 'info',
            'phase' => 'authorization',
            'message' => 'Authorization explicitly confirmed by user.',
        ]);

        return $scan;
    }

    /**
     * Check if assessment has met pre-execution requirements.
     *
     * @param Scan $scan
     * @return bool
     */
    public function canBeStarted(Scan $scan): bool
    {
        // Must have authorization confirmation
        if (is_null($scan->authorization_confirmed_at)) {
            return false;
        }

        // Target URL must be present
        if (empty($scan->target_url) || !filter_var($scan->target_url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Cannot start if already running or queued
        if (in_array($scan->status, ['queued', 'starting', 'running', 'crawling', 'passive_scanning', 'active_scanning', 'processing_results', 'generating_report'])) {
            return false;
        }

        return true;
    }

    /**
     * Queue assessment for execution.
     *
     * @param Scan $scan
     * @return bool
     */
    public function startAssessment(Scan $scan): bool
    {
        if (!$this->canBeStarted($scan)) {
            return false;
        }

        $scan->update([
            'status' => 'queued',
        ]);

        ScanLog::create([
            'scan_id' => $scan->id,
            'level' => 'info',
            'phase' => 'queued',
            'message' => 'Assessment queued for background job dispatch.',
        ]);

        RunAssessment::dispatch($scan)->onQueue('assessments');

        return true;
    }

    /**
     * Cancel a running or queued assessment.
     *
     * @param Scan $scan
     * @return bool
     */
    public function cancelAssessment(Scan $scan): bool
    {
        if (in_array($scan->status, ['completed', 'failed', 'cancelled'])) {
            return false;
        }

        $scan->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        ScanLog::create([
            'scan_id' => $scan->id,
            'level' => 'warning',
            'phase' => 'cancelled',
            'message' => 'Assessment explicitly cancelled by user.',
        ]);

        return true;
    }

    /**
     * Safely transition assessment state and log progress.
     *
     * @param Scan $scan
     * @param string $status
     * @param string|null $message
     * @return void
     */
    public function updateStatus(Scan $scan, string $status, ?string $message = null): void
    {
        $updateData = ['status' => $status];

        if ($status === 'starting' && is_null($scan->started_at)) {
            $updateData['started_at'] = now();
        }

        if (in_array($status, ['completed', 'failed', 'cancelled']) && is_null($scan->completed_at)) {
            $updateData['completed_at'] = now();
        }

        $scan->update($updateData);

        if ($message) {
            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => $status === 'failed' ? 'error' : 'info',
                'phase' => $status,
                'message' => $message,
            ]);
        }
    }

    /**
     * Rerun an existing assessment by creating a new scan record with copied configuration.
     *
     * @param User $user
     * @param Scan $originalScan
     * @return Scan
     */
    public function rerunAssessment(User $user, Scan $originalScan): Scan
    {
        if (!in_array($originalScan->status, ['completed', 'failed', 'cancelled'])) {
            throw new \InvalidArgumentException('Only completed, failed, or cancelled assessments can be rerun.');
        }

        return DB::transaction(function () use ($user, $originalScan) {
            $originalScan->loadMissing(['scanConfiguration', 'scanScopes', 'authenticationConfiguration']);

            // 1. Create a new Scan record referencing the parent assessment
            $newScan = Scan::create([
                'user_id' => $user->id,
                'parent_assessment_id' => $originalScan->id,
                'name' => $originalScan->name,
                'target_url' => $originalScan->target_url,
                'environment' => $originalScan->environment,
                'status' => 'queued',
                'authorization_confirmed_at' => now(),
            ]);

            // 2. Copy ScanConfiguration
            $origConfig = $originalScan->scanConfiguration;
            ScanConfiguration::create([
                'scan_id' => $newScan->id,
                'spider_enabled' => $origConfig->spider_enabled ?? true,
                'ajax_spider_enabled' => $origConfig->ajax_spider_enabled ?? false,
                'passive_scan_enabled' => $origConfig->passive_scan_enabled ?? true,
                'active_scan_enabled' => $origConfig->active_scan_enabled ?? true,
                'authentication_enabled' => $origConfig->authentication_enabled ?? false,
            ]);

            // 3. Copy ScanScope entries
            foreach ($originalScan->scanScopes as $scope) {
                ScanScope::create([
                    'scan_id' => $newScan->id,
                    'type' => $scope->type,
                    'path' => $scope->path,
                ]);
            }

            // 4. Copy AuthenticationConfiguration
            $origAuth = $originalScan->authenticationConfiguration;
            if ($origAuth) {
                AuthenticationConfiguration::create([
                    'scan_id' => $newScan->id,
                    'mode' => $origAuth->mode,
                    'login_url' => $origAuth->login_url,
                    'username_field' => $origAuth->username_field,
                    'password_field' => $origAuth->password_field,
                    'username' => $origAuth->username,
                    'password' => $origAuth->password,
                    'token_name' => $origAuth->token_name,
                    'token_value' => $origAuth->token_value,
                    'login_button_selector' => $origAuth->login_button_selector,
                    'logged_in_indicator' => $origAuth->logged_in_indicator,
                    'logged_out_indicator' => $origAuth->logged_out_indicator,
                    'authenticated_url' => $origAuth->authenticated_url,
                ]);
            }

            // 5. Audit logs for both new and original assessment
            ScanLog::create([
                'scan_id' => $newScan->id,
                'level' => 'info',
                'phase' => 'queued',
                'message' => "ASSESSMENT_RERUN_REQUESTED: Initialized as rerun of Assessment #{$originalScan->id}.",
            ]);

            ScanLog::create([
                'scan_id' => $originalScan->id,
                'level' => 'info',
                'phase' => 'rerun',
                'message' => "Rerun triggered by user '{$user->name}' (ID: {$user->id}). Created child Assessment #{$newScan->id}.",
            ]);

            // 6. Dispatch execution job for the new assessment
            RunAssessment::dispatch($newScan)->onQueue('assessments');

            return $newScan;
        });
    }
}
