
// Funciones Globales de la Aplicación TecnoAgenda

// --- Gestión de Contenedores ---
function hideAllContainers() {
    const welcomeCard = document.getElementById('welcome-card');
    const profileContainer = document.getElementById('profile-container');
    const materialesContainer = document.getElementById('materiales-container');
    const notificationsContainer = document.getElementById('notifications-container');
    const adminContainer = document.getElementById('admin-container');
    const homeAdvisorsList = document.getElementById('home-advisors-list');
    const calendarContainer = document.getElementById('calendar-container');

    if (welcomeCard) welcomeCard.style.display = 'none';
    if (profileContainer) profileContainer.classList.remove('active');
    if (materialesContainer) materialesContainer.classList.remove('active');
    if (notificationsContainer) notificationsContainer.style.display = 'none';
    if (adminContainer) adminContainer.style.display = 'none';
    if (homeAdvisorsList) homeAdvisorsList.style.display = 'none';
    if (calendarContainer) calendarContainer.classList.remove('active');
}


// --- Lógica de Materiales ---
let allMaterials = [];
let allFolders = [];

async function loadMaterials() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_material_data');

        const resp = await fetch('dashboard.php', {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();

        allFolders = data.folders;
        allMaterials = data.materials;
        const allRequests = data.requests;

        renderMaterials(allRequests);
        populateFilters();
        updateFolderSelects();

        if (typeof userRol !== 'undefined' && userRol.toLowerCase() === 'estudiante') {
            checkHomeAdvisors(allRequests);
        }
    } catch (err) {
        console.error("Error cargando datos:", err);
    }
}

async function checkHomeAdvisors(allRequests) {
    const homeAdvisorsList = document.getElementById('home-advisors-list');
    if (!homeAdvisorsList) return;

    const myAdvisors = allRequests
        .filter(r => r.student_email === userEmail && r.status === 'accepted')
        .map(r => r.advisor_email);

    // Solo mostrar si estamos en la pantalla de Inicio
    const welcomeCard = document.getElementById('welcome-card');
    const isHomeVisible = welcomeCard && welcomeCard.style.display === 'block';

    if (typeof userRol !== 'undefined' && userRol.toLowerCase() === 'estudiante' && isHomeVisible) {
        homeAdvisorsList.style.display = 'block';
        loadAdvisorsToJoin('home-advisors-grid', allRequests);
    } else {
        homeAdvisorsList.style.display = 'none';
    }
}

async function renderMaterials(allRequests = []) {
    const list = document.getElementById('materials-list');
    if (!list) return;

    const search = document.getElementById('material-search').value.toLowerCase();
    const filterMateria = document.getElementById('filter-materia').value;

    list.innerHTML = '';

    const filteredFolders = allFolders.filter(f => {
        if (filterMateria && f.nombre !== filterMateria) return false;
        return true;
    });

    if (filteredFolders.length === 0 && !search) {
        list.innerHTML = `<div style="text-align:center; padding:50px; color:#888;">
            <i class="fas fa-folder-open" style="font-size: 50px; display:block; margin-bottom:10px;"></i>
            ${userRol.toLowerCase() === 'estudiante' ? 'No tienes materiales disponibles todavía. Asegúrate de unirte a una asesoría en la pantalla de Inicio.' : 'No has creado ninguna carpeta todavía.'}
        </div>`;
        return;
    }

    filteredFolders.forEach(f => {
        const folderMaterials = allMaterials.filter(m => m.materia === f.nombre);
        const visibleMaterials = folderMaterials.filter(m => {
            if (!search) return true;
            return m.titulo.toLowerCase().includes(search);
        });

        if (search && visibleMaterials.length === 0) return;

        const folderSection = document.createElement('div');
        folderSection.className = 'materia-folder-section';
        folderSection.style.width = '100%';
        folderSection.style.marginBottom = '30px';
        
        folderSection.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--naranja-soft); margin-bottom: 15px; cursor: pointer; padding: 10px; border-radius: 10px; transition: background 0.2s;" onclick="toggleFolder(this)">
                <h2 style="font-family: 'Bangers'; font-size: 28px; color: var(--verde-tecno); margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-folder"></i> ${f.nombre}
                </h2>
                <div style="display: flex; align-items: center; gap: 15px;">
                    ${(userRol.toLowerCase() === 'administrador' || userRol.toLowerCase() === 'admin' || f.creador_email === userEmail) ? 
                        `<button onclick="event.stopPropagation(); showGroupMembers('${f.nombre}')" style="background:#4e6a55; color:white; border:none; padding:5px 12px; border-radius:8px; font-size:12px; font-weight:bold; cursor:pointer;"><i class="fas fa-users"></i> Alumnos Registrados</button>
                        <span style="color: #888; font-size: 12px; cursor:pointer;" onclick="event.stopPropagation(); deleteFolder(${f.id})"><i class="fas fa-trash"></i> Eliminar</span>` : ''}
                    <i class="fas fa-chevron-down folder-chevron" style="transition: transform 0.3s;"></i>
                </div>
            </div>
            <div class="materiales-list-content" style="display: none; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; padding: 10px;">
            </div>
        `;

        const contentDiv = folderSection.querySelector('.materiales-list-content');
        visibleMaterials.forEach(m => {
            const fileExt = m.archivo.split('.').pop().toLowerCase();
            let iconClass = 'fa-file';
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) iconClass = 'fa-file-image';
            else if (fileExt === 'pdf') iconClass = 'fa-file-pdf';
            else if (['mp4', 'mov', 'avi'].includes(fileExt)) iconClass = 'fa-video';
            else if (['mp3', 'wav'].includes(fileExt)) iconClass = 'fa-music';
            else if (['doc', 'docx'].includes(fileExt)) iconClass = 'fa-file-word';

            const card = document.createElement('div');
            card.className = `material-card ${m.color_clase || 'card-blue'}`;
            card.innerHTML = `
                <div class="material-img-container">
                    <i class="fas ${iconClass}"></i>
                </div>
                <div class="material-info">
                    <h3 style="font-size: 1.1rem; margin-bottom: 5px;">${m.titulo}</h3>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 5px;">
                        <a href="${m.archivo}" target="_blank" onclick="markMaterialSeen(${m.id})" style="background: white; color: #333; padding: 5px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: bold; border: 1px solid #ddd;"><i class="fas fa-eye"></i> Ver</a>
                        <a href="${m.archivo}" download onclick="markMaterialSeen(${m.id})" style="background: var(--verde-tecno); color: white; padding: 5px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: bold;"><i class="fas fa-download"></i> Descargar</a>
                        ${(userRol.toLowerCase() === 'administrador' || userRol.toLowerCase() === 'admin' || m.creador_email === userEmail) ? 
                            `<button onclick="showMaterialViews(${m.id})" style="background: #f3a65a; color: white; border: none; padding: 5px 10px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: bold;" title="Ver alumnos que lo han visto"><i class="fas fa-users-viewfinder"></i> Vistas</button>
                            <button onclick="deleteMaterial(${m.id})" style="background: #ff4757; color: white; border: none; padding: 5px 10px; border-radius: 8px; cursor: pointer; font-size: 12px;"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                </div>
            `;
            contentDiv.appendChild(card);
        });

        list.appendChild(folderSection);
    });
}

