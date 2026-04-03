<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $controller = $app->make(\App\Http\Controllers\Api\PersonController::class);
    $request = \Illuminate\Http\Request::create('/api/persons', 'GET');
    $response = $controller->index($request);
    echo $response->getContent();
} catch (\Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
} catch (\Error $e) {
    echo 'FATAL ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
}
