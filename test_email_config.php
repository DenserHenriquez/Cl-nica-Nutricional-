<?php
/**
 * Script de diagnóstico para verificar si el envío de correos está correctamente configurado
 * 
 * Uso:
 *   - Desde terminal: php test_email_config.php
 *   - Desde navegador: http://tu-sitio/test_email_config.php
 */

header('Content-Type: text/html; charset=utf-8');

$isWeb = php_sapi_name() !== 'cli';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>";
echo "body { font-family: Arial; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }";
echo ".success { color: #28a745; }";
echo ".error { color: #dc3545; }";
echo ".warning { color: #ffc107; }";
echo ".info { color: #17a2b8; }";
echo "h1 { color: #198754; border-bottom: 2px solid #198754; padding-bottom: 10px; }";
echo ".check { margin: 15px 0; padding: 10px; border-left: 4px solid #ccc; }";
echo ".check.ok { border-left-color: #28a745; background: #f0f8f5; }";
echo ".check.err { border-left-color: #dc3545; background: #fdf8f7; }";
echo ".check.warn { border-left-color: #ffc107; background: #fffaf0; }";
echo "code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }";
echo "</style></head><body>";

echo "<div class='container'>";
echo "<h1>📧 Diagnóstico de Configuración de Correos</h1>";

// 1. Verificar archivo de credenciales
echo "<div class='check'>";
if (file_exists(__DIR__ . '/email_credentials.php')) {
    echo "<strong class='success'>✅ Archivo email_credentials.php existe</strong>";
} else {
    echo "<strong class='error'>❌ Archivo email_credentials.php NO existe</strong>";
}
echo "</div>";

// 2. Cargar configuración
require_once __DIR__ . '/email_credentials.php';
require_once __DIR__ . '/email_config.php';

// 3. Verificar credenciales
echo "<div class='check " . (defined('SMTP_CREDENTIALS_CONFIGURED') && SMTP_CREDENTIALS_CONFIGURED && !SMTP_NOT_CONFIGURED ? 'ok' : 'err') . "'>";
if (defined('SMTP_CREDENTIALS_CONFIGURED') && SMTP_CREDENTIALS_CONFIGURED && !SMTP_NOT_CONFIGURED) {
    echo "<strong class='success'>✅ Credenciales SMTP configuradas correctamente</strong>";
    echo "<br>Host: <code>" . SMTP_HOST . "</code>";
    echo "<br>Puerto: <code>" . SMTP_PORT . "</code>";
    echo "<br>Usuario: <code>" . (substr(SMTP_USER, 0, 3) . '***') . "</code>";
} else {
    echo "<strong class='error'>❌ Credenciales SMTP NO configuradas</strong>";
    echo "<br><small>Edita <code>email_credentials.php</code> y descomenta una de las 3 opciones</small>";
}
echo "</div>";

// 4. Verificar PHPMailer
echo "<div class='check " . (file_exists(__DIR__ . '/vendor/autoload.php') ? 'ok' : 'warn') . "'>";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<strong class='success'>✅ PHPMailer disponible (vendor/autoload.php existe)</strong>";
} else {
    echo "<strong class='warning'>⚠️ PHPMailer NO disponible (vendor/autoload.php NO existe)</strong>";
    echo "<br><small>Los correos se encolarán pero no se enviarán. Ejecuta: <code>composer install</code></small>";
}
echo "</div>";

// 5. Verificar permisos de escritura
echo "<div class='check " . (is_writable(__DIR__) ? 'ok' : 'err') . "'>";
if (is_writable(__DIR__)) {
    echo "<strong class='success'>✅ Directorio escribible (puede guardar cola de correos)</strong>";
} else {
    echo "<strong class='error'>❌ Directorio NO escribible</strong>";
    echo "<br><small>chmod 755 " . __DIR__ . "</small>";
}
echo "</div>";

// 6. Verificar archivos de logs
echo "<div class='check'>";
$queueFile = __DIR__ . '/email_queue.json';
$logFile = __DIR__ . '/email_errors.log';
if (file_exists($queueFile)) {
    $queueCount = count(json_decode(file_get_contents($queueFile), true) ?: []);
    echo "<strong class='warning'>⚠️ Hay $queueCount correos encolados</strong>";
    echo "<br><small>Ejecuta: <code>php process_email_queue.php</code></small>";
} else {
    echo "<strong class='success'>✅ No hay correos encolados</strong>";
}
echo "</div>";

// 7. Intentar enviar email de prueba
echo "<h2>Prueba de Envío</h2>";

if (defined('SMTP_CREDENTIALS_CONFIGURED') && SMTP_CREDENTIALS_CONFIGURED && !SMTP_NOT_CONFIGURED) {
    echo "<form method='POST'>";
    echo "<p>";
    echo "Email de prueba: <input type='email' name='test_email' required> ";
    echo "<button type='submit'>Enviar Prueba</button>";
    echo "</p>";
    echo "</form>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['test_email'])) {
        require_once __DIR__ . '/email_config.php';
        
        $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
        if (!$test_email) {
            echo "<div class='check err'><strong class='error'>❌ Email inválido</strong></div>";
        } else {
            $subject = 'Prueba de correo desde Clínica Nutricional';
            $body = '<h2>Correo de Prueba ✅</h2><p>Este es un email de prueba para verificar que tu configuración de SMTP funciona correctamente.</p>';
            
            $result = enviarCorreo($test_email, $subject, $body);
            
            echo "<div class='check " . ($result['success'] ? 'ok' : 'warn') . "'>";
            if ($result['success']) {
                echo "<strong class='success'>✅ Email enviado exitosamente</strong>";
                echo "<br>Verifica tu bandeja: <code>" . $test_email . "</code>";
            } else {
                echo "<strong class='warning'>⚠️ Email encolado</strong>";
                echo "<br>Razón: " . htmlspecialchars($result['error']);
                echo "<br><small>Ejecuta: <code>php process_email_queue.php</code></small>";
            }
            echo "</div>";
        }
    }
} else {
    echo "<div class='check err'>";
    echo "<strong class='error'>No se puede probar - Credenciales no configuradas</strong>";
    echo "</div>";
}

// 8. Resumen
echo "<h2>Próximos Pasos</h2>";
echo "<ol>";
if (!defined('SMTP_CREDENTIALS_CONFIGURED') || !SMTP_CREDENTIALS_CONFIGURED || SMTP_NOT_CONFIGURED) {
    echo "<li>Edita <code>email_credentials.php</code></li>";
    echo "<li>Descomenta una de las 3 opciones (MAILTRAP, GMAIL, u otro)</li>";
    echo "<li>Guarda el archivo</li>";
}
if (file_exists($queueFile)) {
    echo "<li>Ejecuta: <code>php process_email_queue.php</code> para procesar correos encolados</li>";
}
echo "<li>Lee <code>CONFIGURAR_CORREOS.md</code> para más detalles</li>";
echo "</ol>";

echo "<hr>";
echo "<small>Para más ayuda, lee: <a href='CONFIGURAR_CORREOS.md'>CONFIGURAR_CORREOS.md</a></small>";
echo "</div>";
echo "</body></html>";
