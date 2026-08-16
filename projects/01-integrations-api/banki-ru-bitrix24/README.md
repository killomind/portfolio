# banki.ru ↔ Битрикс24 — REST API интеграция

REST API, принимающее заявки с финансового маркетплейса **Banki.ru** и создающее лиды в **Битрикс24** для микрофинансовой организации. Проверка дублей по телефону, статусы заявок по воронке сделок, автопредодобрение, защита Bearer-токенами.

> Кейс представлен в переработанном виде: код отрефакторин, клиентские данные,
> вебхуки и учётные данные заменены заглушками в конфигурации.

## Стек

- PHP 8.1+ (без фреймворков и composer, процедурно-ориентированные классы)
- REST API Битрикс24 через вебхук (`crm.lead.*`, `crm.contact.*`, `crm.deal.*`)
- Apache + `.htaccess` (роутинг на `public/index.php`)
- Хранилище токенов и лог — файлы JSON/логи в защищённом каталоге `storage/`

## Что реализовано

- **Бизнес-правила:**
  - минимальная сумма заявки 100 000 ₽ — меньше отклоняется без создания лида;
  - автопредодобрение до 5 000 000 ₽;
  - статусы заявок: `PROCESSING`, `PRE-APPROVED`, `APPROVED`, `ISSUED`, `DECLINED`;
  - «этап сделки → статус» настраивается в конфигурации (воронки, категории).
- **Проверка дублей** по телефону: нормализация к последним 10 цифрам, три формата (`+7…`, `8…`, исходный), поиск по лидам и контактам.
- **Маппинг анкеты в лид**: перевод словарей на русский, детальный комментарий, предмет залога в отдельное пользовательское поле.
- **Авторизация**: пароль → Bearer-токен (32 байта hex, TTL), валидация через `Authorization: Bearer`.
- **Логирование** запросов с ID запроса (`X-Request-ID`) — разбирается диагностическим инструментом.
- **Диагностика дублей** (`tools/debug_duplicates_report.php`): анализ лога + поиск дублей в CRM по периоду.

## Структура

```
config/config.example.php   # шаблон конфигурации (копировать в config.php)
public/index.php            # front controller (роутер)
public/.htaccess            # перенаправление на index.php
src/
  Bootstrap.php             # загрузка конфигурации и автозагрузка
  Config.php                # хранилище настроек
  Response.php              # формат ответов/ошибок по спецификации Banki.ru
  Router.php                # маршрутизация
  TokenService.php          # выпуск/проверка Bearer-токенов
  BitrixClient.php          # REST-клиент Битрикс24
  DuplicateService.php      # проверка дублей по телефону
  RequestLogger.php         # лог проверки дублей (формат для диагностики)
  LeadMapper.php            # маппинг анкеты в поля лида
  ApplicationService.php    # создание заявки/лида
  DecisionService.php       # статус заявки по воронке сделки
storage/                    # хранилище (tokens.json, check_double.log), закрыто от веба
tools/
  call_bitrix_api.php       # обёртка для диагностического инструмента
  debug_duplicates_report.php  # диагностика дублей (анализ лога + поиск в CRM)
```

## Установка и запуск

```bash
# 1. Скопировать конфигурацию и заполнить реальные значения
cp config/config.example.php config/config.php

# 2. Указать в config.php:
#    - webhook_url — вебхук Битрикс24
#    - token_password — пароль для получения токена
#    - funnels — сопоставление категорий/этапов сделок со статусами
#    - base_path — базовый путь (если приложение не в корне веба)

# 3. Развернуть public/ в корень веба (или указать base_path)
```

Локальная проверка синтаксиса:

```bash
find . -name '*.php' -exec php -l {} \;
```

## API

Получение токена:

```bash
curl -X POST https://example.com/token \
  -H 'Content-Type: application/json' \
  -d '{"password":"change-me"}'
# → {"data":{"token":"...","exp":5000}}
```

Проверка дубля по телефону:

```bash
curl -X POST https://example.com/check-double \
  -H 'Authorization: Bearer <token>' \
  -H 'Content-Type: application/json' \
  -d '{"phone":"79000000000"}'
# → {"data":{"status":"new"}}  |  {"data":{"status":"repeat"}}
```

Создание заявки (лид):

```bash
curl -X POST https://example.com/application-for-decisions \
  -H 'Authorization: Bearer <token>' \
  -H 'Content-Type: application/json' \
  -d @application.json
# → 201 {"data":{"link":"...","status":"PROCESSING","application-id":"123"}}
```

Статус заявки:

```bash
curl https://example.com/check-decisions/123 -H 'Authorization: Bearer <token>'
# → {"data":{"application-id":"123","status":"APPROVED","limit":1000000,...}}
```

Формат ошибок — по спецификации Banki.ru:

```json
{"data":[{"errors":[{"code":"invalid_password","message":"Invalid or missing password","field":"password"}]}]}
```

## Диагностический инструмент

`tools/debug_duplicates_report.php` — универсальный инструмент, написанный в рамках проекта
(клиентом не оплачивался). Открывается в браузере:

- анализ лога `check_double.log` (с поддержкой `X-Request-ID`) и сопоставление с лидами по ID;
- поиск дублирующихся лидов в CRM за период;
- выгрузка отчётов в HTML.

Доступ защищён HTTP Basic Auth, учётные данные берутся из конфигурации
(`debug_report_user` / `debug_report_password`). При пустом пароле доступ запрещён.

## Ссылки

Демо: нет (требует реального вебхука Битрикс24) | Исходный репозиторий: приватный
