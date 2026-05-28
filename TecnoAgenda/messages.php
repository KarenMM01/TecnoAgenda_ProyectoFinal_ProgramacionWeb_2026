<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// Inicializar messages.json si no existe
//if (!file_exists($msgsFile)) file_put_contents($msgsFile, json_encode([]));

// ── Manejador POST (AJAX) ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

  if ($action === 'send_message') {

    $sql = "
    INSERT INTO mensajes
    (
        id,
        from_email,
        to_email,
        text,
        timestamp,
        read
    )
    VALUES
    (
        :id,
        :from_email,
        :to_email,
        :text,
        NOW(),
        false
    )";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':id' => uniqid(),
        ':from_email' => $user['email'],
        ':to_email' => $data['to'],
        ':text' => htmlspecialchars(trim($data['text']), ENT_QUOTES)
    ]);

    // Crear notificación para el destinatario
    $shortText = mb_strlen($data['text']) > 60 ? mb_substr($data['text'], 0, 60).'...' : $data['text'];
    $notifStmt = $conexion->prepare("
        INSERT INTO notificaciones (para_email, tipo, titulo, mensaje, de_nombre, de_email)
        VALUES (:para_email, 'mensaje', :titulo, :mensaje, :de_nombre, :de_email)
    ");
    $notifStmt->execute([
        ':para_email' => $data['to'],
        ':titulo'     => '💬 Nuevo mensaje de '.$user['nombre'],
        ':mensaje'    => $user['nombre'].' te envió: "'.$shortText.'"',
        ':de_nombre'  => $user['nombre'],
        ':de_email'   => $user['email']
    ]);

    echo json_encode([
        'status' => 'ok'
    ]);

    exit;
}

   if ($action === 'edit_message') {

    $msgId = $data['id'] ?? '';
    $newText = htmlspecialchars(trim($data['text'] ?? ''), ENT_QUOTES);

    $sql = "
    UPDATE mensajes
    SET text = :text,
        edited = true
    WHERE id = :id
    AND from_email = :from_email
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':text' => $newText,
        ':id' => $msgId,
        ':from_email' => $user['email']
    ]);

    echo json_encode(['status' => 'ok']);
    exit;
}

    if ($action === 'save_call') {

    $sql = "
    INSERT INTO llamadas
    (
        id,
        from_email,
        from_name,
        from_avatar,
        to_email,
        to_name,
        to_avatar,
        type,
        fecha
    )
    VALUES
    (
        :id,
        :from_email,
        :from_name,
        :from_avatar,
        :to_email,
        :to_name,
        :to_avatar,
        :type,
        NOW()
    )";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':id' => uniqid(),
        ':from_email' => $user['email'],
        ':from_name' => $user['nombre'] ?? '',
        ':from_avatar' => $user['avatar'] ?? '',
        ':to_email' => $data['to_email'] ?? '',
        ':to_name' => $data['to_name'] ?? '',
        ':to_avatar' => $data['to_avatar'] ?? '',
        ':type' => $data['type'] ?? 'audio'
    ]);

    echo json_encode([
        'status' => 'ok'
    ]);

    exit;
}
  if ($action === 'get_messages') {

    $me = $user['email'];
    $peer = $data['peer'];

    $sql = "
    SELECT
        id,
        from_email AS from,
        to_email AS to,
        text,
        timestamp,
        read,
        edited
    FROM mensajes
    WHERE
    (
        from_email = :me
        AND to_email = :peer
    )
    OR
    (
        from_email = :peer
        AND to_email = :me
    )
    ORDER BY timestamp ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':me' => $me,
        ':peer' => $peer
    ]);

    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($mensajes);

    exit;
}

   if ($action === 'get_users') {

    $me = $user['email'];

    $sql = "
    SELECT 
        u.nombre,
        u.email,
        u.avatar,
        u.rol,

        COALESCE(
        (
            SELECT m.text
            FROM mensajes m
            WHERE
            (
                m.from_email = :me
                AND m.to_email = u.email
            )
            OR
            (
                m.from_email = u.email
                AND m.to_email = :me
            )
            ORDER BY m.timestamp DESC
            LIMIT 1
        ), '') AS lastMsg,

        COALESCE(
        (
            SELECT m.timestamp::text
            FROM mensajes m
            WHERE
            (
                m.from_email = :me
                AND m.to_email = u.email
            )
            OR
            (
                m.from_email = u.email
                AND m.to_email = :me
            )
            ORDER BY m.timestamp DESC
            LIMIT 1
        ), '') AS lastTime

    FROM usuarios u

    WHERE u.email <> :me

    ORDER BY lastTime DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':me' => $me
    ]);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($result);

    exit;
}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mensajes - TecnoAgenda</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Arima:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --verde: #7d9d85; --verde-oscuro: #4e6a55;
    --naranja: #f3a65a; --crema: #fbf6ec; --crema2: #f5f1e8;
    --sidebar-w: 250px;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arima', system-ui, sans-serif; color: #000; }
