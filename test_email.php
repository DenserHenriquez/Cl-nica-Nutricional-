<?php
require_once __DIR__ . '/email_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = trim($_POST['test_email'] ?? '');
    if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $result = enviarCorreoConfirmacionCita($test_email, 'Test Paciente', '15/03/2025 10:00', 'Dr Test');
        $msg = $result['success'] ? '✅ Enviado' : '❌ ' . $result['error'];
        if (isset($result['queued'])) $msg .= ' (en cola)';
    } else {
        $msg = 'Email inválido';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Email</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>🔧 Test Envío de Email</h1>
    <form method="POST">
        <label>Email de prueba: </label>
        <input type="email" name="test_email" required style="padding:8px;width:300px;">
        <button type="submit" style="padding:8px 16px;background:#198754;color:white;border:none;border-radius:4px;cursor:pointer;">Enviar Test</button>
    </form>
    <?php if (isset($msg)): ?>
        <div style="margin-top:20px;padding:15px;border-radius:6px;<?php echo $result['success'] ? 'background:#d4edda;color:#155724' : 'background:#f8d7da;color:#721c24'; ?>">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>
    <h3>Instrucciones:</h3>
    <ul>
        <li>Edita email_config.php con tus credenciales SMTP (líneas 12-17)</li>
        <li>Ejecuta test</li>
        <li>Para cola: php process_email_queue.php</li>
    </ul>
    <p><a href="diagnose_mail.php">Diagnóstico PHPMailer</a></p>
</body>
</html>

