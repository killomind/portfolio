# Внутренние инструменты (Java/Spring)

Коллекция внутренних сервисов и ботов, которые проектировал и разрабатывал я вместе
со своей командой для внутренней эксплуатации: микросервисная платформа трекинга
задач и учёта рабочего времени, фронтенды к ней, а также Telegram-боты для маркетинга
и транскрибации звонков.

Это не один продукт, а набор наработок, объединённых общим стеком (Java / Spring Boot /
Spring Cloud) и общей историей: **ShareSpot** (трекер), трекеры времени/отчётов и боты.
В репозиториях публичного портфолио размещён очищенный код — пароли, токены и ключи
заменены на плейсхолдеры `${VAR}`.

## Стек

- Java 8–17, Spring Boot 2.x / 3.2, Spring Cloud (Netflix Eureka, Config, Gateway/Zuul)
- Spring Security OAuth2 + JWT (RSA), Feign-клиенты, Liquibase, JPA
- Docker / docker-compose, Grafana Agent (Prometheus, Loki, Tempo)
- Angular (веб-фронтенд трекера), JMix / Vaadin (админ-фронтенд), Freemarker
- Telegram Bot API, Yandex Cloud SpeechKit / Object Storage

## Сервисы

### ShareSpot Platform — микросервисная платформа трекинга

Моногрепо из 19 микросервисов Spring Cloud. Ключевые подсистемы:

| Сервис | Что это | Стек |
|---|---|---|
| discovery-service | Eureka-реестр микросервисов | Spring Cloud Netflix |
| config-service / config-files | Central config server + конфиги сервисов | Spring Cloud Config, Git |
| gateway-service | API-шлюз (Zuul) с фильтрами | Spring Cloud Netflix |
| auth-service | OAuth2/JWT-авторизация | Spring Security OAuth2, JWT |
| entry-point-service | Регистрация/вход клиентов, приглашения | Spring Boot, OAuth2 resource |
| company-/customer-/employer-data | CRUD данные компаний, клиентов, работодателей | Spring Boot, JPA, Liquibase |
| employee-management-service | Сотрудники и штат | Spring Boot 3.2, JPA |
| project-data-service | Проекты | Spring Boot, JPA |
| tasktracker-service | Задачи, связка с Jira | Spring Boot, Feign |
| worktracker-service | Учёт рабочего времени (worklog) | Spring Boot, JPA |
| dashboard-service | Дашборды и аналитика | Spring Boot |
| report-service | Отчёты (в т.ч. HTML в Google Sheets) | Spring Boot, Feign |
| notification-service | Уведомления и шаблоны писем | Spring Boot, SMTP |
| printform-service | Печатные формы (акты/счета в XLSX), Google API | Spring Boot, Google Sheets |
| jira-client-service | Клиент Jira REST API | Spring Boot, Jira REST |
| infra | Запуск всей платформы в Docker + мониторинг | Docker, Grafana Agent |

### Отдельные репозитории

| Репозиторий | Что это | Стек |
|---|---|---|
| [sharespot-platform](https://github.com/killomind/sharespot-platform) | Платформа ShareSpot: 19 микросервисов (см. таблицу выше) | Java, Spring Cloud |
| [sharespot-parent](https://github.com/killomind/sharespot-parent) | Родительский POM + стартеры Spring Boot (cloud/common/db/logging/security) | Maven, Spring Boot 3.2 |
| [tracker-web-front](https://github.com/killomind/tracker-web-front) | Веб-фронтенд трекера | Angular |
| [jmix-front-tracker](https://github.com/killomind/jmix-front-tracker) | Админ-фронтенд | JMix, Vaadin, Gradle |
| [freemarker-front-tracker](https://github.com/killomind/freemarker-front-tracker) | Фронтенд отчётов трекера | Spring Boot, Freemarker |
| [telegram-marketing-bot](https://github.com/killomind/telegram-marketing-bot) | Маркетинговый TG-бот (кейсы, клиенты) | Spring Boot, Telegram API |
| [telegram-transcribe-bot](https://github.com/killomind/telegram-transcribe-bot) | TG-бот транскрибации звонков (admin + transcribe + repository) | Spring Boot, multi-module |

## Запуск

Каждый сервис — отдельный Spring Boot-приложение со своим `pom.xml`; запускается
стандартно (`./mvnw spring-boot:run`). Для запуска платформы целиком используйте
`infra/docker-compose.yml` из `sharespot-platform`. Конфигурации читают параметры
из переменных окружения `${VAR}` (требуется задать ключи, токены, БД-креды).

## Ссылки

- Коллекция: 7 публичных репозиториев, таблица выше
- Контекст проектов: https://leader-it.com (кейсы клиентских продуктов)