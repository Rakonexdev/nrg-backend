<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$persons = App\Models\Person::latest()->take(10)->get();
file_put_contents('persons.json', json_encode($persons));
