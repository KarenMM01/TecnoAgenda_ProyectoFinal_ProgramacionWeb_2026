<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoAgenda - Conectando Estudiantes y Docentes</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Arima:wght@400;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            --bg-color: #fbf8f1;
            --text-dark: #000;
            --text-muted: #000;
            --primary-green: #44654f;
            --primary-green-hover: #324e3c;
            --card-bg: #f5f0e1;
            --border-color: #e2d8c3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arima', system-ui, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-dark);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

/* Global black typography */
body, p, h1, h2, h3, h4, h5, h6, span, a, li, .nav-links a, .footer a {
    color: #000 !important;
}

/* Preserve white text in dark cards */
.info-card.dark * {
    color: #fff !important;
}

        h1,
        h2,
        h3,
        h4,
        .serif-font {
            font-family: 'Aclonica', sans-serif;
            font-weight: 400;
        }

        a {
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }


        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-green);
        }

        .nav-links {
            display: flex;
            gap: 32px;
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--text-muted);
        }

        .nav-links a:hover {
            color: var(--primary-green);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .login-btn {
            font-weight: 700;
            font-size: 0.95rem;
        }

        .login-btn:hover {
            color: var(--primary-green);
        }

        .btn-primary {
            background-color: var(--primary-green);
            color: #000;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background-color: var(--primary-green-hover);
            transform: translateY(-1px);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--text-dark);
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.95rem;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background-color: var(--card-bg);
            border-color: #d1c6ab;
        }


        .hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            padding: 80px 0;
            align-items: center;
        }

        .hero-content {
            max-width: 540px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #e9e1cc;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 24px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .hero-title {
            font-size: 4rem;
            line-height: 1.1;
            margin-bottom: 24px;
            color: var(--text-dark);
            letter-spacing: -1px;
        }

        .hero-subtitle {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 40px;
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            gap: 16px;
        }

        .hero-image-wrapper {
            position: relative;
        }

        .hero-image-bg {
            position: absolute;
            top: 20px;
            left: -20px;
            right: 20px;
            bottom: -20px;
            background-color: #dfd4b7;
            border-radius: 16px;
            z-index: 1;
        }

        .hero-image {
            position: relative;
            z-index: 2;
            width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            object-fit: cover;
            aspect-ratio: 4/3;
        }

        .floating-card {
            position: absolute;
            bottom: -30px;
            left: -40px;
            background-color: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 3;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        .floating-icon {
            background-color: #f7ab5a;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .floating-text h4 {
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            margin: 0;
            color: var(--text-dark);
        }

        .floating-text p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
        }


        .info-section {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 32px;
            padding: 40px 0 60px 0;
        }

        .info-card {
            background-color: var(--card-bg);
            padding: 48px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
        }

        .info-card h3 {
            font-size: 1.8rem;
            margin-bottom: 16px;
        }

        .info-card p {
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1.8;
        }

        .info-card.dark {
            background-color: var(--primary-green);
            color: white;
            border: none;
        }

        .info-card.dark p {
            color: rgba(255, 255, 255, 0.85);
        }

        .info-icon {
            font-size: 2rem;
            margin-bottom: 20px;
            color: white;
        }


        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            padding-bottom: 80px;
        }

        .feature-item {
            background-color: var(--card-bg);
            padding: 32px 24px;
            border-radius: 12px;
            position: relative;
            border: 1px solid transparent;
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            border-color: var(--border-color);
        }

        .feature-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 3px;
            border-radius: 3px 0 0 3px;
        }

        .feature-item:nth-child(1)::before {
            background-color: var(--primary-green);
        }

        .feature-item:nth-child(2)::before {
            background-color: #b87c4c;
        }

        .feature-item:nth-child(3)::before {
            background-color: #5c6c7c;
        }

        .feature-item:nth-child(4)::before {
            background-color: #8c9e84;
        }

        .feature-icon {
            font-size: 1.5rem;
            color: var(--text-dark);
            margin-bottom: 16px;
        }

        .feature-title {
            font-size: 1.25rem;
            margin-bottom: 12px;
        }

        .feature-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.6;
        }



        .footer {
            border-top: 1px solid var(--border-color);
            padding: 60px 0 24px 0;
            margin-top: 40px;
        }

        .footer-top {
            display: grid;
            grid-template-columns: 2fr 2fr 1.5fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        .footer-brand .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-green);
        }

        .footer-brand p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 16px 0 24px 0;
            max-width: 300px;
        }

        .footer-social {
            display: flex;
            gap: 16px;
            font-size: 1.4rem;
            color: var(--text-dark);
        }

        .footer-social i {
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .footer-social i:hover {
            color: var(--primary-green);
        }

        .footer-links {
            display: flex;
            gap: 80px;
        }

        .link-column h4 {
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 20px;
            color: var(--text-dark);
            text-transform: uppercase;
        }

        .link-column a {
            display: block;
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 12px;
        }

        .link-column a:hover {
            color: var(--primary-green);
        }

        .footer-support {
            background-color: var(--card-bg);
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .footer-support h4 {
            font-size: 0.95rem;
            margin-bottom: 12px;
            color: var(--text-dark);
            font-family: 'Inter', sans-serif;
            font-weight: 600;
        }

        .footer-support p {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .footer-support a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-green);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 24px;
            border-top: 1px dashed var(--border-color);
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        @media (max-width: 992px) {

            .footer-top {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .footer-links {
                gap: 40px;
            }

            .footer-bottom {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .hero,
            .info-section {
                grid-template-columns: 1fr;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .hero-title {
                font-size: 3rem;
            }

            .hero-content {
                max-width: 100%;
            }

            .floating-card {
                bottom: -20px;
                left: 20px;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <nav class="navbar">
            <div class="logo">
                <i class="ph ph-calendar-check" style="font-size: 1.6rem;"></i>
                <span class="serif-font">Tecnoagenda</span>
            </div>
            <div class="nav-links">
                <a href="#">Home</a>
                <a href="#">Agenda</a>
                <a href="#">Insights</a>
            </div>
            <div class="nav-actions">
                <?php if(isset($_SESSION['user'])): ?>
                    <a href="dashboard.php" class="btn-primary">Ir al Panel</a>
                <?php else: ?>
                    <a href="login.php" class="login-btn">Iniciar Sesión</a>
                    <a href="registro.php" class="btn-primary">Registrarse</a>
                <?php endif; ?>
            </div>
        </nav>

        <section class="hero">
            <div class="hero-content">
                <h1 class="hero-title">Conectando Estudiantes y Docentes</h1>
                <p class="hero-subtitle">
                    Un espacio digital diseñado para asesorías académicas, chat en tiempo real y colaboración eficiente
                    entre la comunidad técnica.
                </p>
                <div class="hero-actions">
                    <a href="<?php echo isset($_SESSION['user']) ? 'dashboard.php' : 'registro.php'; ?>" class="btn-primary">
                        Comenzar Ahora <i class="ph-bold ph-arrow-right"></i>
                    </a>
                    <a href="#" class="btn-outline">Ver Demo</a>
                </div>
            </div>

            <div class="hero-image-wrapper">
                <div class="hero-image-bg"></div>
                <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=1000&auto=format&fit=crop"
                    alt="Estudiantes colaborando" class="hero-image">

                <div class="floating-card">
                    <div class="floating-icon">
                        <i class="ph-fill ph-users-three"></i>
                    </div>
                    <div class="floating-text">
                        <h4>Asesorías Activas</h4>
                        <p>2.4k+ Sesiones hoy</p>
                    </div>
                </div>
            </div>
        </section>


        <section class="info-section">
            <div class="info-card">
                <h3>¿Qué es Tecnoagenda?</h3>
                <p>
                    TecnoAgenda es una plataforma digital diseñada para facilitar la conexión entre estudiantes y
                    docentes mediante asesorías académicas en línea. Nuestra misión es optimizar el tiempo de estudio y
                    la organización docente a través de un ecosistema que integra comunicación instantánea, gestión de
                    proyectos y seguimiento de hitos académicos bajo una estética minimalista y profesional.
                </p>
            </div>
            <div class="info-card dark">
                <div class="info-icon">
                    <i class="ph-regular ph-shield-check"></i>
                </div>
                <h3>Seguridad y Confianza</h3>
                <p>
                    Validación académica para cada interacción dentro de la plataforma. Tu entorno seguro para el
                    aprendizaje.
                </p>
            </div>
        </section>


        <section class="features-grid">
            <div class="feature-item">
                <div class="feature-icon"><i class="ph-regular ph-calendar-blank"></i></div>
                <h4 class="feature-title serif-font">Agenda</h4>
                <p class="feature-desc">Sincronización perfecta de horarios y entregas.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="ph-regular ph-chat-teardrop-dots"></i></div>
                <h4 class="feature-title serif-font">Chat Directo</h4>
                <p class="feature-desc">Resolución de dudas en tiempo real sin fricciones.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="ph-regular ph-kanban"></i></div>
                <h4 class="feature-title serif-font">Proyectos</h4>
                <p class="feature-desc">Gestión técnica de hitos y avances grupales.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="ph-regular ph-chart-line-up"></i></div>
                <h4 class="feature-title serif-font">Insights</h4>
                <p class="feature-desc">Análisis del progreso académico y áreas de mejora.</p>
            </div>
        </section>

        <footer class="footer">
            <div class="footer-top">
                <div class="footer-brand">
                    <div class="logo">
                        <i class="ph ph-tree-structure" style="font-size: 1.6rem;"></i>
                        <span class="serif-font">Tecnoagenda</span>
                    </div>
                    <p>Redefiniendo la colaboración académica a través del diseño técnico y orgánico.</p>
                    <div class="footer-social">
                        <i class="ph-regular ph-envelope-simple"></i>
                        <i class="ph-regular ph-globe"></i>
                        <i class="ph-regular ph-info"></i>
                    </div>
                </div>

                <div class="footer-links">
                    <div class="link-column">
                        <h4>PLATAFORMA</h4>
                        <a href="#">Características</a>
                        <a href="#">Seguridad</a>
                        <a href="#">Docentes</a>
                    </div>
                    <div class="link-column">
                        <h4>LEGAL</h4>
                        <a href="#">Términos</a>
                        <a href="#">Privacidad</a>
                        <a href="#">Cookies</a>
                    </div>
                </div>

                <div class="footer-support">
                    <h4>Soporte Técnico</h4>
                    <p>¿Necesitas ayuda con tu cuenta?</p>
                    <a href="mailto:soporte@tecnoagenda.edu"><i class="ph-bold ph-at"></i> soporte@tecnoagenda.edu</a>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2024 Tecnoagenda. Todos los derechos reservados.</p>
                <p></p>
            </div>
        </footer>
    </div>

</body>

</html>
