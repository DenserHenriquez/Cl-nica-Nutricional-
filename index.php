<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login y Register - MagtimusPro</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/estilos.css">

    <style>
        body {
            background-image: url('https://www.comunidad.madrid/sites/default/files/styles/image_style_16_9/public/doc/sanidad/comu/nutricion.jpg?itok=Z0-8kGU_');
            background-size: cover; /* Ensures the image covers the entire background */
            background-position: center; /* Centers the image */
            background-repeat: no-repeat; /* Prevents the image from repeating */
        }
        .alert {
            margin: 16px auto;
            max-width: 640px;
            padding: 12px 16px;
            border-radius: 6px;
            font-family: 'Roboto', sans-serif;
            font-size: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            border-left: 6px solid;
            background: #fff;
        }
        .alert.success { border-color: #2e7d32; color: #1b5e20; }
        .alert.error { border-color: #c62828; color: #b71c1c; }
    
    </style>
        

</head>
<body>
<body class="bg-white"></body>
<body>
    
</body>
    
</body>
    <main>
    <?php if (isset($_GET['ok'])): ?>
        <div class="alert success"><?php echo htmlspecialchars($_GET['ok'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="alert error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

        <div class="contenedor__todo">
            <div class="caja__trasera">
                <div class="caja__trasera-login">
                    <h3>¿Ya tienes una cuenta?</h3>
                    <p>Inicia sesión para entrar en la página</p>
                    <button id="btn__iniciar-sesion">Iniciar Sesión</button>
                </div>
                <div class="caja__trasera-register">
                    <h3>¿Aún no tienes una cuenta?</h3>
                    <p>Regístrate para que puedas iniciar sesión</p>
                    <button id="btn__registrarse">Regístrarse</button>
                </div>
            </div>

            <!--Formulario de Login y registro-->
            <div class="contenedor__login-register">
                <!--Login-->
                <form action="Login.php" method="POST" class="formulario__login"> 
                    <h2>Iniciar Sesión</h2>
                    <input type="text" placeholder="Correo Electronico" name="Correo_electronico">
                    <input type="password" placeholder="Contraseña" name="contrasena">
                    <button>Entrar</button>
                </form>

                <!--Register-->
                <form action="Login.php" method="post" class="formulario__register">
                    <h2>Regístrarse</h2>
                    <input type="text" placeholder="Nombre completo" name="nombre_completo">
                    <input type="text" placeholder="Correo Electronico" name="Correo_electronico">
                    <input type="text" placeholder="Usuario" name="Usuario">
                    <input type="password" placeholder="Contraseña" name="contrasena">
                    <input type="hidden" name="origen" value="index">
                    <button>Regístrarse</button>
                </form>
            </div>
        </div>

    </main>

    <script src="assets/js/script.js"></script>
</body>
</html>





