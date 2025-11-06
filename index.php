<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login y Registro - Clínica Nutricional</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="bg-light">

    <div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center" style="background-image: url('https://www.comunidad.madrid/sites/default/files/styles/image_style_16_9/public/doc/sanidad/comu/nutricion.jpg?itok=Z0-8kGU_'); background-size: cover; background-position: center; background-repeat: no-repeat;">
        <div class="row w-100 justify-content-center">
            <div class="col-lg-6 col-md-8 col-sm-10">
                <!-- Alerts -->
                <?php if (isset($_GET['ok'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['ok'], ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-lg border-success rounded-4">
                    <div class="card-body p-0">
                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs nav-justified bg-primary text-white rounded-top-4" id="loginTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active text-white fw-bold" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab" aria-controls="login" aria-selected="true">
                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link text-white fw-bold" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab" aria-controls="register" aria-selected="false">
                                    <i class="fas fa-user-plus me-2"></i>Registrarse
                                </button>
                            </li>
                        </ul>

                        <!-- Tab content -->
                        <div class="tab-content p-4" id="loginTabsContent">
                            <!-- Login Tab -->
                            <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                                <div class="text-center mb-4">
                                    <i class="fas fa-leaf text-success fa-3x mb-3"></i>
                                    <h3 class="text-success fw-bold">Bienvenido de vuelta</h3>
                                    <p class="text-muted">Inicia sesión en tu cuenta de nutricionista</p>
                                </div>
                                <form action="Login.php" method="POST">
                                    <div class="mb-3">
                                        <label for="loginEmail" class="form-label fw-semibold">
                                            <i class="fas fa-envelope me-2 text-success"></i>Correo Electrónico
                                        </label>
                                        <input type="email" class="form-control form-control-lg" id="loginEmail" name="Correo_electronico" placeholder="tu@email.com" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="loginPassword" class="form-label fw-semibold">
                                            <i class="fas fa-lock me-2 text-success"></i>Contraseña
                                        </label>
                                        <input type="password" class="form-control form-control-lg" id="loginPassword" name="contrasena" placeholder="Tu contraseña" required>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success btn-lg fw-bold">
                                            <i class="fas fa-sign-in-alt me-2"></i>Entrar
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Register Tab -->
                            <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                                <div class="text-center mb-4">
                                    <i class="fas fa-heartbeat text-success fa-3x mb-3"></i>
                                    <h3 class="text-success fw-bold">Únete a nosotros</h3>
                                    <p class="text-muted">Regístrate para acceder a tu panel nutricional</p>
                                </div>
                                <form action="Login.php" method="POST">
                                    <div class="mb-3">
                                        <label for="registerName" class="form-label fw-semibold">
                                            <i class="fas fa-user me-2 text-success"></i>Nombre Completo
                                        </label>
                                        <input type="text" class="form-control form-control-lg" id="registerName" name="nombre_completo" placeholder="Tu nombre completo" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="registerEmail" class="form-label fw-semibold">
                                            <i class="fas fa-envelope me-2 text-success"></i>Correo Electrónico
                                        </label>
                                        <input type="email" class="form-control form-control-lg" id="registerEmail" name="Correo_electronico" placeholder="tu@email.com" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="registerUser" class="form-label fw-semibold">
                                            <i class="fas fa-user-tag me-2 text-success"></i>Usuario
                                        </label>
                                        <input type="text" class="form-control form-control-lg" id="registerUser" name="Usuario" placeholder="Tu usuario" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="registerPassword" class="form-label fw-semibold">
                                            <i class="fas fa-lock me-2 text-success"></i>Contraseña
                                        </label>
                                        <input type="password" class="form-control form-control-lg" id="registerPassword" name="contrasena" placeholder="Tu contraseña" required>
                                    </div>
                                    <input type="hidden" name="origen" value="index">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success btn-lg fw-bold">
                                            <i class="fas fa-user-plus me-2"></i>Regístrarse
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS (if needed for additional functionality) -->
    <script src="assets/js/script.js"></script>
</body>
</html>