h1, h2, h3, h4, h5, h6 { font-family: 'Aclonica', sans-serif !important; font-weight: 400 !important; color: #000; }
button, .btn { font-family: 'Arima', system-ui, sans-serif !important; font-weight: 700 !important; color: #000; }
body { background:var(--crema); display:flex; height:100vh; overflow:hidden; }

/* ── Sidebar ── */
.sidebar { width:var(--sidebar-w); background:var(--verde); display:flex; flex-direction:column; padding-top:15px; transition:width .3s; overflow:hidden; z-index:10; }
.sidebar.collapsed { width:70px; }
.sidebar.collapsed .nav-text, .sidebar.collapsed .sidebar-logo-text { display:none; }
.menu-btn { padding:10px 20px; cursor:pointer; font-size:1.5rem; color:#111; }
.sidebar-icons { flex:1; display:flex; flex-direction:column; }
.sidebar-bottom { padding-bottom:20px; }
.nav-item { display:flex; align-items:center; padding:12px 20px; color:#111; text-decoration:none; transition:background .2s; white-space:nowrap; }
.nav-item:hover, .nav-item.active-nav { background:rgba(0,0,0,0.07); }
.nav-item i { font-size:1.5rem; min-width:32px; text-align:center; }
.nav-item span { margin-left:16px; font-size:1rem; font-weight:500; }

/* ── Layout principal ── */
.main-wrap { flex:1; display:flex; flex-direction:column; min-width:0; }
.topbar { height:65px; padding:0 30px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.topbar h1 { font-family:'Bangers'; font-size:2rem; color:#111; letter-spacing:1px; }
.topbar-right { display:flex; gap:18px; align-items:center; font-size:1.2rem; }
.topbar-right img { width:36px; height:36px; border-radius:50%; object-fit:cover; }
.search-bar-top { background:var(--crema2); border-radius:20px; padding:7px 18px; display:flex; align-items:center; gap:8px; min-width:220px; }
.search-bar-top input { border:none; background:transparent; outline:none; font-size:0.85rem; width:100%; }

/* ── Chat Layout ── */
.chat-wrap { flex:1; display:flex; min-height:0; margin:0 20px 20px; border-radius:20px; overflow:hidden; border:2px solid #e5e0d5; background:white; }

/* Contacts Panel */
.contacts-panel { width:250px; flex-shrink:0; border-right:1px solid #eee; display:flex; flex-direction:column; background:#faf7f1; }
.contacts-header { padding:18px 18px 10px; }
.contacts-header h3 { font-family:'Bangers'; font-size:1.5rem; color:#111; letter-spacing:0.5px; }
.contacts-search { margin:10px 15px 0; background:white; border-radius:12px; display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid #eee; }
.contacts-search input { border:none; outline:none; font-size:0.8rem; width:100%; background:transparent; }
.contacts-list { flex:1; overflow-y:auto; }
.contact-item { display:flex; align-items:center; gap:12px; padding:12px 15px; cursor:pointer; border-bottom:1px solid #f0ece2; transition:background .2s; }
.contact-item:hover, .contact-item.selected { background:#e8f0ea; }
.contact-avatar { width:44px; height:44px; border-radius:50%; object-fit:cover; flex-shrink:0; background:#ddd; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#777; }
.contact-avatar img { width:44px; height:44px; border-radius:50%; object-fit:cover; }
.contact-info { flex:1; min-width:0; }
.contact-name { font-weight:600; font-size:0.85rem; color:#111; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.contact-preview { font-size:0.72rem; color:#888; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
.contact-time { font-size:0.65rem; color:#aaa; flex-shrink:0; }

/* Chat Window */
.chat-window { flex:1; display:flex; flex-direction:column; min-width:0; }
.chat-header { padding:15px 22px; border-bottom:1px solid #eee; display:flex; align-items:center; justify-content:space-between; background:white; }
.chat-header-left { display:flex; align-items:center; gap:12px; }
.chat-header-avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; background:#ddd; display:flex; align-items:center; justify-content:center; font-size:1rem; color:#777; }
.chat-header-avatar img { width:40px; height:40px; border-radius:50%; object-fit:cover; }
.chat-peer-name { font-weight:600; font-size:0.95rem; color:#111; }
.chat-peer-status { font-size:0.72rem; color:var(--verde-oscuro); font-weight:500; }
.chat-header-icons { display:flex; gap:18px; color:#666; font-size:1.1rem; }
.chat-header-icons i { cursor:pointer; transition:color .2s; }
.chat-header-icons i:hover { color:var(--verde-oscuro); }

/* Messages Area */
.messages-area { flex:1; overflow-y:auto; padding:20px 22px; display:flex; flex-direction:column; gap:8px; background:var(--crema); }
.day-divider { text-align:center; margin:10px 0; }
.day-divider span { background:#e5e0d5; color:#888; font-size:0.65rem; font-weight:600; padding:4px 14px; border-radius:10px; text-transform:uppercase; }
.msg-bubble { max-width:62%; padding:12px 16px; border-radius:18px; font-size:0.85rem; line-height:1.5; position:relative; }
.msg-bubble.received { background:white; color:#111; align-self:flex-start; border-bottom-left-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,0.06); }
.msg-bubble.sent { background:var(--verde-oscuro); color:white; align-self:flex-end; border-bottom-right-radius:4px; }
.msg-time { font-size:0.62rem; opacity:0.65; margin-top:5px; text-align:right; }
.msg-time i { margin-left:4px; }

/* Input */
.chat-input-row { padding:12px 20px; border-top:1px solid #eee; display:flex; align-items:center; gap:12px; background:white; }
.btn-attach { width:38px; height:38px; border-radius:50%; background:var(--crema2); border:none; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1rem; color:#888; flex-shrink:0; }
.msg-input { flex:1; border:none; outline:none; font-size:0.9rem; background:transparent; padding:6px 0; font-family:'Poppins',sans-serif; }
.btn-emoji { background:none; border:none; cursor:pointer; font-size:1.2rem; color:#888; }
.btn-send { width:42px; height:42px; border-radius:50%; background:var(--verde-oscuro); border:none; display:flex; align-items:center; justify-content:center; cursor:pointer; color:white; font-size:1rem; flex-shrink:0; transition:transform .2s; }
.btn-send:hover { transform:scale(1.1); }

/* Empty state */
.empty-chat { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#bbb; gap:15px; background:var(--crema); }
.empty-chat i { font-size:4rem; color:#ddd; }
.empty-chat p { font-size:0.9rem; }

/* Message Actions */
.msg-actions { position:absolute; top:-10px; right:5px; background:white; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1); display:none; gap:5px; padding:3px 5px; z-index:10; }
.msg-bubble:hover .msg-actions { display:flex; }
.msg-actions i { cursor:pointer; color:#888; font-size:0.8rem; padding:4px; border-radius:50%; transition:background .2s; }
.msg-actions i:hover { background:#f0f0f0; color:#111; }
.msg-actions i.fa-trash:hover { color:#e74c3c; background:#fdf2f2; }
.msg-edited-tag { font-size:0.6rem; opacity:0.6; font-style:italic; margin-left:5px; }

/* Modal Llamada */
.call-modal-overlay { display: none; position: fixed; inset: 0; background: #0d0d1a; z-index: 3000; flex-direction: column; }
.call-modal-overlay.open { display: flex; }
.call-modal-topbar { display: flex; align-items: center; justify-content: space-between; padding: 12px 22px; background: rgba(255,255,255,0.06); border-bottom: 1px solid rgba(255,255,255,0.08); flex-shrink: 0; }
.call-modal-contact { display: flex; align-items: center; gap: 12px; }
.call-modal-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid #4e6a55; }
.call-modal-name { font-size: 1rem; font-weight: 700; color: white; }
.call-modal-badge { font-size: 0.72rem; color: rgba(255,255,255,0.5); margin-top: 2px; }
#jitsi-container { flex: 1; width: 100%; }
.btn-end-call { background: #e74c3c; color: white; border: none; padding: 9px 22px; border-radius: 25px; cursor: pointer; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s, transform 0.1s; }
.btn-end-call:hover { background: #c0392b; transform: scale(1.05); }

/* Scrollbar */
::-webkit-scrollbar { width:4px; } ::-webkit-scrollbar-track { background:transparent; } ::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }
/* Mobile Responsive Layout */
@media (max-width: 768px) {
    body { flex-direction: column; }
    .sidebar { width: 100%; height: 65px; flex-direction: row; padding: 0; justify-content: space-around; z-index: 1000; position: fixed; bottom: 0; }
    .sidebar-bottom { display: none; }
    .sidebar-icons { flex-direction: row; width: 100%; justify-content: space-around; display: flex; }
    .nav-item { padding: 10px; justify-content: center; flex: 1; }
    .nav-item span, .menu-btn { display: none !important; }
    .sidebar.collapsed { width: 100%; }
    .main-wrap { padding-bottom: 65px; height: 100vh; display: flex; flex-direction: column; }
    .topbar { padding: 0 15px; height: 60px; }
    .topbar h1 { font-size: 1.5rem; }
    .topbar-right img { width: 30px; height: 30px; }
    .search-bar-top { display: none; }
    .chat-wrap { flex-direction: column; margin: 0 10px 10px; border-radius: 15px; }
    .contacts-panel { width: 100%; border-right: none; border-bottom: 1px solid #eee; height: 40%; flex-shrink: 0; }
    .chat-window { height: 60%; }
    .msg-bubble { max-width: 85%; }
}
</style>
<script src='https://meet.jit.si/external_api.js'></script>
</head>
<body>

<nav class="sidebar collapsed" id="sidebar">
    <div class="menu-btn" id="menu-btn"><i class="fas fa-bars"></i></div>
    <div class="sidebar-icons">
        <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Inicio</span></a>
        <a href="dashboard.php" class="nav-item"><i class="fas fa-book-open"></i><span>Materiales</span></a>
        <a href="dashboard.php" class="nav-item"><i class="fas fa-calendar-alt"></i><span>Calendario</span></a>
        <a href="messages.php" class="nav-item active-nav"><i class="fas fa-envelope"></i><span>Chats</span></a>
        <a href="dashboard.php" class="nav-item"><i class="fas fa-phone"></i><span>Llamadas</span></a>
        <a href="dashboard.php" class="nav-item"><i class="fas fa-bell"></i><span>Notificaciones</span></a>
        <?php if (($user['rol'] ?? '') === 'Administrador'): ?>
        <a href="dashboard.php" class="nav-item"><i class="fas fa-users-cog"></i><span>Panel Admin</span></a>
        <?php endif; ?>
    </div>
    <div class="sidebar-bottom">
        <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i><span>Ajustes</span></a>
    </div>
</nav>

<div class="main-wrap">
    <div class="topbar">
        <h1>Mensajes</h1>
        <div class="search-bar-top">
            <i class="fas fa-search" style="color:#aaa; font-size:0.85rem;"></i>
            <input type="text" id="search-contacts" placeholder="Busqueda de Persona">
        </div>
        <div class="topbar-right">
            <a href="dashboard.php" title="Notificaciones" style="color:inherit;"><i class="far fa-bell" style="cursor:pointer;"></i></a>
            <a href="settings.php" title="Ajustes" style="color:inherit;"><i class="fas fa-cog" style="cursor:pointer;"></i></a>
            <a href="dashboard.php" title="Mi Perfil" style="color:inherit; display:flex;"><img src="<?php echo $user['avatar'] ?? 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>" alt="Avatar" style="cursor:pointer;"></a>
        </div>
    </div>

    <div class="chat-wrap">
        <!-- Panel de Contactos -->
        <div class="contacts-panel">
            <div class="contacts-header">
                <h3>Usuarios</h3>
            </div>
            <div class="contacts-search">
                <i class="fas fa-search" style="color:#bbb; font-size:0.8rem;"></i>
                <input type="text" id="search-input" placeholder="Buscar...">
            </div>
            <div class="contacts-list" id="contacts-list">
                <div style="padding:30px; text-align:center; color:#bbb; font-size:0.8rem;">Cargando usuarios...</div>
            </div>
        </div>

        <!-- Ventana de Chat -->
        <div class="chat-window" id="chat-window">
            <div class="empty-chat" id="empty-state">
                <i class="fas fa-comments"></i>
                <p>Selecciona un contacto para comenzar a chatear</p>
            </div>
            <div id="active-chat" style="display:none; flex-direction:column; flex:1; min-height:0;">
                <!-- Header del chat -->
                <div class="chat-header">
                    <div class="chat-header-left">
                        <div class="chat-header-avatar" id="chat-header-avatar">
                            <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="">
                        </div>
                        <div>
                            <div class="chat-peer-name" id="chat-peer-name">-</div>
                            <div class="chat-peer-status">EN LÍNEA</div>
                        </div>
                    </div>
                    <div class="chat-header-icons">
                        <i class="fas fa-phone" onclick="startCall('audio')"></i>
<i class="fas fa-video" onclick="startCall('video')"></i>
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>

                <!-- Mensajes -->
                <div class="messages-area" id="messages-area"></div>

                <!-- Input -->
                <div class="chat-input-row">
                    <button class="btn-attach"><i class="fas fa-plus"></i></button>
                    <input class="msg-input" id="msg-input" type="text" placeholder="Escribir mensaje..." autocomplete="off">
                    <button class="btn-emoji">😊</button>
                    <button class="btn-send" id="btn-send"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ME = <?php echo json_encode($user['email']); ?>;
let currentPeer = null;
let pollTimer   = null;
let allContacts = [];

// ── Sidebar toggle
document.getElementById('menu-btn').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('collapsed');
});

// ── Cargar contactos
async function loadContacts() {
    const resp = await fetch('messages.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'get_users'})
    });
    allContacts = await resp.json();
    renderContacts(allContacts);
}

function renderContacts(list) {
    const el = document.getElementById('contacts-list');
    if (!list.length) { el.innerHTML = '<div style="padding:30px;text-align:center;color:#bbb;font-size:0.8rem;">No hay otros usuarios registrados.</div>'; return; }
    el.innerHTML = '';
    list.forEach(u => {
        const div = document.createElement('div');
        div.className = 'contact-item' + (u.email === currentPeer?.email ? ' selected' : '');
        div.dataset.email = u.email;

        const timeStr = u.lastTime ? formatContactTime(u.lastTime) : '';
        const avatarHtml = `<img src="${u.avatar || 'https://cdn-icons-png.flaticon.com/512/149/149071.png'}" alt="">`;

        div.innerHTML = `
            <div class="contact-avatar">${avatarHtml}</div>
            <div class="contact-info">
                <div class="contact-name">${u.nombre}</div>
                <div class="contact-preview">${u.lastMsg || u.rol}</div>
            </div>
            <div class="contact-time">${timeStr}</div>
        `;
        div.addEventListener('click', () => openChat(u));
        el.appendChild(div);
    });
}

// ── Abrir chat
function openChat(u) {
    currentPeer = u;
    document.getElementById('empty-state').style.display = 'none';
    const ac = document.getElementById('active-chat');
    ac.style.display = 'flex';

    document.getElementById('chat-peer-name').textContent = u.nombre;
    const avatarEl = document.getElementById('chat-header-avatar');
    avatarEl.innerHTML = `<img src="${u.avatar || 'https://cdn-icons-png.flaticon.com/512/149/149071.png'}" alt="">`;

    document.querySelectorAll('.contact-item').forEach(el => {
        el.classList.toggle('selected', el.dataset.email === u.email);
    });

    clearInterval(pollTimer);
    loadMessages();
    pollTimer = setInterval(loadMessages, 3000);
}

// ── Cargar mensajes
async function loadMessages() {
    if (!currentPeer) return;
    const resp = await fetch('messages.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'get_messages', peer: currentPeer.email})
    });
    const msgs = await resp.json();
    renderMessages(msgs);
    // Actualizar preview en lista
    loadContacts();
}

function renderMessages(msgs) {
    const area = document.getElementById('messages-area');
    const wasAtBottom = area.scrollHeight - area.clientHeight <= area.scrollTop + 20;

    area.innerHTML = '';
    if (!msgs.length) {
        area.innerHTML = '<div style="text-align:center;color:#bbb;font-size:0.8rem;padding:30px;">Sin mensajes aún. ¡Saluda!</div>';
        return;
    }

    let lastDay = '';
    msgs.forEach(m => {
        const day = m.timestamp.split(' ')[0];
        if (day !== lastDay) {
            const div = document.createElement('div');
            div.className = 'day-divider';
            div.innerHTML = `<span>${formatDay(day)}</span>`;
            area.appendChild(div);
            lastDay = day;
        }
        const isMine = m.from === ME;
        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble ' + (isMine ? 'sent' : 'received');
        const timeStr = m.timestamp.split(' ')[1].substring(0,5);
        bubble.innerHTML = `${m.text}<div class="msg-time">${timeStr}${isMine ? ' <i class="fas fa-check-double"></i>' : ''}</div>`;
        area.appendChild(bubble);
    });

    if (wasAtBottom) area.scrollTop = area.scrollHeight;
}

// ── Enviar mensaje
async function sendMessage() {
    const input = document.getElementById('msg-input');
    const text  = input.value.trim();
    if (!text || !currentPeer) return;
    input.value = '';
    await fetch('messages.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'send_message', to: currentPeer.email, text})
    });
    loadMessages();
}

document.getElementById('btn-send').addEventListener('click', sendMessage);
document.getElementById('msg-input').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

// ── Buscar contactos
document.getElementById('search-input').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    renderContacts(allContacts.filter(u => u.nombre.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)));
});
document.getElementById('search-contacts').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    renderContacts(allContacts.filter(u => u.nombre.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)));
});

// ── Helpers de fecha
function formatContactTime(ts) {
    const d = new Date(ts.replace(' ', 'T'));
    const now = new Date();
    const today = now.toDateString();
    if (d.toDateString() === today) return d.toTimeString().substring(0,5);
    const days = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const diffDays = Math.floor((now - d) / 86400000);
    if (diffDays < 7) return days[d.getDay()];
    return d.toLocaleDateString('es-MX', {day:'numeric', month:'short'});
}
function formatDay(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return 'HOY';
    const yesterday = new Date(now); yesterday.setDate(yesterday.getDate()-1);
    if (d.toDateString() === yesterday.toDateString()) return 'AYER';
    return d.toLocaleDateString('es-MX', {weekday:'long', day:'numeric', month:'long'}).toUpperCase();
}

            async function startCall(type) {
    if (!currentPeer) {
        alert('Selecciona un contacto primero');
        return;
    }

    await fetch('messages.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_call',
            to_email: currentPeer.email,
            to_name: currentPeer.nombre,
            to_avatar: currentPeer.avatar,
            type: type
        })
    });

    alert(type === 'video' ? 'Videollamada iniciada' : 'Llamada iniciada');
}
// Define initialPeer from URL parameter (if present)
let initialPeer = <?php echo json_encode($_GET['peer'] ?? null); ?>;
// Iniciar carga de contactos y abrir chat automáticamente si hay peer
loadContacts().then(() => {
    if (initialPeer) {
        const contact = allContacts.find(u => u.email === initialPeer);
        if (contact) openChat(contact);
    }
});
</script>
</body>
</html>
