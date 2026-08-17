# Портфолио — Сергей Каторгин

**Руководитель направления** · Управление продуктами и командами · Разработка (PHP / Laravel / Vue.js)

- 📞 +7 (953) 913-13-16
- ✉️ katorgin@leader-it.com
- 🌐 [leader-it.com](https://leader-it.com)
- 💬 [Telegram: @killomind_russia](https://t.me/killomind_russia)
- 📄 [Резюме (PDF-страница)](https://leader-it.com/offers/CV_202607150b964e.html)
- 💻 [GitHub: killomind](https://github.com/killomind)

---

## Обо мне

Запускаю новые продукты и направления, 17 лет управленческого опыта, технический и предпринимательский бэкграунд.
Руковожу командами разработки, сам пишу код (PHP, Laravel, Vue.js, Python) и проектирую архитектуру.
Собираю и автоматизирую бизнес-процессы: CRM, 1С, Битрикс24, платёжные и финансовые сервисы, внешние API.

Ключевые направления:

- Управление продуктами и командами, запуск новых направлений, ABM-маркетинг.
- Веб-сервисы и ERP: REST API, оптимизация, рефакторинг.
- Интеграции и автоматизация: 1С, Битрикс24, CRM, банки, платёжные системы.
- Базы данных и инфраструктура: MySQL, PostgreSQL, Docker, Redis, очереди.

Полная версия — в [резюме](https://leader-it.com/offers/CV_202607150b964e.html).

---

## Стек

| Область | Технологии |
|---|---|
| Бэкенд | PHP 8 · Laravel · Symfony · Yii2 · REST API · Python |
| Фронтенд | Vue.js (2/3) · React · JavaScript (ES6+) · TypeScript · HTML5 · CSS3 |
| Базы данных | MySQL · PostgreSQL · оптимизация запросов · индексы |
| Инструменты | Docker · Redis · RabbitMQ · Git · CI/CD · Nginx · Apache |
| Интеграции | 1С · Битрикс24 · платёжные системы · Telegram API · Jira API · Banki.ru API |

---

## Структура портфолио

```
portfolio/
├── README.md                 # этот файл — каталог и описание
└── projects/
    ├── 01-integrations-api/          # интеграции, REST API
    ├── 02-data-import-parsing/       # импорт данных, парсинг, нормализация
    ├── 03-leadgen-scraping/          # лидогенерация, скрапинг, поиск
    ├── 04-websites-corporate/        # сайты и корпоративные продукты
    ├── 05-demos-mvp/                 # демо-версии и MVP (отдельные кейсы)
    ├── 06-nda-projects/              # проекты под НДА — только публичные описания
    └── 07-tools-scripts/             # вспомогательные инструменты и скрипты
```

Каждая подпапка проекта — самодостаточный мини-репозиторий со своим `README.md`,
где указано: что это, стек, как запустить, что реализовано.

---

## Каталог проектов

### 01 · Интеграции и API

| Проект | Что это | Статус |
|---|---|---|
| [banki.ru ↔ Битрикс24](projects/01-integrations-api/banki-ru-bitrix24/README.md) | REST API: заявки с финансового маркетплейса Banki.ru → лиды в Битрикс24 для микрофинансовой организации. Проверка дублей по телефону, статусы заявок по воронке сделок, автопредодобрение, Bearer-токены, диагностика дублей. Код переработан, секреты и клиентские данные вынесены в конфигурацию | в портфолио |

### 02 · Импорт данных и парсинг

| Проект | Что это | Статус |
|---|---|---|
| [dreamhouse-importer](projects/02-data-import-parsing/dreamhouse-importer/README.md) | Python-импортер прайс-листов магазина музыкального оборудования: CSV/XLSX/XML/1С, нормализация и дедуп товаров, авто-подбор фотографий в интернете, отчёты | в портфолио |

### 03 · Лидогенерация и скрапинг

| Проект | Что это | Статус |
|---|---|---|
| [kvartirnik_muzyka](projects/03-leadgen-scraping/kvartirnik-muzyka/README.md) | Поиск музыкантов и площадок во ВКонтакте для квартирников: разбор сохранённых страниц (HTML/PDF), классификация ролей (вокалист/гитара/бар/студия), база контактов CSV. Без личных данных | в портфолио |

### 04 · Сайты и корпоративные продукты

| Проект | Что это | Статус |
|---|---|---|
| [leader-it.com](projects/04-websites-corporate/leader-it-com/README.md) | Корпоративный сайт и рабочие инструменты IT-интегратора «Лидер·Айти»: 10 услуг, решения (финансы, промышленность), блог на PHP, генератор одноразовых КП | переносится (вычистить служебные файлы) |

### 05 · Демо и MVP (отдельные кейсы)

| Проект | Что это | Демо (живое) | Статус |
|---|---|---|---|
| [Автосервис 360](projects/05-demos-mvp/autoservice/README.md) | Платформа управления сетью автосервисов: 5 ролей, календарь записи, заказ-наряды, склад, отчёты (PHP + файловая JSON-БД) | [leader-it.com/demo/autoservice](https://leader-it.com/demo/autoservice/) | в портфолио |
| [Сканер QR-кодов для учёта инвентаря](projects/05-demos-mvp/inventory-qr-scanner/README.md) | Android-приложение (Kotlin, Compose, ZXing): сканирование QR-кодов оборудования. Исходники в публичном репо, кейс на сайте | — | в портфолио (ссылки) |
| [ГРК «Горизонт» — карьерный портал](projects/05-demos-mvp/career-site/README.md) | Поиск вакансий с фильтрами, тест на совместимость, карьерные маршруты, админка с модерацией (PHP + JSON-БД) | [leader-it.com/demo/career-site](https://leader-it.com/demo/career-site/) | в портфолио |
| [Турбаза «Белая Гора»](projects/05-demos-mvp/turbaza/README.md) | Онлайн-бронирование домиков: календарь доступности, расчёт цены по сезонам, оплата, статусы броней, отчёты | [leader-it.com/demo/turbaza](https://leader-it.com/demo/turbaza/) | в портфолио |
| [FlexQC — контроль качества флексоформ](projects/05-demos-mvp/flexqc/README.md) | Рабочее место оператора ОТК: просветный стол, разметка дефектов, автовердикт по регламенту, журнал и аналитика | [leader-it.com/demo/flexqc](https://leader-it.com/demo/flexqc/) | в портфолио |
| [Кинозал](projects/05-demos-mvp/cinema/README.md) | Бронирование мест с выбором сеанса | [demo/cinema.html](https://leader-it.com/demo/cinema.html) | в портфолио |
| [KDS Dashboard (Frontpad)](projects/05-demos-mvp/kds-dashboard/README.md) | Экран кухни (kitchen display), интеграция с Frontpad | [demo/dashboard.html](https://leader-it.com/demo/dashboard.html) | в портфолио |
| [EduPlatform](projects/05-demos-mvp/eduplatform/README.md) | Интерактивная система тестирования | [demo/еduplatform.html](https://leader-it.com/demo/%D0%B5duplatform.html) | в портфолио |
| [Дентал Клиник — мобильное приложение](projects/05-demos-mvp/mobile-dental/README.md) | Прототип мобильного приложения пациента клиники | [demo/mobile_dental.html](https://leader-it.com/demo/mobile_dental.html) | в портфолио |
| [Юлия Гербер](projects/05-demos-mvp/juliagerber/README.md) | Сайт бизнес-тренера по продажам | [demo/juliagerber.html](https://leader-it.com/demo/juliagerber.html) | в портфолио |
| [Pathfinder 1e — лист персонажа](projects/05-demos-mvp/pathfinder-sheet/README.md) | Hobby-проект: лист персонажа для настольной RPG | [demo/pf.html](https://leader-it.com/demo/pf.html) | в портфолио |
| [СметаПро](projects/05-demos-mvp/smetapro/README.md) | Система подготовки смет для монтажно-инженерной компании: пошаговый мастер, спецификация, подбор товаров/работ, контроль соответствия | [demo/smetapro.html](https://leader-it.com/demo/smetapro.html) | в портфолио |
| [SupplyCRM AI](projects/05-demos-mvp/supplycrm-ai/README.md) | CRM для поставок с ИИ-ассистентом: сделки, клиенты, документы, финансы, поставки, AI-анализ | [demo/supplycrm-ai.html](https://leader-it.com/demo/supplycrm-ai.html) | в портфолио |
| [Оповещения о БПЛА](projects/05-demos-mvp/bpla-alert/README.md) | Прототип мобильного приложения системы оповещения о БПЛА: карта, уровни угрозы, звуковая тревога | [demo/bpla-alert.html](https://leader-it.com/demo/bpla-alert.html) | в портфолио |
| [Платёжный контур](projects/05-demos-mvp/payment-contour/README.md) | Финансовый модуль торговой платформы: регистр проводок (двойная запись), идемпотентные webhooks, ЮKassa, облачная ККТ, автотесты (PHP + JSON-БД) | [demo/payment-contour](https://leader-it.com/demo/payment-contour/) | в портфолио |
| [Music CRM](projects/05-demos-mvp/music-crm/README.md) | CRM музыкантов для квартирников: карточки с фото, поиск и фильтры, накопитель заметок (PHP + JSON-БД, демо без реальных контактов). Исходники в публичном репо | [demo/music-crm](https://leader-it.com/demo/music-crm/) | в портфолио (ссылки) |
| [Интерактивное тестирование Tovarum.ru](projects/05-demos-mvp/tovarum-testing/README.md) | Симуляция ручного тестирования веб-сервиса: автопроход сценариев, журнал с вердиктами, фиксация найденных багов (HTML+CSS+JS) | [demo/tovarum-testing.html](https://leader-it.com/demo/tovarum-testing.html) | в портфолио |

### 06 · Проекты под НДА (публичное описание)

Код и материалы под NDA в портфолио не выкладываются — только описание ролей и результатов.

| Проект | Что это (публично) | Статус |
|---|---|---|
| Проект CRM / LMS | Участие в требованиях и запуске системы CRM и LMS для заказчика (ТЗ, структура ролей, интеграции) | под НДА |
| «Битрикс Кортекс Мед» | Медицинская информационная система: доработка модулей, оптимизация, интеграции | под НДА |
| «Экоторг» | Связка Битрикс24 и 1С: обмен данными, синхронизация заказов и товаров, отчётность | под НДА |

### 07 · Инструменты и скрипты

| Проект | Что это | Статус |
|---|---|---|
| [Внутренние инструменты (Java/Spring)](projects/07-tools-scripts/internal-tools/README.md) | Коллекция внутренних сервисов: микросервисная платформа трекинга ShareSpot (19 микросервисов Spring Cloud), фронтенды, Telegram-боты. Исходники в 7 публичных репо killomind | в портфолио (ссылки) |
| [Блог на чистом PHP (JSON + пагинация)](projects/07-tools-scripts/leader-it-blog/README.md) | Движок блога: статьи в JSON, вывод PHP с пагинацией. `blog_parser.php` генерирует данные из HTML-экспорта Telegram | в портфолио |
| [Защищённая отчётная страница для клиента](projects/07-tools-scripts/client-report-page/README.md) | Механизм показа отчётов заказчику: HTML-отчёт за Basic-авторизацией, логин/пароль из переменных окружения, данные обезличены | в портфолио |
| [Генератор квитанции ПД‑4 с QR‑кодом](projects/07-tools-scripts/pd4-qr-generator/README.md) | PHP + TCPDF: бланк «Извещение/Квитанция» ПД‑4 и PDF с QR‑кодом ГОСТ (`ST00012`), реквизиты из формы | в портфолио |
| [Текстовый накопитель (подготовка к чтению)](projects/07-tools-scripts/read-prep-tool/README.md) | Внутренний инструмент для себя и команды: сбор длинных текстов, автоочистка (2 режима), посимвольная пагинация, оценка времени чтения, корзина | в портфолио |
| [Просмотрщик экспорта Skype (потоковый)](projects/07-tools-scripts/skype-export-viewer/README.md) | Инструмент для себя: личный экспорт переписок (десятки МБ) без загрузки в память — JsonMachine + JSON-pointer, поиск, пагинация, разбор системных событий | в портфолио |

