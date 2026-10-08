<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$scan = new App\Models\Scan();
$scan->name = 'Soapbox Admin Test';
$scan->target_url = 'https://soapbox.cloud/admins';
$scan->status = 'pending';

$authConfig = new App\Models\AuthenticationConfiguration();
$authConfig->mode = 'form';
$authConfig->login_url = 'https://soapbox.cloud/login';
$authConfig->username = 'admin@soapbox.cloud';
$authConfig->password = 'Admin@0147';
$authConfig->username_field = 'email';
$authConfig->password_field = 'password';

$scan->setRelation('authenticationConfiguration', $authConfig);
$scan->setRelation('scanScopes', collect());
$scan->setRelation('scanConfiguration', null);

$builder = new App\Services\Zap\ZapConfigurationBuilder();
$yaml = $builder->buildYaml($scan, '/zap/wrk', 'report.json');

echo "=== GENERATED YAML AFTER FIX ===\n";
echo $yaml;
