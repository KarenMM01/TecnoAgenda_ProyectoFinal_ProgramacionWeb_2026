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
    // OLVIDÉ MI CONTRASEÑA — Generar token
    // =========================
    if ($data['action'] === 'forgot_password') {
        $email = trim($data['email'] ?? '');

        if (empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Ingresa un correo válido.']);
            exit;
        }

        // Verificar que el correo exista
        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'No existe ninguna cuenta con ese correo.']);
            exit;
        }

        // Asegurarse de que las columnas existen (ejecutar una vez)
        try {
            $conexion->exec("
                ALTER TABLE usuarios
                ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64),
                ADD COLUMN IF NOT EXISTS reset_token_expires TIMESTAMP
            ");
        } catch (Exception $e) { /* columnas ya existen */ }

        // Generar token seguro
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $upd = $conexion->prepare("
            UPDATE usuarios
            SET reset_token = :token, reset_token_expires = :expires
            WHERE email = :email
        ");
        $upd->execute([':token' => $token, ':expires' => $expires, ':email' => $email]);

        // Construir link
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $dir  = dirname($_SERVER['REQUEST_URI']);
        $link = $protocol . '://' . $host . $dir . '/reset_password.php?token=' . $token;

        echo json_encode([
            'status'  => 'success',
            'message' => '¡Enlace generado! Cópialo y ábrelo en el navegador.',
            'link'    => $link
        ]);
        exit;
    }

    // =========================
    // LOGIN GOOGLE
    // =========================
    if ($data['action'] === 'google_login') {
        $token = $data['credential'];
        
        // Intentar como Access Token (oauth2)
        $response = @file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . $token);
        
        // Si falla, intentar como ID Token (flujo viejo)
        if (!$response) {
            $response = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . $token);
        }
        
        if ($response) {
            $payload = json_decode($response, true);
            if (isset($payload['email'])) {
                $email = $payload['email'];
                $nombre = $payload['name'] ?? 'Usuario';
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
        /* === Modal Forgot Password === */
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.85); }
            to   { opacity: 1; transform: scale(1); }
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
                <!-- Botón olvidé mi contraseña -->
                <button type="button" id="forgot-btn" style="
                    background: transparent;
                    border: none;
                    padding: 4px 0;
                    margin-top: 2px;
                    font-size: 0.8rem;
                    font-weight: 500;
                    color: #555;
                    text-decoration: underline;
                    cursor: pointer;
                    letter-spacing: 0;
                    text-transform: none;
                    transition: color 0.2s;
                " onmouseover="this.style.color='#000'" onmouseout="this.style.color='#555'">¿Olvidaste tu contraseña?</button>
                <button type="submit">Iniciar Sesión</button>
                <div id="login-message" class="msg"></div>

                <!-- Botón Google estilo link -->
                <button type="button" id="google-custom-btn" onclick="signInWithGoogle()" style="
                    background: transparent;
                    border: none;
                    padding: 5px 0;
                    margin-top: 6px;
                    font-size: 0.82rem;
                    font-weight: 500;
                    color: #555;
                    cursor: pointer;
                    letter-spacing: 0;
                    text-transform: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 7px;
                    transition: color 0.2s;
                " onmouseover="this.style.color='#000'" onmouseout="this.style.color='#555'">
                    <!-- Logo Google SVG oficial -->
                    <svg width="16" height="16" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                        <path fill="none" d="M0 0h48v48H0z"/>
                    </svg>
                    Iniciar sesión con Google
                </button>
                
                <p class="mobile-toggle" style="display:none; cursor:pointer; color:var(--verde-tecno); font-weight:bold; margin-top:15px; text-decoration: underline;" id="mobileSignUp">¿No tienes cuenta? Regístrate</p>
            </form>
        </div>

        <!-- ===== MODAL: OLVIDÉ MI CONTRASEÑA ===== -->
        <div id="forgot-modal" style="
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        ">
            <div style="
                background: #fdfaf3;
                border-radius: 20px;
                padding: 40px 36px;
                max-width: 420px;
                width: 90%;
                box-shadow: 0 20px 60px rgba(0,0,0,0.25);
                position: relative;
                animation: popIn 0.3s cubic-bezier(.175,.885,.32,1.275);
            ">
                <!-- Cerrar -->
                <button type="button" onclick="closeForgotModal()" style="
                    position: absolute; top: 14px; right: 16px;
                    background: transparent; border: none; font-size: 1.4rem;
                    cursor: pointer; color: #888; padding: 0; margin: 0;
                    line-height: 1; text-transform: none; letter-spacing: 0;
                ">×</button>

                <!-- Ícono -->
                <div style="text-align:center; margin-bottom: 18px;">
                    <div style="
                        width: 64px; height: 64px;
                        background: var(--crema-fondo);
                        border-radius: 50%;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 2rem;
                    ">🔑</div>
                </div>

                <h2 style="text-align:center; font-size: 1.3rem; margin-bottom: 8px;">¿Olvidaste tu contraseña?</h2>
                <p style="text-align:center; font-size: 0.85rem; color: #666; margin: 0 0 22px;">Ingresa tu correo registrado. Si existe, abriremos la página para restablecer tu contraseña.</p>

                <div class="input-group" style="width: 100%;">
                    <label>Correo electrónico</label>
                    <input type="email" id="forgot-email" placeholder="usuario@itess.edu.mx" onkeydown="if(event.key==='Enter'){submitForgotPassword();}">
                </div>

                <button type="button" id="forgot-submit-btn" onclick="submitForgotPassword()" style="width: 100%; margin-top: 16px;">Confirmar y continuar</button>

                <div id="forgot-message" class="msg" style="margin-top: 12px; text-align:center;"></div>
            </div>
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

        // ===== FORGOT PASSWORD =====
        document.getElementById('forgot-btn').addEventListener('click', function () {
            const modal = document.getElementById('forgot-modal');
            modal.style.display = 'flex';
            // Pre-rellenar si ya hay un correo escrito
            const emailVal = document.getElementById('login-email').value.trim();
            if (emailVal) document.getElementById('forgot-email').value = emailVal;
            document.getElementById('forgot-message').textContent = '';
            document.getElementById('forgot-message').className = 'msg';
            document.getElementById('forgot-link-area').style.display = 'none';
            document.getElementById('copy-confirm').style.display = 'none';
        });

        function closeForgotModal() {
            document.getElementById('forgot-modal').style.display = 'none';
            document.getElementById('forgot-email').value = '';
            document.getElementById('forgot-message').textContent = '';
            document.getElementById('forgot-link-area').style.display = 'none';
        }

        // Cerrar modal al hacer click fuera
        document.getElementById('forgot-modal').addEventListener('click', function (e) {
            if (e.target === this) closeForgotModal();
        });

        async function submitForgotPassword() {
            const email = document.getElementById('forgot-email').value.trim();
            const msgEl = document.getElementById('forgot-message');
            const btn   = document.getElementById('forgot-submit-btn');

            if (!email) {
                msgEl.textContent = 'Por favor ingresa tu correo.';
                msgEl.className = 'msg error';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Verificando...';
            msgEl.textContent = '';
            msgEl.className = 'msg';

            try {
                const res = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'forgot_password', email })
                });
                const result = await res.json();

                if (result.status === 'success' && result.link) {
                    // Correo encontrado → abrir automáticamente en nueva pestaña
                    msgEl.className = 'msg success';
                    msgEl.textContent = '✓ Correo verificado. Redirigiendo...';
                    setTimeout(() => {
                        window.location.href = result.link;
                    }, 800);
                } else {
                    // Correo no existe u otro error
                    msgEl.className = 'msg error';
                    msgEl.textContent = result.message;
                    // Sacudir el input para feedback visual
                    const input = document.getElementById('forgot-email');
                    input.style.transition = 'box-shadow 0.1s';
                    input.style.boxShadow = '0 0 0 3px rgba(217,83,79,0.35)';
                    setTimeout(() => input.style.boxShadow = '', 1200);
                }
            } catch (err) {
                msgEl.className = 'msg error';
                msgEl.textContent = 'Error al conectar con el servidor.';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Confirmar y continuar';
            }
        }

        // Google Sign-In Callback
        async function handleCredentialResponse(response) {
            const loginMessage = document.getElementById('login-message');
            loginMessage.textContent = 'Autenticando con Google...';
            loginMessage.className = 'msg';
            
            try {
                // response.access_token is what we get from initTokenClient
                const token = response.access_token || response.credential;
                
                const res = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'google_login', credential: token })
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

        let tokenClient;
        window.onload = function () {
            try {
                // Usamos initTokenClient para poder usar un botón personalizado y abrir el popup
                tokenClient = google.accounts.oauth2.initTokenClient({
                    client_id: "352598767449-s6g7a6s9hj1bs42iimfejmtblpvurase.apps.googleusercontent.com", // Tu Client ID
                    scope: 'email profile',
                    callback: handleCredentialResponse
                });
            } catch(e) {
                console.warn('Google Identity Services no pudo inicializarse:', e);
            }
        };

        function signInWithGoogle() {
            try {
                // Esto abre el popup oficial de Google
                tokenClient.requestAccessToken();
            } catch(e) {
                console.error("Google Auth Error:", e);
                const loginMessage = document.getElementById('login-message');
                loginMessage.textContent = 'Error Google: ' + (e.message || e);
                loginMessage.className = 'msg error';
            }
        }

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
