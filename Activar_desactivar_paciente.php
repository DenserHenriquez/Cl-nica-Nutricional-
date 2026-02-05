<?php
session_start();
require_once 'db_connection.php';

// Obtener el rol del usuario actual
$userRole = $_SESSION['rol'] ?? 'Paciente';

// Verificar que la columna Rol exista en la tabla usuarios
$checkRol = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'Rol'");
if ($checkRol->num_rows === 0) {
    $conexion->query("ALTER TABLE usuarios ADD COLUMN Rol VARCHAR(20) NOT NULL DEFAULT 'Paciente'");
}

// CONSULTA PACIENTES + USUARIOS (incluir Rol)
$sql = "SELECT p.id_pacientes, p.id_usuarios, p.nombre_completo, p.DNI, p.fecha_nacimiento, p.edad, p.telefono, p.estado,
               u.Nombre_completo as usuario_nombre, u.Correo_electronico, u.Rol
        FROM pacientes p
        INNER JOIN usuarios u ON p.id_usuarios = u.id_usuarios
        ORDER BY p.nombre_completo ASC";

$resultado = $conexion->query($sql);
if (!$resultado) {
    die("Error en la consulta SQL: " . $conexion->error);
}
$total_entradas = $resultado->num_rows;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lista de Usuarios - Nutrición</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .btn-primary {
            background-color: #198754;
            border-color: #198754;
        }
        .btn-primary:hover {
            background-color: #146c43;
            border-color: #13653f;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .alert {
            border-radius: 0.375rem;
        }
        .header-section {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .header-section p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .medical-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ffffff;
        }
        .estado-text { font-weight: bold; }
        .estado-Activo { color: #0d5132; }
        .estado-Inactivo { color: #d32f2f; }
        
        /* Filtros Style */
        .filtros-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filtros-titulo {
            font-size: 1.1rem;
            font-weight: 600;
            color: #198754;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            align-items: end;
        }
        .filtro-grupo {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filtro-grupo label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
        }
        .filtro-grupo input,
        .filtro-grupo select {
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        .filtro-botones {
            display: flex;
            gap: 8px;
        }
        .filtro-botones button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-filtrar {
            background: #198754;
            color: white;
        }
        .btn-filtrar:hover {
            background: #146c43;
        }
        .btn-limpiar {
            background: #e9ecef;
            color: #495057;
        }
        .btn-limpiar:hover {
            background: #dee2e6;
        }
        @media (max-width: 768px) {
            .filtros-grid {
                grid-template-columns: 1fr;
            }
            .filtro-botones {
                flex-direction: column;
                width: 100%;
            }
            .filtro-botones button {
                width: 100%;
            }
        }

        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top:0; left:0; right:0; bottom:0; background-color:#ccc; transition:0.4s; border-radius:24px; }
        .slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:white; transition:0.4s; border-radius:50%; }
        input:checked + .slider { background-color:#198754; }
        input:not(:checked) + .slider { background-color: #d32f2f; }
        input:checked + .slider:before { transform: translateX(26px); }

        /* Responsive table styling */
        .table-responsive {
            overflow-x: auto;
        }
        
        /* Columnas prioritarias - siempre visibles */
        .col-nombre, .col-acciones {
            min-width: 150px;
            position: sticky;
        }
        
        .col-nombre {
            left: 0;
            background: white;
            z-index: 2;
        }
        
        .col-acciones {
            right: 0;
            background: white;
            z-index: 2;
            min-width: 180px;
        }
        
        /* Ocultar columnas menos importantes en pantallas pequeñas */
        @media (max-width: 1200px) {
            .col-hide-lg {
                display: none;
            }
        }
        
        @media (max-width: 992px) {
            .col-hide-md {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .col-hide-sm {
                display: none;
            }
            
            .col-nombre {
                min-width: 120px;
            }
            
            .col-acciones {
                min-width: 160px;
            }
        }
        
        @media (max-width: 576px) {
            .col-hide-xs {
                display: none;
            }
            
            .col-nombre {
                min-width: 100px;
            }
            
            .col-acciones {
                min-width: 140px;
            }
            
            .header-section h1 {
                font-size: 1.8rem;
            }
            
            .header-section p {
                font-size: 0.9rem;
            }
        }
        
        /* Botones más compactos en móviles */
        @media (max-width: 576px) {
            .btn-sm {
                padding: 0.2rem 0.4rem;
                font-size: 0.8rem;
            }
            
            .switch {
                width: 40px;
                height: 20px;
            }
            
            .slider:before {
                height: 14px;
                width: 14px;
                left: 3px;
                bottom: 3px;
            }
            
            input:checked + .slider:before {
                transform: translateX(20px);
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <h1>Lista de Usuarios</h1>
            <p>Administra el estado de los usuarios registrados en la clínica nutricional.</p>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Filtros -->
        <div class="filtros-card">
            <div class="filtros-titulo">
                <i class="bi bi-funnel"></i>Filtros de Usuario
            </div>
            <div class="filtros-grid">
                <div class="filtro-grupo">
                    <label for="filterNombre">
                        <i class="bi bi-search"></i> Nombre
                    </label>
                    <input type="text" id="filterNombre" class="form-control" placeholder="Buscar por nombre...">
                </div>
                <div class="filtro-grupo">
                    <label for="filterEstado">
                        <i class="bi bi-check-circle"></i> Estado
                    </label>
                    <select id="filterEstado" class="form-select">
                        <option value="">Todos</option>
                        <option value="Activo">Activo</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>
                <?php if($userRole === 'Administrador'): ?>
                <div class="filtro-grupo">
                    <label for="filterRol">
                        <i class="bi bi-person-badge"></i> Rol
                    </label>
                    <select id="filterRol" class="form-select">
                        <option value="">Todos</option>
                        <option value="Paciente">Paciente</option>
                        <option value="Medico">Médico</option>
                        <option value="Administrador">Administrador</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filtro-botones">
                    <button class="btn-filtrar" onclick="aplicarFiltros()">
                        <i class="bi bi-search me-1"></i>Filtrar
                    </button>
                    <button class="btn-limpiar" onclick="limpiarFiltros()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Limpiar
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0"><i class="bi bi-list-check me-2"></i>Usuarios</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover enhance-table external-filter" id="usuariosTable">
                        <thead class="table-success">
                            <tr>
                                <th class="col-hide-sm">ID Paciente</th>
                                <th class="col-hide-md">ID Usuario</th>
                                <th class="col-nombre">Nombre Completo</th>
                                <th class="col-hide-xs">DNI</th>
                                <th class="col-hide-lg">Fecha Nac.</th>
                                <th class="col-hide-md">Edad</th>
                                <th class="col-hide-sm">Teléfono</th>
                                <th class="col-hide-lg">Usuario</th>
                                <th class="col-hide-lg">Correo</th>
                                <?php if($userRole === 'Administrador'): ?>
                                <th class="col-hide-md">Rol</th>
                                <?php endif; ?>
                                <th class="col-hide-xs">Estado</th>
                                <th class="col-acciones">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        if ($total_entradas > 0) {
                            while($fila = $resultado->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='col-hide-sm'>".htmlspecialchars($fila['id_pacientes'])."</td>";
                                echo "<td class='col-hide-md'>".htmlspecialchars($fila['id_usuarios'])."</td>";
                                echo "<td class='col-nombre'><strong>".htmlspecialchars($fila['nombre_completo'])."</strong></td>";
                                echo "<td class='col-hide-xs'>".htmlspecialchars($fila['DNI'])."</td>";
                                echo "<td class='col-hide-lg'>".htmlspecialchars($fila['fecha_nacimiento'])."</td>";
                                echo "<td class='col-hide-md'>".htmlspecialchars($fila['edad'])."</td>";
                                echo "<td class='col-hide-sm'>".htmlspecialchars($fila['telefono'])."</td>";
                                echo "<td class='col-hide-lg'>".htmlspecialchars($fila['usuario_nombre'])."</td>";
                                echo "<td class='col-hide-lg'>".htmlspecialchars($fila['Correo_electronico'])."</td>";
                                
                                // Columna Rol (solo visible para Administrador)
                                if($userRole === 'Administrador') {
                                    $rolActual = htmlspecialchars($fila['Rol']);
                                    echo "<td class='col-hide-md'>
                                        <select class='form-select form-select-sm rol-select' data-usuario-id='".$fila['id_usuarios']."' style='min-width: 100px;'>
                                            <option value='Paciente' ".($rolActual=='Paciente'?'selected':'').">👤 Paciente</option>
                                            <option value='Medico' ".($rolActual=='Medico'?'selected':'').">👨‍⚕️ Medico</option>
                                        </select>
                                    </td>";
                                }
                                
                                echo "<td class='estado-text estado-".htmlspecialchars($fila['estado'])." col-hide-xs'>".htmlspecialchars($fila['estado'])."</td>";
                                echo "<td class='col-acciones'>
                            <div class='d-flex align-items-center gap-1'>
                                <!-- Panel evolución -->
                                <a href='panelevolucionpaciente.php?id=".htmlspecialchars($fila['id_pacientes'])."' class='btn btn-outline-info btn-sm' title='Panel evolución'>
                                    <i class='bi bi-bar-chart-line'></i>
                                </a>
                                <!-- Actualizar Perfil -->
                                <a href='Actualizar_perfil.php?id=".htmlspecialchars($fila['id_usuarios'])."' class='btn btn-outline-primary btn-sm' title='Actualizar perfil'>
                                    <i class='bi bi-person-gear'></i>
                                </a>
                                <!-- Eliminar (usa modal personalizado) -->
                                <button data-id='".htmlspecialchars($fila['id_pacientes'])."' class='btn btn-outline-danger btn-sm btn-delete-paciente' title='Eliminar'>
                                    <i class='bi bi-trash'></i>
                                </button>
                                <!-- Activar/Desactivar -->
                                <label class='switch' title='Activar/Desactivar'>
                                    <input type='checkbox' class='estado-switch'
                                           data-id='".$fila['id_pacientes']."'
                                           ".(($fila['estado']=='Activo')?'checked':'').">
                                    <span class='slider round'></span>
                                </label>
                            </div>
                        </td>";
                                echo "</tr>";
                            }
                        } else {
                            $colspan = ($userRole === 'Administrador') ? 12 : 11;
                            echo "<tr><td colspan='$colspan' class='text-center'>No se encontraron pacientes.</td></tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
// Filtros avanzados
function aplicarFiltros() {
    const nombre = document.getElementById('filterNombre').value.toLowerCase();
    const estado = document.getElementById('filterEstado').value;
    const filterRol = document.getElementById('filterRol');
    const rol = filterRol ? filterRol.value : '';
    
    const tabla = document.getElementById('usuariosTable');
    const filas = tabla.querySelectorAll('tbody tr');
    
    filas.forEach(fila => {
        const nombreCompleto = fila.querySelector('.col-nombre').textContent.toLowerCase();
        const estadoFila = fila.querySelector('.estado-text').textContent.trim();
        const selectRol = fila.querySelector('.rol-select');
        const rolFila = selectRol ? selectRol.value : '';
        
        let mostrar = true;
        
        // Filtro nombre
        if (nombre && !nombreCompleto.includes(nombre)) {
            mostrar = false;
        }
        
        // Filtro estado
        if (estado && estadoFila !== estado) {
            mostrar = false;
        }
        
        // Filtro rol
        if (rol && rolFila !== rol) {
            mostrar = false;
        }
        
        fila.style.display = mostrar ? '' : 'none';
    });
}

function limpiarFiltros() {
    document.getElementById('filterNombre').value = '';
    document.getElementById('filterEstado').value = '';
    const filterRol = document.getElementById('filterRol');
    if (filterRol) filterRol.value = '';
    
    // Mostrar todas las filas
    const tabla = document.getElementById('usuariosTable');
    const filas = tabla.querySelectorAll('tbody tr');
    filas.forEach(fila => {
        fila.style.display = '';
    });
}

// Permitir Enter en los inputs de filtro
document.getElementById('filterNombre').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') aplicarFiltros();
});

// Switch de estado (Activo/Inactivo)
document.querySelectorAll('.estado-switch').forEach(function(switchEl) {
    switchEl.addEventListener('change', function() {
        const id = this.dataset.id;
        const estado = this.checked ? 'Activo' : 'Inactivo';
        const tdEstado = this.closest('tr').querySelector('.estado-text');

        // AJAX POST
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "cambiar_estado_paciente.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.onload = function() {
            if(xhr.status === 200) {
                tdEstado.textContent = estado; // Actualizar columna Estado
                tdEstado.className = 'estado-text estado-' + estado; // Actualizar clase para color
            } else {
                alert("Error al cambiar estado del paciente");
                // Revertir switch si falla
                switchEl.checked = !switchEl.checked;
            }
        };
        xhr.send("id=" + id + "&estado=" + estado);
    });
});

// Select de rol (Paciente/Medico) - Solo para Administrador
document.querySelectorAll('.rol-select').forEach(function(selectEl) {
    selectEl.addEventListener('change', function() {
        const idUsuario = this.dataset.usuarioId;
        const nuevoRol = this.value;
        const selectOriginal = this;
        const valorAnterior = selectOriginal.getAttribute('data-current-role') || (Array.from(selectOriginal.options).find(opt => opt.defaultSelected) || {}).value;

        // Mostrar modal estético de confirmación
        showConfirm('¿Cambiar el rol de este usuario a ' + nuevoRol + '?').then(function(confirmed) {
            if (!confirmed) {
                // Revertir selección si cancela
                if (valorAnterior) selectOriginal.value = valorAnterior;
                return;
            }

            // AJAX POST a cambiar_rol_usuario.php
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "cambiar_rol_usuario.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhr.onload = function() {
                if(xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if(response.success) {
                            // Actualizar data-current-role para futuras reversiones
                            selectOriginal.setAttribute('data-current-role', nuevoRol);
                            showToast('Rol actualizado exitosamente a ' + nuevoRol, 'success');
                        } else {
                            showToast('Error: ' + response.message, 'danger');
                            if(valorAnterior) selectOriginal.value = valorAnterior;
                        }
                    } catch(e) {
                        showToast('Error al procesar respuesta del servidor', 'danger');
                        if(valorAnterior) selectOriginal.value = valorAnterior;
                    }
                } else {
                    showToast('Error al cambiar el rol del usuario', 'danger');
                    if(valorAnterior) selectOriginal.value = valorAnterior;
                }
            };
            xhr.onerror = function() {
                showToast('Error de conexión', 'danger');
                if(valorAnterior) selectOriginal.value = valorAnterior;
            };
            xhr.send("id_usuarios=" + encodeURIComponent(idUsuario) + "&rol=" + encodeURIComponent(nuevoRol));
        });
    });
});

// --- Custom confirmation modal and helpers ---
function showConfirm(message) {
    return new Promise(function(resolve) {
        const modal = document.getElementById('confirmModal');
        const msg = document.getElementById('confirmModalMessage');
        const btnConfirm = document.getElementById('confirmModalOk');
        const btnCancel = document.getElementById('confirmModalCancel');

        msg.textContent = message;
        modal.classList.add('show');

        function cleanup() {
            modal.classList.remove('show');
            btnConfirm.removeEventListener('click', onOk);
            btnCancel.removeEventListener('click', onCancel);
        }

        function onOk() { cleanup(); resolve(true); }
        function onCancel() { cleanup(); resolve(false); }

        btnConfirm.addEventListener('click', onOk);
        btnCancel.addEventListener('click', onCancel);
    });
}

function showToast(message, type) {
    // Simple temporary toast in top-right corner
    const toast = document.createElement('div');
    toast.className = 'custom-toast bg-' + (type === 'success' ? 'success' : 'danger');
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.add('visible'); }, 10);
    setTimeout(() => { toast.classList.remove('visible'); setTimeout(() => toast.remove(), 300); }, 3500);
}

// Attach delete handlers that use modal
document.querySelectorAll('.btn-delete-paciente').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        showConfirm('¿Eliminar paciente?').then(function(ok) {
            if (ok) {
                window.location.href = 'eliminar_paciente.php?id=' + encodeURIComponent(id);
            }
        });
    });
});
</script>

