# AGENTS.md

## Project Identity

Project name: Authorized Web Application Security Assessment Platform

This is a standalone Laravel application for managing authorized web application
security assessments.

The application is NOT part of SOAPBOX.CLOUD and must remain completely
independent from all SOAPBOX projects.

The Laravel application is the management, orchestration, result-ingestion,
findings, and reporting layer.

OWASP ZAP is the external security-testing engine.

Do not implement a custom vulnerability scanner, exploit engine, crawler,
payload engine, credential theft mechanism, persistence mechanism, or
alternative penetration-testing engine.

---

# 1. Technology Stack

Use the following technology stack.

Backend:
- Laravel 12
- PHP 8.2.12
- Laravel Eloquent ORM
- Laravel migrations
- Laravel queues/jobs
- Laravel authentication
- Laravel validation
- Laravel policies/authorization

Frontend:
- Laravel Blade
- Bootstrap 5
- SCSS
- Vite
- Vanilla JavaScript where required
- Alpine.js only if genuinely useful

Database:
- MySQL/MariaDB
- Database name: penetration_testing

Security testing engine:
- OWASP ZAP
- ZAP Automation Framework

Reports:
- PDF in V1
- DOCX can be implemented after PDF

Do NOT use:
- React
- Vue
- Next.js
- Inertia.js
- PostgreSQL
- MongoDB
- Tailwind CSS
- Node.js as a separate backend
- Express
- Separate frontend application
- Separate backend application

Node/npm may be used only for frontend asset compilation through Vite.

---

# 2. Database Configuration

The development environment uses XAMPP.

MySQL/MariaDB is provided by XAMPP.

Use the existing database:

    penetration_testing

The Laravel `.env` configuration should use:

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=penetration_testing
    DB_USERNAME=root
    DB_PASSWORD=

Do not create another database.

Do not use:
- soapboxcloud
- soapbox
- laravel
- PostgreSQL
- SQLite

The application must never modify or connect to any SOAPBOX database.

Before making database assumptions:

1. Inspect the existing `.env`.
2. Inspect `config/database.php`.
3. Verify the Laravel database configuration.
4. Run Laravel database commands where appropriate.
5. Use migrations for application tables.

---

# 3. Development Environment

Expected environment:

OS:
- Windows

PHP:
- 8.2.12

Composer:
- 2.x

Node:
- 24.x

npm:
- 11.x

Web server:
- XAMPP / Apache during local development

Database:
- MySQL/MariaDB from XAMPP

The developer may run:

    php artisan
    php artisan migrate
    php artisan migrate:status
    php artisan db:show
    php artisan route:list
    php artisan test
    npm install
    npm run dev
    npm run build

Do not install or replace system-level software unless necessary.

---

# 4. Core Product Purpose

The application provides a controlled interface for authorized web application
security assessments.

The primary workflow is:

1. User logs in.
2. User opens the dashboard.
3. User creates a new assessment.
4. User enters the target URL.
5. User selects the environment.
6. User defines the approved scope.
7. User defines exclusions.
8. User configures authentication if required.
9. User reviews the assessment.
10. User explicitly confirms authorization.
11. User starts the assessment.
12. Laravel creates a queued assessment job.
13. The job invokes the configured OWASP ZAP installation.
14. ZAP performs the configured assessment.
15. Laravel receives/parses the results.
16. Results are stored as findings.
17. User views the findings dashboard.
18. User views individual findings.
19. User generates a report.
20. User can download the generated report.

The system should support only one active assessment at a time in V1.

The architecture should still use Laravel queues/jobs so the HTTP request does
not remain open while a long-running assessment executes.

---

# 5. Authorization and Scope

This application is intended for authorized security assessments.

Every assessment must contain:

- target URL
- environment
- scope
- exclusions
- authorization confirmation
- assessment configuration

Before an active assessment can start, the user must explicitly confirm:

"I confirm that I am authorized to perform security testing against this target
and that the target is within the approved assessment scope."

Do not allow an active assessment to start without this confirmation.

