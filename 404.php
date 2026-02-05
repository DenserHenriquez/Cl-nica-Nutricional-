<?php
// Custom 404 page: Ruta no encontrada
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ruta no encontrada — Clínica Nutricional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for brand icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--green:#198754;--muted:#6c757d}
        body{background:linear-gradient(180deg,#f7fbff,#f3f8ff);font-family:Segoe UI,Roboto,Arial;margin:0}
        .center{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{max-width:740px;border-radius:12px;box-shadow:0 12px 30px rgba(2,6,23,0.08);overflow:hidden}
        .card .left{background:linear-gradient(135deg,rgba(25,118,210,.08),rgba(13,71,161,.04));padding:28px;color:var(--green);display:flex;align-items:center;justify-content:center}
        .badge-icon{width:84px;height:84px;border-radius:50%;background:linear-gradient(135deg,var(--green),#146c43);display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px;font-weight:700}
        .card .right{padding:28px}
        h1{margin:0 0 8px;font-size:1.6rem;color:#1b2b3b}
        p{margin:0 0 16px;color:var(--muted)}
        .muted-small{color:#98a1ab;font-size:0.95rem}
        .btn-back{background:var(--green);border-color:var(--green);}
        .link-home{color:var(--green);text-decoration:none;font-weight:600}
        @media(max-width:720px){.card{flex-direction:column}.card .left{padding:20px}.card .right{padding:20px}}
    </style>
</head>
<body>
    <!-- Header removed to avoid duplicate brand (brand shown above the card) -->

    <main class="center">
        <!-- Brand intentionally removed from the error message per UX request -->

        <div class="card d-flex w-100">
            <div class="right flex-grow-1 w-100">
                <div class="text-center mb-3">
                    <div class="badge-icon" style="margin:0 auto;">!</div>
                </div>

                <h1 class="text-center">Ruta no encontrada</h1>
                <p class="muted-small text-center">La dirección que intentaste acceder no existe en este servidor o contiene parámetros inválidos.</p>

                <div class="mt-4 d-flex gap-2 flex-wrap justify-content-center">
                    <a href="Menuprincipal.php" class="btn btn-back btn-lg">Regresar al Menú Principal</a>
                    <a href="index.php" class="btn btn-outline-secondary btn-lg">Ir a la página de inicio</a>
                </div>

                <hr style="margin:18px 0">
                <small class="text-muted d-block text-center">Si el error persiste, revisa la URL o contacta al administrador.</small>
            </div>
        </div>
    </main>
</body>
</html>