<script src="assets/js/script.js"></script>

<!-- Confirm modal markup (floating box) -->
<div id="confirmModal" class="confirm-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="confirm-modal__backdrop" onclick="document.getElementById('confirmModal').classList.remove('show')"></div>
    <div class="confirm-modal__box">
        <div class="confirm-modal__icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="confirm-modal__content">
            <p id="confirmModalMessage">¿Confirmar?</p>
            <div class="confirm-modal__actions">
                <button id="confirmModalCancel" class="btn btn-secondary">Cancelar</button>
                <button id="confirmModalOk" class="btn btn-danger">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal styles */
.confirm-modal { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 2147483647; }
.confirm-modal__backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.45); opacity: 0; transition: opacity .18s ease; }
.confirm-modal__box { position: relative; width: 100%; max-width: 420px; background: #fff; border-radius: 12px; box-shadow: 0 12px 40px rgba(2,6,23,0.35); transform: translateY(12px) scale(.98); opacity: 0; transition: all .18s ease; display:flex; gap:12px; padding:18px; align-items: center; }
.confirm-modal__icon { font-size: 2.2rem; color: #f6c23e; flex: 0 0 48px; display:flex; align-items:center; justify-content:center; }
.confirm-modal__content p { margin: 0 0 12px 0; font-weight:600; color:#222; }
.confirm-modal__actions { display:flex; gap:8px; justify-content:flex-end; }
.confirm-modal.show { pointer-events: auto; }
.confirm-modal.show .confirm-modal__backdrop { opacity: 1; }
.confirm-modal.show .confirm-modal__box { opacity: 1; transform: translateY(0) scale(1); }

/* Toast */
.custom-toast { position: fixed; top: 20px; right: 20px; padding: 10px 14px; border-radius: 8px; color: #fff; font-weight:600; opacity:0; transform: translateY(-6px); transition: all .25s ease; z-index: 9999; }
.custom-toast.bg-success { background: #198754; }
.custom-toast.bg-danger { background: #d32f2f; }
.custom-toast.visible { opacity: 1; transform: translateY(0); }
</style>

</body>
</html>

<?php $conexion->close(); ?>