function toggleFolder(header) {
    const content = header.nextElementSibling;
    const chevron = header.querySelector('.folder-chevron');
    if (content.style.display === 'none') {
        content.style.display = 'grid';
        chevron.style.transform = 'rotate(180deg)';
    } else {
        content.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
    }
}

async function loadAdvisorsToJoin(targetId = 'advisors-to-join', allRequests = []) {
    const container = document.getElementById(targetId);
    if (!container) return;

    const formData = new FormData();
    formData.append('action', 'get_advisors');
    const resp = await fetch('dashboard.php', { method: 'POST', body: formData });
    const advisors = await resp.json();

    container.innerHTML = '';
    advisors.forEach(adv => {
        // No mostrarse a sí mismo
        if (adv.email === userEmail) return;

        const alreadyAccepted = allRequests.some(r => r.student_email === userEmail && r.advisor_email === adv.email && r.status === 'accepted');
        const alreadyPending = allRequests.some(r => r.student_email === userEmail && r.advisor_email === adv.email && r.status === 'pending');
        
        let buttonHtml = '';
        if (alreadyAccepted) {
            buttonHtml = `<button disabled style="background: var(--verde-tecno); color: white; border: none; padding: 10px; border-radius: 10px; width: 100%; font-weight: bold; cursor: default; opacity: 0.8;"><i class="fas fa-check"></i> Ya es tu tutor</button>`;
        } else if (alreadyPending) {
            buttonHtml = `<button disabled style="background: #aaa; color: white; border: none; padding: 10px; border-radius: 10px; width: 100%; font-weight: bold; cursor: default; opacity: 0.8;"><i class="fas fa-clock"></i> Pendiente...</button>`;
        } else {
            buttonHtml = `<button onclick="requestJoin('${adv.email}')" style="background: var(--naranja-soft); color: white; border: none; padding: 8px 15px; border-radius: 10px; cursor: pointer; font-weight: bold; width: 100%; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">Solicitar Asesoría</button>`;
        }

        const div = document.createElement('div');
        div.style = "background: #fdfaf3; padding: 15px; border-radius: 15px; border: 1px solid #eee; display: flex; flex-direction: column; align-items: center; gap: 10px; text-align: center;";
        div.innerHTML = `
            <img src="${adv.avatar || 'https://cdn-icons-png.flaticon.com/512/149/149071.png'}" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; filter: ${adv.avatar ? 'none' : 'grayscale(1)'}; opacity: ${adv.avatar ? '1' : '0.6'};">
            <div>
                <strong style="display:block; color: #333; font-size: 1.1rem; text-transform: uppercase; font-family: 'Bangers';">${adv.nombre}</strong>
                <span style="font-size: 12px; color: var(--verde-tecno); font-weight: bold; display: block; margin-bottom: 5px;">${adv.rol}</span>
                <p style="font-size: 11px; color: #777; font-style: italic; max-height: 40px; overflow: hidden; line-height: 1.2;">${adv.descripcion || 'Sin descripción disponible.'}</p>
            </div>
            ${buttonHtml}
        `;
        container.appendChild(div);
    });
}

