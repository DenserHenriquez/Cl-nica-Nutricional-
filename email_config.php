<?php
/**
 * Configuración de correo SMTP
 * 
 * Para Gmail: usar App Password (no contraseña normal)
 * Para Outlook: usar contraseña de app
 * Para otros servicios: configurar según documentación
 * 
 * @author Clínica Nutricional Nutrivida
 */

// Configuración de correo
define('EMAIL_CONFIG', [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'nutrividahn@gmail.com',      // REEMPLAZAR con tu correo
    'password' => 'emos qdxh yydv sxro',       // REEMPLAZAR con contraseña de aplicación
    'from_email' => 'nutrividahn@gmail.com',     // REEMPLAZAR con tu correo
    'from_name' => 'Clínica Nutricional',
    'charset' => 'UTF-8',
    'encryption' => 'tls'                     // tls o ssl
]);

/**
 * Función helper para logging de errores de correo
 */
function logEmailError($message, $context = []) {
    $logFile = __DIR__ . '/email_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logEntry = "[$timestamp] $message $contextStr\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Envía un correo electrónico usando PHPMailer
 * 
 * @param string $to Correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $body Cuerpo del correo (puede ser HTML)
 * @param bool $isHtml true si el cuerpo es HTML, false para texto plano
 * @return array ['success' => bool, 'error' => string|null]
 */
function enviarCorreo($to, $subject, $body, $isHtml = false) {
    // Verificar que el autoload existe
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        $error = "No se encontró el archivo autoload.php en vendor/. Ejecutar 'composer install'";
        logEmailError("ERROR: $error", ['to' => $to, 'subject' => $subject]);
        return ['success' => false, 'error' => $error];
    }
    
    // Cargar PHPMailer
    require_once $autoloadPath;
    
    // Verificar que PHPMailer está disponible
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $error = "PHPMailer no está cargado. Verificar que composer install se ejecutó correctamente.";
        logEmailError("ERROR: $error", ['to' => $to, 'subject' => $subject]);
        return ['success' => false, 'error' => $error];
    }
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuración del servidor SMTP
        $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = EMAIL_CONFIG['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_CONFIG['username'];
        $mail->Password   = EMAIL_CONFIG['password'];
        $mail->SMTPSecure = EMAIL_CONFIG['encryption'];
        $mail->Port       = EMAIL_CONFIG['port'];
        
        // Configuración del correo
        $mail->setFrom(EMAIL_CONFIG['from_email'], EMAIL_CONFIG['from_name']);
        $mail->addAddress($to);
        $mail->CharSet = EMAIL_CONFIG['charset'];
        
        // Tiempo de espera
        $mail->Timeout = 30;
        
        // Contenido
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        if (!$isHtml) {
            $mail->AltBody = strip_tags($body);
        }
        
        $mail->send();
        
        logEmailError("Correo enviado exitosamente", ['to' => $to, 'subject' => $subject]);
        return ['success' => true, 'error' => null];
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $errorMsg = "PHPMailer Exception: " . $mail->ErrorInfo;
        logEmailError("ERROR: $errorMsg", ['to' => $to, 'subject' => $subject]);
        return ['success' => false, 'error' => $mail->ErrorInfo];
        
    } catch (\Exception $e) {
        $errorMsg = "Exception: " . $e->getMessage();
        logEmailError("ERROR: $errorMsg", ['to' => $to, 'subject' => $subject]);
        return ['success' => false, 'error' => $e->getMessage()];
        
    } catch (\Error $e) {
        $errorMsg = "Fatal Error: " . $e->getMessage();
        logEmailError("ERROR: $errorMsg", ['to' => $to, 'subject' => $subject]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envía correo de confirmación de cita médica
 * 
 * @param string $pacienteEmail Correo del paciente
 * @param string $pacienteNombre Nombre del paciente
 * @param string $fechaTxt Fecha y hora de la cita
 * @param string $medicoNombre Nombre del médico
 * @return array ['success' => bool, 'error' => string|null]
 */
function enviarCorreoConfirmacionCita($pacienteEmail, $pacienteNombre, $fechaTxt, $medicoNombre) {
    $subject = 'Confirmación de cita médica - Clínica Nutricional';
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #198754 0%, #146c43 100%); padding: 20px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h2 style='color: white; margin: 0;'>Clinica Nutricional</h2>
        </div>
        <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
            <h3 style='color: #198754;'>Su cita ha sido confirmada!</h3>
            <p>Hola <strong>" . htmlspecialchars($pacienteNombre) . "</strong>,</p>
            <p>Nos complace informarle que su cita medica ha sido aceptada.</p>
            <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #198754;'>
                <p style='margin: 5px 0;'><strong>Fecha y hora:</strong> " . htmlspecialchars($fechaTxt) . "</p>
                <p style='margin: 5px 0;'><strong>Medico:</strong> " . htmlspecialchars($medicoNombre) . "</p>
            </div>
            <p style='color: #666;'>Por favor llegue 10 minutos antes de su cita.</p>
            <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
            <p style='color: #999; font-size: 12px;'>Este es un mensaje automatico, no responda a este correo.</p>
        </div>
    </div>
    ";
    
    return enviarCorreo($pacienteEmail, $subject, $body, true);
}

