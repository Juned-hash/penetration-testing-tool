<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OWASP ZAP Execution Configuration (Docker)
    |--------------------------------------------------------------------------
    |
    | Configuration options for running OWASP ZAP inside a Docker container
    | using the ZAP Automation Framework.
    |
    */

    'docker_image' => env('ZAP_DOCKER_IMAGE', 'ghcr.io/zaproxy/zaproxy:stable'),

    'docker_binary' => env('ZAP_DOCKER_BINARY', 'docker'),

    'docker_user' => env('ZAP_DOCKER_USER', 'root'),

    'host_project_path' => env('HOST_PROJECT_PATH'),

    'working_dir' => env('ZAP_WORKING_DIRECTORY')
        ? (preg_match('/^([a-zA-Z]:[\\\\\/]|\/)/', env('ZAP_WORKING_DIRECTORY'))
            ? env('ZAP_WORKING_DIRECTORY')
            : base_path(env('ZAP_WORKING_DIRECTORY')))
        : storage_path('app/zap'),

    'timeout' => (int) env('ZAP_TIMEOUT', 3600),

    'pull_timeout' => (int) env('ZAP_PULL_TIMEOUT', 600),

    'active_scan_max_duration' => (int) env('ZAP_ACTIVE_SCAN_MAX_DURATION', 20),

    'integration_test' => (bool) env('ZAP_INTEGRATION_TEST', false),

];