async function requestJoin(advisorEmail) {
    const formData = new FormData();
    formData.append('action', 'request_join');
    formData.append('advisor_email', advisorEmail);
    const resp = await fetch('dashboard.php', { method: 'POST', body: formData });
    const res = await resp.json();
    alert(res.message);
    loadMaterials();
}

async function loadNotifications() {
    const isAdminList = document.getElementById('notifications-list');
    const isStudentList = document.getElementById('notifications-list-student');
    if (!isAdminList && !isStudentList) return;

    const formData = new FormData();
    formData.append('action', 'get_notifications');
    const resp = await fetch('dashboard.php', { method: 'POST', body: formData });
    const notifs = await resp.json();

    const pendingCountEl = document.getElementById('notif-pending-count');
    if (pendingCountEl) {
        pendingCountEl.textContent = `${notifs.length} Pendiente${notifs.length !== 1 ? 's' : ''} por Aprobar`;
    }

    const list = isAdminList || isStudentList;
    if (!isStudentList) list.innerHTML = '';

    if (notifs.length === 0 && !isStudentList) {
        list.innerHTML = '<p style="text-align:center; padding:40px; color:#999; background:white; border-radius:20px;">No tienes solicitudes pendientes en este momento.</p>';
        return;
    }

    notifs.forEach(n => {
        const item = document.createElement('div');
        item.className = 'notif-card';
        
        if (n.tipo === 'solicitud') {
            item.innerHTML = `
                <div class="notif-card-icon"><i class="fas fa-user-plus"></i></div>
                <div class="notif-card-content">
                    <h5>${n.data.student_name}</h5>
                    <div class="notif-card-info">
                        <i class="fas fa-envelope"></i> ${n.data.student_email}
                    </div>
                    <div class="notif-labels">
                        <span class="notif-badge badge-priority">Alta Prioridad</span>
                        <span class="notif-badge badge-time">Recibido: ${n.data.fecha ? n.data.fecha.split(' ')[1].substring(0,5) : '--:--'}</span>
                    </div>
                </div>
                <div class="notif-actions">
                    <button class="btn-notif-circle" onclick="respondRequest(${n.id}, 'rejected')"><i class="fas fa-times"></i></button>
                    <button class="btn-notif-accept" onclick="respondRequest(${n.id}, 'accepted')">Aceptar</button>
                </div>
            `;
        } else {
            const icon = n.status === 'accepted' ? 'fa-check-circle' : 'fa-times-circle';
            const color = n.status === 'accepted' ? '#4e6a55' : '#d9534f';
            const bg = n.status === 'accepted' ? '#f0f4f1' : '#fdf3e9';
            item.innerHTML = `
                <div class="notif-card-icon" style="background: ${bg}; color: ${color};"><i class="fas ${icon}"></i></div>
                <div class="notif-card-content">
                    <div style="display:flex; justify-content:space-between;">
                        <h5>Estado de Solicitud</h5>
                        <span style="font-size:0.7rem; color:#999; font-weight:700;">RECIENTE</span>
                    </div>
                    <p style="font-size:0.85rem; color:#666; margin-bottom:15px;">${n.mensaje}</p>
                    <div class="notif-actions">
                        <button class="btn-notif-details" onclick="this.parentElement.parentElement.parentElement.remove()">Cerrar</button>
                    </div>
                </div>
            `;
        }
        if (isStudentList) list.prepend(item);
        else list.appendChild(item);
    });
}

async function respondRequest(id, status) {
    const formData = new FormData();
    formData.append('action', 'respond_request');
    formData.append('request_id', id);
    formData.append('status', status);
    await fetch('dashboard.php', { method: 'POST', body: formData });
    loadNotifications();
    loadMaterials();
}

