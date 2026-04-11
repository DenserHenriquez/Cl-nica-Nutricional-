<?php
/**
 * Configuración de correo electrónico con PHPMailer + sistema de cola para fiabilidad
 * Editar SMTP_ credenciales abajo
 */

require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/* ========================================
   ✅ FIXED: SMTP Configuration 
   ========================================
   
   **OPCIÓN 1: TESTING (Mailtrap - RECOMENDADO para desarrollo)**
   Regístrate gratis: https://mailtrap.io/register (free tier)
   Copia credenciales del Inbox → SMTP
   
   **OPCIÓN 2: GMAIL REAL (Producción)**
   1. Activa 2FA: https://myaccount.google.com/security  
   2. App Password: https://myaccount.google.com/apppasswords
   3. Replace EMAIL/PASS below
   
   **Toggle: set USE_PROD_SMTP = true; para producción**
*/

$USE_PROD_SMTP = false;  // Set TRUE for real Gmail

if ($USE_PROD_SMTP) {
    // 🚨 PRODUCTION: Replace with YOUR Gmail App Password
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USER', 'tu-email@gmail.com');  // ← YOUR_EMAIL
    define('SMTP_PASS', 'tu-app-password');     // ← YOUR_APP_PASSWORD
} else {
    // 🧪 TESTING: Mailtrap Demo (emails go to Mailtrap dashboard)
    // Replace YOUR_API_KEY with real Mailtrap creds or use demo below
    define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
    define('SMTP_PORT', 2525);
    define('SMTP_USER', 'your-mailtrap-username');
    define('SMTP_PASS', 'your-mailtrap-password');
    // Demo (limited): Use after signup for full access
}

define('FROM_EMAIL', SMTP_USER);
define('FROM_NAME', 'Clínica Nutricional J ✅ FIXED');

/**
 * Enviar correo inmediato (sync) - usa directamente PHPMailer
 */
function enviarCorreo($to, $subject, $body_html, $is_html = true) {
    $mail = new PHPMailer(true);
    try {
        // Servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Remitente y destinatario
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);

        // Contenido
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return ['success' => true, 'message' => 'Enviado'];
    } catch (Exception $e) {
        queueEmail($to, $subject, $body_html);
        return ['success' => false, 'error' => $mail->ErrorInfo, 'queued' => true];
    }
}

/**
 * Agregar a cola JSON + intento inmediato
 */
function enviarCorreoConfirmacionCita($to_email, $paciente_nombre, $fecha_hora, $medico_nombre) {
    $subject = 'Confirmación de su cita médica - Clínica Nutricional';
    $body_html = generarBodyConfirmacionCita($paciente_nombre, $fecha_hora, $medico_nombre);
    
    // Intentar envío inmediato
    $r = enviarCorreo($to_email, $subject, $body_html);
    if ($r['success']) return $r;
    
    // Fallback cola
    queueEmail($to_email, $subject, $body_html);
    return ['success' => false, 'error' => $r['error'], 'queued' => true];
}



function generarBodyConfirmacionCita($paciente_nombre, $fecha_hora, $medico_nombre) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #198754;'>✅ Cita Confirmada</h2>
        <p>Hola <strong>$paciente_nombre</strong>,</p>
        <p>Su cita ha sido <strong>confirmada</strong> exitosamente.</p>
        <div style='background: #e8f5e9; padding: 20px; border-left: 5px solid #198754; border-radius: 5px;'>
            <h3>Detalles de la Cita</h3>
            <p><strong>Fecha y Hora:</strong> $fecha_hora</p>
            <p><strong>Médico:</strong> $medico_nombre</p>
        </div>
        <p>Por favor llegue 10 minutos antes. Si no puede asistir, cancele con anticipación.</p>
        <p>Saludos,<br><strong>Clínica Nutricional J</strong></p>
    </div>";
}

/**
 * Log error emails y queue
 */
function logEmailError($context, $data) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'context' => $context,
        'data' => $data,
        'error' => error_get_last() ?: 'Unknown'
    ];
    file_put_contents(__DIR__ . '/email_errors.log', json_encode($log) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Queue email to JSON file
 */
function queueEmail($to, $subject, $body) {
    $queue_file = __DIR__ . '/email_queue.json';
    $queue = [];
    if (file_exists($queue_file)) {
        $queue = json_decode(file_get_contents($queue_file), true) ?: [];
    }
    $queue[] = [
        'to' => $to,
        'subject' => $subject,
        'body' => $body,
        'created' => date('Y-m-d H:i:s'),
        'attempts' => 0
    ];
    file_put_contents($queue_file, json_encode($queue, JSON_PRETTY_PRINT));
    logEmailError('Queued email', ['to' => $to]);
}

?>

