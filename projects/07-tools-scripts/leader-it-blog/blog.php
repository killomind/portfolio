<?php
// blog.php — страница блога, оформленная в стиле главной страницы index.html
// Читает данные из blog_data.json (генерируется скриптом blog_parser.php)

$dataFile = 'blog_data.json';
if (!file_exists($dataFile)) {
    die('Файл с данными блога не найден. Пожалуйста, запустите blog_parser.php для генерации JSON.');
}
$json = file_get_contents($dataFile);
$allArticles = json_decode($json, true);
if (!is_array($allArticles)) {
    $allArticles = [];
}


// Принудительная сортировка по дате (новые → старые) на случай, если JSON неотсортирован
usort($allArticles, function($a, $b) {
    $tsA = isset($a['timestamp']) ? (int)$a['timestamp'] : 0;
    $tsB = isset($b['timestamp']) ? (int)$b['timestamp'] : 0;
    return $tsB - $tsA;
});


// Пагинация
$perPage = 12;
$total = count($allArticles);
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$totalPages = ceil($total / $perPage);
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;
$pageArticles = array_slice($allArticles, $offset, $perPage);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Блог | Лидер·Айти</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        /* ========== ГЛОБАЛЬНЫЕ СТИЛИ (аналогично index.html) ========== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #1A2C3E; line-height: 1.5; scroll-behavior: smooth; }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 32px; }

        /* ШАПКА */
        .sticky-header { position: sticky; top: 0; background: white; z-index: 1000; box-shadow: 0 1px 2px rgba(0,0,0,0.03); border-bottom: 1px solid #E9EDF2; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; flex-wrap: wrap; }
        .logo { display: flex; align-items: center; gap: 12px; text-decoration: none; cursor: pointer; }
        .logo img { height: 48px; width: auto; object-fit: contain; }
        .logo-text { font-size: 1.5rem; font-weight: 600; letter-spacing: -0.01em; color: #0B2A41; white-space: nowrap; }
        .logo-text .middot { font-weight: 500; margin: 0 2px; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { text-decoration: none; color: #1F3A5F; font-weight: 500; font-size: 1rem; transition: color 0.2s; cursor: pointer; }
        .nav-links a:hover { color: #0066CC; }
        .nav-links a.active { font-weight: 600; border-bottom: 2px solid #0066CC; padding-bottom: 2px; color: #0066CC; }

        /* Выпадающие меню */
        .dropdown { position: relative; display: inline-block; }
        .dropbtn { cursor: pointer; }
        .dropdown-content { display: none; position: absolute; background-color: #f9f9f9; min-width: 260px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 1; border-radius: 16px; top: 100%; left: 0; padding: 8px 0; }
        .dropdown-content a { color: #1F3A5F; padding: 12px 16px; text-decoration: none; display: block; border-radius: 12px; margin: 0 6px; transition: background 0.2s; }
        .dropdown-content a:hover { background-color: #E6F0FF; }
        .dropdown:hover .dropdown-content { display: block; }
        @media (max-width: 768px) { .dropdown .dropdown-content { display: none !important; } }

        .services-dropdown { position: relative; display: inline-block; }
        .services-dropbtn { cursor: pointer; }
        .services-dropdown-content { display: none; position: absolute; background-color: #f9f9f9; min-width: 260px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 1; border-radius: 16px; top: 100%; left: 0; padding: 8px 0; }
        .services-dropdown-content a { color: #1F3A5F; padding: 12px 16px; text-decoration: none; display: block; border-radius: 12px; margin: 0 6px; transition: background 0.2s; }
        .services-dropdown-content a:hover { background-color: #E6F0FF; }
        .services-dropdown:hover .services-dropdown-content { display: block; }
        @media (max-width: 768px) { .services-dropdown .services-dropdown-content { display: none !important; } }

        .btn-outline { border: 1.5px solid #0066CC; background: transparent; padding: 8px 20px; border-radius: 40px; color: #0066CC; font-weight: 600; transition: 0.2s; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-outline:hover { background: #E6F0FF; transform: scale(0.98); }
        .btn-primary { background: #0066CC; color: white; padding: 12px 32px; border-radius: 40px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-primary:hover { background: #004999; transform: scale(0.98); }

        /* Мобильное меню */
        .menu-toggle { display: none; font-size: 28px; background: none; border: none; cursor: pointer; color: #1F3A5F; }
        .mobile-menu-container { width: 100%; overflow: hidden; transition: max-height 0.35s cubic-bezier(0.2,0.9,0.4,1.1); max-height: 0; }
        .mobile-menu-container.show { max-height: 500px; }
        .mobile-nav { display: flex; flex-direction: column; align-items: flex-start; gap: 20px; padding: 24px 0 16px; border-top: 1px solid #eef2f5; }
        .mobile-nav a { text-decoration: none; color: #1F3A5F; font-weight: 500; font-size: 1rem; cursor: pointer; }
        .mobile-nav a:hover { color: #0066CC; }
        .mobile-nav a.active { font-weight: 600; color: #0066CC; border-left: 3px solid #0066CC; padding-left: 8px; }
        .mobile-dropdown { width: 100%; }
        .mobile-dropbtn { display: block; cursor: pointer; }
        .mobile-dropdown-content { display: none; padding-left: 20px; margin-top: 8px; }
        .mobile-dropdown-content a { display: block; padding: 8px 0; font-size: 0.9rem; }
        .mobile-dropdown.open .mobile-dropdown-content { display: block; }

        @media (max-width: 768px) {
            .container { padding: 0 24px; }
            .logo-text { font-size: 1.25rem; }
            .logo img { height: 40px; }
            .nav-links { display: none; }
            .menu-toggle { display: block; }
        }

        /* Hero блог */
        .blog-hero {
            background: linear-gradient(180deg, #F0F7FF 0%, #FFFFFF 100%);
            padding: 60px 0 40px;
            border-bottom: 1px solid #E9EDF2;
            margin-bottom: 40px;
        }
        .blog-hero h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 16px; }
        .blog-hero p { font-size: 1.1rem; color: #4A6A85; max-width: 720px; }

        /* Сетка статей */
        .blog-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 32px;
            margin: 40px 0 80px;
        }
        .blog-card {
            background: #F9FCFE;
            border-radius: 32px;
            border: 1px solid #E2ECF5;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            padding: 32px;
        }
        .blog-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 35px -12px rgba(0, 102, 204, 0.12);
            border-color: #CFE2F5;
        }
        .blog-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .blog-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
        }
        .blog-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .blog-date {
            font-size: 0.85rem;
            color: #6C86A3;
            background: #F0F4F9;
            padding: 4px 12px;
            border-radius: 40px;
        }
        .blog-text {
            color: #1A2C3E;
            line-height: 1.6;
        }
        .blog-text img {
            max-width: 100%;
            height: auto;
            border-radius: 16px;
            margin: 20px 0;
        }
        .blog-text a {
            color: #0066CC;
            text-decoration: none;
        }
        .blog-text a:hover {
            text-decoration: underline;
        }
        .blog-author {
            margin-top: 24px;
            font-size: 0.85rem;
            color: #6C86A3;
            border-top: 1px solid #E9EDF2;
            padding-top: 20px;
        }

        /* Пагинация с кнопками "В начало" и "В конец" */
        .pagination {
            text-align: center;
            margin: 40px 0 80px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            background: white;
            border: 1px solid #E9EDF2;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 0.9rem;
            transition: 0.2s;
            color: #1F3A5F;
            text-decoration: none;
            display: inline-block;
        }
        .pagination a:hover {
            background: #E6F0FF;
            border-color: #0066CC;
        }
        .pagination .active {
            background: #0066CC;
            color: white;
            border-color: #0066CC;
        }
        .pagination .disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        .pagination .page-jump {
            background: transparent;
            border-color: transparent;
        }
        @media (max-width: 768px) {
            .blog-card { padding: 24px; }
            .pagination a, .pagination span { padding: 6px 12px; }
        }

        /* Секция контактов */
        .contact-section {
            padding: 80px 0;
            background: #F9FCFE;
        }
        .section-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: #0B2A41;
        }
        .section-sub {
            font-size: 1.1rem;
            color: #4A6A85;
            max-width: 720px;
            margin-bottom: 48px;
        }
        .contact-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 48px;
            background: #F0F7FF;
            border-radius: 48px;
            padding: 48px;
            margin-top: 20px;
        }
        .contact-info {
            flex: 1;
        }
        .contact-details p {
            margin: 16px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .contact-details a {
            color: #1F3A5F;
            text-decoration: none;
            font-weight: 500;
        }
        .contact-details a:hover {
            color: #0066CC;
        }
        footer {
            border-top: 1px solid #E9EDF2;
            padding: 32px 0;
            text-align: center;
            color: #6C86A3;
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .contact-grid { padding: 32px; }
            .section-title { font-size: 1.8rem; }
        }

        /* Анимация появления */
        .fade-up {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }
        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
<header class="sticky-header">
    <div class="container">
        <div class="navbar">
            <div class="logo" id="logoLink">
                <img src="logo_invert_small.png" alt="Лидер·Айти" onerror="this.src='https://placehold.co/200x60?text=Лидер·Айти';">
                <span class="logo-text">Лидер<span class="middot">·</span>Айти</span>
            </div>
            <button class="menu-toggle" id="menuToggle" aria-label="Меню">☰</button>
            <div class="nav-links">
                <a href="index.html">Главная</a>
                <div class="services-dropdown">
                                    <a class="services-dropbtn">Услуги ▾</a>
                                    <div class="services-dropdown-content">
                                                        <a href="services/bitrix/">Сайты на 1С‑Битрикс</a>
                                                        <a href="services/bitrix24/">Автоматизация на Битрикс24</a>
                                                        <a href="services/drupal/">Сайты на Drupal</a>
                                                        <a href="services/seo/">SEO‑оптимизация и поддержка</a>
                                                        <a href="services/sites/">Разработка сайтов под ключ</a>
                                                        <a href="services/mobile/">Мобильные приложения</a>
                                                        <a href="services/design/">Проектирование и ТЗ</a>
                                                        <a href="services/web-services/">Веб-сервисы</a>
                                                        <a href="services/1s/">1С проекты</a>
                                                        <a href="services/it-outsourcing/">Системное администрирование</a>
                                    </div>
                </div>
                <div class="dropdown">
                                    <a class="dropbtn">Решения ▾</a>
                                    <div class="dropdown-content">
                                                        <a href="manufacturing/">Промышленность</a>
                                                        <a href="finance/">Лидогенерация в финансах</a>
                                    </div>
                </div>
                <a href="blog.php" class="active">Блог</a>
                <a href="#contact" class="btn-outline">Связаться</a>
            </div>
        </div>
        <div class="mobile-menu-container" id="mobileMenu">
            <div class="mobile-nav">
                <a href="index.html">Главная</a>
                <div class="mobile-dropdown">
                                    <a class="mobile-dropbtn">Услуги ▾</a>
                                    <div class="mobile-dropdown-content">
                                                        <a href="services/bitrix/">Сайты на 1С‑Битрикс</a>
                                                        <a href="services/bitrix24/">Автоматизация на Битрикс24</a>
                                                        <a href="services/drupal/">Сайты на Drupal</a>
                                                        <a href="services/seo/">SEO‑оптимизация и поддержка</a>
                                                        <a href="services/sites/">Разработка сайтов под ключ</a>
                                                        <a href="services/mobile/">Мобильные приложения</a>
                                                        <a href="services/design/">Проектирование и ТЗ</a>
                                                        <a href="services/web-services/">Веб-сервисы</a>
                                                        <a href="services/1s/">1С проекты</a>
                                                        <a href="services/it-outsourcing/">Системное администрирование</a>
                                    </div>
                </div>
                <div class="dropdown">
                                    <a class="dropbtn">Решения ▾</a>
                                    <div class="dropdown-content">
                                                        <a href="manufacturing/">Промышленность</a>
                                                        <a href="finance/">Лидогенерация в финансах</a>
                                    </div>
                </div>
                <a href="blog.php" class="active">Блог</a>
                <a href="#contact" class="btn-outline">Связаться</a>
            </div>
        </div>
    </div>
</header>

<main>
    <div class="blog-hero">
        <div class="container">
            <h1>Блог компании Лидер·Айти</h1>
            <p>Полезные статьи о разработке ПО, автоматизации бизнеса, управлении проектами и опыте реализации сложных решений.</p>
        </div>
    </div>

    <div class="container">
        <div class="blog-grid">
            <?php if (empty($pageArticles)): ?>
                <div class="blog-card">Статей пока нет. Загляните позже.</div>
            <?php else: ?>
                <?php foreach ($pageArticles as $article): ?>
                    <div class="blog-card fade-up">
                        <div class="blog-meta">
                            <div class="blog-avatar">
                                <img src="blog/avatar.jpg" alt="Сергей Каторгин">
                            </div>
                            <div class="blog-date"><?= htmlspecialchars($article['date']) ?></div>
                        </div>
                        <div class="blog-text"><?= $article['text'] ?></div>
                        <div class="blog-author">Сергей Каторгин, CTO и сооснователь Лидер·Айти</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="?page=1" title="В начало">« В начало</a>
                <a href="?page=<?= $currentPage-1 ?>">← Назад</a>
            <?php else: ?>
                <span class="disabled">« В начало</span>
                <span class="disabled">← Назад</span>
            <?php endif; ?>

            <?php
            $start = max(1, $currentPage - 3);
            $end = min($totalPages, $currentPage + 3);
            for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i == $currentPage): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?= $currentPage+1 ?>">Вперёд →</a>
                <a href="?page=<?= $totalPages ?>" title="В конец">В конец »</a>
            <?php else: ?>
                <span class="disabled">Вперёд →</span>
                <span class="disabled">В конец »</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div id="contact" class="contact-section fade-up">
        <div class="container">
            <div class="section-title">Готовы обсудить ваш проект</div>
            <div class="section-sub">Свяжитесь с нами, чтобы получить консультацию и коммерческое предложение</div>
            <div class="contact-grid">
                <div class="contact-info">
                    <h3 style="margin-bottom: 24px;">Контакты</h3>
                    <div class="contact-details">
                        <p>📞 <a href="tel:+79539131316">+7 (953) 913-13-16</a></p>
                        <p>✉️ <a href="mailto:katorgin@leader-it.com">katorgin@leader-it.com</a></p>
                        <p>💬 <a href="https://t.me/killomind_russia" target="_blank">Telegram: @killomind_russia</a></p>
                        <p>🌐 <a href="#">leader-it.com</a></p>
                    </div>
                    <div style="margin-top: 32px;">
                        <p style="color:#4A6A85;">Работаем по всей РФ, офис в Томске. Официальное ООО, договоры, ЭДО, NDA.</p>
                    </div>
                </div>
                <div style="flex:1; background: white; border-radius: 32px; padding: 24px; box-shadow: 0 8px 20px rgba(0,0,0,0.02);">
                    <p style="font-weight: 600; margin-bottom: 16px;">Этапы работы:</p>
                    <ul style="list-style: none;">
                        <li style="margin-bottom: 12px;">✅ Анализ требований, фиксированная оценка</li>
                        <li style="margin-bottom: 12px;">✅ Поэтапная сдача с демонстрацией результатов</li>
                        <li style="margin-bottom: 12px;">✅ Техническая поддержка и документация</li>
                    </ul>
                    <div style="text-align: center; margin-top: 24px;">
                        <a href="https://t.me/killomind_russia" target="_blank" class="btn-primary">Запросить КП →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<footer>
    <div class="container">
        <p>© 2026 Центр разработки ПО «Лидер·Айти». Разработка веб-проектов, интеграции, автоматизация бизнеса.</p>
    </div>
</footer>

<script>
    function smoothScroll(targetId) {
        const target = document.getElementById(targetId);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#' || href === '') return;
            const targetId = href.substring(1);
            if (targetId && document.getElementById(targetId)) {
                e.preventDefault();
                smoothScroll(targetId);
                const mobileMenu = document.getElementById('mobileMenu');
                if (mobileMenu) mobileMenu.classList.remove('show');
            }
        });
    });
    document.getElementById('logoLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = 'index.html';
    });
    const toggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    if (toggle && mobileMenu) {
        toggle.addEventListener('click', () => mobileMenu.classList.toggle('show'));
    }
    document.querySelectorAll('.mobile-dropbtn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            btn.closest('.mobile-dropdown').classList.toggle('open');
        });
    });
    const fadeElements = document.querySelectorAll('.fade-up');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: "0px 0px -50px 0px" });
    fadeElements.forEach(el => observer.observe(el));
</script>
</body>
</html>