// --- Gestión de Usuarios (Admin) ---
async function loadAdminUserListDedicated() {
    const list = document.getElementById('admin-users-list-dedicated');
    if (!list) return;

    list.innerHTML = '<p style="grid-column:1/-1; text-align:center;">Cargando base de datos de usuarios...</p>';
    
    const formData = new FormData();
    formData.append('action', 'get_all_users');
    const resp = await fetch('dashboard.php', { method: 'POST', body: formData });
    const users = await resp.json();
    
    list.innerHTML = '';
    users.forEach(u => {
        const card = document.createElement('div');
        card.style = "background: #f9f9f9; padding: 15px; border-radius: 15px; border: 1px solid #eee; display: flex; flex-direction: column; gap: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);";
        card.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <img src="${u.avatar || 'https://cdn-icons-png.flaticon.com/512/149/149071.png'}" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); filter: ${u.avatar ? 'none' : 'grayscale(1)'}; opacity: ${u.avatar ? '1' : '0.6'};">
                <div style="display:flex; flex-direction:column;">
                    <strong style="font-size: 1rem; color: #333;">${u.nombre}</strong>
                    <span style="font-size: 0.75rem; color: var(--verde-tecno); font-weight: 700;">${u.rol.toUpperCase()}</span>
                </div>
            </div>
            <div style="font-size: 0.85rem; color: #666; background: #fff; padding: 5px 10px; border-radius: 8px; border: 1px solid #f0f0f0;">
                <i class="fas fa-envelope" style="margin-right: 5px; color: #aaa;"></i> ${u.email}
            </div>
            ${(u.rol.toLowerCase() === 'docente' || u.rol.toLowerCase() === 'tutor' || u.rol.toLowerCase() === 'admin' || u.rol.toLowerCase() === 'administrador') ? `
                <button onclick="downloadTeacherReport('${u.email}', '${u.nombre}')" style="background: #f3a65a; color: white; border: none; padding: 10px; border-radius: 10px; cursor: pointer; font-weight: bold; transition: all 0.2s; display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom: 5px;">
                    <i class="fas fa-file-download"></i> Descargar Reporte
                </button>
            ` : ''}
            ${u.email !== userEmail ? `
                <button onclick="deleteUserDedicated('${u.email}')" style="background: #ff4757; color: white; border: none; padding: 10px; border-radius: 10px; cursor: pointer; font-weight: bold; transition: all 0.2s; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="fas fa-user-minus"></i> Eliminar Usuario
                </button>
            ` : `
                <div style="text-align: center; color: #bbb; font-size: 0.8rem; font-style: italic; padding: 10px; border: 1px dashed #ddd; border-radius: 10px;">
                    Esta es tu cuenta de Administrador
                </div>
            `}
        `;
        list.appendChild(card);
    });
}

async function deleteUserDedicated(email) {
    if (!confirm(`¿ESTÁS SEGURO? Eliminarás permanentemente la cuenta de ${email}. Todos sus datos se perderán.`)) return;
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_email', email);
    await fetch('dashboard.php', { method: 'POST', body: formData });
    loadAdminUserListDedicated();
}

async function downloadTeacherReport(email, nombre) {
    try {

        const formData = new FormData();
        formData.append('action', 'get_teacher_report');
        formData.append('teacher_email', email);

        const resp = await fetch('dashboard.php', {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();

        const events = data.events || [];
        const reqs = data.requests || [];
        const mats = data.materials || [];

        const teacherEvents = events.filter(e => e.creador_email === email);
        const teacherReqs = reqs.filter(r => r.advisor_email === email && r.status === 'accepted');
        const teacherMats = mats.filter(m => m.creador_email === email);
        
        const studentsAssigned = teacherReqs.length;
        const completedSessions = teacherEvents.filter(e => e.rating !== undefined || e.status === 'completed').length;
        
        let totalRating = 0;
        let ratingCount = 0;
        teacherEvents.forEach(e => {
            if (e.rating) {
                totalRating += parseFloat(e.rating);
                ratingCount++;
            }
        });
        const avgRating = ratingCount > 0 ? (totalRating / ratingCount).toFixed(1) : 'S/C';
        const materialsShared = teacherMats.length;
        
        const obs = (avgRating !== 'S/C' && parseFloat(avgRating) < 7) 
            ? '¡ATENCIÓN! El docente tiene una calificación promedio baja. Se requiere revisión.' 
            : 'El docente cumple con las expectativas y métricas del sistema adecuadamente.';

        const reportText = `================================================
REPORTE DE DESEMPEÑO DOCENTE - TECNOAGENDA
================================================

Información del Docente:
------------------------
Nombre: ${nombre}
Correo Electrónico: ${email}
Fecha de Emisión: ${new Date().toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}

Métricas Reales de Actividad:
----------------------------------
- Estudiantes Activos Asignados: ${studentsAssigned}
- Asesorías Completadas / Calificadas: ${completedSessions}
- Calificación Promedio de Alumnos: ${avgRating} / 10.0
- Materiales Compartidos: ${materialsShared}

Observaciones del Sistema:
--------------------------
${obs}

================================================
Generado automáticamente por el Panel de Administración de TecnoAgenda.`;
        
        const blob = new Blob([reportText], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Reporte_${nombre.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch(e) {
        alert("Ocurrió un error al generar el reporte.");
        console.error(e);
    }
}

// --- Filtros y Selects ---
function populateFilters() {
    const filterMateria = document.getElementById('filter-materia');
    if (!filterMateria) return;
    const currentMateria = filterMateria.value;
    filterMateria.innerHTML = '<option value="">Todas</option>';
    
    const uniqueMaterias = [...new Set(allFolders.map(f => f.nombre))];
    uniqueMaterias.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m;
        opt.textContent = m;
        if (m === currentMateria) opt.selected = true;
        filterMateria.appendChild(opt);
    });
}

function updateFolderSelects() {
    const select = document.getElementById('select-folder-upload');
    if (!select) return;
    select.innerHTML = '';
    
    const myFolders = allFolders.filter(f => f.creador_email === userEmail || userRol.toLowerCase() === 'administrador' || userRol.toLowerCase() === 'admin');
    myFolders.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.nombre;
        opt.textContent = f.nombre;
        select.appendChild(opt);
    });
}

function deleteFolder(id) {
    if (!confirm("¿Seguro que quieres eliminar esta carpeta y todos sus archivos?")) return;
    const formData = new FormData();
    formData.append('action', 'delete_folder');
    formData.append('folder_id', id);
    fetch('dashboard.php', { method: 'POST', body: formData }).then(() => loadMaterials());
}

function deleteMaterial(id) {
    if (!confirm("¿Seguro que quieres eliminar este archivo?")) return;
    const formData = new FormData();
    formData.append('action', 'delete_material');
    formData.append('material_id', id);
    fetch('dashboard.php', { method: 'POST', body: formData }).then(() => loadMaterials());
}

// --- Lógica del Calendario ---
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();
let allEvents = [];

const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

async function loadEvents() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_events');
        const resp = await fetch('dashboard.php', { method: 'POST', body: formData });
        allEvents = await resp.json();
        renderCalendar();
        
        // Auto-select today on first load
        setTimeout(() => {
            const today = new Date();
            const todayEl = Array.from(document.querySelectorAll('.calendar-day')).find(el => {
                return el.querySelector('.calendar-day-num').textContent == today.getDate() && !el.classList.contains('other-month');
            });
            if (todayEl) todayEl.click();
        }, 100);
    } catch (e) {
        console.warn("No se pudieron cargar los eventos:", e);
        allEvents = [];
        renderCalendar();
    }
}

function renderCalendar() {
    const grid = document.getElementById('calendar-grid');
    const monthYearText = document.getElementById('calendar-month-year');
    if (!grid || !monthYearText) {
        console.error("No se encontraron los elementos del calendario en el DOM");
        return;
    }

    grid.innerHTML = '';
    const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    monthYearText.textContent = `${monthNames[currentMonth]} ${currentYear}`;

    // Cabeceras de días
    const dayLabels = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    dayLabels.forEach(d => {
        const div = document.createElement('div');
        div.className = 'calendar-day-head';
        div.textContent = d;
        grid.appendChild(div);
    });

    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

    // Días vacíos del mes anterior
    for (let i = 0; i < firstDay; i++) {
        const div = document.createElement('div');
        div.className = 'calendar-day other-month';
        div.style.background = '#f9f9f9';
        div.style.minHeight = '100px';
        div.style.borderRight = '1px solid #eee';
        div.style.borderBottom = '1px solid #eee';
        grid.appendChild(div);
    }

    // Días del mes actual
    const today = new Date();
    for (let d = 1; d <= daysInMonth; d++) {
        const div = document.createElement('div');
        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        
        div.className = 'calendar-day';
        if (d === today.getDate() && currentMonth === today.getMonth() && currentYear === today.getFullYear()) {
            div.classList.add('today');
        }

        div.innerHTML = `<div class="calendar-day-num">${d}</div>`;
        
        const dayEvents = (allEvents || []).filter(e => e.fecha === dateStr);
        const dotContainer = document.createElement('div');
        dotContainer.className = 'event-dot-container';
        
        dayEvents.forEach(e => {
            const tag = document.createElement('div');
            tag.className = `event-tag tag-${e.modalidad} ${e.status === 'canceled' ? 'event-canceled' : ''}`;
            
            let icon = 'fa-video';
            if (e.modalidad === 'llamada') icon = 'fa-phone';
            if (e.modalidad === 'presencial') icon = 'fa-map-marker-alt';
            if (e.status === 'canceled') icon = 'fa-times-circle';
            
            tag.innerHTML = `<i class="fas ${icon}"></i> <span style="${e.status === 'canceled' ? 'text-decoration: line-through;' : ''}">${e.titulo}</span>`;
            tag.onclick = (event) => {
                event.stopPropagation();
                
                // Highlight selected day
                document.querySelectorAll('.calendar-day').forEach(cd => cd.classList.remove('selected'));
                div.classList.add('selected');
                
                showEventDetail(e);
            };
            dotContainer.appendChild(tag);
        });
        
        div.appendChild(dotContainer);

        div.onclick = () => {
            // Highlight selected day
            document.querySelectorAll('.calendar-day').forEach(cd => cd.classList.remove('selected'));
            div.classList.add('selected');
            
            if (dayEvents.length > 0) {
                showEventDetail(dayEvents[0]);
            } else {
                // Show date info in right panel even if no events
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const fullDate = new Date(currentYear, currentMonth, d).toLocaleDateString('es-ES', options);
                document.getElementById('right-date-label').textContent = fullDate.charAt(0).toUpperCase() + fullDate.slice(1);
                document.getElementById('right-event-title').textContent = "Sin sesiones";
                document.getElementById('right-event-info').innerHTML = '<p style="color: #888; font-size: 0.9rem;">No hay asesorías programadas para este día.</p>';
                document.getElementById('right-advisor-box').style.display = 'none';
                
                const actionBox = document.getElementById('right-action-box');
                if (typeof userRol !== 'undefined' && userRol.toLowerCase() !== 'estudiante') {
                    actionBox.innerHTML = `
                        <button class="btn-new-entry" style="width: 100%; margin-top: 10px; background: var(--verde-tecno); color: white;" onclick="openEventModal('${dateStr}')">
                            <i class="fas fa-plus"></i> Agendar sesión
                        </button>
                    `;
                } else {
                    actionBox.innerHTML = '';
                }
            }
        };

        grid.appendChild(div);
    }
}

function prevMonth() {
    currentMonth--;
    if (currentMonth < 0) { currentMonth = 11; currentYear--; }
    renderCalendar();
}

function nextMonth() {
    currentMonth++;
    if (currentMonth > 11) { currentMonth = 0; currentYear++; }
    renderCalendar();
}

function openEventModal(date) {
    document.getElementById('event-date').value = date;
    document.getElementById('event-modal').style.display = 'flex';
}

function closeEventModal() {
    document.getElementById('event-modal').style.display = 'none';
}

function toggleModalidadFields() {
    const mod = document.getElementById('event-modalidad').value;
    document.getElementById('field-virtual').style.display = mod === 'virtual' ? 'block' : 'none';
    document.getElementById('field-presencial').style.display = mod === 'presencial' ? 'block' : 'none';
    document.getElementById('field-llamada').style.display = mod === 'llamada' ? 'block' : 'none';
}

function showEventDetail(event) {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateParts = event.fecha.split('-');
    const dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
    const fullDate = dateObj.toLocaleDateString('es-ES', options);
    
    document.getElementById('right-date-label').textContent = fullDate.charAt(0).toUpperCase() + fullDate.slice(1);
    document.getElementById('right-event-title').textContent = event.titulo;
    
    let infoHtml = `
        <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #555;">
            <i class="fas fa-clock" style="color: var(--naranja-soft);"></i> ${event.hora}
        </div>
        <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #555;">
            <i class="fas fa-info-circle" style="color: var(--naranja-soft);"></i> Modalidad: ${event.modalidad.charAt(0).toUpperCase() + event.modalidad.slice(1)}
        </div>
    `;

    if (event.modalidad === 'presencial') {
        infoHtml += `<div style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #555;"><i class="fas fa-map-marker-alt" style="color: var(--naranja-soft);"></i> Lugar: ${event.lugar}</div>`;
    } else if (event.modalidad === 'llamada') {
        infoHtml += `<div style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #555;"><i class="fas fa-phone" style="color: var(--naranja-soft);"></i> ${event.instrucciones}</div>`;
    }

    document.getElementById('right-event-info').innerHTML = infoHtml;
    
    // Advisor box
    document.getElementById('right-advisor-box').style.display = 'flex';
    document.getElementById('right-advisor-name').textContent = event.creador_nombre;
    document.getElementById('right-advisor-role').textContent = "Asesor Académico";
    
    // Action box
    const actionBox = document.getElementById('right-action-box');
    actionBox.innerHTML = '';
    
    if (event.status === 'canceled') {
        actionBox.innerHTML = `
            <div style="background: #fff5f5; color: #c53030; padding: 10px; border-radius: 10px; text-align: center; font-weight: bold; font-size: 0.9rem; border: 1px solid #feb2b2;">
                <i class="fas fa-exclamation-triangle"></i> ESTA ASESORÍA HA SIDO CANCELADA
            </div>
        `;
    } else {
        if (event.modalidad === 'virtual') {
            const isCreator = (event.creador_email === userEmail || userRol.toLowerCase() === 'administrador' || userRol.toLowerCase() === 'admin');
            const btnLabel = isCreator
                ? '<i class="fas fa-video"></i> Iniciar asesoria'
                : '<i class="fas fa-video"></i> Unirse ahora';
            actionBox.innerHTML += `
                <button class="btn-new-entry" style="width: 100%; margin-bottom: 6px; background: ${isCreator ? 'var(--verde-tecno)' : ''};" onclick="joinEventMeeting(${event.id}, '${event.titulo.replace(/'/g, "\\'")}', ${isCreator})">
                    ${btnLabel}
                </button>
            `;
        }
        
        if (userRol.toLowerCase() === 'administrador' || userRol.toLowerCase() === 'admin' || event.creador_email === userEmail) {
            if (event.status !== 'completed' && event.status !== 'canceled') {
                actionBox.innerHTML += `
                    <button class="btn-new-entry" style="width: 100%; background: #4e6a55; color: white; margin-bottom: 10px;" onclick="completeEvent(${event.id})">
                        <i class="fas fa-check-circle"></i> Marcar como Completada
                    </button>
                `;
            }
            actionBox.innerHTML += `
                <button class="btn-new-entry" style="width: 100%; background: #e7e3d7; color: #555;" onclick="cancelEvent(${event.id})">
                    <i class="fas fa-times"></i> Cancelar Asesoría
                </button>
            `;
        }

        if (userRol.toLowerCase() === 'estudiante') {
            if (event.status !== 'completed' && event.status !== 'canceled') {
                actionBox.innerHTML += `
                    <button class="btn-new-entry" style="width: 100%; background: var(--naranja-soft); color: white; margin-top: 10px;" onclick="openRatingModal(${event.id})">
                        <i class="fas fa-star"></i> Finalizar y Calificar
                    </button>
                `;
            } else if (event.status === 'completed' && !event.rating) {
                actionBox.innerHTML += `
                    <button class="btn-new-entry" style="width: 100%; background: var(--naranja-soft); color: white; margin-top: 10px;" onclick="openRatingModal(${event.id})">
                        <i class="fas fa-star"></i> Calificar Asesoría
                    </button>
                `;
            } else if (event.rating) {
                actionBox.innerHTML += `
                    <div style="background: #fdfaf3; padding: 10px; border-radius: 10px; text-align: center; margin-top: 10px; border: 1px solid #eee;">
                        <div style="color: #ffca28; font-size: 1.2rem; margin-bottom: 5px;"><i class="fas fa-star"></i> ${event.rating}/10</div>
                        <span style="font-size: 0.8rem; color: #888;">Ya calificaste esta sesión</span>
                    </div>
                `;
            }
        }
    }
}

