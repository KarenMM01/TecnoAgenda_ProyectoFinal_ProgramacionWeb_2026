<?php
session_start();
require 'conexion.php';

// Redirigir si ya hay sesión activa
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

// -------------------------------------------------------
// PROCESAR POST (cambiar contraseña)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || $data['action'] !== 'reset_password') {
        echo json_encode(['status' => 'error', 'message' => 'Datos inválidos.']);
        exit;
    }

    $token    = trim($data['token']    ?? '');
    $password = trim($data['password'] ?? '');
    $confirm  = trim($data['confirm']  ?? '');

    if (empty($token) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
        exit;
    }

    if ($password !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden.']);
        exit;
    }

    // Verificar token válido y no expirado
    $stmt = $conexion->prepare("
        SELECT id FROM usuarios
        WHERE reset_token = :token
          AND reset_token_expires > NOW()
    ");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'El enlace ha expirado o no es válido. Solicita uno nuevo.']);
        exit;
    }

    // Actualizar contraseña y limpiar token
    $upd = $conexion->prepare("
        UPDATE usuarios
        SET password = :password,
            reset_token = NULL,
            reset_token_expires = NULL
        WHERE id = :id
    ");
    $upd->execute([':password' => $password, ':id' => $user['id']]);

    echo json_encode(['status' => 'success', 'message' => '¡Contraseña actualizada correctamente! Redirigiendo...']);
    exit;
}

// -------------------------------------------------------
// VALIDAR TOKEN PARA MOSTRAR FORMULARIO
// -------------------------------------------------------
$token = trim($_GET['token'] ?? '');
$tokenValido = false;

