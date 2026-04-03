<?php
$logFilePath = __DIR__ . '/storage/logs/laravel.log';
if (!file_exists($logFilePath)) {
    file_put_contents('errors.txt', "Log file not found.\n");
    exit;
}

$lines = file($logFilePath);
$errors = [];
$captureLines = 0;
$currentError = [];

foreach ($lines as $line) {
    if (strpos($line, 'local.ERROR:') !== false || strpos($line, 'production.ERROR:') !== false) {
        $captureLines = 10; // capture the error line + 9 lines of stack trace
        $currentError = [$line];
    } elseif ($captureLines > 0) {
        $currentError[] = $line;
        $captureLines--;
        if ($captureLines === 0) {
            $errors[] = implode("", $currentError);
            $currentError = [];
        }
    }
}

// Get the last 10 errors
$recentErrors = array_slice($errors, -10);
$output = "";
foreach ($recentErrors as $idx => $err) {
    $output .= "--- ERROR $idx ---\n";
    $output .= $err;
}
file_put_contents('errors.txt', $output);
