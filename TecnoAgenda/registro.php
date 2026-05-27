<?php
session_start();

// Si ya hay una sesión activa, redirigir al dashboard
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
// Configuración para el manejo de datos
$file = 'users.json';

// Si el archivo no existe, crearlo con un array vacío
if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
}

// Procesar la solicitud de registro si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Obtener los datos enviados por JS
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if ($data) {
        // Leer usuarios existentes
        $users = json_decode(file_get_contents($file), true);

        // Verificar si el correo ya existe
        foreach ($users as $user) {
            if ($user['email'] === $data['email']) {
                echo json_encode(['status' => 'error', 'message' => 'El correo ya está registrado.']);
                exit;
            }
        }

        // Agregar nuevo usuario
        $users[] = $data;

        // Guardar en el archivo
        if (file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT))) {
            echo json_encode(['status' => 'success', 'message' => '¡Registro exitoso! Redirigiendo al login...']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar los datos en el servidor.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Datos inválidos.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1200, initial-scale=0.35, maximum-scale=3.0, user-scalable=yes">
    <title>TecnoAgenda - Registro</title>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Arima:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
    <style>
        :root {
            --verde-tecno: #4d6650;
            --naranja-soft: #f3a65a;
            --crema-fondo: #fdfaf3;
            --gris-borde: #e0e0e0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Arima', system-ui, sans-serif;
            color: #000;
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Aclonica', sans-serif !important; font-weight: 400 !important; color: #000; }
        button, .btn { font-family: 'Arima', system-ui, sans-serif !important; font-weight: 700 !important; color: #000; }

        body {
            font-family: 'Arima', system-ui, sans-serif;
            background-color: #e7e3d7;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .container {
            background-color: var(--crema-fondo);
            width: 1000px;
            height: 550px;

            display: flex;
            flex-direction: row-reverse;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            opacity: 0;
            animation: fadeIn 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .container.fade-out {
            animation: fadeOut 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        .welcome-side {
            width: 40%;
            background-color: var(--naranja-soft);
            color: #111;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
        }

        .welcome-side h1 {
            font-size: 3.5rem;
            font-family: serif;
            margin-bottom: 15px;
            color: #111;
        }

        .welcome-side p {
            color: #222;
            font-weight: 500;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .form-side {
            width: 60%;
            padding: 15px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .header-logo h2 {
            color: var(--verde-tecno);
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0px;
        }

        .header-logo p {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 5px;
        }

        h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            font-weight: 600;
            color: #111;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 15px;
        }

        .full {
            grid-column: span 2;
        }

        .input-group {
            display: flex;
            flex-direction: column;
        }

        .input-group label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            color: var(--verde-tecno);
            margin-bottom: 2px;
            font-weight: 600;
        }

        input,
        select {
            padding: 6px 10px;
            border: 2px solid var(--gris-borde);
            border-radius: 8px;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            background-color: #fff;
            width: 100%;
            height: 38px;
        }

        input:focus,
        select:focus {
            border-color: var(--naranja-soft);
            box-shadow: 0 0 10px rgba(243, 166, 90, 0.2);
        }

        .btn-submit {
            background-color: var(--verde-tecno);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 5px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: #3b4e3e;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(77, 102, 80, 0.3);
        }

        .btn-ghost {
            background: transparent;
            border: 2px solid #111;
            padding: 12px 40px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1.05rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-ghost:hover {
            background: #111;
            color: var(--naranja-soft);
        }

        .msg {
            width: 100%;
            text-align: center;
            margin-top: 15px;
            font-size: 0.95rem;
            font-weight: 500;
            min-height: 20px;
            grid-column: span 2;
        }

        .error {
            color: #d9534f;
        }

        .success {
            color: #28a745;
        }

        @media (max-width: 850px) {
            .container {
                flex-direction: column;
            }

            .welcome-side,
            .form-side {
                width: 100%;
            }

            .welcome-side {
                padding: 30px 20px;
            }

            .form-side {
                padding: 30px 20px;
            }
        }

        @media (max-width: 500px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: span 1;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="form-side">
            <div class="header-logo">
                <h2>Tecnoagenda</h2>
            </div>
            <h3>Crear una cuenta</h3>
            <form class="form-grid" id="register-form">
                <div class="input-group full"><label for="nombre">Nombre completo</label><input type="text" id="nombre"
                        placeholder="Ej. Juan Pérez"></div>
                <div class="input-group"><label for="fechaNacimiento">Fecha de nacimiento</label><input type="text"
                        id="fechaNacimiento" placeholder="dd/mm/aaaa" readonly style="background-color: #fff;"></div>
                <div class="input-group"><label for="rol">Rol</label><select id="rol">
                        <option value="Estudiante">Estudiante</option>
                        <option value="Docente">Docente</option>
                        <option value="Tutor">Tutor</option>
                        <option value="Admin">Admin</option>
                    </select></div>
                <div class="input-group"><label for="carrera">Carrera</label><select id="carrera">
                        <option value="TICS">TICS</option>
                        <option value="Gestion">Gestion</option>
                        <option value="industrial">industrial</option>
                        <option value="Mecatronica">Mecatronica</option>
                        <option value="Automotris">Automotris</option>
                        <option value="Agricola">Agricola</option>
                    </select></div>
                <div class="input-group"><label for="semestre">Semestre</label><select id="semestre">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                        <option value="6">6</option>
                        <option value="7">7</option>
                        <option value="8">8</option>
                        <option value="9">9</option>
                    </select></div>
                <div class="input-group full"><label for="email">Correo electrónico</label><input type="email"
                        id="email" placeholder="usuario@itess.edu.mx"></div>
                <div class="input-group"><label for="password">Contraseña</label><input type="password" id="password"
                        placeholder="••••••••"></div>
                <div class="input-group"><label for="confirm_password">Confirmar Contraseña</label><input
                        type="password" id="confirm_password" placeholder="••••••••"></div>

                <div id="message" class="msg"></div>
                <button type="submit" class="btn-submit full">Crear Cuenta</button>
            </form>
        </div>
        <div class="welcome-side">
            <h1>!Hola!</h1>
            <p style="font-size: 1.2rem; margin-bottom: 30px;">Regístrate para comenzar con tu TecnoAgenda.</p>
            <button class="btn-ghost" id="btn-switch-login">Iniciar Sesión</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>
        document.getElementById('btn-switch-login').addEventListener('click', () => {
            document.querySelector('.container').classList.add('fade-out');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 450);
        });

        flatpickr("#fechaNacimiento", {
            locale: "es",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            disableMobile: "true"
        });
        const form = document.getElementById('register-form');
        const messageDiv = document.getElementById('message');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            messageDiv.textContent = '';
            messageDiv.className = 'msg';

            const nombre = document.getElementById('nombre').value.trim();
            const fechaNacimiento = document.getElementById('fechaNacimiento').value;
            const rol = document.getElementById('rol').value;
            const carrera = document.getElementById('carrera').value.trim();
            const semestre = document.getElementById('semestre').value;
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            const confirm_password = document.getElementById('confirm_password').value.trim();

            if (!nombre || !fechaNacimiento || !carrera || !email || !password || !confirm_password) {
                messageDiv.textContent = 'Por favor, llena todos los campos obligatorios.';
                messageDiv.classList.add('error');
                return;
            }

            if (password !== confirm_password) {
                messageDiv.textContent = 'Las contraseñas no coinciden.';
                messageDiv.classList.add('error');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                messageDiv.textContent = 'Ingresa un correo electrónico válido.';
                messageDiv.classList.add('error');
                return;
            }

            try {
                // Ahora enviamos los datos al servidor vía PHP
                const newUser = { nombre, fechaNacimiento, rol, carrera, semestre, email, password };
                
                const response = await fetch('registro.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(newUser)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    messageDiv.textContent = result.message;
                    messageDiv.classList.add('success');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1500);
                } else {
                    messageDiv.textContent = result.message;
                    messageDiv.classList.add('error');
                }

            } catch (error) {
                messageDiv.textContent = 'Error al conectar con el servidor.';
                messageDiv.classList.add('error');
            }
        });
    </script>
</body>

</html>