The application must make the assessment scope visible before execution.

Example:

Target:

    https://staging.example.com

Environment:

    Staging

Included:

    https://staging.example.com/*

Excluded:

    /logout
    /payment/*
    /admin/backup/*

---

# 6. Assessment Environments

Supported environments:

- Development
- Staging
- Production

The environment should be stored with the assessment.

The UI should clearly display the selected environment.

Production assessments should have stronger confirmation messaging than
development/staging assessments.

Do not silently assume that a target is authorized.

---

# 7. Authentication Configuration

Support these authentication modes:

1. No authentication
2. Form-based authentication
3. Browser-based authentication
4. Token/API/JSON authentication

Design the system so additional authentication mechanisms can be added later.

## Form authentication

Possible fields:

- login URL
- username
- password
- username field
- password field
- login button selector
- authenticated URL
- success indicator
- logout indicator

## Browser authentication

Allow configuration required for JavaScript-heavy applications and supported
ZAP browser-based authentication workflows.

## Token/API authentication

Allow configuration for appropriate authentication headers/tokens.

Credentials must never be:

- written to application logs
- displayed in findings
- displayed in reports
- stored in plaintext when persistence is required

Use Laravel encryption for persisted secrets.

Never expose credentials in debug output.

Prefer dedicated test accounts.

---

# 8. Scope Management

An assessment must support:

- target URL
- included paths
- excluded paths
- URL patterns where appropriate
- exclusions such as:
  - /logout
  - /payment/*
  - /admin/backup/*

The scope configuration must be persisted.

The ZAP configuration generated by Laravel must respect the configured scope.

Do not silently broaden the user's approved scope.

---

# 9. OWASP ZAP Integration

OWASP ZAP is an external application.

Laravel must NOT reimplement ZAP.

Create a dedicated integration layer.

Suggested structure:

    app/
        Services/
            Zap/
                ZapService.php
                ZapConfigurationBuilder.php
                ZapRunner.php
                ZapResultParser.php

Responsibilities:

## ZapService.php

High-level orchestration.

## ZapConfigurationBuilder.php

Build the ZAP Automation Framework configuration from the Laravel assessment
configuration.

## ZapRunner.php

Execute the configured ZAP process.

The implementation should support an external ZAP installation.

The execution mechanism should be configurable so the project can later support:

- local ZAP installation
- ZAP Docker container
- another controlled execution environment

Do not hard-code a machine-specific ZAP path.

Use configuration/environment variables.

Example conceptual configuration:

    ZAP_BINARY_PATH=
    ZAP_WORKING_DIRECTORY=
    ZAP_TIMEOUT=

Do not assume Docker is installed.

---

# 10. ZAP Automation Framework

Use the OWASP ZAP Automation Framework as the preferred integration mechanism.

Laravel should generate the required YAML configuration internally.

Do not require the user to manually create YAML files.

The user configures the assessment through the Laravel UI.

Laravel converts that configuration into the appropriate ZAP Automation Framework
configuration.

The generated temporary configuration should be handled securely and should
not expose credentials.

---

# 11. Assessment Lifecycle

Use explicit statuses.

Recommended statuses:

- draft
- queued
- starting
- crawling
- passive_scanning
- authentication
- active_scanning
- processing_results
- generating_report
- completed
- failed
- cancelled

The UI should display the current assessment state.

Do not fabricate progress.

If exact progress is unavailable from ZAP, display a meaningful stage instead
of inventing percentages.

---

# 12. Queue Architecture

Create a Laravel queue job:

    RunZapAssessment.php

The job should:

1. Load the assessment.
2. Validate that it is eligible to run.
3. Verify authorization confirmation.
4. Verify target and scope configuration.
5. Update status to starting.
6. Build the ZAP Automation Framework configuration.
7. Launch ZAP.
8. Track the process.
9. Update assessment stages.
10. Capture appropriate non-sensitive logs.
11. Parse the results.
12. Store findings.
13. Generate reports if configured.
14. Mark the assessment completed.

On failure:

- store an appropriate failure status
- store a safe error message
- never store passwords/tokens
- preserve enough information for troubleshooting

Do not run the scan synchronously from the HTTP request.

---

# 13. Database Models

Create appropriate migrations/models for:

- User
- Scan
- ScanConfiguration
- ScanScope
- AuthenticationConfiguration
- Finding
- Report
- ScanLog

Use clear relationships.

Suggested relationships:

User
  hasMany Scan

Scan
  belongsTo User
  hasOne ScanConfiguration
  hasOne ScanScope
  hasOne AuthenticationConfiguration
  hasMany Finding
  hasMany Report
  hasMany ScanLog

---

# 14. Scan Table

The Scan model should contain appropriate fields such as:

- id
- user_id
- name
- target_url
- environment
- status
- authorization_confirmed_at
- started_at
- completed_at
- failure_reason
- created_at
- updated_at

Use appropriate database types.

Do not store unnecessary secrets in the Scan table.

---

# 15. Finding Model

Findings should support fields such as:

- id
- scan_id
- source
- external_id
- name
- risk
- confidence
- severity
- url
- method
- parameter
- attack
- evidence
- description
- impact
- solution
- reference
- cwe_id
- wasc_id
- wstg_id
- status
- created_at
- updated_at

Severity:

- Critical
- High
- Medium
- Low
- Informational

Confidence must remain separate from severity.

Do not incorrectly convert confidence into severity.

---

# 16. Findings Dashboard

Create:

    /scans/{scan}/findings

The findings page should provide:

- total findings
- severity counts
- confidence information
- filtering
- sorting
- pagination
- finding name
- URL
- method
- parameter
- severity
- confidence
- status

Finding detail:

    /scans/{scan}/findings/{finding}

Display:

- title
- severity
- confidence
- affected URL
- HTTP method
- parameter
- evidence
- description
- impact
- solution
- references
- CWE
- WASC
- WSTG
- status

Never display authentication credentials.

---

# 17. Assessment Dashboard

Dashboard:

    /dashboard

Show useful information such as:

- total assessments
- queued assessments
- running assessments
- completed assessments
- failed assessments
- findings by severity
- recent assessments

Do not use fake statistics.

If the database is empty, show appropriate empty states.

---

# 18. Assessment History

Create:

    /scans

Show:

- assessment name
- target
- environment
- status
- created date
- started date
- completed date
- finding count

Allow the user to open an assessment.

---

# 19. Create Assessment

Create:

    GET /scans/create

The UI should guide the user through:

Step 1:
Target

Step 2:
Environment

Step 3:
Scope and exclusions

Step 4:
Authentication

Step 5:
Assessment configuration

Step 6:
Review

Step 7:
Authorization confirmation

Step 8:
Start

Use server-side validation.

Do not rely only on JavaScript validation.

---

# 20. Assessment Detail

Create:

    GET /scans/{scan}

Display:

- assessment information
- target
- environment
- scope
- exclusions
- authentication mode
- current status
- timestamps
- findings summary
- reports
- scan logs where appropriate

During execution, provide a clear status view.

---

# 21. Routes

Use appropriate route organization.

Required routes:

    GET  /dashboard

    GET  /scans
    GET  /scans/create
    POST /scans

    GET  /scans/{scan}
    GET  /scans/{scan}/findings
    GET  /scans/{scan}/findings/{finding}

    POST /scans/{scan}/start
    POST /scans/{scan}/cancel

    GET  /scans/{scan}/status

    POST /scans/{scan}/reports/pdf
    POST /scans/{scan}/reports/docx

    GET  /reports/{report}/download

Use:

- route model binding
- authentication middleware
- authorization policies
- request validation

Users must only be able to access assessments they are authorized to access.

---

# 22. Controllers

Keep controllers thin.

Use dedicated services for business logic.

Suggested controllers:

- DashboardController
- ScanController
- ScanFindingController
- ScanStatusController
- ScanReportController

Do not put large ZAP execution logic directly inside controllers.

---

# 23. Services

Use service classes for significant operations.

Suggested services:

    ScanService
    ScanConfigurationService
    ZapService
    ZapConfigurationBuilder
    ZapRunner
    ZapResultParser
    FindingService
    ReportService
    PdfReportGenerator
    DocxReportGenerator

Keep responsibilities separated.

---

# 24. Reporting

PDF reporting is required for V1.

DOCX is optional/future but design the architecture so it can be added without
rewriting the assessment system.

PDF report structure:

1. Cover
2. Executive summary
3. Assessment information
4. Target
5. Environment
6. Scope
7. Exclusions
8. Authentication summary
9. Methodology
10. Assessment configuration
11. Findings summary
12. Detailed findings
13. Recommendations
14. Limitations
15. Appendix

Never include:

- passwords
- tokens
- authentication secrets
- sensitive session data

Reports should be professional and suitable for security assessment
documentation.

---

# 25. Security Requirements

Follow Laravel security best practices.

Use:

- CSRF protection
- authentication
- authorization policies
- request validation
- mass-assignment protection
- encrypted secrets
- secure file handling
- secure report downloads
- safe process execution
- output escaping
- safe logging

Do not construct shell commands using unsanitized user input.

Use process APIs and validated arguments.

Never expose arbitrary command execution through the web interface.

Never allow a user to submit an arbitrary OS command as a scan configuration.

---

# 26. Logging

Create ScanLog records for meaningful lifecycle events.

Examples:

- assessment queued
- assessment started
- ZAP configuration generated
- ZAP process started
- crawling started
- authentication started
- active assessment started
- result processing started
- findings imported
- report generated
- assessment completed
- assessment failed

Never log:

- passwords
- access tokens
- cookies
- API secrets
- authorization headers containing secrets

---

# 27. UI Design

Use Bootstrap 5.

The UI should feel like a professional security assessment platform.

Design goals:

- clean
- modern
- responsive
- professional
- dashboard-oriented
- clear status indicators
- clear severity badges
- accessible forms
- good empty states
- clear validation messages

Suggested navigation:

Dashboard
Assessments
Findings
Reports
Settings

Use Bootstrap components rather than creating unnecessary custom CSS.

---

# 28. Severity Presentation

Use consistent visual treatment for:

Critical
High
Medium
Low
Informational

Do not rely on color alone.

Include text labels and appropriate accessibility attributes.

---

# 29. Error Handling

Provide user-friendly error pages/messages.

Do not expose:

- stack traces in production
- internal filesystem paths
- shell commands
- credentials
- raw authentication data

Development environments may display Laravel debugging information when
explicitly enabled.

---

# 30. Testing

Create automated tests for:

Authentication:
- login
- authorization

Assessments:
- creation
- validation
- scope
- authorization confirmation
- ownership

Findings:
- listing
- filtering
- authorization

Reports:
- generation
- download authorization

ZAP integration:
- configuration generation
- parser behavior
- failure handling

Queue:
- job dispatch
- lifecycle transitions

Use controlled test targets only.

For integration testing of actual security assessments, use an intentionally
vulnerable local application such as OWASP Juice Shop or another explicitly
controlled test target.

Do not use arbitrary third-party websites for testing.

---

# 31. No Fabricated Data

Never fabricate:

- scan results
- findings
- vulnerability counts
- scan progress
- ZAP output
- target responses

If ZAP has not actually run, the UI must not imply that it has.

If the ZAP executable is unavailable, clearly show that the scan cannot start.

---

# 32. ZAP Availability

Provide a configuration/status mechanism that can determine whether the
configured ZAP execution environment is available.

For example:

    ZAP_BINARY_PATH

or another controlled execution mechanism.

The application should provide a useful error such as:

"OWASP ZAP execution environment is not available."

Do not silently fall back to fake results.

---

# 33. Docker

Docker is NOT required for Laravel.

The Laravel application should continue to run using the user's existing
Windows/XAMPP environment.

Docker may later be used for OWASP ZAP execution.

Design the ZAP integration so Docker can be added without redesigning the
entire Laravel application.

Do not make Docker a hard dependency unless explicitly requested later.

---

# 34. Architecture Principle

Keep this separation:

Laravel:

- UI
- users
- authentication
- assessments
- scope
- configuration
- authorization confirmation
- queue management
- ZAP orchestration
- result ingestion
- findings
- reports
- audit logs

OWASP ZAP:

- crawling
- passive analysis
- authentication/session handling where configured
- active security assessment
- security-testing engine
- raw results

Laravel must not duplicate ZAP's scanning engine.

---

# 35. Development Process

Do NOT attempt to implement the entire application in one uncontrolled change.

Work in phases.

After each phase:

1. Inspect the resulting files.
2. Run relevant tests.
3. Run migrations where appropriate.
4. Check routes.
5. Check for errors.
6. Summarize changed files.
7. Identify anything that still needs to be implemented.

Do not proceed past a broken foundation merely to finish more features.

Prefer small, reviewable changes.

Do not overwrite unrelated project files.

Do not modify SOAPBOX projects.

---

# 36. Phase Order

Phase 1:
Project inspection and environment verification.

Phase 2:
Database configuration and migrations.

Phase 3:
Authentication.

Phase 4:
Bootstrap/Vite and application layout.

Phase 5:
Dashboard.

Phase 6:
Assessment creation workflow.

Phase 7:
Scope/exclusions.

Phase 8:
Authentication configuration.

Phase 9:
Assessment lifecycle and queue.

Phase 10:
OWASP ZAP integration.

Phase 11:
Result parsing.

Phase 12:
Findings dashboard.

Phase 13:
Assessment history.

Phase 14:
PDF reports.

Phase 15:
DOCX reporting.

Phase 16:
Testing.

Phase 17:
Security hardening.

Phase 18:
Documentation and final cleanup.

---

# 37. Coding Standards

Follow Laravel conventions.

Prefer:

- dependency injection
- Form Request classes
- policies
- service classes
- Eloquent relationships
- enums where appropriate
- typed properties
- return types
- clear naming
- small methods
- reusable Blade components

Avoid:

- huge controllers
- duplicated validation
- duplicated business logic
- raw SQL when Eloquent is appropriate
- hard-coded paths
- hard-coded credentials
- hard-coded ZAP installation paths
- unnecessary dependencies

---

# 38. Documentation

Maintain a README containing:

- project purpose
- requirements
- installation
- `.env` configuration
- database setup
- migration instructions
- development commands
- queue configuration
- ZAP installation/configuration
- ZAP execution configuration
- testing
- report generation
- troubleshooting

Also document the architecture.

---

# 39. Important Development Rule

Before making a change:

- inspect the existing implementation
- determine whether the feature already exists
- modify existing code when appropriate
- avoid unnecessary duplicate classes
- preserve working functionality

Do not assume a file does not exist.

Do not regenerate the entire application unnecessarily.

---

# 40. First Task

When starting work on this repository, DO NOT immediately build the entire
application.

First inspect the project.

Determine:

1. Laravel version.
2. PHP compatibility.
3. Existing composer dependencies.
4. Existing npm dependencies.
5. Existing `.env`.
6. Database configuration.
7. Existing routes.
8. Existing migrations.
9. Existing authentication.
10. Existing Blade structure.
11. Existing Vite configuration.

Then configure the project for:

    penetration_testing

using:

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=penetration_testing
    DB_USERNAME=root
    DB_PASSWORD=

Run:

    php artisan config:clear
    php artisan migrate:status

If appropriate, run:

    php artisan migrate

Do not drop or reset databases automatically.

Never run:

    php artisan migrate:fresh

against the user's existing database without explicit approval.

Do not modify SOAPBOX databases.

After the inspection and database setup, report:

- Laravel version
- PHP version
- database connection status
- migration status
- existing authentication status
- Bootstrap/Vite status
- files that were changed

Then proceed to the next phase only after verifying the foundation.