function openRatingModal(eventId) {
    document.getElementById('rating-event-id').value = eventId;
    document.getElementById('rating-modal').style.display = 'flex';
    resetStars();
}

function resetStars() {
    const stars = document.querySelectorAll('.star-btn');
    stars.forEach(s => s.style.color = '#ddd');
    document.getElementById('rating-value').value = 10; // default
    // Resaltar hasta el 10 por defecto
    highlightStars(10);
}

function highlightStars(val) {
    const stars = document.querySelectorAll('.star-btn');
    stars.forEach(s => {
        if (parseInt(s.dataset.value) <= val) {
            s.style.color = '#ffca28';
        } else {
            s.style.color = '#ddd';
        }
    });
}

// Inicializar estrellas
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('star-btn')) {
        const val = parseInt(e.target.dataset.value);
        document.getElementById('rating-value').value = val;
        highlightStars(val);
    }
});

const btnSubmitRating = document.getElementById('btn-submit-rating');
if (btnSubmitRating) {
    btnSubmitRating.addEventListener('click', async () => {
        const eventId = document.getElementById('rating-event-id').value;
        const rating = document.getElementById('rating-value').value;

        const formData = new FormData();
        formData.append('action', 'rate_event');
        formData.append('event_id', eventId);
        formData.append('rating', rating);
        
        const resp = await fetch('dashboard.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.status === 'success') {
            document.getElementById('rating-modal').style.display = 'none';
            alert("¡Gracias por tu calificación!");
            loadEvents();
            if (typeof loadAdminDashboard === 'function') loadAdminDashboard();
        }
    });
}

