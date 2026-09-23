<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Security Assessment Report - {{ $scan->name }}</title>
    <style>
        @page {
            margin: 40pt 40pt 50pt 40pt;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #212529;
            font-size: 10pt;
            line-height: 1.5;
        }
        .page-break {
            page-break-after: always;
        }
        .cover {
            text-align: center;
            padding-top: 120pt;
        }
        .cover-title {
            font-size: 26pt;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 10pt;
        }
        .cover-subtitle {
            font-size: 14pt;
            color: #6c757d;
            margin-bottom: 40pt;
        }
        .cover-meta {
            margin-top: 150pt;
            font-size: 10pt;
            color: #495057;
            border-top: 2pt solid #0d6efd;
            padding-top: 15pt;
        }
        h2 {
            font-size: 14pt;
            color: #0d6efd;
            border-bottom: 1pt solid #dee2e6;
            padding-bottom: 4pt;
            margin-top: 15pt;
            margin-bottom: 10pt;
        }
        h3 {
            font-size: 11pt;
            color: #212529;
            margin-top: 10pt;
            margin-bottom: 5pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12pt;
        }
        th, td {
            padding: 6pt 8pt;
            text-align: left;
            vertical-align: top;
            font-size: 9pt;
        }
        th {
            background-color: #f8f9fa;
            border-bottom: 2pt solid #dee2e6;
            color: #495057;
            font-weight: bold;
        }
        td {
            border-bottom: 1pt solid #e9ecef;
        }
        .badge {
            display: inline-block;
            padding: 2pt 6pt;
            font-size: 8pt;
            font-weight: bold;
            border-radius: 3pt;
            text-transform: uppercase;
        }
        .bg-danger { background-color: #dc3545; color: #ffffff; }
        .bg-warning { background-color: #ffc107; color: #212529; }
        .bg-info { background-color: #0dcaf0; color: #212529; }
        .bg-secondary { background-color: #6c757d; color: #ffffff; }
        .bg-dark { background-color: #212529; color: #ffffff; }
        .card {
            border: 1pt solid #dee2e6;
            border-radius: 4pt;
            padding: 10pt;
            margin-bottom: 12pt;
            background-color: #ffffff;
        }
        .code-block {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f8f9fa;
            border: 1pt solid #e9ecef;
            padding: 6pt;
            font-size: 8pt;
            word-wrap: break-word;
            margin-top: 4pt;
            margin-bottom: 8pt;
        }
        .text-muted { color: #6c757d; }
        .font-monospace { font-family: 'Courier New', Courier, monospace; }
        .footer {
            position: fixed;
            bottom: -30pt;
            left: 0pt;
            right: 0pt;
            height: 20pt;
            font-size: 8pt;
            color: #adb5bd;
            border-top: 1pt solid #e9ecef;
            padding-top: 4pt;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="footer">
        Confidential - Authorized Security Assessment Report | Target: {{ $scan->target_url }}
    </div>

    <!-- 1. COVER -->
    <div class="cover page-break">
        <div class="cover-title">SECURITY ASSESSMENT REPORT</div>
        <div class="cover-subtitle">Web Application Vulnerability Assessment & Security Analysis</div>
        
        <div style="margin-top: 60pt;">
            <table style="width: 80%; margin: 0 auto;">
                <tr>
                    <td class="text-muted" style="width: 40%;">Target Application:</td>
                    <td class="font-monospace" style="font-weight: bold;">{{ $scan->target_url }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Assessment Name:</td>
                    <td>{{ $scan->name }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Target Environment:</td>
                    <td>{{ ucfirst($scan->environment) }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Assessment ID:</td>
                    <td>SCAN-{{ str_pad($scan->id, 5, '0', STR_PAD_LEFT) }}</td>
                </tr>
            </table>
        </div>

        <div class="cover-meta">
            <div>Prepared by Authorized Security Testing Platform</div>
            <div>Generated on: {{ now()->format('F d, Y \a\t H:i:s T') }}</div>
        </div>
    </div>

    <!-- 2. EXECUTIVE SUMMARY -->
    <h2>1. Executive Summary</h2>
    <p>
        An authorized web application security assessment was conducted against <strong>{{ $scan->target_url }}</strong> in the <strong>{{ ucfirst($scan->environment) }}</strong> environment. The evaluation evaluated security posture using automated crawling, passive analysis, and active security vulnerability scanning powered by the OWASP ZAP Automation Framework.
    </p>

    @php
        $findings = $scan->findings;
        $highCount = $findings->where('severity', 'high')->count();
        $mediumCount = $findings->where('severity', 'medium')->count();
        $lowCount = $findings->where('severity', 'low')->count();
        $infoCount = $findings->where('severity', 'info')->count();
    @endphp

    <div class="card">
        <h3>Vulnerability Summary Breakdown</h3>
        <table>
            <thead>
                <tr>
                    <th>SEVERITY RATING</th>
                    <th>COUNT</th>
                    <th>RISK LEVEL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge bg-danger">HIGH</span></td>
                    <td><strong>{{ $highCount }}</strong></td>
                    <td>Immediate remediation required. High potential impact.</td>
                </tr>
                <tr>
                    <td><span class="badge bg-warning">MEDIUM</span></td>
                    <td><strong>{{ $mediumCount }}</strong></td>
                    <td>Remediation required. Moderate potential risk.</td>
                </tr>
                <tr>
                    <td><span class="badge bg-info">LOW</span></td>
                    <td><strong>{{ $lowCount }}</strong></td>
                    <td>Remediate according to security lifecycle schedule.</td>
                </tr>
                <tr>
                    <td><span class="badge bg-secondary">INFORMATIONAL</span></td>
                    <td><strong>{{ $infoCount }}</strong></td>
                    <td>Informational observation / hardening recommendation.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 3. ASSESSMENT INFORMATION -->
    <h2>2. Assessment Information</h2>
    <table>
        <tr>
            <th style="width: 30%;">Assessment Name</th>
            <td>{{ $scan->name }}</td>
        </tr>
        <tr>
            <th>Assessment Status</th>
            <td><span class="badge bg-dark">{{ ucfirst($scan->status) }}</span></td>
        </tr>
        <tr>
            <th>Creation Date</th>
            <td>{{ $scan->created_at->format('M d, Y H:i:s') }}</td>
        </tr>
        <tr>
            <th>Started Date</th>
            <td>{{ $scan->started_at ? $scan->started_at->format('M d, Y H:i:s') : 'N/A' }}</td>
        </tr>
        <tr>
            <th>Completion Date</th>
            <td>{{ $scan->completed_at ? $scan->completed_at->format('M d, Y H:i:s') : 'N/A' }}</td>
        </tr>
        <tr>
            <th>Explicit Authorization Date</th>
            <td>{{ $scan->authorization_confirmed_at ? $scan->authorization_confirmed_at->format('M d, Y H:i:s') : 'N/A' }}</td>
        </tr>
    </table>

    <!-- 4. TARGET -->
    <h2>3. Target Information</h2>
    <table>
        <tr>
            <th style="width: 30%;">Target URL</th>
            <td class="font-monospace">{{ $scan->target_url }}</td>
        </tr>
        <tr>
            <th>Target Domain</th>
            <td class="font-monospace">{{ parse_url($scan->target_url, PHP_URL_HOST) }}</td>
        </tr>
        <tr>
            <th>Scheme / Protocol</th>
            <td class="font-monospace">{{ strtoupper(parse_url($scan->target_url, PHP_URL_SCHEME)) }}</td>
        </tr>
    </table>

    <!-- 5. ENVIRONMENT -->
    <h2>4. Environment</h2>
    <p>
        Target Environment: <strong>{{ ucfirst($scan->environment) }}</strong>.
    </p>
    @if ($scan->environment === 'production')
        <div class="card" style="border-color: #ffc107; background-color: #fff3cd;">
            <strong>PRODUCTION ENVIRONMENT NOTICE:</strong> This assessment was performed against an active production environment. Security testing activities were conducted within explicitly authorized boundaries to minimize operational impact.
        </div>
    @endif

    <!-- 6. SCOPE -->
    <h2>5. Approved Scope</h2>
    <table>
        <thead>
            <tr>
                <th>INCLUDED PATH PATTERN</th>
                <th>TYPE</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($scan->scanScopes->where('type', 'include') as $scope)
                <tr>
                    <td class="font-monospace">{{ $scope->path }}</td>
                    <td>Included Scope</td>
                </tr>
            @empty
                <tr>
                    <td class="font-monospace">/* (Entire Target Domain)</td>
                    <td>Included Scope</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 7. EXCLUSIONS -->
    <h2>6. Scope Exclusions</h2>
    <table>
        <thead>
            <tr>
                <th>EXCLUDED PATH PATTERN</th>
                <th>TYPE</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($scan->scanScopes->where('type', 'exclude') as $scope)
                <tr>
                    <td class="font-monospace">{{ $scope->path }}</td>
                    <td>Excluded Path</td>
                </tr>
            @empty
                <tr>
                    <td class="text-muted">No explicit path exclusions defined.</td>
                    <td>N/A</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 8. AUTHENTICATION SUMMARY -->
    <h2>7. Authentication Summary</h2>
    @php
        $auth = $scan->authenticationConfiguration;
    @endphp
    <table>
        <tr>
            <th style="width: 30%;">Authentication Mode</th>
            <td><span class="badge bg-dark">{{ strtoupper($auth->mode ?? 'none') }}</span></td>
        </tr>
        @if ($auth && $auth->mode !== 'none')
            <tr>
                <th>Login Endpoint</th>
                <td class="font-monospace">{{ $auth->login_url ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Account Username</th>
                <td class="font-monospace">{{ $auth->username ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Authenticated Indicator</th>
                <td class="font-monospace">{{ $auth->logged_in_indicator ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Credential Protection</th>
                <td><span class="badge bg-secondary">•••••••• [ENCRYPTED AT REST & REDACTED FROM REPORT]</span></td>
            </tr>
        @endif
    </table>

    <!-- 9. METHODOLOGY -->
    <h2>8. Assessment Methodology</h2>
    <p>
        The security assessment followed the industry-standard OWASP Web Security Testing Guide (WSTG v4.2) methodology using the OWASP ZAP Automation Framework:
    </p>
    <ul>
        <li><strong>Automated Spidering / Crawling:</strong> Mapping application attack surface, HTML forms, parameters, and endpoints.</li>
        <li><strong>Passive Security Analysis:</strong> Inspecting HTTP response headers, cookie flags, and metadata without modifying request structures.</li>
        <li><strong>Active Security Scanning:</strong> Sending structured security payloads to identify vulnerabilities like Cross-Site Scripting (XSS), SQL Injection, and header misconfigurations.</li>
    </ul>

    <!-- 10. ASSESSMENT CONFIGURATION -->
    <h2>9. Assessment Engine Configuration</h2>
    @php
        $config = $scan->scanConfiguration;
    @endphp
    <table>
        <tr>
            <th style="width: 30%;">Spidering / Crawling</th>
            <td>{{ ($config->spider_enabled ?? true) ? 'Enabled' : 'Disabled' }}</td>
        </tr>
        <tr>
            <th>Passive Security Analysis</th>
            <td>{{ ($config->passive_scan_enabled ?? true) ? 'Enabled' : 'Disabled' }}</td>
        </tr>
        <tr>
            <th>Active Security Scanning</th>
            <td>{{ ($config->active_scan_enabled ?? true) ? 'Enabled' : 'Disabled' }}</td>
        </tr>
    </table>

    <div class="page-break"></div>

    <!-- 11. FINDINGS SUMMARY -->
    <h2>10. Findings Summary Table</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">SEVERITY</th>
                <th style="width: 45%;">VULNERABILITY NAME</th>
                <th style="width: 25%;">TARGET URL</th>
                <th style="width: 15%;">CWE / WASC</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($scan->findings as $finding)
                @php
                    $sevBadge = match(strtolower($finding->severity)) {
                        'high', 'critical' => 'bg-danger',
                        'medium' => 'bg-warning',
                        'low' => 'bg-info',
                        default => 'bg-secondary',
                    };
                @endphp
                <tr>
                    <td><span class="badge {{ $sevBadge }}">{{ strtoupper($finding->severity) }}</span></td>
                    <td><strong>{{ $finding->name }}</strong></td>
                    <td class="font-monospace text-muted">{{ $finding->url }}</td>
                    <td class="font-monospace">
                        @if ($finding->cwe_id) CWE-{{ $finding->cwe_id }} @elseif($finding->wasc_id) WASC-{{ $finding->wasc_id }} @else N/A @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-muted">No security findings recorded for this assessment.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 12. DETAILED FINDINGS -->
    <h2>11. Detailed Security Findings</h2>
    @forelse ($scan->findings as $index => $finding)
        @php
            $sevBadge = match(strtolower($finding->severity)) {
                'high', 'critical' => 'bg-danger',
                'medium' => 'bg-warning',
                'low' => 'bg-info',
                default => 'bg-secondary',
            };
        @endphp
        <div class="card">
            <h3>Finding #{{ $index + 1 }}: {{ $finding->name }}</h3>
            <table style="margin-bottom: 6pt;">
                <tr>
                    <th style="width: 20%;">Severity:</th>
                    <td><span class="badge {{ $sevBadge }}">{{ strtoupper($finding->severity) }}</span></td>
                    <th style="width: 20%;">Risk Rating:</th>
                    <td>{{ $finding->risk }}</td>
                </tr>
                <tr>
                    <th>Confidence:</th>
                    <td>{{ $finding->confidence }}</td>
                    <th>Method & Param:</th>
                    <td class="font-monospace">{{ $finding->method ?? 'GET' }} @if($finding->parameter)[{{ $finding->parameter }}]@endif</td>
                </tr>
                <tr>
                    <th>URL:</th>
                    <td colspan="3" class="font-monospace">{{ $finding->url }}</td>
                </tr>
                <tr>
                    <th>CWE / WASC:</th>
                    <td colspan="3" class="font-monospace">
                        @if ($finding->cwe_id) CWE-{{ $finding->cwe_id }} @endif
                        @if ($finding->wasc_id) WASC-{{ $finding->wasc_id }} @endif
                        @if ($finding->wstg_id) {{ $finding->wstg_id }} @endif
                    </td>
                </tr>
            </table>

            @if ($finding->attack)
                <div style="font-weight: bold; margin-top: 4pt; font-size: 8.5pt;">Attack Payload:</div>
                <div class="code-block">{{ $finding->attack }}</div>
            @endif

            @if ($finding->evidence)
                <div style="font-weight: bold; margin-top: 4pt; font-size: 8.5pt;">Evidence Output:</div>
                <div class="code-block">{{ $finding->evidence }}</div>
            @endif

            <div style="font-weight: bold; margin-top: 4pt; font-size: 8.5pt;">Vulnerability Description:</div>
            <p style="font-size: 8.5pt; margin-top: 2pt; margin-bottom: 4pt;">{{ $finding->description ?: 'N/A' }}</p>

            @if ($finding->impact)
                <div style="font-weight: bold; margin-top: 4pt; font-size: 8.5pt;">Potential Impact:</div>
                <p style="font-size: 8.5pt; margin-top: 2pt; margin-bottom: 4pt;">{{ $finding->impact }}</p>
            @endif

            <div style="font-weight: bold; margin-top: 4pt; font-size: 8.5pt;">Recommended Solution:</div>
            <p style="font-size: 8.5pt; margin-top: 2pt; margin-bottom: 4pt;">{{ $finding->solution ?: 'Apply standard secure coding controls.' }}</p>

            @if ($finding->reference)
                <div style="font-weight: bold; margin-top: 4pt; font-size: 8.5pt;">References:</div>
                <div class="font-monospace" style="font-size: 8pt; margin-top: 2pt;">{{ $finding->reference }}</div>
            @endif
        </div>
    @empty
        <p class="text-muted">No detailed vulnerability findings recorded.</p>
    @endforelse

    <!-- 13. RECOMMENDATIONS -->
    <h2>12. Strategic Security Recommendations</h2>
    <ul>
        <li><strong>Implement Input Validation & Sanitization:</strong> Ensure all user-controllable input data is strictly validated and contextually encoded before rendering.</li>
        <li><strong>Enforce Hardened HTTP Security Headers:</strong> Configure `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Content-Security-Policy`, and `Strict-Transport-Security`.</li>
        <li><strong>Regular Automated Testing:</strong> Integrate continuous vulnerability scanning into CI/CD build pipelines prior to production deployments.</li>
    </ul>

    <!-- 14. LIMITATIONS -->
    <h2>13. Assessment Scope & Technical Limitations</h2>
    <p class="text-muted" style="font-size: 8.5pt;">
        This automated security evaluation reflects the security posture of the target application at the time of execution within the defined scope. Automated testing tools cannot replace manual penetration testing for complex business logic vulnerabilities, access control flaws, or manual authentication flow validation.
    </p>

    <!-- 15. APPENDIX -->
    <h2>14. Appendix: Risk Rating Glossary</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 20%;">RATING</th>
                <th>DEFINITION & REMEDIATION TIMELINE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="badge bg-danger">HIGH</span></td>
                <td>Vulnerabilities allowing unauthorized access, data compromise, or remote execution. Immediate remediation required.</td>
            </tr>
            <tr>
                <td><span class="badge bg-warning">MEDIUM</span></td>
                <td>Flaws exposing sensitive information or subverting specific security controls. Remediate within standard maintenance cycle.</td>
            </tr>
            <tr>
                <td><span class="badge bg-info">LOW</span></td>
                <td>Minor security flaws or defense-in-depth gaps with limited impact. Remediate as scheduling permits.</td>
            </tr>
            <tr>
                <td><span class="badge bg-secondary">INFORMATIONAL</span></td>
                <td>Observations regarding target configuration, header presence, or general security hardening recommendations.</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
