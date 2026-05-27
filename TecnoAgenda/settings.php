<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$file = 'users.json';

// Manejador de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['action']) && $data['action'] === 'update_settings') {
        $users = json_decode(file_get_contents($file), true);
        $updated = false;
        foreach ($users as &$u) {
            if ($u['email'] === $user['email']) {
                $u['nombre'] = $data['nombre'];
                $u['email'] = $data['email'];
                if (!empty($data['password'])) {
                    $u['password'] = $data['password'];
                }
                $u['two_factor'] = $data['two_factor'];
                $u['report_frequency'] = $data['report_frequency'];
                $u['theme'] = $data['theme'];
                
                $_SESSION['user'] = $u;
                $updated = true;
                break;
            }
        }
        if ($updated) {
            file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
            echo json_encode(['status' => 'success', 'message' => 'Configuración guardada correctamente.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar la configuración.']);
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
    <title>Configuración - TecnoAgenda</title>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Arima:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --verde-tecno: #7d9d85;
            --verde-oscuro: #4e6a55;
            --naranja-soft: #f3a65a;
            --crema-fondo: #fbf6ec;
            --crema-card: #f5f1e8;
            --text-color: #000;
            --sidebar-width: 250px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arima', system-ui, sans-serif; color: #000; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Aclonica', sans-serif !important; font-weight: 400 !important; color: #000; }
        button, .btn { font-family: 'Arima', system-ui, sans-serif !important; font-weight: 700 !important; color: #000; }
        body { background-color: var(--crema-fondo); display: flex; height: 100vh; overflow: hidden; }

        /* === SIDEBAR (idéntico al dashboard) === */
        .sidebar { width: var(--sidebar-width); background-color: var(--verde-tecno); display: flex; flex-direction: column; padding-top: 15px; z-index: 10; transition: width 0.3s; overflow: hidden; }
        .sidebar.collapsed { width: 70px; }
        .sidebar.collapsed .nav-text { display: none; }
        .sidebar.collapsed .sidebar-logo-text { display: none; }
        .menu-btn-container { padding: 10px 20px 10px 18px; cursor: pointer; font-size: 1.6rem; color: #111; }
        .sidebar-icons { display: flex; flex-direction: column; flex: 1; }
        .sidebar-bottom { padding-bottom: 20px; }
        .nav-item { display: flex; align-items: center; width: 100%; padding: 12px 20px; cursor: pointer; transition: background 0.2s; color: #111; text-decoration: none; white-space: nowrap; }
        .nav-item:hover, .nav-item.active-nav { background-color: rgba(0, 0, 0, 0.07); border-radius: 0; }
        .nav-item i { font-size: 1.5rem; min-width: 32px; text-align: center; }
        .nav-text { margin-left: 16px; font-size: 1rem; font-weight: 500; }

        .main-container { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        /* Topbar con búsqueda */
        .topbar { height: 70px; padding: 0 40px; display: flex; justify-content: space-between; align-items: center; background: transparent; }
        .search-bar { background: #f0ebe0; border-radius: 20px; padding: 8px 20px; display: flex; align-items: center; gap: 10px; width: 400px; }
        .search-bar input { border: none; background: transparent; outline: none; width: 100%; font-size: 0.9rem; }
        .topbar-right { display: flex; gap: 20px; align-items: center; font-size: 1.2rem; }
        .topbar-right img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }

        .content { padding: 0 40px 40px 40px; max-width: 1200px; margin: 0 auto; width: 100%; }
        
        .page-header { margin-bottom: 35px; }
        .page-header h1 { font-family: 'Bangers'; font-size: 2.8rem; color: #111; letter-spacing: 1px; }
        .page-header p { color: #888; font-size: 1rem; }

        .settings-layout { display: grid; grid-template-columns: 300px 1fr; gap: 40px; }
        
        /* Columna Izquierda */
        .side-col { display: flex; flex-direction: column; gap: 25px; }
        
        .user-card { background: var(--crema-card); border-radius: 25px; padding: 35px 25px; text-align: center; border: 1px solid rgba(0,0,0,0.03); }
        .user-avatar-large { width: 100px; height: 100px; border-radius: 50%; border: 3px solid var(--verde-oscuro); margin: 0 auto 20px; object-fit: cover; }
        .user-card h3 { font-size: 1.4rem; color: #111; margin-bottom: 5px; }
        .user-card p { font-size: 0.9rem; color: #888; margin-bottom: 25px; }
        .sync-info { font-size: 0.7rem; color: #999; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px; display: block; }
        .btn-outline { background: transparent; border: 1.5px solid #dcd8cc; padding: 10px; border-radius: 12px; width: 100%; font-weight: 600; color: #555; cursor: pointer; }

        .plan-card { background: var(--naranja-soft); border-radius: 25px; padding: 25px; color: #333; position: relative; }
        .plan-card h4 { font-family: 'Bangers'; font-size: 1.2rem; margin-bottom: 10px; text-transform: uppercase; }
        .plan-card p { font-size: 0.75rem; line-height: 1.4; margin-bottom: 20px; opacity: 0.8; font-weight: 600; }
        .btn-white { background: rgba(255,255,255,0.4); border: none; padding: 12px; border-radius: 12px; width: 100%; font-weight: 700; cursor: pointer; color: #333; }

        /* Columna Derecha */
        .main-col { display: flex; flex-direction: column; gap: 20px; }
        
        .settings-section { background: white; border-radius: 25px; border: 1px solid rgba(0,0,0,0.02); overflow: hidden; }
        .section-header { padding: 20px 25px; display: flex; align-items: center; gap: 12px; background: #fdfdfd; }
        .section-header i { color: #4e6a55; font-size: 1.1rem; }
        .section-header h4 { font-size: 0.95rem; font-weight: 700; color: #111; }
        
        .section-body { padding: 25px; border-top: 1px solid #f9f9f9; }
        
        /* Aspecto */
        .theme-switch { background: #f5f1e8; padding: 5px; border-radius: 15px; display: inline-flex; gap: 5px; }
        .theme-btn { padding: 8px 20px; border-radius: 12px; border: none; font-size: 0.8rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .theme-btn.active { background: #4e6a55; color: white; box-shadow: 0 4px 10px rgba(78,106,85,0.3); }
        .theme-btn:not(.active) { background: transparent; color: #888; }
        
        .setting-row { display: flex; justify-content: space-between; align-items: center; }
        .setting-info h5 { font-size: 1rem; color: #333; margin-bottom: 4px; }
        .setting-info p { font-size: 0.75rem; color: #999; }
        
        /* Seguridad */
        .password-link { color: #888; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 1.2rem; }
        
        /* Switch */
        .switch { position: relative; display: inline-block; width: 45px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #eee; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #7d9d85; }
        input:checked + .slider:before { transform: translateX(21px); }

        /* Reportes */
        .freq-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .freq-btn { background: white; border: 1.5px solid #eee; padding: 15px; border-radius: 15px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .freq-btn.active { background: #d4e3d8; border-color: #4e6a55; color: #4e6a55; }

        /* Perfil */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { font-size: 0.7rem; font-weight: 700; color: #333; text-transform: uppercase; }
        .input-group input { background: #f5f1e8; border: none; padding: 15px; border-radius: 12px; font-size: 0.95rem; outline: none; }
        .btn-save-settings { background: #4e6a55; color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 600; cursor: pointer; margin-top: 20px; align-self: flex-start; transition: opacity 0.2s; }
        .btn-save-settings:hover { opacity: 0.9; }

        .fab-settings { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; background: #8a4b08; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; cursor: pointer; box-shadow: 0 10px 30px rgba(138,75,8,0.3); }
        
        .alert-toast { position: fixed; top: 20px; right: 20px; background: #4e6a55; color: white; padding: 15px 30px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); display: none; z-index: 2000; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }

        /* === DARK THEME === */
        body.dark-theme {
            --crema-fondo: #121212;
            --crema-card: #1e1e1e;
            --text-color: #f0f0f0;
        }
        body.dark-theme h1, body.dark-theme h2, body.dark-theme h3, body.dark-theme h4, body.dark-theme h5 {
            color: #f0f0f0 !important;
        }
        body.dark-theme p, body.dark-theme span { color: #aaa; }
        body.dark-theme .settings-section { background: #1e1e1e; border-color: #333; }
        body.dark-theme .section-header { background: #252525; }
        body.dark-theme .section-body { border-top-color: #333; }
        body.dark-theme .theme-switch { background: #333; }
        body.dark-theme .theme-btn:not(.active) { color: #888; }
        body.dark-theme .input-group input, body.dark-theme .search-bar { background: #333; color: #fff; }
        body.dark-theme .freq-btn { background: #333; border-color: #444; color: #f0f0f0; }
        body.dark-theme .freq-btn.active { background: #4e6a55; color: white; border-color: #4e6a55; }
        body.dark-theme .topbar-right i { color: #fff !important; }
        body.dark-theme .plan-card { background: #4e6a55; color: #fff; }
        body.dark-theme .plan-card h4 { color: #fff; }
        body.dark-theme .plan-card p { color: #eee; }
        body.dark-theme .btn-white { background: #f3a65a; color: #111; }
        /* Mobile Responsive Layout */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: 65px; flex-direction: row; padding: 0; justify-content: space-around; z-index: 1000; position: fixed; bottom: 0; }
            .sidebar-bottom { display: none; }
            .sidebar-icons { flex-direction: row; width: 100%; justify-content: space-around; display: flex; }
            .nav-item { padding: 10px; justify-content: center; flex: 1; }
            .nav-text, .menu-btn-container { display: none !important; }
            .sidebar.collapsed { width: 100%; }
            .main-container { padding-bottom: 65px; height: 100vh; }
            .topbar { padding: 0 15px; height: 60px; }
            .search-bar { display: none; }
            .content { padding: 20px; }
            .page-header h1 { font-size: 2rem; }
            .settings-layout { grid-template-columns: 1fr; gap: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .freq-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="<?php echo ($user['theme'] ?? 'Claro') === 'Oscuro' ? 'dark-theme' : ''; ?>">
    <!-- Modal: Mejorar Plan -->
    <div id="plan-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:3000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:25px; padding:40px; max-width:460px; width:90%; position:relative;">
            <button onclick="document.getElementById('plan-modal').style.display='none'" style="position:absolute; top:15px; right:20px; border:none; background:none; font-size:1.6rem; cursor:pointer; color:#aaa;">&times;</button>
            <div style="text-align:center; margin-bottom:25px;">
                <div style="width:70px; height:70px; background:#f3a65a; border-radius:20px; display:flex; align-items:center; justify-content:center; font-size:2rem; margin:0 auto 15px;">&#x1F31F;</div>
                <h2 style="font-family:'Bangers'; font-size:2rem; color:#111;">Mejorar a Plan Pro</h2>
                <p style="color:#888; font-size:0.9rem; margin-top:8px;">Desbloquea todas las funciones avanzadas de TecnoAgenda.</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:25px;">
                <div style="display:flex; align-items:center; gap:12px; padding:15px; background:#f5f1e8; border-radius:15px;">
                    <i class="fas fa-check-circle" style="color:#4e6a55; font-size:1.2rem;"></i>
                    <div><strong style="font-size:0.9rem;">Sesiones Ilimitadas</strong><p style="font-size:0.75rem; color:#888;">Sin límite de asesorías mensuales.</p></div>
                </div>
                <div style="display:flex; align-items:center; gap:12px; padding:15px; background:#f5f1e8; border-radius:15px;">
                    <i class="fas fa-check-circle" style="color:#4e6a55; font-size:1.2rem;"></i>
                    <div><strong style="font-size:0.9rem;">Análisis Avanzados</strong><p style="font-size:0.75rem; color:#888;">Reportes detallados de rendimiento.</p></div>
                </div>
                <div style="display:flex; align-items:center; gap:12px; padding:15px; background:#f5f1e8; border-radius:15px;">
                    <i class="fas fa-check-circle" style="color:#4e6a55; font-size:1.2rem;"></i>
                    <div><strong style="font-size:0.9rem;">Soporte Prioritario</strong><p style="font-size:0.75rem; color:#888;">Atención 24/7 con respuesta inmediata.</p></div>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <button onclick="confirmUpgrade()" style="flex:1; background:#4e6a55; color:white; border:none; padding:15px; border-radius:15px; font-weight:700; font-size:1rem; cursor:pointer;">Renovar - $9.99/mes</button>
                <button onclick="document.getElementById('plan-modal').style.display='none'" style="background:#f5f1e8; border:none; padding:15px 20px; border-radius:15px; cursor:pointer; font-weight:600; color:#555;">Después</button>
            </div>
        </div>
    </div>

    <!-- Modal: Perfil Público -->
    <div id="profile-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:3000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:25px; padding:40px; max-width:400px; width:90%; position:relative; text-align:center;">
            <button onclick="document.getElementById('profile-modal').style.display='none'" style="position:absolute; top:15px; right:20px; border:none; background:none; font-size:1.6rem; cursor:pointer; color:#aaa;">&times;</button>
            <img id="modal-avatar" src="<?php echo $user['avatar'] ?? 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>" style="width:90px; height:90px; border-radius:50%; border:3px solid #4e6a55; object-fit:cover; margin-bottom:15px;">
            <h3 id="modal-nombre" style="font-size:1.5rem; color:#111;"><?php echo htmlspecialchars($user['nombre']); ?></h3>
            <p style="color:#888; margin-bottom:5px;"><?php echo htmlspecialchars($user['rol'] ?? 'Usuario'); ?></p>
            <p style="color:#aaa; font-size:0.8rem; margin-bottom:25px;"><?php echo htmlspecialchars($user['email']); ?></p>
            <div style="background:#f5f1e8; border-radius:15px; padding:15px; text-align:left;">
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;"><span style="font-size:0.8rem; color:#888;">Miembro desde</span><span style="font-size:0.8rem; font-weight:600;">2024</span></div>
                <div style="display:flex; justify-content:space-between;"><span style="font-size:0.8rem; color:#888;">Plan activo</span><span style="font-size:0.8rem; font-weight:600; color:#f3a65a;">Premium</span></div>
            </div>
        </div>
    </div>

    <nav class="sidebar" id="sidebar">
        <div class="menu-btn-container" id="menu-btn"><i class="fas fa-bars"></i></div>
        <div class="sidebar-icons">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i><span class="nav-text">Inicio</span>
            </a>
            <a href="dashboard.php#materiales" class="nav-item">
                <i class="fas fa-book-open"></i><span class="nav-text">Materiales</span>
            </a>
            <a href="dashboard.php#calendario" class="nav-item">
                <i class="fas fa-calendar-alt"></i><span class="nav-text">Calendario</span>
            </a>
            <a href="dashboard.php#chats" class="nav-item">
                <i class="fas fa-envelope"></i><span class="nav-text">Chats</span>
            </a>
            <a href="dashboard.php#llamadas" class="nav-item">
                <i class="fas fa-phone"></i><span class="nav-text">Llamadas</span>
            </a>
            <a href="dashboard.php#notificaciones" class="nav-item">
                <i class="fas fa-bell"></i><span class="nav-text">Notificaciones</span>
            </a>
            <?php if (($user['rol'] ?? '') === 'Administrador'): ?>
            <a href="dashboard.php#admin" class="nav-item">
                <i class="fas fa-users-cog"></i><span class="nav-text">Panel Admin</span>
            </a>
            <?php endif; ?>
        </div>
        <div class="sidebar-bottom">
            <a href="settings.php" class="nav-item active-nav">
                <i class="fas fa-cog"></i><span class="nav-text">Ajustes</span>
            </a>
        </div>
    </nav>

    <div class="main-container">
        <div class="topbar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Busqueda de Persona">
            </div>
            <div class="topbar-right">
                <i class="far fa-bell" style="cursor:pointer;"></i>
                <i class="fas fa-cog" style="cursor:pointer; color: var(--verde-oscuro);"></i>
                <img src="<?php echo $user['avatar'] ?? 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>" alt="Avatar">
            </div>
        </div>

        <div class="content">
            <div class="page-header">
                <h1>Configuración</h1>
                <p>Gestiona tu cuenta y preferencias de sistema</p>
            </div>

            <div class="settings-layout">
                <!-- Columna Izquierda -->
                <div class="side-col">
                    <div class="user-card">
                        <img src="<?php echo $user['avatar'] ?? 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>" class="user-avatar-large">
                        <h3><?php echo htmlspecialchars($user['nombre']); ?></h3>
                        <p><?php echo htmlspecialchars($user['rol'] ?? 'Usuario'); ?></p>
                        <span class="sync-info">Sincronizado Hace 2 min</span>
                        <button class="btn-outline" onclick="document.getElementById('profile-modal').style.display='flex'">Ver Perfil Público</button>
                    </div>

                    <div class="plan-card">
                        <h4>Plan Premium</h4>
                        <p>Expira en 14 días. Renueva para mantener el acceso a Insights avanzados.</p>
                        <button class="btn-white" onclick="document.getElementById('plan-modal').style.display='flex'">Renovar Ahora</button>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="main-col">
                    <!-- Aspecto -->
                    <div class="settings-section">
                        <div class="section-header">
                            <i class="fas fa-palette"></i>
                            <h4>Aspecto</h4>
                        </div>
                        <div class="section-body">
                            <div class="setting-row">
                                <div class="setting-info">
                                    <h5>Cambiar de Modo</h5>
                                    <p>Alternar entre tema claro y oscuro</p>
                                </div>
                                <div class="theme-switch">
                                    <button class="theme-btn theme-option <?php echo ($user['theme'] ?? 'Claro') == 'Claro' ? 'active' : ''; ?>" data-theme="Claro">
                                        <i class="fas fa-sun"></i> Claro
                                    </button>
                                    <button class="theme-btn theme-option <?php echo ($user['theme'] ?? 'Claro') == 'Oscuro' ? 'active' : ''; ?>" data-theme="Oscuro">
                                        <i class="fas fa-moon"></i> Oscuro
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seguridad -->
                    <div class="settings-section">
                        <div class="section-header">
                            <i class="fas fa-shield-halved"></i>
                            <h4>Seguridad</h4>
                        </div>
                        <div class="section-body">
                            <div class="setting-row" style="margin-bottom: 25px;">
                                <div class="setting-info">
                                    <h5>Cambiar Contraseña</h5>
                                    <p>Actualiza tus credenciales de acceso</p>
                                </div>
                                <a href="#" class="password-link" onclick="togglePasswordInput()"><i class="fas fa-chevron-right"></i></a>
                            </div>
                            <div id="password-input-group" style="display:none; margin-bottom: 25px;">
                                <div class="input-group">
                                    <label>Nueva Contraseña</label>
                                    <input type="password" id="new-password" placeholder="••••••••">
                                </div>
                            </div>
                            <div class="setting-row">
                                <div class="setting-info">
                                    <h5>Autenticación de dos pasos</h5>
                                    <p style="color: <?php echo ($user['two_factor'] ?? false) ? '#4e6a55' : '#888'; ?>;">
                                        <?php echo ($user['two_factor'] ?? false) ? 'Activado' : 'Desactivado'; ?>
                                    </p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="two-factor" <?php echo ($user['two_factor'] ?? false) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Reportes -->
                    <?php if ($user['rol'] !== 'Estudiante'): ?>
                    <div class="settings-section">
                        <div class="section-header">
                            <i class="fas fa-file-lines"></i>
                            <h4>Reportes</h4>
                        </div>
                        <div class="section-body">
                            <h5 style="font-size: 1rem; margin-bottom: 10px;">Frecuencia de Reportes</h5>
                            <p style="font-size: 0.75rem; color: #999; margin-bottom: 20px;">Define qué tan seguido quieres recibir tu análisis de productividad</p>
                            <div class="freq-grid">
                                <button class="freq-btn freq-option <?php echo ($user['report_frequency'] ?? 'Semanal') == 'Diario' ? 'active' : ''; ?>" data-freq="Diario">Diario</button>
                                <button class="freq-btn freq-option <?php echo ($user['report_frequency'] ?? 'Semanal') == 'Semanal' ? 'active' : ''; ?>" data-freq="Semanal">Semanal</button>
                                <button class="freq-btn freq-option <?php echo ($user['report_frequency'] ?? 'Semanal') == 'Mensual' ? 'active' : ''; ?>" data-freq="Mensual">Mensual</button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Perfil -->
                    <div class="settings-section">
                        <div class="section-header">
                            <i class="fas fa-user"></i>
                            <h4>Perfil</h4>
                        </div>
                        <div class="section-body">
                            <div class="form-grid">
                                <div class="input-group">
                                    <label>Nombre Completo</label>
                                    <input type="text" id="settings-nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>">
                                </div>
                                <div class="input-group">
                                    <label>Correo Electrónico</label>
                                    <input type="email" id="settings-email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                </div>
                            </div>
                            <button class="btn-save-settings" id="btn-save-settings">Guardar Configuración General</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="fab-settings" onclick="document.getElementById('plan-modal').style.display='flex'" title="Mejorar Plan"><i class="fas fa-star"></i></div>
    </div>

    <div class="alert-toast" id="toast">Configuración guardada correctamente.</div>

    <script>
        // Sidebar toggle
        document.getElementById('menu-btn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        // Confirm upgrade (demo)
        function confirmUpgrade() {
            document.getElementById('plan-modal').style.display = 'none';
            showToast('¡Plan renovado exitosamente! Gracias por confiar en TecnoAgenda.');
        }

        let currentTheme = "<?php echo $user['theme'] ?? 'Claro'; ?>";
        let currentFreq = "<?php echo $user['report_frequency'] ?? 'Semanal'; ?>";

        // Toggle Password Input
        function togglePasswordInput() {
            const group = document.getElementById('password-input-group');
            group.style.display = group.style.display === 'none' ? 'block' : 'none';
        }

        // Theme Selection
        document.querySelectorAll('.theme-option').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.theme-option').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentTheme = btn.dataset.theme;
            });
        });

        // Frequency Selection
        document.querySelectorAll('.freq-option').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.freq-option').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentFreq = btn.dataset.freq;
            });
        });

        // Save Settings
        document.getElementById('btn-save-settings').addEventListener('click', async () => {
            const data = {
                action: 'update_settings',
                nombre: document.getElementById('settings-nombre').value,
                email: document.getElementById('settings-email').value,
                password: document.getElementById('new-password').value,
                two_factor: document.getElementById('two-factor').checked,
                report_frequency: currentFreq,
                theme: currentTheme
            };

            const resp = await fetch('settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const res = await resp.json();
            
            if (res.status === 'success') {
                showToast(res.message);
                setTimeout(() => window.location.reload(), 1500);
            } else {
                alert(res.message);
            }
        });

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 3000);
        }
    </script>
</body>
</html>
