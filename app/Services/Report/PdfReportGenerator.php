<?php

namespace App\Services\Report;

use App\Models\Scan;
use App\Services\Report\Contracts\ReportGeneratorInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class PdfReportGenerator implements ReportGeneratorInterface
{
    /**
     * Generate an assessment PDF report file for the given Scan.
     *
     * @param Scan $scan
     * @return string Relative storage path to generated PDF report.
     */
    public function generate(Scan $scan): string
    {
        $scan->loadMissing([
            'user',
            'scanScopes',
            'scanConfiguration',
            'authenticationConfiguration',
            'findings',
        ]);

        $pdf = Pdf::loadView('reports.pdf', ['scan' => $scan]);
        $pdf->setPaper('a4', 'portrait');

        $reportsDir = storage_path('app/reports');
        File::ensureDirectoryExists($reportsDir);

        $filename = 'report_' . $scan->id . '_' . time() . '.pdf';
        $fullPath = $reportsDir . '/' . $filename;
        $relativePath = 'reports/' . $filename;

        $pdf->save($fullPath);

        return $relativePath;
    }
}