if ($token) {
    try {
        $stmt = $conexion->prepare("
            SELECT id FROM usuarios
            WHERE reset_token = :token
              AND reset_token_expires > NOW()
        ");
        $stmt->execute([':token' => $token]);
        $tokenValido = (bool) $stmt->fetch();
    } catch (Exception $e) {
        $tokenValido = false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoAgenda - Restablecer Contraseña</title>

    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Arima:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            --verde-tecno:   #7d9d85;
            --verde-sage:    #a3b899;
            --naranja-soft:  #f3a65a;
            --naranja-hover: #e89548;
            --crema-fondo:   #e7e3d7;
        }

        * {
            margin: 0; padding: 0;
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
            padding: 20px;
        }

        h1 { font-family: 'Aclonica', sans-serif; font-weight: 400; }
        h2 { font-family: 'Aclonica', sans-serif; font-weight: 400; }

        .card {
            background: #fdfaf3;
            border-radius: 25px;
            box-shadow: 0 14px 40px rgba(0,0,0,0.12), 0 6px 16px rgba(0,0,0,0.08);
            padding: 48px 44px;
            width: 100%;
            max-width: 460px;
            text-align: center;
            animation: fadeUp 0.5s ease;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .icon-wrap {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--crema-fondo);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            margin-bottom: 22px;
        }

        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .card p.subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 32px;
            line-height: 1.5;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            text-align: left;
            margin-bottom: 16px;
            width: 100%;
        }

        .input-group label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            width: 100%;
            padding-right: 42px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            cursor: pointer;
            font-size: 1.2rem;
            opacity: 0.55;
            transition: opacity 0.2s;
        }
        .toggle-password:hover { opacity: 1; }

        input {
            background: #eee;
            border: none;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 0.95rem;
            width: 100%;
            outline: none;
            transition: background 0.2s, box-shadow 0.2s;
        }
        input:focus {
            background: #e2dfd8;
            box-shadow: 0 0 0 3px rgba(125,157,133,0.25);
        }

        .btn-primary {
            width: 100%;
            background: var(--naranja-soft);
            border: 1px solid var(--naranja-soft);
            border-radius: 20px;
            padding: 13px;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s, transform 80ms ease-in;
        }
        .btn-primary:hover  { background: var(--naranja-hover); }
        .btn-primary:active { transform: scale(0.97); }
        .btn-primary:disabled { opacity: 0.65; cursor: not-allowed; }

        .msg {
            font-size: 0.88rem;
            margin-top: 14px;
            min-height: 20px;
            font-weight: 500;
        }
        .error   { color: #d9534f; }
        .success { color: #28a745; }

        /* ---- Estado: token inválido ---- */
        .state-invalid .icon-wrap { background: #fdecea; }
        .back-link {
            display: inline-block;
            margin-top: 22px;
            color: var(--verde-tecno);
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: #5a7d63; }

        /* ---- Estado: éxito ---- */
        .state-success .icon-wrap { background: #e6f4ea; }

        /* Strength bar */
        .strength-bar-wrap {
            width: 100%;
            height: 4px;
            background: #ddd;
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }
        .strength-bar {
            height: 100%;
            border-radius: 2px;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }
        .strength-label {
            font-size: 0.72rem;
            margin-top: 3px;
            text-align: right;
            color: #999;
        }
    </style>
</head>
<body>

<?php if (!$token): ?>
<!-- ====== SIN TOKEN ====== -->
<div class="card state-invalid">
    <div class="icon-wrap">⚠️</div>
    <h2>Enlace inválido</h2>
    <p class="subtitle">No se encontró ningún token de restablecimiento. Por favor solicita un nuevo enlace desde la página de inicio de sesión.</p>
    <a href="login.php" class="back-link">← Volver al inicio de sesión</a>
</div>

<?php elseif (!$tokenValido): ?>
<!-- ====== TOKEN EXPIRADO / NO ENCONTRADO ====== -->
<div class="card state-invalid">
    <div class="icon-wrap">⏰</div>
    <h2>Enlace expirado</h2>
    <p class="subtitle">Este enlace de restablecimiento ya no es válido o ha expirado (duración: 1 hora). Solicita uno nuevo.</p>
    <a href="login.php" class="back-link">← Solicitar nuevo enlace</a>
</div>

<?php else: ?>
<!-- ====== FORMULARIO RESTABLECER ====== -->
<div class="card" id="reset-card">
    <div class="icon-wrap">🔐</div>
    <h2>Nueva contraseña</h2>
    <p class="subtitle">Elige una contraseña segura para tu cuenta de TecnoAgenda.</p>

    <form id="reset-form" autocomplete="off">
        <input type="hidden" id="reset-token" value="<?= htmlspecialchars($token) ?>">

        <div class="input-group">
            <label>Nueva contraseña</label>
            <div class="password-wrapper">
                <input type="password" id="new-password" placeholder="Mínimo 6 caracteres" required oninput="checkStrength(this.value)">
                <i class="ph ph-eye-slash toggle-password" data-target="new-password"></i>
            </div>
            <div class="strength-bar-wrap">
                <div class="strength-bar" id="strength-bar"></div>
            </div>
            <span class="strength-label" id="strength-label"></span>
        </div>

        <div class="input-group">
            <label>Confirmar contraseña</label>
            <div class="password-wrapper">
                <input type="password" id="confirm-password" placeholder="Repite tu contraseña" required>
                <i class="ph ph-eye-slash toggle-password" data-target="confirm-password"></i>
            </div>
        </div>

        <button type="submit" class="btn-primary" id="reset-btn">Restablecer contraseña</button>
        <div id="reset-message" class="msg"></div>
    </form>

    <a href="login.php" class="back-link">← Volver al inicio de sesión</a>
</div>

<!-- ====== ESTADO ÉXITO (oculto al inicio) ====== -->
<div class="card state-success" id="success-card" style="display:none;">
    <div class="icon-wrap">✅</div>
    <h2>¡Listo!</h2>
    <p class="subtitle">Tu contraseña ha sido actualizada correctamente. Ya puedes iniciar sesión con tu nueva contraseña.</p>
    <a href="login.php" class="back-link" style="
        display: inline-block;
        background: var(--verde-tecno);
        color: #fff;
        padding: 12px 30px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 700;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        margin-top: 20px;
        transition: background 0.2s;
    ">Ir al inicio de sesión</a>
</div>
<?php endif; ?>

<script>
    // ---- Toggle de contraseñas ----
    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', function () {
            const input = document.getElementById(this.getAttribute('data-target'));
            if (input.type === 'password') {
                input.type = 'text';
                this.classList.replace('ph-eye-slash', 'ph-eye');
            } else {
                input.type = 'password';
                this.classList.replace('ph-eye', 'ph-eye-slash');
            }
        });
    });

    // ---- Indicador de fortaleza ----
    function checkStrength(val) {
        const bar   = document.getElementById('strength-bar');
        const label = document.getElementById('strength-label');
        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { pct: '0%',   color: '#ddd',    text: '' },
            { pct: '25%',  color: '#e74c3c', text: 'Muy débil' },
            { pct: '50%',  color: '#e67e22', text: 'Débil' },
            { pct: '70%',  color: '#f1c40f', text: 'Aceptable' },
            { pct: '85%',  color: '#2ecc71', text: 'Fuerte' },
            { pct: '100%', color: '#27ae60', text: 'Muy fuerte' },
        ];

        const lvl = levels[Math.min(score, 5)];
        bar.style.width      = lvl.pct;
        bar.style.background = lvl.color;
        label.textContent    = lvl.text;
        label.style.color    = lvl.color;
    }

    // ---- Submit del formulario ----
    const form = document.getElementById('reset-form');
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const msgEl    = document.getElementById('reset-message');
            const btn      = document.getElementById('reset-btn');
            const token    = document.getElementById('reset-token').value;
            const password = document.getElementById('new-password').value.trim();
            const confirm  = document.getElementById('confirm-password').value.trim();

            msgEl.textContent = '';
            msgEl.className   = 'msg';

            if (password !== confirm) {
                msgEl.textContent = 'Las contraseñas no coinciden.';
                msgEl.className   = 'msg error';
                return;
            }

            btn.disabled    = true;
            btn.textContent = 'Actualizando...';

            try {
                const res = await fetch('reset_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset_password', token, password, confirm })
                });
                const result = await res.json();

                if (result.status === 'success') {
                    document.getElementById('reset-card').style.display   = 'none';
                    document.getElementById('success-card').style.display = 'block';
                    setTimeout(() => { window.location.href = 'login.php'; }, 3000);
                } else {
                    msgEl.textContent = result.message;
                    msgEl.className   = 'msg error';
                    btn.disabled    = false;
                    btn.textContent = 'Restablecer contraseña';
                }
            } catch (err) {
                msgEl.textContent = 'Error al conectar con el servidor.';
                msgEl.className   = 'msg error';
                btn.disabled    = false;
                btn.textContent = 'Restablecer contraseña';
            }
        });
    }
</script>
</body>
</html>
