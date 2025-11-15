!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínica Nutricional - Tu Salud en Nuestras Manos</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-success" href="#">
                <i class="fas fa-leaf me-2"></i>Clínica Nutricional
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#servicios">Servicios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contacto">Contacto</a>
                    </li>
                </ul>
            </div>
            <!-- Login button -->
            <button class="btn btn-outline-success ms-3" id="loginBtn" data-bs-toggle="modal" data-bs-target="#loginModal">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
        </div>
    </nav>

    <!-- Welcome Title Section -->
    <section class="py-5 bg-success text-white text-center">
        <div class="container">
            <h1 class="display-4 fw-bold">Bienvenidos a la Clínica Nutricional</h1>
        </div>
    </section>





    <!-- Services Section -->
    <section id="servicios" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="display-5 fw-bold text-success">Nuestros Servicios</h2>
                    <p class="lead text-muted">Soluciones nutricionales completas para tu bienestar</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-utensils text-success fa-3x mb-3"></i>
                            <h5 class="card-title fw-bold">Planes Alimenticios</h5>
                            <p class="card-text">Diseñamos dietas personalizadas según tus necesidades nutricionales, preferencias y objetivos de salud.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-weight text-success fa-3x mb-3"></i>
                            <h5 class="card-title fw-bold">Control de Peso</h5>
                            <p class="card-text">Programas especializados para pérdida, ganancia o mantenimiento de peso de forma saludable.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-heartbeat text-success fa-3x mb-3"></i>
                            <h5 class="card-title fw-bold">Nutrición Clínica</h5>
                            <p class="card-text">Apoyo nutricional para enfermedades crónicas, alergias alimentarias y condiciones especiales.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-running text-success fa-3x mb-3"></i>
                            <h5 class="card-title fw-bold">Deporte y Fitness</h5>
                            <p class="card-text">Nutrición deportiva para atletas y personas activas que buscan optimizar su rendimiento.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-baby text-success fa-3x mb-3"></i>
                            <h5 class="card-title fw-bold">Nutrición Infantil</h5>
                            <p class="card-text">Asesoramiento para el crecimiento saludable de niños y adolescentes.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-users text-success fa-3x mb-3"></i>
                            <h5 class="card-title fw-bold">Consultas Familiares</h5>
                            <p class="card-text">Planes nutricionales para toda la familia, adaptados a diferentes edades y necesidades.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contacto" class="py-5 bg-success text-white">
        <div class="container text-center">
            <h2 class="display-5 fw-bold mb-4">¿Listo para Empezar?</h2>
            <p class="lead mb-4">
                Agenda tu consulta inicial y comienza tu camino hacia una vida más saludable.
            </p>
            <button class="btn btn-outline-light btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
                <i class="fas fa-calendar-plus me-2"></i>Agendar Cita
            </button>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="fw-bold">Clínica Nutricional</h5>
                    <p>Tu salud es nuestra prioridad. Expertos en nutrición personalizada.</p>
                </div>
                <div class="col-md-4">
                    <h5 class="fw-bold">Enlaces Rápidos</h5>
                    <ul class="list-unstyled">
                        <li><a href="#servicios" class="text-white">Servicios</a></li>
                        <li><a href="#contacto" class="text-white">Contacto</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="fw-bold">Contacto</h5>
                    <p><i class="fas fa-phone me-2"></i> (504) 8896-8963</p>
                    <p><i class="fas fa-envelope me-2"></i>info@nutri.hn</p>
                    <p><i class="fas fa-map-marker-alt me-2"></i>Ciudad Universitaria</p>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2025 Clínica Nutricional. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-sign-in-alt me-2"></i>Acceder al Sistema
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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

                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs nav-justified" id="loginTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab" aria-controls="login" aria-selected="true">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab" aria-controls="register" aria-selected="false">
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
                                <h4 class="text-success fw-bold">Bienvenido de vuelta</h4>
                                <p class="text-muted">Inicia sesión en tu cuenta de nutricionista</p>
                            </div>
                            <form action="Login.php" method="POST">
                                <div class="mb-3">
                                    <label for="loginEmail" class="form-label fw-semibold">
                                        <i class="fas fa-envelope me-2 text-success"></i>Correo Electrónico
                                    </label>
                     <input type="email" class="form-control" id="loginEmail" name="Correo_electronico" placeholder="tu@email.com" required>
                                    
                                </div>
                                <div class="mb-3">
                                    <label for="loginPassword" class="form-label fw-semibold">
                                        <i class="fas fa-lock me-2 text-success"></i>Contraseña
                                    </label>
                                    <input type="password" class="form-control" id="loginPassword" name="contrasena" placeholder="Tu contraseña" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success fw-bold">
                                        <i class="fas fa-sign-in-alt me-2"></i>Entrar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Register Tab -->
                        <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                            <div class="text-center mb-4">
                                <i class="fas fa-heartbeat text-success fa-3x mb-3"></i>
                                <h4 class="text-success fw-bold">Únete a nosotros</h4>
                                <p class="text-muted">Regístrate para acceder a tu panel nutricional</p>
                            </div>
                            <form action="Login.php" method="POST">
                                <div class="mb-3">
                                    <label for="registerName" class="form-label fw-semibold">
                                        <i class="fas fa-user me-2 text-success"></i>Nombre Completo
                                    </label>
                                    <input type="text" class="form-control" id="registerName" name="nombre_completo" placeholder="Tu nombre completo" required>
                                </div>
                                <div class="mb-3">
                                    <label for="registerEmail" class="form-label fw-semibold">
                                        <i class="fas fa-envelope me-2 text-success"></i>Correo Electrónico
                                    </label>
                                    <input type="email" class="form-control" id="registerEmail" name="Correo_electronico" placeholder="tu@email.com" required>
                                </div>
                                <div class="mb-3">
                                    <label for="registerUser" class="form-label fw-semibold">
                                        <i class="fas fa-user-tag me-2 text-success"></i>Usuario
                                    </label>
                                    <input type="text" class="form-control" id="registerUser" name="Usuario" placeholder="Tu usuario" required>
                                </div>
                                <div class="mb-3">
                                    <label for="registerPassword" class="form-label fw-semibold">
                                        <i class="fas fa-lock me-2 text-success"></i>Contraseña
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="registerPassword" name="contrasena" placeholder="Tu contraseña" required>
                                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" style="z-index: 10; text-decoration: none;" onclick="togglePasswordVisibility('registerPassword', this)">
                                            <i class="fas fa-eye text-muted"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="registerPasswordConfirm" class="form-label fw-semibold">
                                        <i class="fas fa-lock me-2 text-success"></i>Confirmar Contraseña
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="registerPasswordConfirm" name="contrasena_confirm" placeholder="Repite tu contraseña" required>
                                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" style="z-index: 10; text-decoration: none;" onclick="togglePasswordVisibility('registerPasswordConfirm', this)">
                                            <i class="fas fa-eye text-muted"></i>
                                        </button>
                                    </div>
                                    <div id="passwordHelp" class="form-text text-danger d-none">Las contraseñas no coinciden</div>
                                </div>
                                <input type="hidden" name="origen" value="index">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success fw-bold">
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

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    <script>
        // Toggle password visibility
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const pass = document.getElementById('registerPassword');
            const pass2 = document.getElementById('registerPasswordConfirm');
            const form = pass.closest('form');
            const help = document.getElementById('passwordHelp');
            function validate() {
                if (pass2.value && pass.value !== pass2.value) {
                    help.classList.remove('d-none');
                    pass2.classList.add('is-invalid');
                    return false;
                } else {
                    help.classList.add('d-none');
                    pass2.classList.remove('is-invalid');
                    return true;
                }
            }
            pass.addEventListener('input', validate);
            pass2.addEventListener('input', validate);
            form.addEventListener('submit', function (e) {
                if (!validate()) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>






</body>
</html>