async function cancelEvent(eventId) {
    if (!confirm("¿Seguro que quieres cancelar esta asesoría?")) return;
    const formData = new FormData();
    formData.append('action', 'cancel_event');
    formData.append('event_id', eventId);
    await fetch('dashboard.php', { method: 'POST', body: formData });
    loadEvents();
}

async function deleteEvent(eventId) {
    if (!confirm("¿Seguro que quieres eliminar esta asesoría?")) return;
    const formData = new FormData();
    formData.append('action', 'delete_event');
    formData.append('event_id', eventId);
    await fetch('dashboard.php', { method: 'POST', body: formData });
    loadEvents();
}

async function completeEvent(eventId) {
    if (!confirm("¿Marcar esta asesoría como completada?")) return;
    const formData = new FormData();
    formData.append('action', 'complete_event');
    formData.append('event_id', eventId);
    await fetch('dashboard.php', { method: 'POST', body: formData });
    loadEvents();
    if (typeof loadAdminDashboard === 'function') loadAdminDashboard();
}

async function markMaterialSeen(materialId) {
    if (userRol.toLowerCase() !== 'estudiante') return;
    const formData = new FormData();
    formData.append('action', 'mark_material_seen');
    formData.append('material_id', materialId);
    await fetch('dashboard.php', { method: 'POST', body: formData });
}

