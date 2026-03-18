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
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery + DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .header-section {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
            padding: 0.8rem 0;
            margin-bottom: 1rem;
        }
        .header-section h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0.15rem 0 0.25rem;
        }
        .header-section p {
            font-size: 1.05rem;
            opacity: 0.95;
            margin: 0;
        }
        .medical-icon {
            font-size: 1.9rem;
            margin-bottom: 0.35rem;
            color: #ffffff;
        }
        .estado-text { font-weight: bold; }
        .estado-Activo { color: #0d5132; }
        .estado-Inactivo { color: #d32f2f; }

        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top:0; left:0; right:0; bottom:0; background-color:#ccc; transition:0.4s; border-radius:24px; }
        .slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:white; transition:0.4s; border-radius:50%; }
        input:checked + .slider { background-color:#198754; }
        input:not(:checked) + .slider { background-color: #d32f2f; }
        input:checked + .slider:before { transform: translateX(26px); }

        /* DataTables customization */
        .dataTables_wrapper .dataTables_length select {
            min-width: 70px;
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 5px 10px;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25,135,84,.25);
            outline: none;
        }
        .dataTables_wrapper .dataTables_info {
            color: #6c757d;
            font-size: 0.875rem;
            padding-top: 0.75rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #198754 !important;
            border-color: #198754 !important;
            color: #fff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e8f5e9 !important;
            border-color: #c8e6c9 !important;
            color: #0d5132 !important;
        }
        table.dataTable thead th {
            border-bottom: 2px solid #198754;
            white-space: nowrap;
        }
        table.dataTable tbody td {
            white-space: nowrap;
        }
        table.dataTable {
            width: 100% !important;
        }
        table.dataTable.table-striped > tbody > tr.odd > * {
            box-shadow: inset 0 0 0 9999px rgba(25,135,84,.04);
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        /* Filtros inline encima de la tabla */
        .dt-custom-filters {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .dt-custom-filters label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #495057;
            margin-right: 4px;
        }
        .dt-custom-filters select {
            font-size: 0.875rem;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .header-section h1 { font-size: 1.8rem; }
            .header-section p { font-size: 0.9rem; }
            .switch { width: 40px; height: 20px; }
            .slider:before { height: 14px; width: 14px; }
            input:checked + .slider:before { transform: translateX(20px); }
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

    <div class="container-fluid mb-5 px-4">

        <div class="card">
            <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="bi bi-list-check me-2"></i>Usuarios</h5>
                <span class="badge bg-light text-success"><?= $total_entradas; ?> registros</span>
            </div>
            <div class="card-body">
                <!-- Filtros extra (Estado / Rol) se inyectan por JS dentro del wrapper de DataTables -->
                <div id="customFilters" class="dt-custom-filters mb-3" style="display:none;">
                    <div>
                        <label><i class="bi bi-bookmark-check me-1"></i>Estado:</label>
                        <select id="filterEstado" class="form-select form-select-sm d-inline-block" style="width:auto;">
                            <option value="">Todos</option>
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                    <?php if($userRole === 'Administrador' || $userRole === 'Medico'): ?>
                    <div>
                        <label><i class="bi bi-shield-check me-1"></i>Rol:</label>
                        <select id="filterRol" class="form-select form-select-sm d-inline-block" style="width:auto;">
                            <option value="">Todos</option>
                            <option value="Paciente">Paciente</option>
                            <option value="Medico">Medico</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="usuariosTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID Pac.</th>
                                <th>ID Usr.</th>
                                <th>Nombre Completo</th>
                                <th>DNI</th>
                                <th>Fecha Nac.</th>
                                <th>Edad</th>
                                <th>Teléfono</th>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <?php if($userRole === 'Administrador' || $userRole === 'Medico'): ?>
                                <th>Rol</th>
                                <?php endif; ?>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        if ($total_entradas > 0) {
                            while($fila = $resultado->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>".htmlspecialchars($fila['id_pacientes'])."</td>";
                                echo "<td>".htmlspecialchars($fila['id_usuarios'])."</td>";
                                echo "<td><strong>".htmlspecialchars($fila['nombre_completo'])."</strong></td>";
                                echo "<td>".htmlspecialchars($fila['DNI'])."</td>";
                                echo "<td>".htmlspecialchars($fila['fecha_nacimiento'])."</td>";
                                echo "<td>".htmlspecialchars($fila['edad'])."</td>";
                                echo "<td>".htmlspecialchars($fila['telefono'])."</td>";
                                echo "<td>".htmlspecialchars($fila['usuario_nombre'])."</td>";
                                echo "<td>".htmlspecialchars($fila['Correo_electronico'])."</td>";
                                
                                // Columna Rol (visible para Administrador y Médico, pero solo modificable por Administrador)
                                if($userRole === 'Administrador' || $userRole === 'Medico') {
                                    $rolActual = htmlspecialchars($fila['Rol']);
                                    if($userRole === 'Administrador') {
                                        echo "<td>
                                            <select class='form-select form-select-sm rol-select' data-usuario-id='".$fila['id_usuarios']."' style='min-width: 100px;'>
                                                <option value='Paciente' ".($rolActual=='Paciente'?'selected':'').">Paciente</option>
                                                <option value='Medico' ".($rolActual=='Medico'?'selected':'').">Medico</option>
                                            </select>
                                        </td>";
                                    } else {
                                        echo "<td><span class='badge bg-info'>".$rolActual."</span></td>";
                                    }
                                }
                                
                                echo "<td class='estado-text estado-".htmlspecialchars($fila['estado'])."'>".htmlspecialchars($fila['estado'])."</td>";
                                echo "<td>
                            <div class='d-flex align-items-center gap-1 flex-nowrap'>
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
                            $colspan = ($userRole === 'Administrador' || $userRole === 'Medico') ? 12 : 11;
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
$(document).ready(function() {
    // Columna de Estado y Rol (índices varían según rol del usuario)
    var hasRolCol = <?= ($userRole === 'Administrador' || $userRole === 'Medico') ? 'true' : 'false'; ?>;
    var estadoColIdx = hasRolCol ? 10 : 9;
    var rolColIdx = 9;
    var accionColIdx = hasRolCol ? 11 : 10;

    var table = $('#usuariosTable').DataTable({
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
        order: [[2, 'asc']], // Ordenar por nombre
        autoWidth: true,
        scrollX: false,
        columnDefs: [
            { orderable: false, targets: accionColIdx },
            { width: '140px', targets: accionColIdx }
        ],
        dom: '<"row align-items-center mb-3"<"col-sm-6 col-md-auto"l><"col-sm-6 col-md"f>>rt<"row align-items-center mt-3"<"col-sm-5"i><"col-sm-7"p>>',
        drawCallback: function() {
            bindSwitchEvents();
            bindRolEvents();
            bindDeleteEvents();
        }
    });

    // Show and insert custom filters
    var filtersEl = $('#customFilters').detach().show();
    $(filtersEl).insertAfter('#usuariosTable_wrapper .row:first-child');

    // Filter by Estado
    $('#filterEstado').on('change', function() {
        table.column(estadoColIdx).search(this.value).draw();
    });

    // Filter by Rol
    if (hasRolCol) {
        $('#filterRol').on('change', function() {
            table.column(rolColIdx).search(this.value).draw();
        });
    }
});

// ─── Event binding functions (re-bind after DataTables redraws) ───
function bindSwitchEvents() {
    document.querySelectorAll('.estado-switch').forEach(function(switchEl) {
        if (switchEl._bound) return;
        switchEl._bound = true;
        switchEl.addEventListener('change', function() {
            var id = this.dataset.id;
            var estado = this.checked ? 'Activo' : 'Inactivo';
            var tdEstado = this.closest('tr').querySelector('.estado-text');
            var sw = this;

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "cambiar_estado_paciente.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhr.onload = function() {
                if(xhr.status === 200) {
                    tdEstado.textContent = estado;
                    tdEstado.className = 'estado-text estado-' + estado;
                } else {
                    alert("Error al cambiar estado del paciente");
                    sw.checked = !sw.checked;
                }
            };
            xhr.send("id=" + id + "&estado=" + estado);
        });
    });
}

function bindRolEvents() {
    document.querySelectorAll('.rol-select').forEach(function(selectEl) {
        if (selectEl._bound) return;
        selectEl._bound = true;
        selectEl.addEventListener('change', function() {
            var idUsuario = this.dataset.usuarioId;
            var nuevoRol = this.value;
            var selectOriginal = this;
            var valorAnterior = selectOriginal.getAttribute('data-current-role') || (Array.from(selectOriginal.options).find(function(opt){ return opt.defaultSelected; }) || {}).value;

            showConfirm('¿Cambiar el rol de este usuario a ' + nuevoRol + '?').then(function(confirmed) {
                if (!confirmed) {
                    if (valorAnterior) selectOriginal.value = valorAnterior;
                    return;
                }
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "cambiar_rol_usuario.php", true);
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                xhr.onload = function() {
                    if(xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if(response.success) {
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
}

function bindDeleteEvents() {
    document.querySelectorAll('.btn-delete-paciente').forEach(function(btn) {
        if (btn._bound) return;
        btn._bound = true;
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            showConfirm('¿Eliminar paciente?').then(function(ok) {
                if (ok) {
                    window.location.href = 'eliminar_paciente.php?id=' + encodeURIComponent(id);
                }
            });
        });
    });
}

// ─── Confirm modal & toast helpers ───
function showConfirm(message) {
    return new Promise(function(resolve) {
        var modal = document.getElementById('confirmModal');
        var msg = document.getElementById('confirmModalMessage');
        var btnConfirm = document.getElementById('confirmModalOk');
        var btnCancel = document.getElementById('confirmModalCancel');

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
    var toast = document.createElement('div');
    toast.className = 'custom-toast bg-' + (type === 'success' ? 'success' : 'danger');
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function(){ toast.classList.add('visible'); }, 10);
    setTimeout(function(){ toast.classList.remove('visible'); setTimeout(function(){ toast.remove(); }, 300); }, 3500);
}
</script>

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