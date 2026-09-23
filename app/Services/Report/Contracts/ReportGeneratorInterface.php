<?php

namespace App\Services\Report\Contracts;

use App\Models\Scan;

interface ReportGeneratorInterface
{
    /**
     * Generate an assessment report file for the given Scan.
     *
     * @param Scan $scan
     * @return string Absolute or relative file path to the generated report on disk.
     */
    public function generate(Scan $scan): string;
}
