# 📧 Configurar Envío de Correos - Clínica Nutricional

## ❌ Problema Actual
Los correos se están encolando pero **NO se envían** porque:
- ❌ Las credenciales SMTP son placeholders `your-mailtrap-username`
- ❌ PHPMailer no puede autenticarse
- ❌ Los correos quedan guardados en `email_queue.json` sin ser procesados

## ✅ Solución - 3 Pasos

### PASO 1️⃣: Elegir un servicio de correo

#### **Opción A: MAILTRAP (RECOMENDADO para desarrollo)**
- ✅ Gratuito (free tier)
- ✅ Seguro para testing (los correos no se envían a direcciones reales)
- ✅ Puedes ver todos los correos en el dashboard

1. Ve a: https://mailtrap.io/register
2. Crea una cuenta (es gratis)
3. Inicia sesión y ve a **Inbox → SMTP Settings**
4. Copia el usuario y password

---

#### **Opción B: GMAIL (Para producción)**
1. Ve a: https://myaccount.google.com/security
2. Activa **2-Step Verification** (2FA)
3. Luego ve a: https://myaccount.google.com/apppasswords
4. Genera un **App Password** (16 caracteres)
5. Copia email y app password

---

#### **Opción C: Otro servicio (Sendgrid, AWS SES, etc)**
- Sigue instrucciones del proveedor
- Obtén: Host, Port, Usuario, Password

---

### PASO 2️⃣: Configurar en el archivo `email_credentials.php`

**Abre el archivo:** `email_credentials.php`

Busca la sección que dice `// === OPCIÓN 1: TESTING CON MAILTRAP ===` 

**Si estás usando MAILTRAP:**
```php
define('SMTP_CREDENTIALS_CONFIGURED', true);
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_PORT', 2525);
define('SMTP_USER', 'TU_USUARIO_MAILTRAP');      // Reemplaza aquí
define('SMTP_PASS', 'TU_PASSWORD_MAILTRAP');     // Reemplaza aquí
```

**Si estás usando GMAIL:**
```php
define('SMTP_CREDENTIALS_CONFIGURED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu-email@gmail.com');              // Tu email
define('SMTP_PASS', 'tu-app-password-16-caracteres');   // Tu app password
```

💾 **Guarda el archivo**

---

### PASO 3️⃣: Procesar correos encolados

Ahora que configuraste credenciales, ejecuta:

```bash
php process_email_queue.php
```

Este comando:
- ✅ Lee los correos encolados en `email_queue.json`
- ✅ Los intenta enviar hasta 5 veces
- ✅ Genera logs en `email_queue_processed.log`

---

## 🔄 Configurar envío automático

Para que se procese la cola automáticamente cada 5 minutos:

### **Linux/Mac - Agregar a CRON**
```bash
crontab -e
```

Pega esta línea al final:
```
*/5 * * * * php /ruta/completa/a/process_email_queue.php >> /tmp/email_queue.log 2>&1
```

(Reemplaza `/ruta/completa/a/` con la ruta real, ej: `/home/usuario/public_html/clinica/`)

### **Windows - Usar Task Scheduler**
1. Abre **Task Scheduler**
2. **Create Basic Task**
3. Name: `Process Email Queue`
4. Trigger: **Daily** → Repeat every 5 minutes
5. Action: **Start a Program**
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\Clinica-Nutricional-\process_email_queue.php`

---

## ✅ Verificar que funciona

### 1️⃣ Confirmar una cita nueva
- Ve a Gestión de Citas
- Confirma una cita

### 2️⃣ Revisar archivos generados

**Si se envió correctamente:**
- ✅ Abre `email_queue_processed.log` → Debe decir `✅ Enviado exitosamente`
- ✅ `email_queue.json` → Debe estar vacío o no existir

**Si hay error:**
- 📄 Abre `email_errors.log` → Revisa el error
- 📄 Abre `email_queue.json` → Los correos están en espera

### 3️⃣ Verificar en el proveedor

**Si usas MAILTRAP:**
- Ve a https://mailtrap.io
- Inbox → Debes ver el correo
- ✅ Comprueba que llegó correctamente

**Si usas GMAIL:**
- El correo debe llegar a la bandeja del paciente

---

## 🐛 Solucionar problemas

### Problema: "Invalid address: (From): your-mailtrap-username"
**Solución:** Descomenta solo UNA de las 3 opciones en `email_credentials.php`

### Problema: "SMTP Error: Could not authenticate"
**Solución:** Verifica que hayas copiado correctamente:
- 🔍 Sin espacios extras al inicio o final
- 🔍 Contraseña correcta
- 🔍 Usuario correcto
- 🔍 Service correcto (MAILTRAP vs GMAIL)

### Problema: Los correos siguen encolados
**Solución:** Ejecuta manualmente:
```bash
php process_email_queue.php
```

Y revisa la salida por errores.

### Problema: `email_errors.log` muestra PHP Warning
**Solución:** Esto es normal en desarrollo. Los correos se siguen encolando correctamente.

---

## 📋 Checklist Final

- [ ] Abri `email_credentials.php`
- [ ] Puse mis credenciales (MAILTRAP o GMAIL)
- [ ] Guardé el archivo
- [ ] Ejecuté `php process_email_queue.php` (o espéré 5 min para CRON)
- [ ] Verifiqué que los correos se enviaron
- [ ] Configué CRON o Task Scheduler para procesamiento automático

---

## 💡 Preguntas frecuentes

**P: ¿Es seguro configurar las credenciales en `email_credentials.php`?**
R: Sí. A diferencia de `email_config.php`, este archivo es solo para credenciales. No lo subas a GitHub (excepto .gitignore).

**P: ¿Puedo cambiar de MAILTRAP a GMAIL en el futuro?**
R: Sí. Solo edita `email_credentials.php` y reinicia los servicios.

**P: ¿Qué pasa si cierro PHP?**
R: Los correos se guardan en `email_queue.json` y se procesarán cuando vuelvas a ejecutar `process_email_queue.php`.

**P: ¿Puedo usar otro servicio que no sea MAILTRAP o GMAIL?**
R: Sí. Busca en `email_credentials.php` la sección "OPCIÓN 3" y configura con tus datos.

---

**Listo! 🎉 Ahora los correos deberían funcionar correctamente.**
