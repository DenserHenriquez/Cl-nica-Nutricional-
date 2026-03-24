<?php
// Simple diagnostic for PHPMailer / Composer in this project
header('Content-Type: application/json; charset=utf-8');
$base = __DIR__;
$autoload = $base . '/vendor/autoload.php';
$resp = [
    'php_version' => PHP_VERSION,
    'autoload_exists' => file_exists($autoload),
    'autoload_path' => str_replace('\\', '/', $autoload),
    'composer_lock_exists' => file_exists($base . '/composer.lock'),
    'composer_json_exists' => file_exists($base . '/composer.json'),
    'vendor_dir_list' => @scandir($base . '/vendor') ?: [],
];
if ($resp['autoload_exists']) {
    try {
        require_once $autoload;
        $resp['phpmailer_class_exists'] = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
        $resp['exceptions_class_exists'] = class_exists('PHPMailer\\PHPMailer\\Exception');
    } catch (Throwable $e) {
        $resp['autoload_error'] = $e->getMessage();
    }
} else {
    $resp['phpmailer_class_exists'] = false;
}
echo json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