async function showMaterialViews(materialId) {
    const modal = document.getElementById('material-views-modal');
    const list = document.getElementById('material-views-list');
    list.innerHTML = '<p style="text-align:center; color:#888;">Cargando visualizaciones...</p>';
    modal.style.display = 'flex';

  const formData = new FormData();
formData.append('action', 'get_material_views');
formData.append('material_id', materialId);

const resp = await fetch('dashboard.php', {
    method: 'POST',
    body: formData
});

const material = await resp.json();

    list.innerHTML = '';
    if (!material || !material.visto_por || material.visto_por.length === 0) {
        list.innerHTML = '<p style="text-align:center; color:#888; padding:20px;">Ningún alumno ha visto este material aún.</p>';
        return;
    }

    material.visto_por.forEach(v => {
        // En caso de estructura antigua vs nueva (algunos podrían ser solo un string email)
        const email = typeof v === 'object' ? v.email : v;
        const nombre = typeof v === 'object' ? v.nombre : email;
        const fecha = typeof v === 'object' && v.fecha ? new Date(v.fecha).toLocaleDateString('es-ES', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'}) : '';

        const item = document.createElement('div');
        item.style = "background: #fdfaf3; padding: 12px; border-radius: 12px; display: flex; align-items: center; gap: 12px; border: 1px solid #eee;";
        item.innerHTML = `
            <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;">
            <div>
                <strong style="display:block; font-size: 0.9rem; color: #333;">${nombre}</strong>
                <span style="font-size: 0.75rem; color: #888;">${email} ${fecha ? '• ' + fecha : ''}</span>
            </div>
        `;
        list.appendChild(item);
    });
}

async function showGroupMembers(groupName) {
    const modal = document.getElementById('group-members-modal');
    const title = document.getElementById('group-modal-title');
    const list = document.getElementById('group-members-list');
    
    title.textContent = `Miembros: ${groupName}`;
    list.innerHTML = '<p style="text-align:center; color:#888;">Cargando miembros...</p>';
    modal.style.display = 'flex';
    
    const formData = new FormData();
formData.append('action', 'get_group_members');

const resp = await fetch('dashboard.php', {
    method: 'POST',
    body: formData
});

const reqs = await resp.json();
    
    // Filtrar alumnos aceptados por este asesor
    const members = reqs.filter(r => r.advisor_email === userEmail && r.status === 'accepted');
    
    list.innerHTML = '';
    if (members.length === 0) {
        list.innerHTML = '<p style="text-align:center; color:#888; padding:20px;">No hay alumnos en este grupo aún.</p>';
        return;
    }
    
    members.forEach(m => {
        const item = document.createElement('div');
        item.style = "background: #fdfaf3; padding: 12px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border: 1px solid #eee;";
        item.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;">
                <div>
                    <strong style="display:block; font-size: 0.9rem; color: #333;">${m.student_name}</strong>
                    <span style="font-size: 0.75rem; color: #888;">${m.student_email}</span>
                </div>
            </div>
            <button onclick="removeGroupMember(${m.id}, '${groupName}')" style="background: #ff4757; color: white; border: none; padding: 5px 10px; border-radius: 8px; cursor: pointer; font-size: 0.75rem; font-weight: bold;">
                <i class="fas fa-user-minus"></i> Quitar
            </button>
        `;
        list.appendChild(item);
    });
}

async function removeGroupMember(requestId, groupName) {
    if (!confirm("¿Seguro que quieres quitar a este alumno del grupo? Ya no podrá ver tus materiales.")) return;
    
    const formData = new FormData();
    formData.append('action', 'remove_member');
    formData.append('request_id', requestId);
    
    await fetch('dashboard.php', { method: 'POST', body: formData });
    showGroupMembers(groupName); // Recargar lista del modal
    if (typeof loadAdminDashboard === 'function') loadAdminDashboard(); // Actualizar contador en dashboard
}

/**
 * Abre la videollamada de asesoría en una ventana popup de meet.jit.si
 * con el nombre del usuario y configuración pre-cargada.
 */
function joinEventMeeting(eventId, eventTitle, isCreator) {
    const roomName = 'TecnoAgenda-Asesoria-' + eventId;
    const displayName = (typeof userName !== 'undefined' && userName) ? userName : 'Participante';

    // Construir URL con toda la configuración en el hash (no necesita JS de Jitsi)
    const hashParams = [
        'userInfo.displayName=' + encodeURIComponent(displayName),
        'userInfo.email=' + encodeURIComponent(typeof userEmail !== 'undefined' ? userEmail : ''),
        'config.prejoinPageEnabled=false',
        'config.startWithVideoMuted=false',
        'config.startWithAudioMuted=false',
        'config.disableDeepLinking=true',
        'config.enableWelcomePage=false',
        'config.toolbarButtons=["microphone","camera","hangup","chat","tileview","fullscreen"]'
    ].join('&');

    const jitsiUrl = 'https://meet.jit.si/' + roomName + '#' + hashParams;

    // Tamaño de ventana: casi pantalla completa
    const w = Math.min(1200, screen.width - 100);
    const h = Math.min(800, screen.height - 100);
    const left = (screen.width - w) / 2;
    const top  = (screen.height - h) / 2;

    window.open(
        jitsiUrl,
        'TecnoAgenda_Videollamada_' + eventId,
        `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=no,toolbar=no,menubar=no,location=no,status=no`
    );
}

