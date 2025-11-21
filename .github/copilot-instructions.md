# Copilot Instructions for Clínica Nutricional

## Project Overview
This is a PHP-based web application for managing a nutritional clinic. The codebase is organized as a set of standalone PHP scripts, each handling a specific feature (e.g., patient registration, appointment scheduling, food classification, user management).

## Key Components
- **Authentication & User Management**: `Login.php`, `cambiar_rol_usuario.php`, `Actualizar_perfil.php`, `Activar_desactivar_paciente.php`
- **Patient Management**: `Registropacientes.php`, `Listar_pacientes.php`, `eliminar_paciente.php`, `cambiar_estado_paciente.php`
- **Appointments**: `citas_medico.php`, `Disponibilidad_citas.php`
- **Nutrition & Food**: `Clasificacion_alimentos.php`, `Resgistro_Alimentos.php`, `Crear_Receta.php`, `Exportar_Receta.php`, `Gestion_Receta.php`
- **Tracking & Feedback**: `panelevolucionpaciente.php`, `Seguimiento_ejercicio.php`, `retroalimentacion1.php`
- **Database Connection**: Centralized in `db_connection.php`. All scripts requiring DB access should include this file.
- **Assets**: Static files (CSS, JS, images) are under `assets/`. Uploaded files (e.g., exercise images) are in `uploads/`.
- **SQL Schema**: See `BD/clinica1.sql` and `Tablas Clinica Nutri.txt` for database structure.

## Patterns & Conventions
- **Single-Responsibility Scripts**: Each PHP file implements a single feature or endpoint. Navigation is handled via links/forms between these scripts.
- **No Framework**: This is a custom PHP project, not using Laravel, Symfony, or similar frameworks.
- **Session Management**: User sessions are managed manually in scripts like `Login.php`.
- **Direct SQL**: Database queries are written directly in PHP using `mysqli` or `PDO` (check `db_connection.php`).
- **Minimal Frontend Frameworks**: UI is built with plain HTML/CSS/JS. Styles are in `assets/css/estilos.css`.

## Developer Workflows
- **Local Development**: Use XAMPP or similar LAMP stack. Place the project in the `htdocs` directory.
- **Database Setup**: Import `BD/clinica1.sql` into MySQL. Update DB credentials in `db_connection.php` as needed.
- **Testing**: No automated test suite is present. Manual testing is done via browser.
- **Debugging**: Use `test_connection.php` to verify DB connectivity. Add `var_dump`/`echo` for debugging.
- **Adding Features**: Create a new PHP file for each new feature. Include `db_connection.php` for DB access. Follow the single-responsibility pattern.

## Integration Points
- **Database**: All persistent data is stored in MySQL. See SQL files for schema.
- **Uploads**: User-uploaded files are stored in `uploads/`.
- **No External APIs**: The project does not currently integrate with third-party APIs.

## Examples
- To add a new patient, see `Registropacientes.php` for form handling and DB insertion.
- To list patients, see `Listar_pacientes.php` for DB queries and table rendering.
- For user login, see `Login.php` for session and credential checks.

## Recommendations for AI Agents
- Always include `db_connection.php` for DB access.
- Follow the single-feature-per-file pattern.
- Reference existing scripts for similar features before adding new code.
- Use relative paths for includes and assets.
- Keep UI changes in `assets/css/estilos.css`.

---
If any conventions or workflows are unclear, please request clarification or examples from the user.
