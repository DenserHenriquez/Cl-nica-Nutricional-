<?php
session_start();
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db_connection.php';

$userRole = $_SESSION['rol'] ?? 'Paciente';
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Classification functions (copied from Consulta_Medica.php/panelevolucionpaciente.php)
class EvaluadorRangos {
    public static function clasificarIMC($imc) {
        if ($imc < 18.5) return ['label' => 'Delgadez I', 'color' => '#fd7e14', 'rango' => '< 18.5'];
        if ($imc < 25.0) return ['label' => 'Normal', 'color' => '#28a745', 'rango' => '18.5-24.9'];
        if ($imc < 30.0) return ['label' => 'Sobrepeso', 'color' => '#ffc107', 'rango' => '25.0-29.9'];
        if ($imc < 35.0) return ['label' => 'Obesidad I', 'color' => '#dc3545', 'rango' => '30.0-34.9'];
        if ($imc < 40.0) return ['label' => 'Obesidad II', 'color' => '#c82333', 'rango' => '35.0-39.9'];
        return ['label' => 'Obesidad III', 'color' => '#721c24', 'rango' => '≥ 40.0'];
    }

    public static function clasificarGrasaVisceral($nivel) {
        if ($nivel <= 9) return ['label' => 'Saludable', 'color' => '#28a745'];
        if ($nivel <= 14) return ['label' => 'Alerta', 'color' => '#ffc107'];
        return ['label' => 'Peligro', 'color' => '#dc3545'];
    }

    public static function getTablaGrasaCorporal() {
        return [
            'Hombres' => [
                ['Edad' => '20-39', 'Bajo' => '<8%', 'Recomendado' => '8-20%', 'Alto' => '20-25%', 'Muy Alto' => '>25%'],
                ['Edad' => '40-59', 'Bajo' => '<11%', 'Recomendado' => '11-22%', 'Alto' => '22-28%', 'Muy Alto' => '>28%'],
                ['Edad' => '60-79', 'Bajo' => '<13%', 'Recomendado' => '13-25%', 'Alto' => '25-30%', 'Muy Alto' => '>30%']
            ],
            'Mujeres' => [
                ['Edad' => '20-39', 'Bajo' => '<21%', 'Recomendado' => '21-33%', 'Alto' => '33-39%', 'Muy Alto' => '>39%'],
                ['Edad' => '40-59', 'Bajo' => '<23%', 'Recomendado' => '23-34%', 'Alto' => '34-40%', 'Muy Alto' => '>40%'],
                ['Edad' => '60-79', 'Bajo' => '<24%', 'Recomendado' => '24-36%', 'Alto' => '36-42%', 'Muy Alto' => '>42%']
            ]
        ];
    }

    public static function getMusculoEsqueletico() {
        return [
            'Hombres' => ['Bajo' => '<32.9%', 'Recomendado' => '32.9-39.1%', 'Alto' => '39.1-45.8%', 'Muy Alto' => '>45.8%'],
            'Mujeres' => ['Bajo' => '<24.3%', 'Recomendado' => '24.3-30.2%', 'Alto' => '30.2-35.2%', 'Muy Alto' => '>35.2%']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rangos Corporales | NUTRIVIDA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="bg-light">
    <div class="container py-4 py-lg-5">
        <!-- Header Section -->
        <div class="header-section mb-4 text-center">
            <div class="medical-icon mb-2"><i class="bi bi-rulers"></i></div>
            <h1 class="fw-bold">Rangos de Referencia</h1>
            <p class="lead mb-0">Tablas estándar para IMC, Grasa Visceral, %Grasa Corporal y %Músculo Esquelético</p>
        </div>

        <div class="row g-4">
            <!-- 1. IMC -->
            <div class="col-lg-6">
                <div class="card h-100 stat-card">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="bi bi-activity text-primary me-2"></i>Índice de Masa Corporal (OMS)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr><th>Rango</th><th>Clasificación</th><th>Color</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $imcEjemplos = [17, 22, 27, 32, 37, 42];
                                    foreach ($imcEjemplos as $ej) {
                                        $clasif = EvaluadorRangos::clasificarIMC($ej);
                                        echo "<tr><td>{$clasif['rango']}</td><td><span class='badge' style='background:{$clasif['color']};color:white;'>{$clasif['label']}</span></td><td><span class='badge fs-6' style='background:{$clasif['color']};width:24px;height:24px;opacity:0.8;'></span></td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Grasa Visceral -->
            <div class="col-lg-6">
                <div class="card h-100 stat-card">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="bi bi-fire text-danger me-2"></i>Grasa Visceral</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr><th>Nivel</th><th>Rango</th><th>Clasificación</th><th>Color</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $gvEjemplos = [5, 12, 16];
                                    $gvRangos = [
                                        [1,9,'Saludable (Verde)','#28a745'],
                                        [10,14,'Alerta (Amarillo)','#ffc107'],
                                        [15,'∞','Peligro (Rojo)','#dc3545']
                                    ];
                                    foreach ($gvRangos as $r) {
                                        echo "<tr><td>{$r[0]}</td><td>" . ($r[1]=='∞' ? '≥15' : "{$r[0]}-{$r[1]}") . "</td><td>{$r[2]}</td><td><span class='badge fs-6' style='background:{$r[3]};width:24px;height:24px;opacity:0.8;'></span></td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. %Grasa Corporal -->
            <div class="col-12">
                <div class="card stat-card">
                    <div class="card-body">
                        <h5 class="card-title mb-4"><i class="bi bi-droplet-half text-warning me-2"></i>% Grasa Corporal (ACSM)</h5>
                        <?php $tablasGC = EvaluadorRangos::getTablaGrasaCorporal(); ?>
                        <div class="row text-center">
                            <?php foreach (['Hombres', 'Mujeres'] as $sexo): ?>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold mb-2"><?= $sexo ?></h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr><th>Edad</th><th class="bg-info text-white">Bajo</th><th class="bg-success text-white">Recomendado</th><th class="bg-warning text-dark">Alto</th><th class="bg-danger text-white">Muy Alto</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tablasGC[$sexo] as $fila): ?>
                                            <tr>
                                                <td><?= $fila['Edad'] ?></td>
                                                <td><?= $fila['Bajo'] ?></td>
                                                <td><?= $fila['Recomendado'] ?></td>
                                                <td><?= $fila['Alto'] ?></td>
                                                <td><?= $fila['Muy Alto'] ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. %Músculo Esquelético -->
            <div class="col-12">
                <div class="card stat-card">
                    <div class="card-body">
                        <h5 class="card-title mb-4"><i class="bi bi-flexible text-info me-2"></i>% Músculo Esquelético</h5>
                        <?php $musculoData = EvaluadorRangos::getMusculoEsqueletico(); ?>
                        <div class="row g-3">
                            <?php foreach ($musculoData as $sexo => $rangos): ?>
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3"><?= $sexo ?></h6>
                                <div class="row g-2">
                                    <?php foreach ($rangos as $nivel => $rango): ?>
                                    <div class="col-6">
                                        <div class="p-2 border rounded text-center fw-semibold" style="background: <?= $nivel=='Bajo' ? '#ffebee' : ($nivel=='Recomendado' ? '#d4edda' : ($nivel=='Alto' ? '#fff3cd' : '#f3e5f5')); ?>">
                                            <small class="d-block text-muted"><?= $nivel ?></small>
                                            <?= str_replace('%', '<small>%</small>', $rango) ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="text-center mt-5 pt-4 border-top">
            <a href="Menuprincipal.php" class="btn btn-outline-success btn-lg">
                <i class="bi bi-arrow-left me-2"></i>Volver al Menú Principal
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

