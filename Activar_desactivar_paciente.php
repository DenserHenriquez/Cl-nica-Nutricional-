<?php
session_start();
require_once 'db_connection.php';

// CONSULTA PACIENTES + USUARIOS
$sql = "SELECT p.id_pacientes, p.id_usuarios, p.nombre_completo, p.DNI, p.fecha_nacimiento, p.edad, p.telefono, p.estado,
               u.Nombre_completo as usuario_nombre, u.Correo_electronico
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lista de Pacientes - Nutrición</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
:root {
    --primary-900: #0d47a1;
    --primary-700: #1565c0;
    --primary-500: #1976d2;
    --primary-300: #42a5f5;
    --white: #ffffff;
    --text-900: #0b1b34;
    --text-700: #22426e;
    --shadow: 0 10px 25px rgba(13, 71, 161, 0.18);
    --radius-lg: 16px;
}
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    margin:0; 
    padding:0; 
    background: linear-gradient(180deg, #f7fbff 0%, #f3f8ff 100%);
}
a { color: inherit; text-decoration: none; }
/* Topbar estilo menú principal */
.topbar { position: sticky; top:0; z-index:50; background: linear-gradient(90deg, var(--primary-900), var(--primary-700)); color: var(--white); box-shadow: var(--shadow); }
.topbar__inner { max-width: 1200px; margin:0 auto; padding:12px 20px; display:flex; align-items:center; justify-content: space-between; gap:16px; }
.brand { display:flex; align-items:center; gap:12px; font-weight:700; letter-spacing:.3px; }
.brand__logo { width:36px; height:36px; border-radius:50%; background: radial-gradient(120% 120% at 20% 20%, var(--primary-300), var(--primary-900)); display:inline-flex; align-items:center; justify-content:center; box-shadow: 0 6px 14px rgba(0,0,0,.15) inset, 0 2px 8px rgba(255,255,255,.25); }
.brand__logo svg { width:22px; height:22px; fill:#fff; opacity:.95; }
.brand__name { font-size:1.05rem; }
.topbar__actions { display:flex; align-items:center; gap:10px; }
.topbar__actions a { display:inline-block; padding:8px 14px; background: rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22); border-radius:999px; color:#fff; font-size:.92rem; transition: all .2s ease; }
.topbar__actions a:hover { background: rgba(255,255,255,.22); transform: translateY(-1px); }
.user-pill { display:inline-flex; align-items:center; gap:10px; padding:6px 10px 6px 6px; background: rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.24); border-radius:999px; color:#fff; font-weight:600; letter-spacing:.2px; white-space:nowrap; }
.user-avatar { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background: linear-gradient(135deg, rgba(255,255,255,.35), rgba(255,255,255,.05)); color: var(--primary-900); font-weight:800; border:1px solid rgba(255,255,255,.45); }

h1 { color:#0d47a1; text-align:center; margin:16px 0 0; text-shadow: 1px 1px 3px rgba(0,0,0,0.1); }

.main-content { 
    width:95%; 
    max-width: 1400px;
    margin:20px auto; 
    background:rgba(255, 255, 255, 0.95); 
    border-radius:var(--radius-lg); 
    padding:20px; 
    box-shadow: 0 6px 16px rgba(13, 71, 161, 0.10);
    transition:0.3s; 
}
.main-content:hover { box-shadow: 0 10px 24px rgba(13,71,161,0.18); }

.table-wrapper { overflow-x:auto; margin-top:20px; }
.table-card { background:#fff; border-radius:16px; box-shadow: 0 8px 24px rgba(13,71,161,0.12); overflow:hidden; border:1px solid rgba(13,71,161,.08); }
.table { width:100%; border-collapse: separate; border-spacing:0 10px; font-size:14px; min-width:1000px; }
.table thead th { padding:16px 18px; text-align:left; font-weight:800; color:#0b1b34; background:#fff; position:sticky; top:0; z-index:1; }
.table tbody tr { background:#fff; border-radius:12px; box-shadow: 0 6px 16px rgba(13,71,161,0.08); }
.table tbody td { padding:18px; background:#fff; border-top:1px solid rgba(2,16,43,.04); }

.badge { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:999px; font-weight:700; font-size:.9rem; }
.badge .dot { width:18px; height:18px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; color:#fff; }
.badge.estado-activo { color:#064e3b; background: linear-gradient(90deg,#34d399,#10b981); }
.badge.estado-activo .dot { background: rgba(255,255,255,.25); }
.badge.estado-inactivo { color:#6b1111; background: linear-gradient(90deg,#ef9a9a,#ef5350); }
.badge.estado-inactivo .dot { background: rgba(255,255,255,.25); }

.badge.role { color:#fff; background: linear-gradient(90deg,#7c4dff,#673ab7); }

.switch { position: relative; display: inline-block; width: 56px; height: 28px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top:0; left:0; right:0; bottom:0; background-color:#ef5350; transition:0.3s; border-radius:999px; }
.slider:before { position:absolute; content:""; height:22px; width:22px; left:3px; bottom:3px; background:white; transition:0.3s; border-radius:50%; box-shadow:0 2px 6px rgba(0,0,0,.2); }
input:checked + .slider { background: #10b981; }
input:checked + .slider:before { transform: translateX(28px); }

.estado-text { display:none; }

.switch { position: relative; display: inline-block; width: 50px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top:0; left:0; right:0; bottom:0; background-color:#ccc; transition:0.4s; border-radius:24px; }
.slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:white; transition:0.4s; border-radius:50%; }
input:checked + .slider { background-color:#1e88e5; }
input:not(:checked) + .slider { background-color: #d32f2f; } 
input:checked + .slider:before { transform: translateX(26px); }

.btn-archivar {
    background: #d32f2f;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
}
.btn-archivar:hover {
    background: #b71c1c;
}

@media(max-width:768px) {
    th, td { font-size:12px; padding:8px; }
}
</style>
</head>
<body>

<header class="topbar" role="banner">
    <div class="topbar__inner">
        <div class="brand" aria-label="Clínica Nutricional">
            <span class="brand__logo" aria-hidden="true">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">
                    <path d="M10.5 3a1 1 0 0 0-1 1v5H4.5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h5v5a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-5h5a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-5V4a1 1 0 0 0-1-1h-4z"/>
                </svg>
            </span>
            <span class="brand__name">Clínica Nutricional</span>
        </div>
        <div class="topbar__actions">
            <a href="Menuprincipal.php" title="Volver al menú">← Menú Principal</a>
            <span class="user-pill" title="Usuario actual">
                <span class="user-avatar" aria-hidden="true"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? 'U'), 0, 1), 'UTF-8')); ?></span>
                <span><?php echo htmlspecialchars($_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? 'Usuario')); ?></span>
            </span>
            <a href="Login.php" title="Cerrar sesión">Salir</a>
        </div>
    </div>
</header>

<div class="main-content">
    <h1>Pacientes</h1>
    
    <div class="table-wrapper">
        <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th>NOMBRE</th>
                    <th>DNI</th>
                    <th>TELÉFONO</th>
                    <th>FECHA NAC.</th>
                    <th>EDAD</th>
                    <th>CORREO</th>
                    <th>ESTADO</th>
                    <th>ACCIÓN</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($total_entradas > 0) {
                while($fila = $resultado->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>".htmlspecialchars($fila['nombre_completo'])."</td>";
                    echo "<td>".htmlspecialchars($fila['DNI'])."</td>";
                    echo "<td>".htmlspecialchars($fila['telefono'])."</td>";
                    echo "<td>".htmlspecialchars($fila['fecha_nacimiento'])."</td>";
                    echo "<td>".htmlspecialchars($fila['edad'])."</td>";
                    echo "<td>".htmlspecialchars($fila['Correo_electronico'])."</td>";
                    $isActivo = ($fila['estado'] == 'Activo');
                    $badgeClass = $isActivo ? 'badge estado-activo' : 'badge estado-inactivo';
                    $badgeText = $isActivo ? 'Activo' : 'Inactivo';
                    $checkIcon = '&#10003;';
                    echo "<td><span class='".$badgeClass."'><span class='dot'>".$checkIcon."</span>".$badgeText."</span></td>";
                    echo "<td>
                    <label class='switch'>
                    <input type='checkbox' class='estado-switch' 
                    data-id='".$fila['id_pacientes']."' 
                    ".($isActivo?'checked':'').">
                    <span class='slider'></span>
                    </label>
                    </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='11' style='text-align:center;'>No se encontraron pacientes.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.estado-switch').forEach(function(switchEl) {
    switchEl.addEventListener('change', function() {
        const id = this.dataset.id;
        const estado = this.checked ? 'Activo' : 'Inactivo';
        const tdEstado = this.closest('tr').querySelector('td:nth-last-child(2)');

        // AJAX POST
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "cambiar_estado_paciente.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.onload = function() {
            if(xhr.status === 200) {
                // Reemplazar la celda estado por badge
                const estadoTd = tdEstado; // la celda de estado
                const isActivo = (estado === 'Activo');
                estadoTd.innerHTML = `<span class="${isActivo ? 'badge estado-activo' : 'badge estado-inactivo'}"><span class="dot">&#10003;</span>${estado}</span>`;
            } else {
                alert("Error al cambiar estado del paciente");
                // Revertir switch si falla
                switchEl.checked = !switchEl.checked;
            }
        };
        xhr.send("id=" + id + "&estado=" + estado);
    });
});
</script>

</body>
</html>

<?php $conexion->close(); ?>