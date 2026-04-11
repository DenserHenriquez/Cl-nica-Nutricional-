<?php
/**
 * Processador de cola de emails - ejecutar via cron cada 5 min o manual
 * php process_email_queue.php
 */

require_once __DIR__ . '/email_config.php';

$queue_file = __DIR__ . '/email_queue.json';
if (!file_exists($queue_file)) {
    echo "Cola vacía.\n";
    exit(0);
}

$queue = json_decode(file_get_contents($queue_file), true) ?: [];
if (empty($queue)) {
    echo "Cola vacía.\n";
    exit(0);
}

$new_queue = [];
$processed = 0;
$errors = 0;

foreach ($queue as $email_data) {
    if ($email_data['attempts'] >= 5) {
        $errors++;
        continue; // descartar después 5 intentos
    }

    $r = enviarCorreo($email_data['to'], $email_data['subject'], $email_data['body']);
    if ($r['success']) {
        $processed++;
    } else {
        $email_data['attempts']++;
        $email_data['last_error'] = $r['error'];
        $new_queue[] = $email_data;
        $errors++;
    }
}

file_put_contents($queue_file, json_encode($new_queue, JSON_PRETTY_PRINT));

echo "Procesados: $processed OK, $errors errores, " . count($new_queue) . " pendientes.\n";
?>

