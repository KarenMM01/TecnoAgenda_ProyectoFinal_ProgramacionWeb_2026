<?php
session_start();
require 'conexion.php';

// Si ya hay sesión
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Datos inválidos.'
        ]);
        exit;
    }

    // =========================
    // REGISTRO
    // =========================
    if ($data['action'] === 'register') {

        // Verificar si existe
        $check = $conexion->prepare("
            SELECT id FROM usuarios
            WHERE email = :email
        ");

        $check->execute([
            ':email' => $data['email']
        ]);

        if ($check->fetch()) {

            echo json_encode([
                'status' => 'error',
                'message' => 'El correo ya está registrado.'
            ]);

            exit;
        }

        // Insertar usuario
        $sql = "
        INSERT INTO usuarios
        (
            nombre,
            fecha_nacimiento,
            rol,
            carrera,
            semestre,
            email,
            password
        )
        VALUES
        (
            :nombre,
            :fechaNacimiento,
            :rol,
            :carrera,
            :semestre,
            :email,
            :password
        )";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':fechaNacimiento' => $data['fechaNacimiento'],
            ':rol' => $data['rol'],
            ':carrera' => $data['carrera'],
            ':semestre' => $data['semestre'],
            ':email' => $data['email'],
            ':password' => $data['password']
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => '¡Registro exitoso!'
        ]);

        exit;
    }

    // =========================
    // LOGIN
    // =========================
    if ($data['action'] === 'login') {

        $sql = "
        SELECT *
        FROM usuarios
        WHERE email = :email
        AND password = :password
        ";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ':email' => $data['email'],
            ':password' => $data['password']
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            $_SESSION['user'] = $user;

            echo json_encode([
                'status' => 'success',
                'message' => '¡Inicio exitoso!'
            ]);

        } else {

            echo json_encode([
                'status' => 'error',
                'message' => 'Correo o contraseña incorrectos.'
            ]);
        }

        exit;
    }

    // =========================
    // LOGIN GOOGLE
    // =========================
    if ($data['action'] === 'google_login') {
        $token = $data['credential'];
        
        $response = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . $token);
        if ($response) {
            $payload = json_decode($response, true);
            if (isset($payload['email'])) {
                $email = $payload['email'];
                $nombre = $payload['name'];
                $avatar = $payload['picture'] ?? null;

                $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $sql = "INSERT INTO usuarios (nombre, fecha_nacimiento, rol, carrera, semestre, email, password, avatar) VALUES (:nombre, :fechaNacimiento, :rol, :carrera, :semestre, :email, :password, :avatar)";
                    $stmt = $conexion->prepare($sql);
                    $stmt->execute([
                        ':nombre' => $nombre,
                        ':fechaNacimiento' => date('Y-m-d'),
                        ':rol' => 'Estudiante',
                        ':carrera' => 'Sin Especificar',
                        ':semestre' => '1',
                        ':email' => $email,
                        ':password' => '',
                        ':avatar' => $avatar
                    ]);
                    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = :email");
                    $stmt->execute([':email' => $email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                $_SESSION['user'] = $user;
                echo json_encode(['status' => 'success', 'message' => '¡Inicio de sesión exitoso con Google!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Token inválido.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Fallo al verificar con Google.']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoAgenda - Acceso</title>

    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Arima:wght@400;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            --verde-tecno: #7d9d85;
            --verde-sage: #a3b899;
            --naranja-soft: #f3a65a;
            --naranja-hover: #e89548;
            --crema-fondo: #e7e3d7;
            --text-color: #000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arima', system-ui, sans-serif;
            color: #000;
        }

        body {
            background-color: var(--crema-fondo);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
        }

        h1 {
            font-family: 'Aclonica', sans-serif;
            font-weight: 400;
            margin: 0;
        }

        h2 {
            font-family: 'Aclonica', sans-serif;
            font-weight: 400;
            margin-bottom: 20px;
        }

        p {
            font-size: 14px;
            font-weight: 300;
            line-height: 20px;
            letter-spacing: 0.5px;
            margin: 20px 0 30px;
        }


        button {
            border-radius: 20px;
            border: 1px solid var(--naranja-soft);
            background-color: var(--naranja-soft);
            color: #000;
            font-size: 12px;
            font-weight: 700;
            padding: 12px 45px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: transform 80ms ease-in;
            cursor: pointer;
            margin-top: 15px;
        }

        button:active {
            transform: scale(0.95);
        }

        button:focus {
            outline: none;
        }

        button.ghost {
            background-color: transparent;
            border-color: #000;
            color: #000;
        }

        button.ghost:hover {
            background-color: #000;
            color: #fff;
        }


        .container {
            background-color: #fdfaf3;
            border-radius: 25px;
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            width: 1000px;
            max-width: 100%;
            min-height: 550px;
        }

        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            transition: all 0.6s ease-in-out;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .sign-in-container {
            left: 0;
            width: 50%;
            z-index: 2;
        }

        .container.right-panel-active .sign-in-container {
            transform: translateX(100%);
            opacity: 0;
        }

        .sign-up-container {
            left: 0;
            width: 50%;
            opacity: 0;
            z-index: 1;
        }

        .container.right-panel-active .sign-up-container {
            transform: translateX(100%);
            opacity: 1;
            z-index: 5;
            animation: show 0.6s;
        }

        @keyframes show {

            0%,
            49.99% {
                opacity: 0;
                z-index: 1;
            }

            50%,
            100% {
                opacity: 1;
                z-index: 5;
            }
        }


        form {
            background-color: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            width: 100%;
            text-align: center;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
        }

        .full {
            grid-column: span 2;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            text-align: left;
            margin-bottom: 8px;
            width: 100%;
        }

        .input-group label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #000;
            margin-bottom: 3px;
        }

        input,
        select {
            background-color: #eee;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            width: 100%;
            outline: none;
            height: 38px;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .password-wrapper input {
            width: 100%;
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            cursor: pointer;
            color: #000;
            font-size: 1.2rem;
            transition: opacity 0.3s;
            opacity: 0.6;
        }

        .toggle-password:hover {
            opacity: 1;
        }


        .overlay-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            transition: transform 0.6s ease-in-out;
            z-index: 100;
        }

        .container.right-panel-active .overlay-container {
            transform: translateX(-100%);
        }

        .overlay {
            background-color: var(--verde-sage);
            color: #000;
            position: relative;
            left: -100%;
            height: 100%;
            width: 200%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out, background-color 0.6s ease-in-out;
        }

        .container.right-panel-active .overlay {
            transform: translateX(50%);
            background-color: var(--naranja-soft);
        }

        .overlay-panel {
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 40px;
            text-align: center;
            top: 0;
            height: 100%;
            width: 50%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out;
        }

        .overlay-left {
            transform: translateX(-20%);
        }

        .container.right-panel-active .overlay-left {
            transform: translateX(0);
        }

        .overlay-right {
            right: 0;
            transform: translateX(0);
        }

        .container.right-panel-active .overlay-right {
            transform: translateX(20%);
        }

        .msg {
            font-size: 0.85rem;
            margin-top: 10px;
            min-height: 18px;
            font-weight: 500;
        }

        .error {
            color: #d9534f;
        }

        .success {
            color: #28a745;
        }

        .google-login {
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            color: #555;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .google-login:hover {
            color: #000;
        }

        .google-login img {
            width: 18px;
            margin-right: 8px;
        }

        /* --- Estilos Responsivos para Móviles (Estilo PC Vertical) --- */
        @media (max-width: 768px) {
            body { padding: 15px; }
            .container {
                min-height: 750px;
                width: 100%;
                border-radius: 20px;
            }
            .form-container {
                width: 100%;
                height: 70%;
                padding: 20px;
            }
            
            /* Contenedor Formulario Login */
            .sign-in-container {
                left: 0;
                top: 0;
                transform: none;
                transition: top 0.6s ease-in-out, opacity 0.6s ease-in-out;
                z-index: 2;
            }
            .container.right-panel-active .sign-in-container {
                transform: none;
                top: 30%;
                opacity: 0;
            }

            /* Contenedor Formulario Registro */
            .sign-up-container {
                left: 0;
                top: 30%;
                transform: none;
                transition: top 0.6s ease-in-out, opacity 0.6s ease-in-out;
                z-index: 1;
                opacity: 0;
            }
            .container.right-panel-active .sign-up-container {
                transform: none;
                top: 30%;
                opacity: 1;
                z-index: 5;
                animation: none;
            }

            /* Panel Verde (Overlay) */
            .overlay-container {
                width: 100%;
                height: 30%;
                left: 0;
                top: 70%;
                transform: none;
                transition: top 0.6s ease-in-out;
            }
            .container.right-panel-active .overlay-container {
                transform: none;
                top: 0;
            }

            /* Fondo del panel verde */
            .overlay {
                width: 100%;
                height: 200%;
                left: 0;
                top: -100%;
                transform: translateY(0);
                transition: transform 0.6s ease-in-out, background-color 0.6s ease-in-out;
            }
            .container.right-panel-active .overlay {
                transform: translateY(50%);
                background-color: var(--naranja-soft);
            }

            /* Textos dentro del panel verde */
            .overlay-panel {
                position: absolute;
                width: 100%;
                height: 50%;
                padding: 0 15px;
                transform: translateY(0);
            }
            .overlay-panel h1 { font-size: 1.4rem !important; margin-bottom: 5px; }
            .overlay-panel p { font-size: 0.75rem !important; margin: 5px 0 15px; line-height: 1.3; }
            .overlay-panel button { padding: 8px 25px !important; font-size: 0.8rem !important; }

            .overlay-left {
                top: 0;
                left: 0;
                transform: translateY(-10%);
            }
            .container.right-panel-active .overlay-left {
                transform: translateY(0);
            }

            .overlay-right {
                top: 50%;
                right: 0;
                transform: translateY(0);
            }
            .container.right-panel-active .overlay-right {
                transform: translateY(10%);
            }

            /* Ajustes extras para que quepa todo */
            .form-grid { grid-template-columns: 1fr; gap: 5px; }
            .full { grid-column: span 1; }
            .mobile-toggle { display: none !important; }
        }
    </style>
</head>

<body>

    <div class="container" id="container">


        <div class="form-container sign-up-container">
            <form id="register-form">
                <h2>Crear una Cuenta</h2>
                <div class="form-grid">
                    <div class="input-group full">
                        <label>Nombre completo</label>
                        <input type="text" id="reg-nombre" placeholder="Ej. Rafael Hernandez" required>
                    </div>
                    <div class="input-group">
                        <label>Fecha de nacimiento</label>
                        <input type="text" id="reg-fechaNacimiento" placeholder="dd/mm/aaaa" readonly required>
                    </div>
                    <div class="input-group">
                        <label>Rol</label>
                        <select id="reg-rol">
                            <option value="Estudiante">Estudiante</option>
                            <option value="Docente">Docente</option>
                            <option value="Tutor">Tutor</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Carrera</label>
                        <select id="reg-carrera" required>
                            <option value="TICS">TICS</option>
                            <option value="Gestion">Gestion</option>
                            <option value="industrial">industrial</option>
                            <option value="Mecatronica">Mecatronica</option>
                            <option value="Automotris">Automotris</option>
                            <option value="Agricola">Agricola</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Semestre</label>
                        <select id="reg-semestre">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                            <option value="6">6</option>
                            <option value="7">7</option>
                            <option value="8">8</option>
                            <option value="9">9</option>
                        </select>
                    </div>
                    <div class="input-group full">
                        <label>Correo electrónico</label>
                        <input type="email" id="reg-email" placeholder="usuario@itess.edu.mx" required>
                    </div>
                    <div class="input-group">
                        <label>Contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" id="reg-password" placeholder="••••••••" required>
                            <i class="ph ph-eye-slash toggle-password" data-target="reg-password"></i>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Confirmar</label>
                        <div class="password-wrapper">
                            <input type="password" id="reg-confirm_password" placeholder="••••••••" required>
                            <i class="ph ph-eye-slash toggle-password" data-target="reg-confirm_password"></i>
                        </div>
                    </div>
                </div>
                <button type="submit">Registrar</button>
                <div id="reg-message" class="msg"></div>
                <p class="mobile-toggle" style="display:none; cursor:pointer; color:var(--verde-tecno); font-weight:bold; margin-top:15px; text-decoration: underline;" id="mobileSignIn">¿Ya tienes cuenta? Inicia sesión</p>
            </form>
        </div>


        <div class="form-container sign-in-container" id="login-container">
            <form id="login-form">
                <h2>Iniciar Sesión</h2>
                <div class="input-group" style="width: 100%;">
                    <label>Correo Electrónico:</label>
                    <input type="email" id="login-email" placeholder="Ej. usuario@itess.edu.mx" required>
                </div>
                <div class="input-group" style="width: 100%;">
                    <label>Contraseña:</label>
                    <div class="password-wrapper">
                        <input type="password" id="login-password" placeholder="••••••••" required>
                        <i class="ph ph-eye-slash toggle-password" data-target="login-password"></i>
                    </div>
                </div>
                <button type="submit">Iniciar Sesión</button>
                <div id="login-message" class="msg"></div>

                <div id="google-btn-container" style="display: flex; justify-content: center; margin-top: 15px;"></div>
                
                <p class="mobile-toggle" style="display:none; cursor:pointer; color:var(--verde-tecno); font-weight:bold; margin-top:15px; text-decoration: underline;" id="mobileSignUp">¿No tienes cuenta? Regístrate</p>
            </form>
        </div>

        <div class="form-container sign-in-container" id="two-factor-container" style="display:none; z-index: 10; background: #fdfaf3;">
            <form id="two-factor-form">
                <h2 style="margin-bottom: 10px;">Verificación 2 Pasos</h2>
                <p style="margin-bottom: 25px; color:#555;">Ingresa el código de 6 dígitos que enviamos a tu dispositivo.</p>
                <div class="input-group" style="width: 100%;">
                    <input type="text" id="tfa-code" placeholder="123456" maxlength="6" style="text-align:center; font-size: 1.5rem; letter-spacing: 5px; height: 50px;" required>
                </div>
                <button type="submit">Verificar</button>
                <div id="tfa-message" class="msg"></div>
                <button type="button" class="ghost" onclick="cancel2FA()" style="margin-top: 10px; font-size: 0.8rem; padding: 8px 20px;">Cancelar</button>
            </form>
        </div>


        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1 style="font-family: serif; font-size: 3.5rem;">!Hola!</h1>
                    <p style="font-size: 1.1rem;">Regístrate para comenzar con tu TecnoAgenda.</p>
                    <button class="ghost" id="signIn"
                        style="border: 2px solid #111; font-size: 1.1rem; padding: 10px 40px;">Iniciar Sesión</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1 style="font-family: serif; font-size: 3.5rem;">!Bienvenido!</h1>
                    <p style="font-size: 1.1rem;">Ingrese con sus datos para utilizar todas las funciones del sitio.</p>
                    <button class="ghost" id="signUp"
                        style="border: 2px solid #111; font-size: 1.1rem; padding: 10px 40px;">Registrarse</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>

        const signUpButton = document.getElementById('signUp');
        const signInButton = document.getElementById('signIn');
        const container = document.getElementById('container');
        const mobileSignUp = document.getElementById('mobileSignUp');
        const mobileSignIn = document.getElementById('mobileSignIn');

        signUpButton.addEventListener('click', () => {
            container.classList.add("right-panel-active");
        });

        signInButton.addEventListener('click', () => {
            container.classList.remove("right-panel-active");
        });

        if (mobileSignUp) {
            mobileSignUp.addEventListener('click', () => {
                container.classList.add("right-panel-active");
            });
        }
        
        if (mobileSignIn) {
            mobileSignIn.addEventListener('click', () => {
                container.classList.remove("right-panel-active");
            });
        }


        flatpickr("#reg-fechaNacimiento", {
            locale: "es",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            disableMobile: "true"
        });


        const regForm = document.getElementById('register-form');
        const regMessage = document.getElementById('reg-message');

        regForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            regMessage.textContent = '';
            regMessage.className = 'msg';

            const nombre = document.getElementById('reg-nombre').value.trim();
            const fechaNacimiento = document.getElementById('reg-fechaNacimiento').value;
            const rol = document.getElementById('reg-rol').value;
            const carrera = document.getElementById('reg-carrera').value.trim();
            const semestre = document.getElementById('reg-semestre').value;
            const email = document.getElementById('reg-email').value.trim();
            const password = document.getElementById('reg-password').value.trim();
            const confirm_password = document.getElementById('reg-confirm_password').value.trim();

            if (password !== confirm_password) {
                regMessage.textContent = 'Las contraseñas no coinciden.';
                regMessage.classList.add('error');
                return;
            }

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'register',
                        nombre, fechaNacimiento, rol, carrera, semestre, email, password
                    })
                });
                const result = await response.json();
                
                if (result.status === 'success') {
                    regMessage.textContent = result.message;
                    regMessage.classList.add('success');
                    setTimeout(() => {
                        container.classList.remove("right-panel-active");
                        regForm.reset();
                        regMessage.textContent = '';
                    }, 1500);
                } else {
                    regMessage.textContent = result.message;
                    regMessage.classList.add('error');
                }
            } catch (err) {
                regMessage.textContent = 'Error al conectar con el servidor.';
                regMessage.classList.add('error');
            }
        });


        const loginForm = document.getElementById('login-form');
        const loginMessage = document.getElementById('login-message');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            loginMessage.textContent = '';
            loginMessage.className = 'msg';

            const email = document.getElementById('login-email').value.trim();
            const password = document.getElementById('login-password').value.trim();

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', email, password })
                });
                const result = await response.json();

                if (result.status === 'success') {
                    loginMessage.textContent = result.message;
                    loginMessage.classList.add('success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else if (result.status === '2fa_required') {
                    document.getElementById('login-container').style.display = 'none';
                    document.getElementById('two-factor-container').style.display = 'flex';
                } else {
                    loginMessage.textContent = result.message;
                    loginMessage.classList.add('error');
                }
            } catch (err) {
                loginMessage.textContent = 'Error al conectar con el servidor.';
                loginMessage.classList.add('error');
            }
        });

        const tfaForm = document.getElementById('two-factor-form');
        const tfaMessage = document.getElementById('tfa-message');

        tfaForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            tfaMessage.textContent = '';
            tfaMessage.className = 'msg';
            
            const email = document.getElementById('login-email').value.trim();
            const code = document.getElementById('tfa-code').value.trim();

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'verify_2fa', email, code })
                });
                const result = await response.json();

                if (result.status === 'success') {
                    tfaMessage.textContent = result.message;
                    tfaMessage.classList.add('success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    tfaMessage.textContent = result.message;
                    tfaMessage.classList.add('error');
                }
            } catch (err) {
                tfaMessage.textContent = 'Error al conectar.';
                tfaMessage.classList.add('error');
            }
        });

        function cancel2FA() {
            document.getElementById('two-factor-container').style.display = 'none';
            document.getElementById('login-container').style.display = 'flex';
            document.getElementById('tfa-code').value = '';
            document.getElementById('tfa-message').textContent = '';
        }

        // Google Sign-In Callback
        async function handleCredentialResponse(response) {
            const loginMessage = document.getElementById('login-message');
            loginMessage.textContent = 'Autenticando con Google...';
            loginMessage.className = 'msg';
            
            try {
                const res = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'google_login', credential: response.credential })
                });
                const result = await res.json();
                
                if (result.status === 'success') {
                    loginMessage.textContent = result.message;
                    loginMessage.classList.add('success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    loginMessage.textContent = result.message;
                    loginMessage.classList.add('error');
                }
            } catch (err) {
                loginMessage.textContent = 'Error al conectar con el servidor.';
                loginMessage.classList.add('error');
            }
        }

        window.onload = function () {
            google.accounts.id.initialize({
                client_id: "TU_CLIENT_ID_DE_GOOGLE", // <-- PEGA TU CLIENT ID AQUI
                callback: handleCredentialResponse
            });
            google.accounts.id.renderButton(
                document.getElementById("google-btn-container"),
                { theme: "outline", size: "large", width: 280, text: "continue_with" }
            );
        };

        // Toggle Password Visibility
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                
                if (input.type === 'password') {
                    input.type = 'text';
                    this.classList.remove('ph-eye-slash');
                    this.classList.add('ph-eye');
                } else {
                    input.type = 'password';
                    this.classList.remove('ph-eye');
                    this.classList.add('ph-eye-slash');
                }
            });
        });
    </script>
</body>

</html>
