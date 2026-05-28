<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json');

    $action = $_POST['action'];
    $userRol = strtolower(trim($user['rol'] ?? ''));

    // EVENTOS
    if ($action === 'get_events') {
        $stmt = $conexion->query("
            SELECT id, titulo, modalidad, fecha::text AS fecha, hora::text AS hora,
                   enlace, lugar, instrucciones, creador_email, creador_nombre,
                   status, rating, rated_by
            FROM eventos
            ORDER BY fecha ASC, hora ASC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if (($action === 'save_event' || $action === 'create_event') && $userRol !== 'estudiante') {
        $stmt = $conexion->prepare("
            INSERT INTO eventos
            (id, titulo, modalidad, fecha, hora, enlace, lugar, instrucciones, creador_email, creador_nombre, status)
            VALUES
            (:id, :titulo, :modalidad, :fecha, :hora, :enlace, :lugar, :instrucciones, :creador_email, :creador_nombre, 'active')
        ");

        $stmt->execute([
            ':id' => time(),
            ':titulo' => $_POST['titulo'],
            ':modalidad' => $_POST['modalidad'],
            ':fecha' => $_POST['fecha'],
            ':hora' => $_POST['hora'],
            ':enlace' => $_POST['enlace'] ?? '',
            ':lugar' => $_POST['lugar'] ?? '',
            ':instrucciones' => $_POST['instrucciones'] ?? '',
            ':creador_email' => $user['email'],
            ':creador_nombre' => $user['nombre']
        ]);

        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'cancel_event' && $userRol !== 'estudiante') {
        $stmt = $conexion->prepare("UPDATE eventos SET status = 'canceled' WHERE id = :id");
        $stmt->execute([':id' => $_POST['event_id']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'delete_event' && $userRol !== 'estudiante') {
        $stmt = $conexion->prepare("DELETE FROM eventos WHERE id = :id");
        $stmt->execute([':id' => $_POST['event_id']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'complete_event' && $userRol !== 'estudiante') {
        $stmt = $conexion->prepare("UPDATE eventos SET status = 'completed' WHERE id = :id");
        $stmt->execute([':id' => $_POST['event_id']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'rate_event' && $userRol === 'estudiante') {
        $stmt = $conexion->prepare("
            UPDATE eventos
            SET status = 'completed', rating = :rating, rated_by = :rated_by
            WHERE id = :id
        ");
        $stmt->execute([
            ':rating' => $_POST['rating'],
            ':rated_by' => $user['email'],
            ':id' => $_POST['event_id']
        ]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // USUARIOS / ASESORES
    if ($action === 'get_advisors') {
        $stmt = $conexion->prepare("
            SELECT nombre, email, rol, carrera, semestre, avatar, descripcion, materias_inscritas
            FROM usuarios
            WHERE LOWER(rol) IN ('docente', 'tutor')
            AND email <> :email
            ORDER BY nombre ASC
        ");
        $stmt->execute([':email' => $user['email']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'get_all_users' && ($userRol === 'administrador' || $userRol === 'admin')) {
        $stmt = $conexion->query("
            SELECT id, nombre, fecha_nacimiento, rol, carrera, semestre, email, avatar
            FROM usuarios
            ORDER BY nombre ASC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'delete_user' && ($userRol === 'administrador' || $userRol === 'admin')) {
        $stmt = $conexion->prepare("DELETE FROM usuarios WHERE email = :email");
        $stmt->execute([':email' => $_POST['user_email']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // SOLICITUDES
    if ($action === 'request_join') {
        $stmt = $conexion->prepare("
            INSERT INTO solicitudes
            (id, student_email, student_name, advisor_email, status, fecha)
            VALUES
            (:id, :student_email, :student_name, :advisor_email, 'pending', NOW())
        ");
        $stmt->execute([
            ':id' => time(),
            ':student_email' => $user['email'],
            ':student_name' => $user['nombre'],
            ':advisor_email' => $_POST['advisor_email']
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Solicitud enviada correctamente.']);
        exit;
    }

    if ($action === 'cancel_request' && $userRol === 'estudiante') {
        $stmt = $conexion->prepare("DELETE FROM solicitudes WHERE id = :id AND student_email = :student_email");
        $stmt->execute([
            ':id' => $_POST['request_id'],
            ':student_email' => $user['email']
        ]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'respond_request' && $userRol !== 'estudiante') {
        $stmt = $conexion->prepare("
            UPDATE solicitudes
            SET status = :status
            WHERE id = :id
            AND advisor_email = :advisor_email
        ");
        $stmt->execute([
            ':status' => $_POST['status'],
            ':id' => $_POST['request_id'],
            ':advisor_email' => $user['email']
        ]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'remove_member' && $userRol !== 'estudiante') {
        $stmt = $conexion->prepare("
            UPDATE solicitudes
            SET status = 'rejected'
            WHERE id = :id
            AND advisor_email = :advisor_email
        ");
        $stmt->execute([
            ':id' => $_POST['request_id'],
            ':advisor_email' => $user['email']
        ]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'get_notifications') {
        $notifs = [];

        if ($userRol !== 'estudiante') {
            $stmt = $conexion->prepare("
                SELECT *
                FROM solicitudes
                WHERE advisor_email = :email
                AND status = 'pending'
                ORDER BY fecha DESC
            ");
            $stmt->execute([':email' => $user['email']]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($requests as $r) {
                $notifs[] = [
                    'id' => $r['id'],
                    'tipo' => 'solicitud',
                    'mensaje' => 'El alumno ' . $r['student_name'] . ' quiere unirse a tu asesoría.',
                    'data' => $r
                ];
            }
        } else {
            $stmt = $conexion->prepare("
                SELECT *
                FROM solicitudes
                WHERE student_email = :email
                AND status IN ('accepted', 'rejected')
                ORDER BY fecha DESC
            ");
            $stmt->execute([':email' => $user['email']]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($requests as $r) {
                $notifs[] = [
                    'id' => $r['id'],
                    'tipo' => 'respuesta',
                    'mensaje' => 'Tu solicitud para unirte al asesor ' . $r['advisor_email'] . ' fue ' . ($r['status'] === 'accepted' ? 'ACEPTADA' : 'RECHAZADA') . '.',
                    'status' => $r['status']
                ];
            }
        }

        echo json_encode($notifs);
        exit;
    }

    // MATERIALES / CARPETAS
    if ($action === 'get_material_data') {
        $folders = $conexion->query("SELECT * FROM carpetas ORDER BY nombre ASC")
                            ->fetchAll(PDO::FETCH_ASSOC);

        $materials = $conexion->query("SELECT * FROM materiales ORDER BY fecha DESC")
                              ->fetchAll(PDO::FETCH_ASSOC);

        $requests = $conexion->query("SELECT * FROM solicitudes ORDER BY fecha DESC")
                             ->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'folders' => $folders,
            'materials' => $materials,
            'requests' => $requests
        ]);
        exit;
    }

    if ($action === 'create_folder' && $userRol !== 'estudiante') {
        $stmt = $conexion->prepare("
            INSERT INTO carpetas (id, nombre, creador_email)
            VALUES (:id, :nombre, :creador_email)
        ");
        $stmt->execute([
            ':id' => time(),
            ':nombre' => $_POST['nombre_carpeta'],
            ':creador_email' => $user['email']
        ]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'delete_folder' && $userRol !== 'estudiante') {
        $stmt = $conexion->prepare("DELETE FROM carpetas WHERE id = :id");
        $stmt->execute([':id' => $_POST['folder_id']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'upload_material' && $userRol !== 'estudiante') {
        $titulo = $_POST['titulo'] ?? '';
        $materia = $_POST['materia'] ?? '';
        $file_path = '';

        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
            $filename = uniqid() . "." . $ext;
            $target = "uploads/" . $filename;

            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }

            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $target)) {
                $file_path = $target;
            }
        }

        if ($file_path === '') {
            echo json_encode(['status' => 'error', 'message' => 'Error al subir archivo']);
            exit;
        }

        $colors = ['card-blue', 'card-red', 'card-purple'];

        $stmt = $conexion->prepare("
            INSERT INTO materiales
            (id, titulo, materia, color_clase, archivo, creador_email, fecha)
            VALUES
            (:id, :titulo, :materia, :color_clase, :archivo, :creador_email, CURRENT_DATE)
        ");
        $stmt->execute([
            ':id' => time(),
            ':titulo' => $titulo,
            ':materia' => $materia,
            ':color_clase' => $colors[array_rand($colors)],
            ':archivo' => $file_path,
            ':creador_email' => $user['email']
        ]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'delete_material' && $userRol !== 'estudiante') {
        $stmt = $conexion->prepare("DELETE FROM materiales WHERE id = :id");
        $stmt->execute([':id' => $_POST['material_id']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'mark_material_seen') {
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'get_material_views') {
        $stmt = $conexion->prepare("SELECT * FROM materiales WHERE id = :id");
        $stmt->execute([':id' => $_POST['material_id']]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'get_group_members') {
        $stmt = $conexion->prepare("
            SELECT *
            FROM solicitudes
            WHERE advisor_email = :email
            AND status = 'accepted'
            ORDER BY student_name ASC
        ");
        $stmt->execute([':email' => $user['email']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // REPORTES
    if ($action === 'get_teacher_report') {
        $teacher = $_POST['teacher_email'];

        $eventsStmt = $conexion->prepare("SELECT * FROM eventos WHERE creador_email = :email");
        $eventsStmt->execute([':email' => $teacher]);

        $reqStmt = $conexion->prepare("SELECT * FROM solicitudes WHERE advisor_email = :email");
        $reqStmt->execute([':email' => $teacher]);

        $matStmt = $conexion->prepare("SELECT * FROM materiales WHERE creador_email = :email");
        $matStmt->execute([':email' => $teacher]);

        echo json_encode([
            'events' => $eventsStmt->fetchAll(PDO::FETCH_ASSOC),
            'requests' => $reqStmt->fetchAll(PDO::FETCH_ASSOC),
            'materials' => $matStmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
        exit;
    }

    // PERFIL
    if ($action === 'update_profile') {
        // En caso de que se envíe como raw JSON (fallback)
        if (empty($_POST['nombre'])) {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input) $_POST = array_merge($_POST, $input);
        }

        $stmt = $conexion->prepare("
            UPDATE usuarios 
            SET nombre = :nombre, fecha_nacimiento = :fecha, carrera = :carrera, semestre = :semestre, 
                descripcion = :descripcion, materias_inscritas = :materias, avatar = :avatar
                " . (!empty($_POST['password']) ? ", password = :password" : "") . "
            WHERE email = :email
        ");
        
        $params = [
            ':nombre' => $_POST['nombre'],
            ':fecha' => $_POST['fechaNacimiento'],
            ':carrera' => $_POST['carrera'],
            ':semestre' => $_POST['semestre'],
            ':descripcion' => $_POST['descripcion'],
            ':materias' => $_POST['materias'],
            ':avatar' => $_POST['avatar'],
            ':email' => $user['email']
        ];

        if (!empty($_POST['password'])) {
            $params[':password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $stmt->execute($params);

        // Update session
        $_SESSION['user']['nombre'] = $_POST['nombre'];
        $_SESSION['user']['fecha_nacimiento'] = $_POST['fechaNacimiento'];
        $_SESSION['user']['carrera'] = $_POST['carrera'];
        $_SESSION['user']['semestre'] = $_POST['semestre'];
        $_SESSION['user']['descripcion'] = $_POST['descripcion'];
        $_SESSION['user']['materias_inscritas'] = $_POST['materias'];
        $_SESSION['user']['avatar'] = $_POST['avatar'];

        echo json_encode(['status' => 'success', 'message' => 'Perfil actualizado correctamente']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida: ' . $action]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoAgenda - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Arima:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --verde-tecno: #7d9d85;
            --verde-oscuro: #4e6a55;
            --naranja-soft: #f3a65a;
            --crema-fondo: #e7e3d7;
            --crema-claro: #fdfaf3;
            --sidebar-width: 70px;
            --topbar-height: 70px;
            --text-color: #000;
            --sombra: 0 10px 30px rgba(0,0,0,0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arima', system-ui, sans-serif; color: #000; }
        h1, h2, h3, h4, h5, h6, .topbar-title { font-family: 'Aclonica', sans-serif !important; font-weight: 400 !important; color: #000; }
        button, .btn { font-family: 'Arima', system-ui, sans-serif !important; font-weight: 700 !important; color: #000; }
        body { background-color: var(--crema-fondo); display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: var(--sidebar-width); background-color: var(--verde-tecno); display: flex; flex-direction: column; padding-top: 15px; z-index: 10; transition: width 0.3s ease; }
        .sidebar.expanded { width: 250px; }
        .sidebar-bottom { margin-top: auto; padding-bottom: 20px; }
        .nav-item { display: flex; align-items: center; width: 100%; padding: 12px 25px; cursor: pointer; transition: background 0.2s; color: #111; text-decoration: none; }
        .nav-item:hover { background-color: rgba(0, 0, 0, 0.05); }
        .nav-item i { font-size: 1.6rem; min-width: 30px; text-align: center; }
        .nav-text { margin-left: 20px; font-size: 1.1rem; font-weight: 500; opacity: 0; display: none; white-space: nowrap; }
        .sidebar.expanded .nav-text { display: block; opacity: 1; }
        .menu-btn-container { display: flex; align-items: center; padding: 0 25px; margin-bottom: 20px; cursor: pointer; }
        
        .main-container { flex: 1; display: flex; flex-direction: column; }
        .topbar { height: var(--topbar-height); background-color: var(--naranja-soft); display: flex; justify-content: space-between; align-items: center; padding: 0 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05); }
        .topbar-title { font-family: 'Bangers', cursive; font-size: 2.5rem; color: #111; }
        .topbar-icons { display: flex; gap: 20px; align-items: center; }
        .topbar-icons i { font-size: 1.8rem; color: #111; cursor: pointer; }
        .content { flex: 1; padding: 40px; overflow-y: auto; position: relative; }
        .content:has(.calendar-container.active) { padding: 20px; }

        .welcome-card { background: #fdfaf3; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); max-width: 800px; margin: 0 auto; }
        .logout-btn { background-color: #d9534f; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; margin-top: 20px; }

        /* Profile */
        .topbar-user-menu { position: relative; }
        .user-btn { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 5px 12px; border-radius: 25px; transition: background 0.2s; }
        .user-btn:hover { background: rgba(0,0,0,0.05); }
        .user-btn-name { font-weight: 700; font-size: 1.1rem; }
        .user-btn img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #333; }
        .user-dropdown { display: none; position: absolute; right: 0; top: 115%; background: #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.15); border-radius: 15px; overflow: hidden; min-width: 220px; z-index: 1000; flex-direction: column; border: 1px solid rgba(0,0,0,0.05); }
        .dropdown-item { padding: 14px 20px; cursor: pointer; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 0.95rem; color: #333; transition: background 0.2s; }
        .dropdown-item:not(:last-child) { border-bottom: 1px solid #f0f0f0; }
        .dropdown-item:hover { background: #f9f9f9; }
        .dropdown-item.danger { color: #d9534f; }
        .dropdown-item.danger:hover { background: #fdf2f2; }
        
        .profile-container { display: none; width: 100%; max-width: 950px; margin: 0 auto; gap: 25px; }
        .profile-container.active { display: flex; }
        .profile-left { width: 320px; background-color: #e8e4d9; border-radius: 12px; padding: 40px 30px; border: 1px solid #d5d0c3; display: flex; flex-direction: column; align-items: center; }
        .avatar-circle { width: 200px; height: 200px; border-radius: 50%; background-color: #d1cfc7; margin-bottom: 30px; border: 4px solid #4e6a55; overflow: hidden; position: relative; display: flex; justify-content: center; align-items: center; }
        .avatar-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; cursor: pointer; }
        .avatar-circle.editable:hover .avatar-overlay { display: flex; }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .profile-btn { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #111; cursor: pointer; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 500; }
        .btn-edit { background: #f4ad6b; }
        .btn-save { background: #4e6a55; color: white; }
        .profile-right { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .profile-form-card { background: #e8e4d9; border-radius: 12px; padding: 30px; border: 1px solid #d5d0c3; }
        .form-grid-profile { display: grid; grid-template-columns: 1fr 1fr; gap: 15px 20px; }
        .full-width { grid-column: span 2; }
        .input-grp { display: flex; flex-direction: column; gap: 5px; }
        .input-grp label { font-size: 0.8rem; color: #888; }
        .input-grp input, .input-grp select { background: #f5f1e8; border: 1px solid #d5d0c3; padding: 10px; border-radius: 6px; }
        .icon-input { position: relative; }
        .icon-input i { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888; }

        /* Materiales */
        .materiales-container { display: none; flex-direction: column; gap: 20px; }
        .materiales-container.active { display: flex; }
        .search-container { background: white; border-radius: 30px; padding: 5px 20px; display: flex; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .search-container input { flex: 1; border: none; padding: 10px; outline: none; }
        .materiales-body { display: flex; gap: 30px; }
        .materiales-list { flex: 1; }
        .material-card { background: white; border-radius: 15px; padding: 15px; display: flex; gap: 20px; align-items: center; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .card-blue { background: #9cb4c4; }
        .card-red { background: #c48c8c; }
        .card-purple { background: #cda4ca; }
        .material-img-container { width: 80px; height: 80px; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 2rem; color: white; background: rgba(0,0,0,0.1); }
        .materiales-sidebar { width: 280px; display: flex; flex-direction: column; gap: 20px; }
        .filter-box { background: #bccbc1; border-radius: 15px; padding: 20px; }
        .upload-box { background: #9ab49a; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 15px; }
        .filter-group input, .filter-group select { padding: 8px; border-radius: 5px; border: 1px solid #ddd; }
        .progress-container { width: 100%; background: #eee; border-radius: 10px; height: 10px; margin-top: 10px; display: none; overflow: hidden; }
        .progress-bar { width: 0%; height: 100%; background: var(--verde-tecno); transition: width 0.3s; }
        .loading-spinner { display: none; color: var(--verde-tecno); font-size: 1.2rem; margin-top: 10px; text-align: center; }

        /* Calendario Premium */
        .calendar-container { display: none; flex-direction: column; min-height: calc(100vh - var(--topbar-height) - 40px); background: white; border-radius: 20px; overflow: hidden; box-shadow: var(--sombra); }
        .calendar-container.active { display: flex; }
        .calendar-layout { display: flex; flex: 1; }
        .calendar-main { flex: 1; background: white; padding: 30px; border-right: 1px solid #f0f0f0; }
        .calendar-header { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; }
        .calendar-nav { display: flex; gap: 5px; background: #f5f5f5; padding: 5px; border-radius: 10px; }
        .nav-btn { border: none; background: transparent; padding: 5px 10px; cursor: pointer; border-radius: 8px; color: #666; transition: all 0.2s; }
        .nav-btn:hover { background: white; color: var(--text-color); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); border: 1px solid #f0f0f0; border-radius: 15px; overflow: hidden; }
        .calendar-day-head { background: #fafafa; padding: 12px; text-align: center; font-weight: 600; color: #999; text-transform: uppercase; font-size: 0.7rem; border-bottom: 1px solid #f0f0f0; }
        .calendar-day { min-height: 100px; padding: 8px; border-right: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; position: relative; cursor: pointer; transition: all 0.2s; background: white; }
        .calendar-day:nth-child(7n) { border-right: none; }
        .calendar-day:hover { background: #f9f9f9; }
        .calendar-day.today { background: #f4f7f5; }
        .calendar-day.today .calendar-day-num { color: var(--verde-oscuro); }
        .calendar-day.selected { background: #f0f4f1; outline: 2px solid var(--verde-tecno); z-index: 2; }
        .calendar-day.other-month { background: #fafafa; color: #eee; }
        .calendar-day-num { font-weight: 600; font-size: 0.95rem; color: #444; margin-bottom: 5px; }
        
        .event-tag { font-size: 9px; padding: 4px 6px; border-radius: 5px; color: white; display: flex; align-items: center; gap: 4px; margin-bottom: 3px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tag-virtual { background: var(--verde-tecno); }
        .tag-llamada { background: var(--naranja-soft); }
        .tag-presencial { background: #4a5568; }

        /* Panel Derecho */
        .calendar-right-panel { width: 300px; background: #fcfbf7; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; }
        .detail-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02); }
        .detail-card .date-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: #999; letter-spacing: 0.5px; margin-bottom: 8px; display: block; }
        .detail-card h3 { font-size: 1.2rem; margin-bottom: 12px; color: var(--text-color); }
        
        .advisor-info { display: flex; align-items: center; gap: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #f5f5f5; }
        .advisor-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .advisor-details span { display: block; font-size: 0.85rem; font-weight: 600; }
        .advisor-details small { color: #888; font-size: 0.75rem; }

        .categories-list { display: flex; flex-direction: column; gap: 15px; }
        .category-item { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; }
        .cat-label { display: flex; align-items: center; gap: 10px; font-weight: 500; }
        .cat-dot { width: 10px; height: 10px; border-radius: 50%; }
        .cat-time { color: #888; font-weight: 400; }

        .progress-section { background: #e9edea; border-radius: 20px; padding: 25px; }
        .progress-section h4 { font-size: 0.9rem; margin-bottom: 15px; color: var(--verde-oscuro); }
        .progress-bar-container { height: 8px; background: white; border-radius: 10px; overflow: hidden; margin-bottom: 10px; }
        .progress-bar-fill { height: 100%; background: var(--verde-oscuro); width: 65%; border-radius: 10px; }
        .progress-section p { font-size: 0.7rem; color: #888; text-align: right; }

        /* Modal de Evento */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 400px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }

        /* Dashboard de Análisis (Admin/Docente) */
        .admin-dash-container { display: none; flex-direction: column; gap: 30px; padding-bottom: 50px; width: 100%; }
        .admin-dash-container.active { display: flex; }
        
        .dash-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 10px; }
        .dash-title-group h4 { color: var(--verde-tecno); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; margin-bottom: 5px; }
        .dash-title-group h2 { font-size: 2.2rem; color: #111; font-weight: 700; }
        .dash-actions { display: flex; gap: 10px; }
        .btn-dash { padding: 10px 20px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; font-size: 0.9rem; }
        .btn-dash-light { background: #eee; color: #555; }
        .btn-dash-dark { background: var(--verde-oscuro); color: white; }

        .dash-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; box-shadow: var(--sombra); border: 1px solid rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 15px; }
        .stat-header { display: flex; justify-content: space-between; align-items: center; }
        .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .stat-trend { font-size: 0.8rem; font-weight: 700; }
        .trend-up { color: var(--verde-tecno); }
        .trend-down { color: #ff4757; }
        .stat-info span { color: #888; font-size: 0.85rem; display: block; margin-bottom: 5px; }
        .stat-info strong { font-size: 1.8rem; color: #111; }

        .dash-main-layout { display: grid; grid-template-columns: 1fr 320px; gap: 30px; }
        .dash-left-col { display: flex; flex-direction: column; gap: 40px; }
        
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h3 { font-size: 1.4rem; color: #111; }
        .section-header a { color: var(--verde-tecno); text-decoration: none; font-size: 0.9rem; font-weight: 600; }

        .dash-list { display: flex; flex-direction: column; gap: 15px; }
        .dash-item { background: white; padding: 15px 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border-left: 5px solid #eee; display: flex; align-items: center; gap: 20px; transition: transform 0.2s; cursor: pointer; }
        .dash-item:hover { transform: translateX(5px); }
        .item-icon { width: 50px; height: 50px; background: #f9f9f9; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #888; }
        .item-info { flex: 1; }
        .item-info h5 { font-size: 1rem; margin-bottom: 4px; color: #333; }
        .item-info p { font-size: 0.75rem; color: #999; }
        .item-status { padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }

        .dash-report-card { background: #fdfaf3; padding: 30px; border-radius: 25px; border: 1px solid #eee; }
        .report-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .report-progress-group h6 { font-size: 0.9rem; margin-bottom: 15px; display: flex; justify-content: space-between; }
        .report-progress-group h6 span { color: var(--verde-oscuro); font-weight: 700; }
        .progress-bar { height: 10px; background: #eee; border-radius: 5px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 5px; }

        .report-sub-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .sub-card { background: white; padding: 15px; border-radius: 15px; display: flex; align-items: center; gap: 15px; }
        .sub-card i { font-size: 1.2rem; color: var(--verde-tecno); }
        .sub-card div span { font-size: 0.7rem; color: #999; display: block; }
        .sub-card div strong { font-size: 0.85rem; color: #333; }

        .dash-right-col { display: flex; flex-direction: column; gap: 25px; }
        .side-panel-card { background: white; padding: 25px; border-radius: 25px; box-shadow: var(--sombra); display: flex; flex-direction: column; gap: 20px; }
        .side-panel-card h4 { font-size: 1.2rem; color: #111; margin-bottom: 5px; }
        
        .group-list { display: flex; flex-direction: column; gap: 15px; }
        .group-item { display: flex; align-items: center; gap: 15px; cursor: pointer; padding: 10px; border-radius: 12px; transition: background 0.2s; }
        .group-item:hover { background: #f9f9f9; }
        .group-letter { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #555; }
        .group-info { flex: 1; }
        .group-info strong { font-size: 0.9rem; display: block; }
        .group-info span { font-size: 0.75rem; color: #999; }
        
        .upcoming-sessions-dash { display: flex; flex-direction: column; gap: 15px; }
        .session-mini-card { background: #f4f2eb; padding: 15px; border-radius: 15px; display: flex; flex-direction: column; gap: 10px; }
        .session-time { font-size: 0.7rem; font-weight: 700; color: var(--verde-oscuro); text-transform: uppercase; }
        .session-title { font-size: 0.9rem; font-weight: 600; color: #333; }
        .session-avatars { display: flex; }
        .mini-avatar { width: 24px; height: 24px; border-radius: 50%; border: 2px solid #f4f2eb; margin-right: -8px; object-fit: cover; }

        /* Nuevas Solicitudes & Próximas Asesorías */
        .request-card { background: white; padding: 20px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 15px; }
        .request-user-info { display: flex; align-items: center; gap: 15px; }
        .request-user-info img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
        .request-user-info div h5 { font-size: 1.1rem; color: #333; }
        .request-user-info div p { font-size: 0.8rem; color: #888; font-family: monospace; }
        .request-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .btn-request { padding: 10px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; transition: opacity 0.2s; }
        .btn-request:hover { opacity: 0.9; }
        .btn-acc { background: var(--verde-oscuro); color: white; }
        .btn-rej { background: #e7e3d7; color: #555; }

        /* Estilos Notificaciones Estilo "Alerts" */
        .notif-dashboard { display: grid; grid-template-columns: 1fr 340px; gap: 30px; margin-top: 20px; }
        .notif-main-col { display: flex; flex-direction: column; gap: 30px; }
        .notif-side-col { display: flex; flex-direction: column; gap: 20px; }

        .notif-hero-card { background: #f5f1e8; border-radius: 25px; padding: 35px; border: 1px solid rgba(0,0,0,0.05); }
        .notif-hero-card h4 { font-size: 0.8rem; text-transform: uppercase; color: #8a8a8a; letter-spacing: 1px; margin-bottom: 10px; font-weight: 600; }
        .notif-hero-card h2 { font-size: 2rem; color: #111; margin-bottom: 10px; font-weight: 700; }
        .notif-hero-card p { color: #777; margin-bottom: 25px; line-height: 1.5; font-size: 0.95rem; }
        .notif-hero-btns { display: flex; gap: 12px; }
        .notif-btn-bulk { background: #4e6a55; color: white; border: none; padding: 12px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .notif-btn-schedule { background: transparent; border: 1.5px solid #d1ccc0; padding: 12px 20px; border-radius: 12px; font-weight: 600; color: #555; cursor: pointer; }

        .notif-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .notif-section-header h3 { font-size: 1.3rem; display: flex; align-items: center; gap: 10px; color: #111; }
        .notif-sync-text { font-size: 0.75rem; color: #999; background: #eee; padding: 4px 10px; border-radius: 8px; }

        .notif-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 15px; display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.03); transition: transform 0.2s; }
        .notif-card:hover { transform: translateY(-3px); }
        .notif-card-icon { width: 55px; height: 55px; background: #f0f3f1; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #8a9d8a; }
        .notif-card-content { flex: 1; }
        .notif-card-content h5 { font-size: 1.05rem; margin-bottom: 5px; color: #333; font-weight: 600; }
        .notif-card-info { font-size: 0.85rem; color: #888; display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .notif-labels { display: flex; gap: 8px; flex-wrap: wrap; }
        .notif-badge { font-size: 0.65rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; }
        .badge-time { background: #fdfaf3; border: 1px solid #eee; color: #666; }
        .badge-priority { background: #e9f0ec; color: #6a8d7a; }
        .badge-optional { background: #fdf3e9; color: #d4a373; }

        .notif-actions { display: flex; align-items: center; gap: 15px; }
        .btn-notif-circle { width: 35px; height: 35px; border-radius: 50%; border: none; background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #888; transition: background 0.2s; }
        .btn-notif-circle:hover { background: #f5f5f5; color: #d9534f; }
        .btn-notif-accept { background: #4e6a55; color: white; padding: 10px 22px; border-radius: 12px; border: none; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .btn-notif-details { background: transparent; border: 1.5px solid #eee; color: #666; padding: 10px 18px; border-radius: 12px; cursor: pointer; font-size: 0.85rem; font-weight: 600; }

        .side-efficiency-card { background: #fbe6d4; border-radius: 25px; padding: 25px; text-align: center; display: flex; flex-direction: column; gap: 15px; }
        .side-efficiency-card i { font-size: 2rem; color: #111; }
        .efficiency-val { font-size: 2.2rem; font-weight: 700; color: #111; line-height: 1; }
        .efficiency-label { font-size: 0.7rem; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        .efficiency-footer { padding-top: 15px; border-top: 2px solid rgba(0,0,0,0.05); font-size: 0.75rem; color: #555; }

        .side-activity-card { background: #f3efe3; border-radius: 25px; padding: 25px; }
        .side-activity-card h4 { font-size: 1.1rem; display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .activity-timeline { display: flex; flex-direction: column; gap: 20px; }
        .activity-item { display: flex; gap: 15px; position: relative; }
        .activity-dot { width: 12px; height: 12px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
        .dot-green { background: #4e6a55; }
        .dot-orange { background: #d4a373; }
        .dot-grey { background: #999; }
        .activity-info h6 { font-size: 0.9rem; font-weight: 700; margin-bottom: 4px; color: #333; }
        .activity-info p { font-size: 0.75rem; color: #666; line-height: 1.4; margin-bottom: 4px; }
        .activity-info span { font-size: 0.65rem; font-weight: 700; color: #999; text-transform: uppercase; }

        .side-status-card { background: white; border-radius: 25px; padding: 25px; box-shadow: var(--sombra); border: 1px solid rgba(0,0,0,0.02); position: relative; }
        .status-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-size: 0.85rem; }
        .status-label { color: #888; font-weight: 500; }
        .status-value { font-weight: 600; color: #333; }
        .status-bar-bg { height: 6px; background: #f0f0f0; border-radius: 10px; overflow: hidden; margin: 15px 0; }
        .status-bar-fill { height: 100%; background: #4e6a55; border-radius: 10px; }
        .btn-report { background: transparent; border: none; color: #4e6a55; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 0; }
        
        .fab-notif { position: absolute; bottom: -15px; right: -15px; width: 50px; height: 50px; background: #f3a65a; border-radius: 15px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; box-shadow: 0 10px 20px rgba(243,166,90,0.3); cursor: pointer; }

        /* Estilos Notificaciones Estudiante */
        .student-notif-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
        .student-notif-title h2 { font-size: 2.2rem; color: #4e6a55; margin-bottom: 5px; font-weight: 700; }
        .student-notif-title p { color: #888; font-size: 0.9rem; max-width: 400px; }
        .btn-mark-read { background: #4e6a55; color: white; border: none; padding: 12px 25px; border-radius: 15px; font-weight: 600; cursor: pointer; }

        .student-pref-card { background: #eeebe1; border-radius: 25px; padding: 25px; }
        .student-pref-card h4 { font-size: 1.1rem; margin-bottom: 20px; color: #333; }
        .pref-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .pref-label { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: #444; font-weight: 600; }
        .pref-label i { font-size: 1rem; color: #666; }
        
        .switch { position: relative; display: inline-block; width: 40px; height: 20px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 20px; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #4e6a55; }
        input:checked + .slider:before { transform: translateX(20px); }

        .student-quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .quick-action-btn { background: white; border: none; padding: 15px 10px; border-radius: 15px; display: flex; flex-direction: column; align-items: center; gap: 8px; cursor: pointer; transition: transform 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .quick-action-btn:hover { transform: translateY(-3px); }
        .quick-action-btn i { font-size: 1.2rem; color: #4e6a55; padding: 10px; background: #f5f5f5; border-radius: 10px; }
        .quick-action-btn span { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: #666; text-align: center; }

        .student-progress-card { background: #4e6a55; color: white; border-radius: 25px; padding: 25px; position: relative; overflow: hidden; }
        .student-progress-card h4 { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; margin-bottom: 10px; }
        .progress-val { font-size: 2.2rem; font-weight: 700; margin-bottom: 5px; }
        .progress-desc { font-size: 0.8rem; opacity: 0.9; margin-bottom: 15px; }
        .progress-bar-student { height: 6px; background: rgba(255,255,255,0.2); border-radius: 10px; }
        .progress-bar-student-fill { height: 100%; background: white; border-radius: 10px; width: 84%; }

        .announcement-banner { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1521587760476-6c12a4b040da?q=80&w=2070&auto=format&fit=crop'); background-size: cover; background-position: center; border-radius: 30px; padding: 40px; color: white; display: flex; flex-direction: column; justify-content: flex-end; min-height: 250px; margin-top: 30px; position: relative; }
        .announcement-banner .badge { background: rgba(255,255,255,0.2); backdrop-filter: blur(5px); padding: 5px 12px; border-radius: 8px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; align-self: flex-start; margin-bottom: 15px; }
        .announcement-banner h3 { font-size: 2rem; margin-bottom: 10px; font-weight: 700; }
        .announcement-banner p { font-size: 0.95rem; opacity: 0.9; margin-bottom: 25px; max-width: 400px; }
        .btn-read-more { background: white; color: #333; border: none; padding: 10px 22px; border-radius: 15px; font-weight: 600; cursor: pointer; align-self: flex-start; }
        
        .chat-fab { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; background: #8a4b08; border-radius: 20px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; box-shadow: 0 10px 30px rgba(138,75,8,0.4); cursor: pointer; z-index: 1000; }

        .session-wide-card { background: white; padding: 20px; border-radius: 20px; display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border-left: 6px solid #8a6d3b; }
        .session-date-box { min-width: 60px; text-align: center; border-right: 1px solid #eee; padding-right: 15px; }
        .session-date-box strong { font-size: 1.3rem; display: block; color: #333; }
        .session-date-box span { font-size: 0.7rem; color: #999; text-transform: uppercase; font-weight: 700; }
        .session-wide-info { flex: 1; }
        .session-wide-info h5 { font-size: 1.1rem; color: #111; margin-bottom: 5px; }
        .session-wide-info p { font-size: 0.85rem; color: #666; }
        .session-wide-icons { display: flex; gap: 15px; color: #555; font-size: 1.2rem; }
        .session-wide-icons i { cursor: pointer; transition: color 0.2s; }
        .session-wide-icons i:hover { color: var(--verde-tecno); }

        .ia-insight-banner { background: linear-gradient(135deg, #f3a65a, #e67e22); padding: 20px; border-radius: 15px; color: white; position: relative; overflow: hidden; }
        .ia-insight-banner strong { display: block; margin-bottom: 5px; font-size: 0.9rem; }
        .ia-insight-banner p { font-size: 0.8rem; opacity: 0.9; line-height: 1.4; }

        @media (max-width: 1100px) {
            .dash-main-layout { grid-template-columns: 1fr; }
            .dash-right-col { display: grid; grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            body { flex-direction: column; font-size: 0.85rem; }
            .sidebar { width: 100%; height: 60px; flex-direction: row; padding: 0; justify-content: space-around; z-index: 1000; position: fixed; bottom: 0; }
            .sidebar-bottom { display: none; }
            .sidebar-icons { flex-direction: row; width: 100%; justify-content: space-around; display: flex; }
            .nav-item { padding: 8px; justify-content: center; flex: 1; }
            .nav-item i { font-size: 1.2rem; }
            .menu-btn-container { display: none; }
            .nav-text { display: none !important; }
            .sidebar.expanded { width: 100%; }
            .main-container { padding-bottom: 60px; height: 100vh; }
            .content { padding: 15px; }
            
            /* Ajustes de tipografía para celular (no tan grandes) */
            .topbar { padding: 0 15px; height: 55px; }
            .topbar-title { font-size: 1.4rem !important; }
            .topbar-icons { gap: 10px; }
            .topbar-icons i { font-size: 1.1rem; }
            
            .dash-header h2, .notif-hero-card h2, .student-notif-title h2, .page-header h1, .dash-title-group h2, .calls-header h2 { font-size: 1.4rem !important; }
            h1, h2, h3 { font-size: 1.1rem !important; }
            h4, h5 { font-size: 0.9rem !important; }
            .stat-info strong, .efficiency-val, .progress-val, .call-stat strong { font-size: 1.3rem !important; }
            .dash-title-group h4 { font-size: 0.7rem !important; }
            
            .dash-right-col { grid-template-columns: 1fr; }
            .report-grid { grid-template-columns: 1fr; }
            .materiales-body { flex-direction: column; }
            .materiales-sidebar { width: 100%; }
            .calls-body { grid-template-columns: 1fr; }
            
            .profile-container { flex-direction: column; }
            .profile-left { width: 100%; }
            .form-grid-profile { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            
            .calendar-layout { flex-direction: column; }
            .calendar-main { border-right: none; border-bottom: 1px solid #f0f0f0; padding: 15px; }
            .calendar-right-panel { width: 100%; padding: 15px; }
            
            .dash-main-layout { grid-template-columns: 1fr; gap: 20px; }
            .notif-dashboard { grid-template-columns: 1fr; gap: 20px; }
            .dash-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .notif-hero-card { padding: 20px; }
            .notif-hero-card h2 { font-size: 1.5rem; }
            
            .calls-header { flex-direction: column; align-items: flex-start; gap: 15px; padding: 20px; }
            .calls-header-stats { width: 100%; justify-content: space-around; }
            
            .welcome-card, .stat-card, .dash-item, .dash-report-card, .side-panel-card { padding: 15px; }
        }

        /* ===== LLAMADAS ===== */
        .calls-container { display: none; flex-direction: column; gap: 25px; }
        .calls-container.active { display: flex; }

        .calls-header { background: linear-gradient(135deg, var(--verde-oscuro), #2d4a34); border-radius: 20px; padding: 30px 35px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .calls-header h2 { font-family: 'Bangers', cursive; font-size: 2.2rem; letter-spacing: 1px; margin-bottom: 5px; }
        .calls-header p { font-size: 0.9rem; opacity: 0.8; }
        .calls-header-stats { display: flex; gap: 25px; }
        .call-stat { text-align: center; }
        .call-stat strong { font-size: 1.8rem; font-weight: 700; display: block; }
        .call-stat span { font-size: 0.7rem; text-transform: uppercase; opacity: 0.7; letter-spacing: 1px; }

        .calls-body { display: grid; grid-template-columns: 1fr 320px; gap: 25px; }
        .calls-main { display: flex; flex-direction: column; gap: 20px; }
        .calls-side { display: flex; flex-direction: column; gap: 20px; }

        .calls-search-bar { background: white; border-radius: 15px; padding: 10px 20px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.05); }
        .calls-search-bar input { flex: 1; border: none; outline: none; font-size: 0.95rem; color: #333; background: transparent; }
        .calls-search-bar i { color: #aaa; font-size: 1rem; }

        .contact-card { background: white; border-radius: 18px; padding: 18px 22px; display: flex; align-items: center; gap: 18px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.04); transition: transform 0.2s, box-shadow 0.2s; cursor: default; }
        .contact-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .contact-avatar { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 3px solid #e8f0eb; flex-shrink: 0; }
        .contact-info { flex: 1; }
        .contact-info h5 { font-size: 1rem; color: #222; margin-bottom: 3px; font-weight: 600; }
        .contact-info p { font-size: 0.78rem; color: #999; margin-bottom: 5px; }
        .contact-role-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .role-estudiante { background: #e9f5ec; color: #2d7a44; }
        .role-docente { background: #e9f0fb; color: #4a3a9e; }
        .role-tutor { background: #fdf3e9; color: #c47a1e; }
        .role-admin { background: #fde9e9; color: #c0392b; }
        .contact-actions { display: flex; gap: 10px; }
        .btn-call { width: 42px; height: 42px; border-radius: 12px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s; }
        .btn-call-audio { background: #e8f5ee; color: #2d7a44; }
        .btn-call-audio:hover { background: #2d7a44; color: white; }
        .btn-call-video { background: #e9f0fb; color: #4a3a9e; }
        .btn-call-video:hover { background: #4a3a9e; color: white; }
        .no-contacts { text-align: center; padding: 60px 20px; color: #aaa; }
        .no-contacts i { font-size: 3rem; margin-bottom: 15px; color: #ddd; }

        .calls-side-card { background: white; border-radius: 18px; padding: 22px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.04); }
        .calls-side-card h4 { font-size: 1rem; font-weight: 700; color: #333; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
        .history-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
        .history-item:last-child { border-bottom: none; }
        .history-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
        .history-info { flex: 1; }
        .history-info h6 { font-size: 0.85rem; color: #333; margin-bottom: 2px; }
        .history-info span { font-size: 0.7rem; color: #aaa; }
        .history-type { font-size: 0.8rem; }
        .hist-out { color: #2d7a44; }
        .hist-in { color: #4a3a9e; }
        .hist-missed { color: #e74c3c; }

        /* Modal de llamada EMBEBIDA */
        .call-modal-overlay { display: none; position: fixed; inset: 0; background: #0d0d1a; z-index: 3000; flex-direction: column; }
        .call-modal-overlay.open { display: flex; }
        .call-modal-topbar { display: flex; align-items: center; justify-content: space-between; padding: 12px 22px; background: rgba(255,255,255,0.06); border-bottom: 1px solid rgba(255,255,255,0.08); flex-shrink: 0; }
        .call-modal-contact { display: flex; align-items: center; gap: 12px; }
        .call-modal-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid #4a3a9e; }
        .call-modal-name { font-size: 1rem; font-weight: 700; color: white; }
        .call-modal-badge { font-size: 0.72rem; color: rgba(255,255,255,0.5); margin-top: 2px; }
        .call-modal-logo { font-family: 'Bangers', cursive; font-size: 1.4rem; color: rgba(255,255,255,0.4); letter-spacing: 2px; }
        #jitsi-container { flex: 1; width: 100%; }
        .btn-end-call { background: #e74c3c; color: white; border: none; padding: 9px 22px; border-radius: 25px; cursor: pointer; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s, transform 0.1s; }
        .btn-end-call:hover { background: #c0392b; transform: scale(1.05); }

        /* === DARK THEME === */
        body.dark-theme {
            --crema-fondo: #121212;
            --crema-claro: #1e1e1e;
            --text-color: #f0f0f0;
        }
        body.dark-theme .topbar, body.dark-theme .sidebar { border-color: #333; }
        body.dark-theme .topbar-title { color: #f0f0f0; }
        body.dark-theme .topbar-icons i { color: #f0f0f0; }
        body.dark-theme .welcome-card, body.dark-theme .stat-card, body.dark-theme .dash-item, body.dark-theme .side-panel-card, body.dark-theme .request-card, body.dark-theme .notif-card, body.dark-theme .side-status-card {
            background: #1e1e1e; color: #f0f0f0; border-color: #333;
        }
        body.dark-theme h2, body.dark-theme h3, body.dark-theme h4, body.dark-theme h5 { color: #f0f0f0 !important; }
        body.dark-theme .stat-info strong, body.dark-theme .efficiency-val { color: #fff; }
        body.dark-theme .dash-header h2 { color: #fff; }
        body.dark-theme .material-card { background: #2a2a2a; color: #f0f0f0; }
        body.dark-theme .search-container, body.dark-theme .filter-box { background: #1e1e1e; border: 1px solid #333; }
        body.dark-theme .search-container input, body.dark-theme .filter-group input, body.dark-theme .filter-group select { background: #333; color: #fff; border: none; }
        body.dark-theme .calendar-container { background: #1e1e1e; }
        body.dark-theme .calendar-main { background: #1e1e1e; border-color: #333; }
        body.dark-theme .calendar-day, body.dark-theme .calendar-day-head, body.dark-theme .calendar-grid { border-color: #333; background: #1e1e1e; }
        body.dark-theme .calendar-day:hover { background: #2a2a2a; }
        body.dark-theme .calendar-day.today { background: #3a4a3e; }
        body.dark-theme .calendar-right-panel { background: #161616; }
        body.dark-theme .detail-card, body.dark-theme .profile-left, body.dark-theme .profile-form-card { background: #252525; border-color: #333; }
        body.dark-theme .input-grp input, body.dark-theme .input-grp select { background: #333; color: #fff; border-color: #444; }
        body.dark-theme .calls-search-bar { background: #252525; border-color: #333; }
        body.dark-theme .calls-search-bar input { color: #fff; }
        body.dark-theme .contact-card, body.dark-theme .calls-side-card { background: #252525; border-color: #333; }
        body.dark-theme .btn-dash-light { background: #333; color: #f0f0f0; }
        body.dark-theme .student-pref-card { background: #252525; }
        
        body.dark-theme .user-dropdown { background: #252525; border-color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        body.dark-theme .dropdown-item { color: #f0f0f0; border-bottom-color: #333; }
        body.dark-theme .dropdown-item:hover { background: #333; }
        body.dark-theme .user-btn:hover { background: rgba(255,255,255,0.05); }
        body.dark-theme .user-btn img { border-color: #f0f0f0; }
        body.dark-theme .dropdown-item.danger:hover { background: rgba(217,83,79,0.15); }
    </style>
    <!-- Jitsi Meet External API -->
    <script src='https://meet.jit.si/external_api.js'></script>
</head>

<body class="<?php echo ($user['theme'] ?? 'Claro') === 'Oscuro' ? 'dark-theme' : ''; ?>">
    <nav class="sidebar" id="sidebar">
        <div class="menu-btn-container" id="menu-btn">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-icons">
            <div class="nav-item">
                <i class="fas fa-home"></i>
                <span class="nav-text">Inicio</span>
            </div>
            <div class="nav-item">
                <i class="fas fa-book-open"></i>
                <span class="nav-text">Materiales</span>
            </div>
            <div class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span class="nav-text">Calendario</span>
            </div>
            <div class="nav-item">
                <i class="fas fa-envelope"></i>
                <span class="nav-text">Chats</span>
            </div>
            <div class="nav-item">
                <i class="fas fa-phone"></i>
                <span class="nav-text">Llamadas</span>
            </div>
            <div class="nav-item">
                <i class="fas fa-bell"></i>
                <span class="nav-text">Notificaciones</span>
            </div>
            <?php if (in_array(strtolower(trim($user['rol'] ?? '')), ['administrador', 'admin'])): ?>
            <div class="nav-item">
                <i class="fas fa-users-cog"></i>
                <span class="nav-text">Panel Admin</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="sidebar-bottom">
            <div class="nav-item">
                <i class="fas fa-cog"></i>
                <span class="nav-text">Ajustes</span>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <header class="topbar">
            <div class="topbar-title" style="font-family: 'Bangers'; font-size: 3rem;">TECNO AGENDA</div>
            <div class="topbar-user-menu">
                <div class="user-btn" id="user-btn">
                    <span class="user-btn-name"><?php echo htmlspecialchars($user['nombre']); ?></span>
                    <img src="<?php echo isset($user['avatar']) && !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>" alt="User">
                </div>
                <div class="user-dropdown" id="user-dropdown">
                    <div class="dropdown-item" id="btn-dropdown-profile">
                        <i class="fas fa-user-edit" style="color:var(--verde-tecno); font-size: 1.1rem;"></i> Editar perfil
                    </div>
                    <div class="dropdown-item danger" onclick="logout()">
                        <i class="fas fa-sign-out-alt" style="font-size: 1.1rem;"></i> Cerrar sesión
                    </div>
                </div>
            </div>
        </header>

        <main class="content">
            <!-- Pantalla de Inicio Estudiante -->
            <div class="welcome-card" id="welcome-card" style="<?php $r0 = strtolower(trim($user['rol'] ?? '')); echo $r0 === 'estudiante' ? 'display:block;' : 'display:none;'; ?>">
                <div class="welcome-header">
                    <h2 id="welcome-text" style="font-family: 'Bangers'; color: var(--verde-tecno); font-size: 38px; text-transform: uppercase;">¡Bienvenido, <?php echo htmlspecialchars($user['nombre']); ?>!</h2>
                    <p style="color: #666; font-size: 1.1rem;">Gestiona tus asesorías y materiales de forma eficiente.</p>
                </div>
            </div>

            <!-- Dashboard Analytics (Admin, Docente, Tutor) -->
            <div class="admin-dash-container" id="admin-dash-container" style="<?php $r1 = strtolower(trim($user['rol'] ?? '')); echo $r1 !== 'estudiante' ? 'display:flex;' : 'display:none;'; ?>">
                <div class="dash-header">
                    <div class="dash-title-group">
                        <h4>Resumen de Actividad</h4>
                        <h2>Análisis de Rendimiento</h2>
                    </div>
                    <div class="dash-actions">
                        <button class="btn-dash btn-dash-light">Esta Semana</button>
                        <button class="btn-dash btn-dash-dark" onclick="window.print()">Exportar Reporte</button>
                    </div>
                </div>

                <div class="dash-stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon" style="background: #e9f5ed; color: var(--verde-tecno);"><i class="fas fa-users"></i></div>
                            <div class="stat-trend trend-up">+12%</div>
                        </div>
                        <div class="stat-info">
                            <span>Alumnos Activos</span>
                            <strong id="stat-alumnos">0</strong>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon" style="background: #fff5e9; color: #f39c12;"><i class="fas fa-file-signature"></i></div>
                            <div class="stat-trend trend-down">-3%</div>
                        </div>
                        <div class="stat-info">
                            <span>Revisiones Pendientes</span>
                            <strong id="stat-pendientes">0</strong>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon" style="background: #eef2ff; color: #4f46e5;"><i class="fas fa-chart-line"></i></div>
                            <div class="stat-trend trend-up">+5.4</div>
                        </div>
                        <div class="stat-info">
                            <span>Promedio General</span>
                            <strong id="stat-promedio">8.6</strong>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-check-double"></i></div>
                            <div class="stat-trend" style="color:#10b981;">92%</div>
                        </div>
                        <div class="stat-info">
                            <span>Tasa de Entrega</span>
                            <strong id="stat-entrega">89</strong>
                        </div>
                    </div>
                </div>

                <div class="dash-main-layout">
                    <div class="dash-left-col">
                        <section id="dash-new-requests-section" style="display: none;">
                            <div class="section-header">
                                <h3 style="color: #4e6a55;">Nuevas Solicitudes</h3>
                            </div>
                            <div class="dash-stats-grid" id="dash-new-requests-list" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                                <!-- Poblado dinámicamente -->
                            </div>
                        </section>

                        <section>
                            <div class="section-header">
                                <h3 style="color: #4e6a55;">Próximas Asesorías</h3>
                                <a href="#" onclick="document.querySelectorAll('.nav-item')[2].click()">Ver todo →</a>
                            </div>
                            <div class="dash-list" id="dash-upcoming-wide-list">
                                <!-- Poblado dinámicamente -->
                            </div>
                        </section>

                        <section>
                            <div class="section-header">
                                <h3>Gestión de Materiales</h3>
                                <a href="#" onclick="document.querySelectorAll('.nav-item')[1].click()">Ver todo el repositorio</a>
                            </div>
                            <div class="dash-list" id="dash-material-list">
                                <!-- Poblado dinámicamente -->
                            </div>
                        </section>
                    </div>

                    <div class="dash-right-col">
                        <div class="side-panel-card">
                            <h4>Gestión de Grupos</h4>
                            <div class="group-list" id="dash-group-list">
                                <!-- Poblado dinámicamente -->
                            </div>
                        </div>

                        <section>
                            <div class="section-header">
                                <h3>Reportes Académicos</h3>
                            </div>
                            <div class="dash-report-card">
                                <div class="report-grid" style="grid-template-columns: 1fr; gap: 20px;">
                                    <div class="report-progress-group">
                                        <h6>Progreso del Plan de Estudios <span>68%</span></h6>
                                        <div class="progress-bar"><div class="progress-fill" style="width: 68%; background: var(--verde-tecno);"></div></div>
                                    </div>
                                    <div class="report-progress-group">
                                        <h6>Calidad de Mentorías <span>84%</span></h6>
                                        <div class="progress-bar"><div class="progress-fill" style="width: 84%; background: #a37c3c;"></div></div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <!-- Lista de Asesores para Alumnos (FUERA del welcome-card) -->
            <?php if ($user['rol'] === 'Estudiante'): ?>
            <div id="home-advisors-list" style="margin-top: 40px; width: 100%; max-width: 1000px; margin-left: auto; margin-right: auto; display: none;">
                <h3 style="color: var(--verde-tecno); font-family: 'Bangers'; font-size: 32px; margin-bottom: 25px; text-align: center; text-transform: uppercase; text-shadow: 2px 2px 0px rgba(255,255,255,0.5);">Únete a una Asesoría</h3>
                
                <div style="margin-bottom: 25px; display: flex; justify-content: flex-end;">
                    <div style="position: relative; width: 100%; max-width: 350px;">
                        <input type="text" id="advisor-specialty-search" placeholder="Filtrar por especialidad (ej. Matemáticas)..." style="width: 100%; padding: 12px 15px 12px 40px; border-radius: 10px; border: 1px solid #ddd; outline: none; background: #fff;">
                        <i class="fas fa-filter" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa;"></i>
                    </div>
                </div>

                <div id="home-advisors-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px;"></div>
            </div>
            <?php endif; ?>

            <!-- Perfil -->
            <div class="profile-container" id="profile-container">
                <div class="profile-left">
                    <div class="avatar-circle" id="avatar-container">
                        <img src="<?php echo isset($user['avatar']) ? $user['avatar'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>" id="prof-avatar-img">
                        <div class="avatar-overlay" id="avatar-overlay"><i class="fas fa-camera"></i></div>
                        <input type="file" id="avatar-upload" accept="image/*" style="display: none;">
                    </div>
                    <button class="profile-btn btn-edit" id="btn-edit-profile">Editar Perfil</button>
                    <button class="profile-btn btn-save" id="btn-save-profile" style="display:none;">Guardar Cambios</button>
                    <button class="profile-btn" onclick="logout()" style="background: #db5d5d; color: white; border: none; margin-top: 10px;"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</button>
                </div>
                <div class="profile-right">
                    <div class="profile-form-card">
                        <div class="form-grid-profile">
                            <div class="input-grp full-width"><label>Nombre</label><input type="text" id="prof-nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>" readonly></div>
                            <div class="input-grp full-width"><label>Descripción / Biografía</label><textarea id="prof-descripcion" rows="3" style="background: #f5f1e8; border: 1px solid #d5d0c3; padding: 10px; border-radius: 6px; resize: none;" readonly><?php echo htmlspecialchars($user['descripcion'] ?? ''); ?></textarea></div>
                            <div class="input-grp"><label>Fecha de Nacimiento</label><input type="date" id="prof-fecha" value="<?php echo htmlspecialchars($user['fechaNacimiento']); ?>" readonly></div>
                            <div class="input-grp"><label>Carrera</label>
                                <select id="prof-carrera" disabled>
                                    <option value="TICS" <?php echo $user['carrera'] == 'TICS' ? 'selected' : ''; ?>>TICS</option>
                                    <option value="Gestion" <?php echo $user['carrera'] == 'Gestion' ? 'selected' : ''; ?>>Gestion</option>
                                    <option value="industrial" <?php echo $user['carrera'] == 'industrial' ? 'selected' : ''; ?>>industrial</option>
                                    <option value="Mecatronica" <?php echo $user['carrera'] == 'Mecatronica' ? 'selected' : ''; ?>>Mecatronica</option>
                                    <option value="Automotris" <?php echo $user['carrera'] == 'Automotris' ? 'selected' : ''; ?>>Automotris</option>
                                    <option value="Agricola" <?php echo $user['carrera'] == 'Agricola' ? 'selected' : ''; ?>>Agricola</option>
                                </select>
                            </div>
                            <div class="input-grp"><label>Semestre</label><input type="number" id="prof-semestre" value="<?php echo $user['semestre']; ?>" readonly></div>
                            <div class="input-grp"><label>Rol</label><input type="text" id="prof-rol" value="<?php echo $user['rol']; ?>" readonly class="locked"></div>
                            <div class="input-grp full-width"><label>Contraseña</label><div class="icon-input"><input type="password" id="prof-pass" placeholder="Dejar en blanco para no cambiar" readonly><i class="fas fa-eye-slash" id="toggle-prof-pass"></i></div></div>
                            <div class="input-grp full-width" id="materias-grp" <?php echo (strtolower(trim($user['rol'] ?? '')) === 'estudiante') ? 'style="display:none;"' : ''; ?>><label>Especialidad / Materias que Imparto</label><input type="text" id="prof-materias" value="<?php echo htmlspecialchars($user['materias_inscritas'] ?? ''); ?>" placeholder="Ej. Matemáticas, Programación..." readonly></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Materiales -->
            <div class="materiales-container" id="materiales-container">
                <div class="search-container"><input type="text" id="material-search" placeholder="Buscar materiales..."><i class="fas fa-search"></i></div>
                <div class="materiales-body">
                    <div class="materiales-list" id="materials-list"></div>
                    <div class="materiales-sidebar">
                        <div class="filter-box">
                            <h4>Filtros</h4>
                            <div class="filter-group"><label>Materia:</label><select id="filter-materia"><option value="">Todas</option></select></div>
                        </div>
                        <?php if (strtolower(trim($user['rol'] ?? '')) !== 'estudiante'): ?>
                        <div class="filter-box upload-box">
                            <h4>Nueva Carpeta</h4>
                            <form id="form-create-folder">
                                <input type="hidden" name="action" value="create_folder">
                                <div class="filter-group"><input type="text" name="nombre_carpeta" required placeholder="Nombre de la materia"></div>
                                <button type="submit" class="profile-btn btn-save">Crear</button>
                            </form>
                        </div>
                        <div class="filter-box upload-box">
                            <h4>Subir Archivo</h4>
                            <form id="form-upload-material" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_material">
                                <div class="filter-group"><input type="text" name="titulo" required placeholder="Título"></div>
                                <div class="filter-group"><select name="materia" id="select-folder-upload" required></select></div>
                                <div class="filter-group"><input type="file" name="archivo" required></div>
                                <button type="submit" class="profile-btn btn-save" id="btn-upload-submit">Subir</button>
                                <div class="progress-container" id="upload-progress-container">
                                    <div class="progress-bar" id="upload-progress-bar"></div>
                                </div>
                                <div class="loading-spinner" id="upload-status-text" style="font-size: 0.9rem; font-weight: bold;">Subiendo...</div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Calendario Rediseñado -->
            <div class="calendar-container" id="calendar-container">
                <div class="calendar-layout">
                    <div class="calendar-main">
                        <div class="calendar-header">
                            <h2 id="calendar-month-year" style="font-weight: 600; font-size: 1.4rem;">Abril 2026</h2>
                            <div class="calendar-nav">
                                <button onclick="prevMonth()" class="nav-btn"><i class="fas fa-chevron-left"></i></button>
                                <button onclick="nextMonth()" class="nav-btn"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="calendar-grid">
                            <!-- JS genera esto -->
                        </div>
                    </div>
                    
                    <div class="calendar-right-panel">
                        <div id="selected-event-detail">
                            <div class="detail-card">
                                <span class="date-label" id="right-date-label">Martes 21 Abril 2026</span>
                                <h3 id="right-event-title">Selecciona una sesión</h3>
                                <div id="right-event-info">
                                    <p style="color: #888; font-size: 0.9rem;">Haz clic en un evento del calendario para ver los detalles aquí.</p>
                                </div>
                                <div id="right-advisor-box" class="advisor-info" style="display: none;">
                                    <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" class="advisor-avatar" id="right-advisor-avatar">
                                    <div class="advisor-details">
                                        <span id="right-advisor-name">Nombre Asesor</span>
                                        <small id="right-advisor-role">Especialista</small>
                                    </div>
                                </div>
                                <div id="right-action-box" style="margin-top: 25px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Crear Evento (Solo Asesores) -->
            <div id="event-modal" class="modal">
                <div class="modal-content">
                    <h2 style="font-family: 'Bangers'; color: var(--verde-tecno); margin-bottom: 20px;">Programar Asesoría</h2>
                    <form id="form-event">
                        <div class="filter-group">
                            <label>Título:</label>
                            <input type="text" name="titulo" required>
                        </div>
                        <div class="filter-group">
                            <label>Modalidad:</label>
                            <select name="modalidad" id="event-modalidad" onchange="toggleModalidadFields()">
                                <option value="virtual">Videollamada</option>
                                <option value="llamada">Llamada Telefónica</option>
                                <option value="presencial">Presencial</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Fecha:</label>
                            <input type="date" name="fecha" id="event-date" required>
                        </div>
                        <div class="filter-group">
                            <label>Hora:</label>
                            <input type="time" name="hora" required>
                        </div>
                        <div id="field-virtual" class="filter-group">
                            <label>Enlace (Meet/Teams):</label>
                            <input type="url" name="enlace" placeholder="https://meet.google.com/...">
                        </div>
                        <div id="field-presencial" class="filter-group" style="display:none;">
                            <label>Lugar / Ubicación:</label>
                            <input type="text" name="lugar" placeholder="Ej: Cubículo 5, Biblioteca">
                        </div>
                        <div id="field-llamada" class="filter-group" style="display:none;">
                            <label>Instrucciones de Llamada:</label>
                            <input type="text" name="instrucciones" placeholder="Ej: El alumno llamará al asesor">
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" class="profile-btn btn-save">Guardar</button>
                            <button type="button" onclick="closeEventModal()" class="profile-btn" style="background:#ccc;">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notificaciones -->
            <div class="notifications-container" id="notifications-container" style="display:none;">
                <?php if ($user['rol'] !== 'Estudiante'): ?>
                <!-- VISTA DOCENTE / ADMIN / TUTOR (ALERTS) -->
                <div id="notif-admin-view">
                    <!-- Header del Dashboard de Alertas -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #f3a65a; padding: 15px 30px; border-radius: 20px; color: #111;">
                        <h1 style="font-family: 'Bangers'; font-size: 2.2rem; margin: 0; letter-spacing: 1px;">Alertas</h1>
                        <div style="display: flex; gap: 20px; align-items: center; flex: 1; max-width: 500px; margin: 0 40px;">
                            <div style="background: rgba(255,255,255,0.3); border-radius: 12px; display: flex; align-items: center; padding: 8px 15px; width: 100%;">
                                <i class="fas fa-search" style="font-size: 0.9rem; margin-right: 10px; opacity: 0.7;"></i>
                                <input type="text" placeholder="Buscar alertas..." style="background: transparent; border: none; outline: none; width: 100%; font-size: 0.9rem; color: #111;">
                            </div>
                        </div>
                    </div>

                    <div class="notif-dashboard">
                        <!-- Columna Principal -->
                        <div class="notif-main-col">
                            <!-- Hero Card (Active Requests) -->
                            <div class="notif-hero-card">
                                <h4>Solicitudes Activas</h4>
                                <h2 id="notif-pending-count">0 Pendientes por Aprobar</h2>
                                <p id="notif-hero-desc">Tienes solicitudes de alumnos esperando tu confirmación para unirse a tus grupos de asesoría.</p>
                                <div class="notif-hero-btns">
                                    <button class="notif-btn-bulk"><i class="fas fa-layer-group"></i> Acción en Lote</button>
                                    <button class="notif-btn-schedule" onclick="document.getElementById('calendar-container').click()">Ver Calendario</button>
                                </div>
                            </div>

                            <!-- Session Requests Section -->
                            <div class="notif-requests-section">
                                <div class="notif-section-header">
                                    <h3><i class="fas fa-calendar-check" style="color: #d4a373;"></i> Solicitudes de Sesión</h3>
                                    <span class="notif-sync-text">Sincronizado: hace 1m</span>
                                </div>
                                <div id="notifications-list">
                                    <!-- Poblado dinámicamente -->
                                </div>
                            </div>
                        </div>

                        <!-- Columna Lateral -->
                        <div class="notif-side-col">
                            <div class="side-efficiency-card">
                                <i class="fas fa-chart-line"></i>
                                <span class="efficiency-label">Eficiencia del Sistema</span>
                                <div class="efficiency-val">98.4%</div>
                                <div class="efficiency-footer">Reporte automatizado activo.</div>
                            </div>
                            <div class="side-activity-card">
                                <h4><i class="fas fa-users-viewfinder" style="color: #d4a373;"></i> Actividad de Alumnos</h4>
                                <div class="activity-timeline" id="notif-activity-list">
                                    <div class="activity-item"><div class="activity-dot dot-green"></div><div class="activity-info"><h6>Proyecto Subido</h6><p>El alumno Liam S. subió "Draft_v2.pdf".</p><span>HACE 15 MINUTOS</span></div></div>
                                    <div class="activity-item"><div class="activity-dot dot-orange"></div><div class="activity-info"><h6>Comentario Recibido</h6><p>Nueva consulta sobre el material.</p><span>HACE 1 HORA</span></div></div>
                                </div>
                            </div>
                            <div class="side-status-card">
                                <div class="status-row"><span class="status-label">Almacenamiento</span><span class="status-value">14.2 GB / 50 GB</span></div>
                                <div class="status-bar-bg"><div class="status-bar-fill" style="width: 28%;"></div></div>
                                <div class="fab-notif"><i class="fas fa-plus"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- VISTA ESTUDIANTE -->
                <div id="notif-student-view">
                    <div class="student-notif-header">
                        <div class="student-notif-title">
                            <h2>Notificaciones</h2>
                            <p>Gestiona tus últimas actualizaciones académicas y alertas de tus sesiones programadas.</p>
                        </div>
                        <button class="btn-mark-read">Marcar todo como leído</button>
                    </div>

                    <div class="notif-dashboard">
                        <!-- Columna Principal -->
                        <div class="notif-main-col">
                            <div id="notifications-list-student">
                                <!-- Poblado dinámicamente -->
                                <!-- Ejemplos estáticos solicitados para visualización inicial -->
                                <div class="notif-card">
                                    <div class="notif-card-icon" style="background: #f0f4f1; color: #4e6a55;"><i class="fas fa-file-invoice"></i></div>
                                    <div class="notif-card-content">
                                        <div style="display:flex; justify-content:space-between;">
                                            <h5>Nueva Calificación y Retroalimentación</h5>
                                            <span style="font-size:0.7rem; color:#999; font-weight:700;">HACE 2M</span>
                                        </div>
                                        <p style="font-size:0.85rem; color:#666; margin-bottom:15px;">El profesor Arisleyda acaba de publicar la retroalimentación de tu trabajo final de "Arquitectura de Sistemas". ¡Excelente trabajo!</p>
                                        <div style="display:flex; gap:10px;">
                                            <button class="btn-notif-accept" style="padding:8px 15px; font-size:0.8rem;">Ver Feedback</button>
                                            <button class="btn-notif-details" style="padding:8px 15px; font-size:0.8rem;">Descargar PDF</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="notif-card">
                                    <div class="notif-card-icon" style="background: #fdf3e9; color: #d4a373;"><i class="fas fa-clock"></i></div>
                                    <div class="notif-card-content">
                                        <div style="display:flex; justify-content:space-between;">
                                            <h5>Recordatorio de Sesión Próxima</h5>
                                            <span style="font-size:0.7rem; color:#999; font-weight:700;">HACE 1H</span>
                                        </div>
                                        <p style="font-size:0.85rem; color:#666; margin-bottom:15px;">Tu sesión 1-a-1 con el Dr. Marcus Vane comienza en 30 minutos. Tema: "Sistemas de Planificación Orgánica".</p>
                                        <div style="display:flex; gap:10px;">
                                            <button class="btn-notif-accept" style="padding:8px 15px; font-size:0.8rem; background:#8a4b08;">Unirse a Reunión</button>
                                            <button class="btn-notif-details" style="padding:8px 15px; font-size:0.8rem;">Reprogramar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Banner de Anuncio -->
                            <div class="announcement-banner">
                                <span class="badge">Anuncio del Campus</span>
                                <h3>Horario Extendido de Biblioteca</h3>
                                <p>Debido al periodo de exámenes finales, el centro de investigación permanecerá abierto 24/7 a partir de este lunes.</p>
                                <button class="btn-read-more">Leer más</button>
                            </div>
                        </div>

                        <!-- Columna Lateral -->
                        <div class="notif-side-col">
                            <div class="student-pref-card">
                                <h4>Preferencias</h4>
                                <div class="pref-item">
                                    <div class="pref-label"><i class="fas fa-envelope"></i> Email Notificaciones</div>
                                    <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                </div>
                                <div class="pref-item">
                                    <div class="pref-label"><i class="fas fa-mobile-screen"></i> Push Notificaciones</div>
                                    <label class="switch"><input type="checkbox"><span class="slider"></span></label>
                                </div>
                                <div class="pref-item">
                                    <div class="pref-label"><i class="fas fa-comment-dots"></i> Alertas de Feedback</div>
                                    <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                </div>
                            </div>

                            <div class="side-activity-card" style="background:#eeebe1; padding:25px;">
                                <h4 style="font-size: 1.1rem; margin-bottom:20px;">Acciones Rápidas</h4>
                                <div class="student-quick-grid">
                                    <button class="quick-action-btn"><i class="fas fa-calendar-alt"></i><span>Ver Horario</span></button>
                                    <button class="quick-action-btn"><i class="fas fa-history"></i><span>Alertas Pasadas</span></button>
                                    <button class="quick-action-btn"><i class="fas fa-headset"></i><span>Soporte</span></button>
                                    <button class="quick-action-btn"><i class="fas fa-archive"></i><span>Archivar Todo</span></button>
                                </div>
                            </div>

                            <div class="student-progress-card">
                                <h4>Progreso del Perfil</h4>
                                <div class="progress-val">84%</div>
                                <p class="progress-desc">Onboarding Técnico completado.</p>
                                <div class="progress-bar-student">
                                    <div class="progress-bar-student-fill"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Chat FAB -->
                    <div class="chat-fab"><i class="fas fa-comment-alt"></i></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===== SECCIÓN LLAMADAS ===== -->
            <div class="calls-container" id="calls-container">

                <!-- Header -->
                <div class="calls-header">
                    <div>
                        <h2><i class="fas fa-phone-alt"></i> Llamadas</h2>
                        <p>Conecta con los usuarios registrados en la plataforma</p>
                    </div>
                    <div class="calls-header-stats">
                        <div class="call-stat">
                            <strong id="calls-stat-total">0</strong>
                            <span>Contactos</span>
                        </div>
                        <div class="call-stat">
                            <strong id="calls-stat-online">0</strong>
                            <span>En línea</span>
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div class="calls-body">
                    <!-- Columna principal: lista de contactos -->
                    <div class="calls-main">
                        <div class="calls-search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" id="calls-search-input" placeholder="Buscar usuario por nombre, correo o rol...">
                        </div>
                        <div id="contacts-list">
                            <!-- Poblado dinámicamente por JS -->
                        </div>
                    </div>

                    <!-- Columna lateral -->
                    <div class="calls-side">
                        <!-- Mi perfil -->
                        <div class="calls-side-card">
                            <h4><i class="fas fa-user-circle" style="color:var(--verde-tecno);"></i> Mi Cuenta</h4>
                            <div style="display:flex; align-items:center; gap:15px;">
                                <img src="<?php echo isset($user['avatar']) ? $user['avatar'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:3px solid var(--verde-tecno);">
                                <div>
                                    <strong style="display:block;font-size:0.95rem;color:#222;"><?php echo htmlspecialchars($user['nombre']); ?></strong>
                                    <span style="font-size:0.75rem;color:#888;"><?php echo htmlspecialchars($user['email']); ?></span><br>
                                    <span class="contact-role-badge <?php echo 'role-'.strtolower($user['rol']); ?>"><?php echo $user['rol']; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Historial de llamadas DINAMICO -->
                        <div class="calls-side-card">
                            <h4><i class="fas fa-history" style="color:var(--naranja-soft);"></i> Historial Reciente</h4>
                            <div id="calls-history">
                                <p style="text-align:center;color:#ccc;font-size:0.8rem;padding:20px 0;"><i class="fas fa-spinner fa-spin"></i> Cargando...</p>
                            </div>
                        </div>

                        <!-- Tip Jitsi -->
                        <div class="calls-side-card" style="background: linear-gradient(135deg,#e9f5ec,#f4f7ff);">
                            <h4><i class="fas fa-info-circle" style="color:#4a3a9e;"></i> ¿Cómo funciona?</h4>
                            <p style="font-size:0.82rem;color:#555;line-height:1.6;">
                                Al hacer clic en <strong>Llamar</strong> o <strong>Videollamar</strong> se abre una sala privada con el contacto. Comparte el enlace o pide que ingrese desde su sesión.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Llamada / Videollamada EMBEBIDA -->
            <div class="call-modal-overlay" id="call-modal-overlay">
                <div class="call-modal-topbar">
                    <div class="call-modal-contact">
                        <img src="" id="call-modal-avatar" class="call-modal-avatar">
                        <div>
                            <div class="call-modal-name" id="call-modal-name">Usuario</div>
                            <div class="call-modal-badge" id="call-modal-badge">En llamada...</div>
                        </div>
                    </div>
                    <div class="call-modal-logo">TECNO AGENDA</div>
                    <button class="btn-end-call" onclick="closeCallModal()">
                        <i class="fas fa-phone-slash"></i> Finalizar llamada
                    </button>
                </div>
                <div id="jitsi-container"></div>
            </div>

            <!-- Admin -->
            <div class="admin-container" id="admin-container" style="display:none; background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <h2 style="font-family: 'Bangers'; color: var(--naranja-soft); margin-bottom: 20px;">Panel Admin</h2>
                <div id="admin-users-list-dedicated" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;"></div>
            </div>
            <!-- Modal Miembros del Grupo -->
            <div id="group-members-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
                <div class="modal-content" style="background:white; padding:30px; border-radius:20px; width:90%; max-width:500px; position:relative;">
                    <button onclick="document.getElementById('group-members-modal').style.display='none'" style="position:absolute; top:15px; right:15px; border:none; background:none; font-size:1.5rem; cursor:pointer; color:#888;">&times;</button>
                    <h3 id="group-modal-title" style="font-family:'Bangers'; color:var(--verde-tecno); margin-bottom:20px; font-size:24px;">Miembros del Grupo</h3>
                    <div id="group-members-list" style="display:flex; flex-direction:column; gap:15px; max-height:400px; overflow-y:auto; padding-right:10px;">
                        <!-- Poblado dinámicamente -->
                    </div>
                </div>
            </div>
            <!-- Modal Vistas de Material -->
            <div id="material-views-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
                <div class="modal-content" style="background:white; padding:30px; border-radius:20px; width:90%; max-width:500px; position:relative;">
                    <button onclick="document.getElementById('material-views-modal').style.display='none'" style="position:absolute; top:15px; right:15px; border:none; background:none; font-size:1.5rem; cursor:pointer; color:#888;">&times;</button>
                    <h3 id="material-views-title" style="font-family:'Bangers'; color:var(--verde-tecno); margin-bottom:10px; font-size:24px;">Vistas de Material</h3>
                    <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">Alumnos que han visto o descargado este material:</p>
                    <div id="material-views-list" style="display:flex; flex-direction:column; gap:15px; max-height:400px; overflow-y:auto; padding-right:10px;">
                        <!-- Poblado dinámicamente -->
                    </div>
                </div>
            </div>
            <!-- Modal para Calificar Sesión -->
            <div id="rating-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; justify-content:center; align-items:center; backdrop-filter: blur(5px);">
                <div class="modal-content" style="background:white; padding:40px; border-radius:30px; width:90%; max-width:400px; position:relative; text-align:center; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
                    <div style="font-size: 50px; color: #ffca28; margin-bottom: 20px;"><i class="fas fa-star"></i></div>
                    <h3 style="font-family:'Bangers'; color:var(--verde-tecno); margin-bottom:10px; font-size:28px;">¡Sesión Finalizada!</h3>
                    <p style="color: #666; margin-bottom: 25px;">¿Qué te pareció la asesoría? Tu opinión ayuda a mejorar.</p>
                    
                    <div id="star-rating" style="display: flex; justify-content: center; gap: 5px; margin-bottom: 30px; font-size: 24px; color: #ddd; cursor: pointer;">
                        <i class="fas fa-star star-btn" data-value="1"></i>
                        <i class="fas fa-star star-btn" data-value="2"></i>
                        <i class="fas fa-star star-btn" data-value="3"></i>
                        <i class="fas fa-star star-btn" data-value="4"></i>
                        <i class="fas fa-star star-btn" data-value="5"></i>
                        <i class="fas fa-star star-btn" data-value="6"></i>
                        <i class="fas fa-star star-btn" data-value="7"></i>
                        <i class="fas fa-star star-btn" data-value="8"></i>
                        <i class="fas fa-star star-btn" data-value="9"></i>
                        <i class="fas fa-star star-btn" data-value="10"></i>
                    </div>
                    
                    <input type="hidden" id="rating-event-id">
                    <input type="hidden" id="rating-value" value="10">
                    
                    <button id="btn-submit-rating" class="profile-btn btn-save" style="width: 100%; padding: 15px; font-size: 1.1rem; border-radius: 15px;">Enviar Calificación</button>
                    <button onclick="document.getElementById('rating-modal').style.display='none'" style="margin-top: 15px; background: none; border: none; color: #999; cursor: pointer; text-decoration: underline;">Omitir por ahora</button>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Variables PHP -> JS
        const userEmail = "<?php echo $user['email']; ?>";
        const userRol = "<?php echo $user['rol']; ?>";
        const userName = "<?php echo addslashes($user['nombre']); ?>";
        const userMaterias = <?php echo json_encode($user['materias_inscritas'] ?? []); ?>;
    </script>
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuBtn = document.getElementById('menu-btn');
            const sidebar = document.getElementById('sidebar');
            const userCircle = document.querySelector('.fa-user-circle');
            const topbarTitle = document.querySelector('.topbar-title');
            const menuItems = document.querySelectorAll('.nav-item');

            if (menuBtn) menuBtn.addEventListener('click', () => sidebar.classList.toggle('expanded'));

            const userBtn = document.getElementById('user-btn');
            const userDropdown = document.getElementById('user-dropdown');
            const btnDropdownProfile = document.getElementById('btn-dropdown-profile');

            if (userBtn && userDropdown) {
                userBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdown.style.display = userDropdown.style.display === 'flex' ? 'none' : 'flex';
                });
            }

            document.addEventListener('click', () => {
                if (userDropdown) userDropdown.style.display = 'none';
            });

            if (btnDropdownProfile) {
                btnDropdownProfile.addEventListener('click', () => {
                    const profileContainer = document.getElementById('profile-container');
                    hideAllContainers();
                    profileContainer.style.display = 'flex';
                    profileContainer.classList.add('active');
                    topbarTitle.textContent = 'PERFIL';
                });
            }

            menuItems.forEach(item => {
                item.addEventListener('click', () => {
                    const text = item.querySelector('.nav-text')?.textContent;
                    hideAllContainers();
                    
                    if (text === 'Inicio') {
                        if (typeof userRol !== 'undefined' && userRol.toLowerCase() === 'estudiante') {
                            document.getElementById('welcome-card').style.display = 'block';
                            const advisorsList = document.getElementById('home-advisors-list');
                            if (advisorsList) advisorsList.style.display = 'block';
                        } else {
                            const dash = document.getElementById('admin-dash-container');
                            dash.style.display = 'flex';
                            dash.classList.add('active');
                            loadAdminDashboard();
                        }
                        topbarTitle.textContent = 'TECNO AGENDA';
                    } else if (text === 'Materiales') {
                        const matCont = document.getElementById('materiales-container');
                        matCont.style.display = 'flex';
                        matCont.classList.add('active');
                        topbarTitle.textContent = 'MATERIALES';
                        loadMaterials();
                    } else if (text === 'Notificaciones') {
                        document.getElementById('notifications-container').style.display = 'block';
                        topbarTitle.textContent = 'NOTIFICACIONES';
                        loadNotifications();
                    } else if (text === 'Panel Admin') {
                        document.getElementById('admin-container').style.display = 'block';
                        topbarTitle.textContent = 'PANEL ADMIN';
                        loadAdminUserListDedicated();
                    } else if (text === 'Calendario') {
                        const calCont = document.getElementById('calendar-container');
                        calCont.style.display = 'flex';
                        calCont.classList.add('active');
                        topbarTitle.textContent = 'CALENDARIO';
                        loadEvents();
                    } else if (text === 'Perfil') {
                        const profCont = document.getElementById('profile-container');
                        profCont.style.display = 'flex';
                        profCont.classList.add('active');
                        topbarTitle.textContent = 'PERFIL';
                    } else if (text === 'Ajustes') {
                        window.location.href = 'settings.php';
                    } else if (text === 'Chats') {
                        window.location.href = 'messages.php';
                    } else if (text === 'Llamadas') {
                        const callsCont = document.getElementById('calls-container');
                        callsCont.style.display = 'flex';
                        callsCont.classList.add('active');
                        topbarTitle.textContent = 'LLAMADAS';
                        loadCalls();
                    } else {
                        document.getElementById('welcome-card').style.display = 'block';
                        topbarTitle.textContent = text.toUpperCase();
                    }
                });
            });

            // Perfil
            const btnEdit = document.getElementById('btn-edit-profile');
            const btnSave = document.getElementById('btn-save-profile');
            if (btnEdit) {
                btnEdit.addEventListener('click', () => {
                    ['prof-nombre', 'prof-fecha', 'prof-pass', 'prof-materias', 'prof-semestre', 'prof-descripcion'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el && !el.classList.contains('locked')) el.removeAttribute('readonly');
                    });
                    document.getElementById('prof-carrera')?.removeAttribute('disabled');
                    document.getElementById('avatar-container').classList.add('editable');
                    btnEdit.style.display = 'none';
                    btnSave.style.display = 'block';
                });
            }

            if (btnSave) {
                btnSave.addEventListener('click', async () => {
                    const data = {
                        action: 'update_profile',
                        nombre: document.getElementById('prof-nombre').value,
                        fechaNacimiento: document.getElementById('prof-fecha').value,
                        carrera: document.getElementById('prof-carrera').value,
                        semestre: document.getElementById('prof-semestre').value,
                        password: document.getElementById('prof-pass').value,
                        rol: userRol,
                        descripcion: document.getElementById('prof-descripcion').value,
                        materias: document.getElementById('prof-materias').value,
                        avatar: document.getElementById('prof-avatar-img').src
                    };
                    const resp = await fetch('dashboard.php', { method: 'POST', body: JSON.stringify(data) });
                    const res = await resp.json();
                    if (res.status === 'success') {
                        alert(res.message);
                        window.location.href = 'dashboard.php?tab=perfil';
                    }
                });
            }

            const avatarOverlay = document.getElementById('avatar-overlay');
            if (avatarOverlay) {
                avatarOverlay.addEventListener('click', () => document.getElementById('avatar-upload').click());
                document.getElementById('avatar-upload').addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (ev) => document.getElementById('prof-avatar-img').src = ev.target.result;
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Formularios de Materiales (AJAX para evitar recargas)
            const handleFormSubmit = (formId) => {
                const form = document.getElementById(formId);
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const formData = new FormData(form);
                        try {
                            const resp = await fetch('dashboard.php', { method: 'POST', body: formData });
                            const res = await resp.json();
                            if (res.status === 'success') {
                                form.reset();
                                await loadMaterials(); // Recargar lista sin cambiar de vista
                            } else {
                                alert(res.message || "Error al procesar la solicitud");
                            }
                        } catch (err) {
                            console.error("Error en el envío del formulario:", err);
                        }
                        return false;
                    });
                }
            };

            const formUpload = document.getElementById('form-upload-material');
            if (formUpload) {
                formUpload.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const formData = new FormData(formUpload);
                    const btn = document.getElementById('btn-upload-submit');
                    const progCont = document.getElementById('upload-progress-container');
                    const progBar = document.getElementById('upload-progress-bar');
                    const statusText = document.getElementById('upload-status-text');

                    btn.disabled = true;
                    progCont.style.display = 'block';
                    statusText.style.display = 'block';
                    progBar.style.width = '0%';

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'dashboard.php', true);

                    xhr.upload.onprogress = (ev) => {
                        if (ev.lengthComputable) {
                            const percent = (ev.loaded / ev.total) * 100;
                            progBar.style.width = percent + '%';
                            statusText.textContent = `Subiendo... ${Math.round(percent)}%`;
                        }
                    };

                    xhr.onload = () => {
                        const res = JSON.parse(xhr.responseText);
                        btn.disabled = false;
                        if (res.status === 'success') {
                            statusText.textContent = "¡Completado!";
                            setTimeout(() => {
                                progCont.style.display = 'none';
                                statusText.style.display = 'none';
                                formUpload.reset();
                                loadMaterials();
                            }, 1000);
                        } else {
                            alert(res.message || "Error al subir archivo");
                            progCont.style.display = 'none';
                            statusText.style.display = 'none';
                        }
                    };

                    xhr.onerror = () => {
                        alert("Error de conexión");
                        btn.disabled = false;
                        progCont.style.display = 'none';
                    };

                    xhr.send(formData);
                    return false;
                });
            }

            handleFormSubmit('form-create-folder');

            const formEvent = document.getElementById('form-event');
            if (formEvent) {
                formEvent.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(formEvent);
                    formData.append('action', 'save_event');
                    const resp = await fetch('dashboard.php', { method: 'POST', body: formData });
                    const res = await resp.json();
                    if (res.status === 'success') {
                        closeEventModal();
                        formEvent.reset();
                        loadEvents();
                    }
                });
            }

            // Inicialización
            const urlParams = new URLSearchParams(window.location.search);
            const initialTab = urlParams.get('tab');
            
            if (initialTab === 'perfil') {
                hideAllContainers();
                document.getElementById('profile-container').classList.add('active');
                topbarTitle.textContent = 'PERFIL';
            } else if (initialTab === 'materiales') {
                hideAllContainers();
                document.getElementById('materiales-container').classList.add('active');
                topbarTitle.textContent = 'MATERIALES';
                loadMaterials();
            } else {
                // Por defecto, cargar lo necesario
                hideAllContainers();
                loadMaterials();
                loadNotifications();
                if (typeof userRol !== 'undefined' && userRol.toLowerCase() !== 'estudiante') {
                    const dash = document.getElementById('admin-dash-container');
                    dash.style.display = 'flex';
                    dash.classList.add('active');
                    loadAdminDashboard();
                } else {
                    document.getElementById('welcome-card').style.display = 'block';
                    const advisorsList = document.getElementById('home-advisors-list');
                    if (advisorsList) advisorsList.style.display = 'block';
                }
            }
        });

        function hideAllContainers() {
            const containers = [
                'welcome-card',
                'admin-dash-container',
                'materiales-container',
                'calendar-container',
                'notifications-container',
                'admin-container',
                'profile-container',
                'home-advisors-list',
                'calls-container'
            ];
            
            containers.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.style.display = 'none';
                    el.classList.remove('active');
                }
            });
        }

        async function loadAdminDashboard() {
            // Cargar Alumnos Activos (unique emails in requests belonging to this advisor)
            const respReq = await fetch('requests.json?' + new Date().getTime());
            const reqs = await respReq.json();
            const myAccepted = reqs.filter(r => r.advisor_email === userEmail && r.status === 'accepted');
            const myPending = reqs.filter(r => r.advisor_email === userEmail && r.status === 'pending');
            const uniqueStudents = [...new Set(myAccepted.map(r => r.student_email))].length;
            document.getElementById('stat-alumnos').textContent = uniqueStudents || 0;

            // Poblado de Nuevas Solicitudes
            const reqSection = document.getElementById('dash-new-requests-section');
            const reqList = document.getElementById('dash-new-requests-list');
            if (myPending.length > 0) {
                reqSection.style.display = 'block';
                reqList.innerHTML = '';
                myPending.slice(0, 4).forEach(r => {
                    const div = document.createElement('div');
                    div.className = 'request-card';
                    div.innerHTML = `
                        <div class="request-user-info">
                            <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Avatar">
                            <div>
                                <h5>${r.student_name}</h5>
                                <p>${r.student_email}</p>
                            </div>
                        </div>
                        <div class="request-btns">
                            <button class="btn-request btn-acc" onclick="respondRequest(${r.id}, 'accepted').then(loadAdminDashboard)">Aceptar</button>
                            <button class="btn-request btn-rej" onclick="respondRequest(${r.id}, 'rejected').then(loadAdminDashboard)">Rechazar</button>
                        </div>
                    `;
                    reqList.appendChild(div);
                });
            } else {
                reqSection.style.display = 'none';
            }

            // Cargar Revisiones Pendientes (materials created by this advisor)
            const respMat = await fetch('materials.json?' + new Date().getTime());
            const mats = await respMat.json();
            const myMats = mats.filter(m => m.creador_email === userEmail);
            document.getElementById('stat-pendientes').textContent = myMats.length;

            // Renderizar lista de materiales en dashboard
            const matList = document.getElementById('dash-material-list');
            matList.innerHTML = '';
            if (myMats.length === 0) {
                matList.innerHTML = '<p style="color:#888; font-size:0.8rem; text-align:center;">No has subido materiales aún.</p>';
            }
            myMats.slice(-3).reverse().forEach(m => {
                const div = document.createElement('div');
                div.className = 'dash-item';
                div.style.borderLeftColor = m.color_clase === 'card-blue' ? '#7d9d85' : '#f3a65a';
                div.innerHTML = `
                    <div class="item-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="item-info">
                        <h5>${m.titulo}</h5>
                        <p>${m.materia} • Gestionado por ti</p>
                    </div>
                    <div class="item-status" style="background: #fdf2f2; color: #c53030;">Activo</div>
                `;
                matList.appendChild(div);
            });

            // Cargar Grupos (Folders) creados por el docente
            const respFolders = await fetch('folders.json?' + new Date().getTime());
            const folders = await respFolders.json();
            const myFolders = folders.filter(f => f.creador_email === userEmail);
            
            const groupList = document.getElementById('dash-group-list');
            groupList.innerHTML = '';
            if (myFolders.length === 0) {
                groupList.innerHTML = '<p style="color:#888; font-size:0.8rem; text-align:center;">No tienes grupos creados.</p>';
            }
            myFolders.forEach((f, index) => {
                const colors = [
                    {bg: '#dcfce7', text: '#166534'},
                    {bg: '#fef3c7', text: '#92400e'},
                    {bg: '#e0f2fe', text: '#075985'}
                ];
                const color = colors[index % colors.length];
                const letter = f.nombre.charAt(0).toUpperCase();
                
                // Count students in this specific folder/materia from requests
                const folderStudents = myAccepted.length; // Simplified for now
                
                const div = document.createElement('div');
                div.className = 'group-item';
                div.style.cursor = 'pointer';
                div.onclick = () => showGroupMembers(f.nombre);
                div.innerHTML = `
                    <div class="group-letter" style="background: ${color.bg}; color: ${color.text};">${letter}</div>
                    <div class="group-info"><strong>${f.nombre}</strong><span>${folderStudents} Alumnos activos</span></div>
                    <i class="fas fa-chevron-right" style="color:#ccc; font-size:0.8rem;"></i>
                `;
                groupList.appendChild(div);
            });

            // Cargar Próximas Tutorías del Docente (Wide Style)
            const respEvents = await fetch('events.json?' + new Date().getTime());
            const events = await respEvents.json();
            
            const myEvents = events.filter(e => e.creador_email === userEmail);
            const myCompleted = myEvents.filter(e => e.status === 'completed');
            const myRatings = myCompleted.map(e => e.rating).filter(r => r !== undefined);
            
            // Calcular Promedio General
            if (myRatings.length > 0) {
                const avg = myRatings.reduce((a, b) => a + b, 0) / myRatings.length;
                document.getElementById('stat-promedio').textContent = avg.toFixed(1);
                
                // Simular una tendencia basada en el promedio (esto es estético)
                const trend = document.querySelector('#stat-promedio').closest('.stat-card').querySelector('.stat-trend');
                if (avg >= 9) {
                    trend.textContent = "+0.8";
                    trend.className = "stat-trend trend-up";
                } else if (avg < 7) {
                    trend.textContent = "-0.5";
                    trend.className = "stat-trend trend-down";
                }
            } else {
                document.getElementById('stat-promedio').textContent = "0.0";
            }

            // Calcular Tasa de Entrega (Sesiones completadas vs creadas)
            if (myEvents.length > 0) {
                const deliveryRate = Math.round((myCompleted.length / myEvents.length) * 100);
                document.getElementById('stat-entrega').textContent = deliveryRate;
                const deliveryTrend = document.querySelector('#stat-entrega').closest('.stat-card').querySelector('.stat-trend');
                deliveryTrend.textContent = deliveryRate + "%";
            }

            const today = new Date();
            today.setHours(0,0,0,0);
            
            const myUpcoming = myEvents.filter(e => {
                const eventDate = new Date(e.fecha + 'T00:00:00');
                return eventDate >= today && e.status !== 'completed' && e.status !== 'canceled';
            }).sort((a,b) => new Date(a.fecha) - new Date(b.fecha)).slice(0, 3);
            
            const wideList = document.getElementById('dash-upcoming-wide-list');
            wideList.innerHTML = '';
            if (myUpcoming.length === 0) {
                wideList.innerHTML = '<p style="color:#888; font-size:0.8rem; text-align:center;">No tienes sesiones próximas.</p>';
            }
            myUpcoming.forEach((e, idx) => {
                const date = new Date(e.fecha);
                const day = date.getDate();
                const month = date.toLocaleDateString('es-ES', { month: 'short' }).toUpperCase().replace('.', '');
                
                const div = document.createElement('div');
                div.className = 'session-wide-card';
                div.style.borderLeftColor = idx % 2 === 0 ? '#8a6d3b' : '#4e6a55';
                div.innerHTML = `
                    <div class="session-date-box">
                        <strong>${day}</strong>
                        <span>${month}</span>
                    </div>
                    <div class="session-wide-info">
                        <h5>${e.titulo}</h5>
                        <p>Alumno: General • ${e.hora}</p>
                    </div>
                    <div class="session-wide-icons">
                        <i class="fas fa-video-slash"></i>
                        <i class="fas fa-comment-alt"></i>
                    </div>
                `;
                wideList.appendChild(div);
            });
        }

        function logout() { window.location.href = 'logout.php'; }

        /* ===== LLAMADAS ===== */
        let allContacts = [];
        let jitsiApi = null;

        async function loadCalls() {
            const list = document.getElementById('contacts-list');
            list.innerHTML = '<p style="text-align:center;color:#aaa;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Cargando contactos...</p>';

            try {
                const fd = new FormData();
                fd.append('action', 'get_all_contacts');
                const resp = await fetch('dashboard.php', { method: 'POST', body: fd });
                const data = await resp.json();
                allContacts = data;
                document.getElementById('calls-stat-total').textContent = data.length;
                document.getElementById('calls-stat-online').textContent = Math.floor(Math.random() * (data.length + 1));
                renderContacts(data);
            } catch(e) {
                list.innerHTML = '<p style="text-align:center;color:#e74c3c;padding:40px;"><i class="fas fa-exclamation-circle"></i> Error al cargar contactos.</p>';
            }

            // Búsqueda en tiempo real
            const searchInput = document.getElementById('calls-search-input');
            if (searchInput) {
                searchInput.oninput = function() {
                    const q = this.value.toLowerCase();
                    const filtered = allContacts.filter(u =>
                        u.nombre.toLowerCase().includes(q) ||
                        u.email.toLowerCase().includes(q) ||
                        u.rol.toLowerCase().includes(q)
                    );
                    renderContacts(filtered);
                };
            }

            // Cargar historial real
            loadCallHistory();
        }

        async function loadCallHistory() {
            const histDiv = document.getElementById('calls-history');
            if (!histDiv) return;
            try {
                const fd = new FormData();
                fd.append('action', 'get_call_history');
                const resp = await fetch('dashboard.php', { method: 'POST', body: fd });
                const calls = await resp.json();
                if (!calls.length) {
                    histDiv.innerHTML = '<p style="text-align:center;color:#aaa;font-size:0.8rem;padding:15px 0;">Sin llamadas recientes.</p>';
                    return;
                }
                histDiv.innerHTML = '';
                calls.forEach(c => {
                    const isMe = c.from_email === userEmail;
                    const contactName = isMe ? c.to_name : c.from_name;
                    const contactAvatar = isMe
                        ? (c.to_avatar && c.to_avatar.startsWith('data:') ? c.to_avatar : 'https://cdn-icons-png.flaticon.com/512/149/149071.png')
                        : (c.from_avatar && c.from_avatar.startsWith('data:') ? c.from_avatar : 'https://cdn-icons-png.flaticon.com/512/149/149071.png');
                    const typeIcon = c.type === 'video' ? 'fa-video' : 'fa-phone';
                    const typeColor = isMe ? 'hist-out' : 'hist-in';
                    const typeLabel = c.type === 'video' ? 'Videollamada' : 'Llamada';
                    const dirLabel = isMe ? 'Saliente' : 'Entrante';
                    // Hora relativa
                    const diff = Math.floor((Date.now() - new Date(c.fecha).getTime()) / 1000);
                    let timeAgo = diff < 60 ? 'Hace un momento'
                        : diff < 3600 ? `Hace ${Math.floor(diff/60)}m`
                        : diff < 86400 ? `Hace ${Math.floor(diff/3600)}h`
                        : `Hace ${Math.floor(diff/86400)}d`;
                    histDiv.innerHTML += `
                        <div class="history-item">
                            <img src="${contactAvatar}" class="history-avatar" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
                            <div class="history-info">
                                <h6>${contactName}</h6>
                                <span>${typeLabel} ${dirLabel} &bull; ${timeAgo}</span>
                            </div>
                            <i class="fas ${typeIcon} ${typeColor}"></i>
                        </div>`;
                });
            } catch(e) {
                histDiv.innerHTML = '<p style="color:#e74c3c;font-size:0.75rem;text-align:center;">Error al cargar historial.</p>';
            }
        }

        function renderContacts(users) {
            const list = document.getElementById('contacts-list');
            if (!users.length) {
                list.innerHTML = `<div class="no-contacts"><i class="fas fa-user-slash"></i><p>No se encontraron contactos.</p></div>`;
                return;
            }
            list.innerHTML = '';
            users.forEach(u => {
                const rolClass = 'role-' + u.rol.toLowerCase();
                const avatar = u.avatar && u.avatar.startsWith('data:') ? u.avatar : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                const card = document.createElement('div');
                card.className = 'contact-card';
                const nameEsc = u.nombre.replace(/'/g, "\\'");
                const emailEsc = u.email.replace(/'/g, "\\'");
                const avEsc = (!u.avatar || !u.avatar.startsWith('data:')) ? ('https://cdn-icons-png.flaticon.com/512/149/149071.png') : u.avatar.replace(/'/g, "\\'");
                card.innerHTML = `
                    <img src="${avatar}" class="contact-avatar" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
                    <div class="contact-info">
                        <h5>${u.nombre}</h5>
                        <p>${u.email}</p>
                        <span class="contact-role-badge ${rolClass}">${u.rol}</span>
                    </div>
                    <div class="contact-actions">
                        <button class="btn-call btn-call-audio" title="Llamada de voz"
                            onclick="openCallModal('${nameEsc}','${avEsc}','audio','${emailEsc}')">
                            <i class="fas fa-phone"></i>
                        </button>
                        <button class="btn-call btn-call-video" title="Videollamada"
                            onclick="openCallModal('${nameEsc}','${avEsc}','video','${emailEsc}')">
                            <i class="fas fa-video"></i>
                        </button>
                    </div>
                `;
                list.appendChild(card);
            });
        }

        async function saveCallHistory(toEmail, toName, toAvatar, type) {
            const fd = new FormData();
            fd.append('action', 'save_call');
            fd.append('to_email', toEmail);
            fd.append('to_name', toName);
            fd.append('to_avatar', toAvatar);
            fd.append('type', type);
            await fetch('dashboard.php', { method: 'POST', body: fd });
        }

        function openCallModal(name, avatar, type, email) {
            // ID de sala deterministico basado en ambos emails
            const roomId = 'TA' + btoa([userEmail, email].sort().join('|'))
                .replace(/[^a-zA-Z0-9]/g,'').substring(0, 18);
            const isVideo = type === 'video';

            document.getElementById('call-modal-avatar').src = avatar ||
                'https://cdn-icons-png.flaticon.com/512/149/149071.png';
            document.getElementById('call-modal-name').textContent = name;
            document.getElementById('call-modal-badge').textContent =
                isVideo ? 'Videollamada en curso...' : 'Llamada de voz en curso...';

            document.getElementById('call-modal-overlay').classList.add('open');

            // Guardar en historial
            saveCallHistory(email, name, avatar, type);

            // Iniciar Jitsi embebido
            const container = document.getElementById('jitsi-container');
            container.innerHTML = '';
            if (jitsiApi) { try { jitsiApi.dispose(); } catch(e){} jitsiApi = null; }

            if (typeof JitsiMeetExternalAPI === 'undefined') {
                container.innerHTML = '<p style="color:white;text-align:center;padding:60px;font-size:1.1rem;">'
                    + '<i class="fas fa-exclamation-triangle" style="color:#f39c12;font-size:2rem;display:block;margin-bottom:15px;"></i>'
                    + 'Necesitas conexi&oacute;n a internet para las llamadas. Aseg&uacute;rate de estar conectado.</p>';
                return;
            }

            jitsiApi = new JitsiMeetExternalAPI('meet.jit.si', {
                roomName: roomId,
                width: '100%',
                height: '100%',
                parentNode: container,
                configOverwrite: {
                    startWithVideoMuted: !isVideo,
                    startWithAudioMuted: false,
                    prejoinPageEnabled: false,
                    disableDeepLinking: true
                },
                interfaceConfigOverwrite: {
                    TOOLBAR_BUTTONS: ['microphone','camera','hangup','chat','tileview','fullscreen'],
                    SHOW_JITSI_WATERMARK: false,
                    SHOW_WATERMARK_FOR_GUESTS: false,
                    DEFAULT_REMOTE_DISPLAY_NAME: name
                },
                userInfo: { displayName: '<?php echo addslashes($user["nombre"]); ?>' }
            });

            jitsiApi.addEventListener('readyToClose', closeCallModal);
        }

        function openEventCallModal(eventId, eventTitle, isCreator) {
            const roomName = 'TecnoAgenda-Asesoria-' + eventId;

            document.getElementById('call-modal-avatar').src = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
            document.getElementById('call-modal-name').textContent = eventTitle;
            document.getElementById('call-modal-badge').textContent = isCreator ? 'Anfitrión — Asesoría Virtual en curso...' : 'Asesoría Virtual en curso...';

            document.getElementById('call-modal-overlay').classList.add('open');

            // Iniciar Jitsi embebido
            const container = document.getElementById('jitsi-container');
            container.innerHTML = '';
            if (jitsiApi) { try { jitsiApi.dispose(); } catch(e){} jitsiApi = null; }

            if (typeof JitsiMeetExternalAPI === 'undefined') {
                container.innerHTML = '<p style="color:white;text-align:center;padding:60px;font-size:1.1rem;">'
                    + '<i class="fas fa-exclamation-triangle" style="color:#f39c12;font-size:2rem;display:block;margin-bottom:15px;"></i>'
                    + 'Necesitas conexi&oacute;n a internet para las videollamadas. Aseg&uacute;rate de estar conectado.</p>';
                return;
            }

            jitsiApi = new JitsiMeetExternalAPI('meet.jit.si', {
                roomName: roomName,
                width: '100%',
                height: '100%',
                parentNode: container,
                configOverwrite: {
                    startWithVideoMuted: false,
                    startWithAudioMuted: false,
                    prejoinPageEnabled: false,
                    disableDeepLinking: true,
                    // El creador entra sin lobby/sala de espera
                    lobbyModeEnabled: false,
                    enableWelcomePage: false
                },
                interfaceConfigOverwrite: {
                    TOOLBAR_BUTTONS: ['microphone','camera','hangup','chat','tileview','fullscreen','settings'],
                    SHOW_JITSI_WATERMARK: false,
                    SHOW_WATERMARK_FOR_GUESTS: false,
                    DEFAULT_REMOTE_DISPLAY_NAME: 'Participante'
                },
                userInfo: {
                    displayName: userName,
                    email: userEmail
                }
            });

            jitsiApi.addEventListener('readyToClose', closeCallModal);
        }

        function closeCallModal() {
            if (jitsiApi) { try { jitsiApi.dispose(); } catch(e){} jitsiApi = null; }
            document.getElementById('call-modal-overlay').classList.remove('open');
            document.getElementById('jitsi-container').innerHTML = '';
            // Actualizar historial
            loadCallHistory();
        }
    </script>
</body>
</html>
