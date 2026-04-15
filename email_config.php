<?php
/**
 * Configuración de correo electrónico con PHPMailer + sistema de cola para fiabilidad
<<<<<<< Updated upstream
 * 
 * ⚠️ IMPORTANTE: Configura tus credenciales en 'email_credentials.php' (NO aquí)
 */

// Cargar credenciales del archivo de configuración separado
require_once __DIR__ . '/email_credentials.php';

// Intentar cargar PHPMailer si está disponible
$PHPMailer_available = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    @require_once __DIR__ . '/vendor/autoload.php';
    $PHPMailer_available = class_exists('PHPMailer\PHPMailer\PHPMailer');
}

// Verificar si las credenciales fueron configuradas
if (!defined('SMTP_CREDENTIALS_CONFIGURED') || !SMTP_CREDENTIALS_CONFIGURED) {
    // Credenciales por defecto (no funcionarán - solo demostración)
    define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
    define('SMTP_PORT', 2525);
    define('SMTP_USER', 'your-mailtrap-username');
    define('SMTP_PASS', 'your-mailtrap-password');
    define('SMTP_NOT_CONFIGURED', true);
} else {
    define('SMTP_NOT_CONFIGURED', false);
}
=======
 * Los correos se encolan automáticamente si PHPMailer no está disponible
 */

// Usar fully qualified names para evitar conflictos
// Los statements use no pueden estar en condicionales

// Verificar si PHPMailer está disponible
$PHPMailer_available = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    @require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $PHPMailer_available = true;
    }
}

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

$USE_PROD_SMTP = true;  // Usar Gmail real

// SMTP para Gmail + App Password
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'nutrividahn@gmail.com');
define('SMTP_PASS', 'abfd kelq gkkk wlmd');

define('FROM_EMAIL', SMTP_USER);
define('FROM_NAME', 'Clínica Nutricional J');
>>>>>>> Stashed changes

// Asegurar que FROM_EMAIL esté definido
if (!defined('FROM_EMAIL')) {
    define('FROM_EMAIL', 'clinica-nutricional@ejemplo.com');
}
if (!defined('FROM_NAME')) {
    define('FROM_NAME', 'Clínica Nutricional');
}

/**
 * Enviar correo inmediato (sync) - usa directamente PHPMailer si está disponible
 */
function enviarCorreo($to, $subject, $body_html, $is_html = true) {
    global $PHPMailer_available;
    
<<<<<<< Updated upstream
    // Si no hay credenciales configuradas o PHPMailer no está disponible, encolar
    if (!$PHPMailer_available || SMTP_NOT_CONFIGURED) {
        queueEmail($to, $subject, $body_html);
        return [
            'success' => false,
            'error' => SMTP_NOT_CONFIGURED 
                ? 'Credenciales SMTP no configuradas (en cola)' 
                : 'PHPMailer no disponible (en cola)',
            'queued' => true
        ];
    }
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
=======
    // Si PHPMailer no está disponible, encolar inmediatamente
    if (!$PHPMailer_available) {
        queueEmail($to, $subject, $body_html, 'PHPMailer no disponible - encolado automáticamente', 'PHPMailer not available');
        return ['success' => false, 'error' => 'PHPMailer no disponible - correo encolado', 'queued' => true];
    }
    
    // Verificar credenciales SMTP
    if (!defined('SMTP_USER') || !defined('SMTP_PASS') || strpos(SMTP_USER, 'REEMPLAZA') !== false || strpos(SMTP_PASS, 'tu-app') !== false) {
        queueEmail($to, $subject, $body_html, 'Credenciales SMTP no configuradas - encolado automáticamente', 'Credentials not configured');
        return ['success' => false, 'error' => 'Credenciales SMTP no configuradas - correo encolado para después', 'queued' => true];
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
>>>>>>> Stashed changes
        // Servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
<<<<<<< Updated upstream
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
=======
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
>>>>>>> Stashed changes
        $mail->Port       = SMTP_PORT;

        // Remitente y destinatario
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);

        // Contenido
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->CharSet = 'UTF-8';

<<<<<<< Updated upstream
        $sent = $mail->send();
        return ['success' => true, 'message' => 'Enviado correctamente'];
    } catch (Exception $e) {
        // Si falla, encolar para reintento
        queueEmail($to, $subject, $body_html);
        logEmailError('Intento de envío falló, encolado', [
            'to' => $to,
            'smtp_error' => $e->getMessage()
        ]);
        return [
            'success' => false,
            'error' => 'Error SMTP: ' . $e->getMessage() . ' (en cola para reintento)',
            'queued' => true
        ];
=======
        $mail->send();
        return ['success' => true, 'message' => 'Enviado exitosamente'];
    } catch (Exception $e) {
        $error = isset($mail) ? ($mail->ErrorInfo ?: $e->getMessage()) : $e->getMessage();
        logEmailError('Email send failed', [
            'to' => $to,
            'subject' => $subject,
            'error_info' => $error,
            'exception' => $e->getMessage()
        ], $error);
        
        // Encolar como fallback
        queueEmail($to, $subject, $body_html, 'Error en envío - encolado para reintento', $error);
        return ['success' => false, 'error' => $error, 'queued' => true];
>>>>>>> Stashed changes
    }
}

/**
 * Agregar a cola JSON + intento inmediato - CONFIRMACIÓN
 */
function enviarCorreoConfirmacionCita($to_email, $paciente_nombre, $fecha_hora, $medico_nombre) {
    if (!$to_email || empty($to_email)) {
        return ['success' => false, 'error' => 'Email no registrado'];
    }
    
    $subject = 'Confirmación de su cita médica - Clínica Nutricional';
    $body_html = generarBodyConfirmacionCita($paciente_nombre, $fecha_hora, $medico_nombre);
    
    // Intentar envío inmediato
    $r = enviarCorreo($to_email, $subject, $body_html);
    return $r;
}

/**
 * Enviar correo de CANCELACIÓN de cita
 */
function enviarCorreoCancelacionCita($to_email, $paciente_nombre, $fecha_hora, $medico_nombre) {
    if (!$to_email || empty($to_email)) {
        return ['success' => false, 'error' => 'Email no registrado'];
    }
    
    $subject = 'Cancelación de su cita médica - Clínica Nutricional';
    $body_html = generarBodyCancelacionCita($paciente_nombre, $fecha_hora, $medico_nombre);
    
    // Intentar envío inmediato
    $r = enviarCorreo($to_email, $subject, $body_html);
    return $r;
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
 * Generar cuerpo HTML para correo de cancelación
 */
function generarBodyCancelacionCita($paciente_nombre, $fecha_hora, $medico_nombre) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #e53935;'>❌ Cita Cancelada</h2>
        <p>Hola <strong>$paciente_nombre</strong>,</p>
        <p>Su cita ha sido <strong>cancelada</strong>.</p>
        <div style='background: #ffebee; padding: 20px; border-left: 5px solid #e53935; border-radius: 5px;'>
            <h3>Detalles de la Cita Cancelada</h3>
            <p><strong>Fecha y Hora:</strong> $fecha_hora</p>
            <p><strong>Médico:</strong> $medico_nombre</p>
        </div>
        <p>Si desea reprogramar su cita, no dude en contactarnos.</p>
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

