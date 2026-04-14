<?php
/**
 * Script para procesar la cola de correos encolados
 * 
 * Uso:
 *  - Ejecutar directamente: php process_email_queue.php
 *  - O agregarlo a CRON: */5 * * * * php /ruta/to/process_email_queue.php

 */

// Configuración
define('MAX_ATTEMPTS_PER_EMAIL', 5);
define('QUEUE_FILE', __DIR__ . '/email_queue.json');
define('LOG_FILE', __DIR__ . '/email_queue_processed.log');

// Cargar configuración de email
require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/email_credentials.php';

// Verificar si hay credenciales configuradas
if (!defined('SMTP_CREDENTIALS_CONFIGURED') || !SMTP_CREDENTIALS_CONFIGURED || SMTP_NOT_CONFIGURED) {
    $msg = 'Email queue processor: Credenciales SMTP no configuradas. Configura email_credentials.php primero.';
    logQueueError($msg);
    echo "[ERROR] " . $msg . "\n";
    exit(1);
}

// Procesar la cola
$queueData = [];
if (file_exists(QUEUE_FILE)) {
    $content = file_get_contents(QUEUE_FILE);
    $queueData = json_decode($content, true) ?: [];
}

if (empty($queueData)) {
    echo "[INFO] Cola vacía - nada que procesar.\n";
    exit(0);
}

$processed = 0;
$failed = 0;
$updated_queue = [];

foreach ($queueData as $email_data) {
    $attempts = (int)($email_data['attempts'] ?? 0);
    
    // Si ya alcanzó max intentos, descartar
    if ($attempts >= MAX_ATTEMPTS_PER_EMAIL) {
        $msg = "Correo a {$email_data['to']} descartado después de {$attempts} intentos";
        logQueueError($msg);
        echo "[DISCARD] " . $msg . "\n";
        continue;
    }
    
    // Intentar enviar
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email_data['to']);
        $mail->isHTML(true);
        $mail->Subject = $email_data['subject'];
        $mail->Body    = $email_data['body'];
        $mail->CharSet = 'UTF-8';
        
        $mail->send();
        
        $processed++;
        $msg = "✅ Enviado exitosamente a: {$email_data['to']}";
        logQueueError($msg);
        echo "[SUCCESS] " . $msg . "\n";
        
    } catch (Exception $e) {
        $failed++;
        $attempts++;
        $email_data['attempts'] = $attempts;
        $updated_queue[] = $email_data;
        
        $msg = "❌ Intento {$attempts}/" . MAX_ATTEMPTS_PER_EMAIL . " falló para {$email_data['to']}: " . $e->getMessage();
        logQueueError($msg);
        echo "[RETRY] " . $msg . "\n";
    }
}

// Guardar cola actualizada
if (!empty($updated_queue)) {
    file_put_contents(QUEUE_FILE, json_encode($updated_queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} else {
    // Si todo se envió, limpiar la cola
    if (file_exists(QUEUE_FILE)) {
        unlink(QUEUE_FILE);
    }
}

echo "\n[SUMMARY] Procesados: {$processed}, Fallos: {$failed}, Pendientes: " . count($updated_queue) . "\n";

/**
 * Registrar en log
 */
function logQueueError($msg) {
    $log_line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    file_put_contents(LOG_FILE, $log_line, FILE_APPEND | LOCK_EX);
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

