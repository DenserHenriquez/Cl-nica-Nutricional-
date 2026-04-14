<?php
/**
 * ✅ CONFIGURAR AQUÍ TUS CREDENCIALES DE CORREO
 * 
 * Este archivo es SEPARADO de email_config.php para facilitar cambios de credenciales
 * sin tocar la lógica principal.
 */

// === OPCIÓN 1: TESTING CON MAILTRAP (RECOMENDADO PARA DESARROLLO) ===
// Regístrate GRATIS: https://mailtrap.io/register
// Copia credenciales de: Inbox → SMTP Settings
// 
// Luego descomenta las líneas abajo y reemplaza con TUS credenciales:

/*
define('SMTP_CREDENTIALS_CONFIGURED', true);
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_PORT', 2525);
define('SMTP_USER', 'REEMPLAZA_CON_TU_USUARIO');      // ← Tu usuario de Mailtrap
define('SMTP_PASS', 'REEMPLAZA_CON_TU_PASSWORD');     // ← Tu password de Mailtrap
*/

// === OPCIÓN 2: GMAIL REAL (PRODUCCIÓN) ===
// 1. Activa 2FA en: https://myaccount.google.com/security
// 2. Genera App Password en: https://myaccount.google.com/apppasswords
//
// Luego descomenta y reemplaza:

define('SMTP_CREDENTIALS_CONFIGURED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu-email@gmail.com');              // ← Tu email de Gmail
define('SMTP_PASS', 'tu-app-password-16-caracteres');   // ← Tu App Password (16 caracteres)

// === OPCIÓN 3: OTRO SERVICIO (Sendgrid, AWS, etc) ===
// Reemplaza HOST, PORT, USER, PASS con tus credenciales

/*
define('SMTP_CREDENTIALS_CONFIGURED', true);
define('SMTP_HOST', 'smtp.sendgrid.net');
define('SMTP_PORT', 587);
define('SMTP_USER', 'apikey');
define('SMTP_PASS', 'SG.xxxxxxxxxxxx');
*/

// ⚠️ Si no configuras nada, los correos se encolarán y NO se enviarán
// Los correos encolados se guardan en: email_queue.json

// Email remitente (aparecerá en los correos)
define('FROM_EMAIL', 'clinica-nutricional@ejemplo.com');
define('FROM_NAME', 'Clínica Nutricional');

// Por defecto, si no se configuran credenciales
if (!defined('SMTP_CREDENTIALS_CONFIGURED')) {
    define('SMTP_CREDENTIALS_CONFIGURED', false);
}
