# Contact AI API

Laravel API для формы обратной связи с дополнительным Vue frontend на корневой странице `/`.

Текущий статус репозитория:

- реализован `POST /api/contact`;
- работает валидация входных данных;
- подключён AI-анализ комментария;
- реализован graceful fallback при ошибке AI;
- отправляются два письма: владельцу сайта и пользователю;
- добавлена обработка почтовой ошибки с JSON-ответом `503`;
- включён rate limiting `5` запросов в минуту на IP;
- добавлено логирование начала и завершения запроса;
- настроен CORS для `FRONTEND_URL`;
- реализованы `GET /api/health` и `GET /api/metrics`;
- метрики хранятся в JSON-файле с блокировкой записи;
- добавлены OpenAPI JSON и Swagger UI documentation page;
- добавлен Vue 3 frontend для ручной отправки формы и отдельного monitoring UI для health и metrics;
- написаны feature-тесты для валидации, AI, почты, rate limiting, CORS, health, metrics и документации.

Production deployment:

- Railway URL: `https://testapi-production-ae54.up.railway.app/`

## Текущий стек

- PHP `^8.3` по текущему `composer.json`
- Laravel `^13.8` по текущему `composer.json`
- Vue `^3.5`
- Vite `^7`
- Laravel HTTP Client
- Laravel Mail
- PHPUnit
- OpenAPI 3.1
- Swagger UI через CDN на странице документации

Задание в `TASK.md` описывает целевой стек как Laravel 12 / PHP 8.4, но текущая кодовая база на момент реализации уже была создана на Laravel 13.8 и PHP ^8.3. Изменения вносились поверх существующего состояния репозитория.

## Архитектура текущей реализации

Для `POST /api/contact` сейчас используется цепочка:

```text
Route
  -> middleware: log.contact
  -> middleware: throttle:5,1
  -> StoreContactRequest
  -> ContactController
  -> ContactData DTO
  -> ContactService
  -> AiServiceInterface
  -> HttpAiService
  -> Laravel Mail
  -> MetricsService
```

Дополнительные endpoint:

- `GET /api/health`
- `GET /api/metrics`
- `GET /api/openapi.json`
- `GET /api/documentation`

Frontend:

- `GET /` отдаёт Vue-приложение с формой обратной связи;
- Vue UI работает поверх уже существующего API и не меняет публичный JSON-контракт backend;
- на корневой странице есть отдельные frontend-блоки для `health` и `metrics`.

Контроллеры остаются тонкими: принимают запрос, вызывают нужный сервис и возвращают JSON или view.

## Endpoints

### `GET /`

Корневая страница отдаёт Vue frontend, который:

- отправляет форму в `POST /api/contact`;
- показывает отдельный health-блок со статусами `application`, `ai`, `mail`;
- показывает отдельный metrics-блок со счётчиками и отношениями success/failure/fallback;
- содержит быстрые ссылки на `/api/documentation` и `/api/openapi.json`.

Если Vite manifest ещё не собран и dev-server не запущен, корневая страница выводит fallback-заглушку с ссылками на API и документацию вместо падения по `ViteManifestNotFoundException`.

### `POST /api/contact`

Принимает JSON:

```json
{
  "name": "Никита",
  "phone": "+79999999999",
  "email": "nikita@example.com",
  "comment": "Хочу обсудить разработку интернет-магазина"
}
```

Успешный ответ:

```json
{
  "success": true,
  "message": "Обращение успешно отправлено.",
  "data": {
    "category": "project_request",
    "sentiment": "positive",
    "priority": "high",
    "processed_by_ai": true
  }
}
```

Ошибка валидации:

```json
{
  "success": false,
  "message": "Данные не прошли проверку.",
  "errors": {
    "email": [
      "Укажите корректный email."
    ]
  }
}
```

Ошибка лимита:

```json
{
  "success": false,
  "message": "Слишком много запросов. Попробуйте позже."
}
```

Ошибка почты:

```json
{
  "success": false,
  "message": "Сервис отправки сообщений временно недоступен."
}
```

Внутренняя ошибка:

```json
{
  "success": false,
  "message": "Произошла внутренняя ошибка."
}
```

### `GET /api/health`

Ответ:

```json
{
  "status": "ok",
  "timestamp": "2026-07-23T12:00:00+00:00",
  "services": {
    "application": "available",
    "ai": "configured",
    "mail": "configured"
  }
}
```

Endpoint не отправляет реальные письма и не делает AI-запрос.

### `GET /api/metrics`

Ответ:

```json
{
  "total_requests": 10,
  "successful_requests": 8,
  "failed_requests": 2,
  "ai_fallbacks": 1
}
```

### `GET /api/openapi.json`

Возвращает OpenAPI 3.1 specification для всех публичных endpoint.

### `GET /api/documentation`

Отдаёт HTML-страницу Swagger UI, которая загружает `/api/openapi.json`.

## Curl Examples

Создать обращение:

```bash
curl -X POST http://localhost/api/contact \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"Никита\",\"phone\":\"+79999999999\",\"email\":\"nikita@example.com\",\"comment\":\"Хочу обсудить разработку интернет-магазина\"}"
```

Проверить health:

```bash
curl http://localhost/api/health
```

Получить metrics:

```bash
curl http://localhost/api/metrics
```

Получить OpenAPI JSON:

```bash
curl http://localhost/api/openapi.json
```

Production examples:

```bash
curl https://testapi-production-ae54.up.railway.app/api/health
curl https://testapi-production-ae54.up.railway.app/api/metrics
curl https://testapi-production-ae54.up.railway.app/api/openapi.json
```

## Frontend

Frontend специально сделан простым: один Vue-компонент без роутера и без state manager. Это соответствует исходной задаче, где frontend не требовался, но даёт рабочую демонстрационную страницу для ручной проверки backend.

Что делает интерфейс:

- собирает `name`, `phone`, `email`, `comment`;
- показывает полевые ошибки `422`;
- показывает результат AI-анализа после успешной отправки;
- подтягивает `health` и `metrics` при загрузке страницы;
- позволяет переключаться между отдельными frontend-представлениями для `health` и `metrics`;
- даёт ручное обновление мониторинговых данных;
- позволяет быстро проверить документацию и OpenAPI.

Технически фронт находится в:

- `resources/js/components/ContactFrontApp.vue`
- `resources/js/app.js`
- `resources/css/app.css`
- `resources/views/welcome.blade.php`

Примечание: этот Vue frontend является расширением поверх исходного backend-only задания из `TASK.md`.

## Swagger / OpenAPI

Документация доступна по адресам:

- `/api/documentation`
- `/api/openapi.json`

В спецификации описаны:

- `POST /api/contact`
- `GET /api/health`
- `GET /api/metrics`

Для каждого endpoint указаны:

- назначение;
- входные данные;
- обязательные поля;
- успешные ответы;
- возможные ошибки;
- HTTP-статусы;
- примеры схем.

Примечание: Swagger UI подключается через CDN. Если среда выполнения заблокирует внешний CDN, OpenAPI JSON по `/api/openapi.json` всё равно останется доступен локально.

## Валидация

Используется `StoreContactRequest`.

Проверяются:

- `name`: от 2 до 100 символов
- `phone`: от 7 до 20 символов, только цифры, пробелы, скобки, дефисы и `+`
- `email`: корректный email, максимум 255 символов
- `comment`: от 10 до 3000 символов

Сообщения валидации возвращаются на русском языке.

## AI-интеграция

AI анализирует только поле `comment`.

Основной провайдер проекта сейчас: `gemini`.

Дополнительно в коде сохранена совместимость с `openai`, но базовая конфигурация, примеры и тесты ориентированы на Gemini.

Конфигурация хранится в `config/services.php` и читается из `.env`.

Требуемые переменные:

```env
AI_PROVIDER=gemini
AI_API_KEY=
AI_MODEL=gemini-3.5-flash-lite
AI_TIMEOUT=10
```

Промпт, который отправляется AI-провайдеру:

```text
Проанализируй обращение пользователя.

Верни только JSON без markdown и дополнительного текста.

Формат ответа:

{
  "category": "project_request|support|cooperation|question|other",
  "sentiment": "positive|neutral|negative",
  "priority": "low|medium|high",
  "summary": "Краткое описание обращения до 150 символов"
}

Обращение пользователя:
{{ comment }}
```

Сервис валидирует AI-ответ:

- ответ не должен быть пустым;
- ответ должен быть корректным JSON;
- должны присутствовать `category`, `sentiment`, `priority`, `summary`;
- значения должны входить в разрешённые списки;
- `summary` не должен превышать 150 символов.

## Fallback

Если AI не настроен, возвращает пустой ответ, выдаёт невалидный JSON или отвечает ошибкой, запрос не падает.

Вместо этого используется fallback:

```json
{
  "category": "other",
  "sentiment": "neutral",
  "priority": "medium",
  "summary": "Первые 150 символов комментария",
  "processed_by_ai": false
}
```

При fallback сервис пишет предупреждение в лог и увеличивает `ai_fallbacks` в метриках.

## Почта

После AI-анализа сервис отправляет два письма:

1. Владельцу сайта на `OWNER_EMAIL`
2. Пользователю на email из запроса

Для писем используются:

- `App\Mail\ContactOwnerMail`
- `App\Mail\ContactUserMail`
- Blade-шаблоны в `resources/views/emails`

Если отправка почты не удалась, API возвращает `503`, а внутренний текст SMTP-ошибки пользователю не показывается.

## Rate Limiting

Для `POST /api/contact` установлен лимит:

- не более `5` запросов в минуту с одного IP

При превышении API возвращает JSON со статусом `429`.

## Логирование

Сейчас логируются:

- начало обработки обращения;
- успешное завершение обращения;
- ошибка AI и переход на fallback;
- ошибка отправки почты;
- превышение rate limit;
- неожиданные исключения API;
- время выполнения и HTTP-статус.

Персональные данные в логах маскируются.

## CORS

Разрешён только origin из:

```env
FRONTEND_URL=https://your-frontend-domain.example
```

Конфигурация находится в `config/cors.php` и применяется только к `api/*`.

Для production на Railway не используется wildcard `*`. Нужно указывать точный frontend origin:

- если frontend и API живут в одном Laravel-сервисе, можно поставить `FRONTEND_URL` равным `APP_URL`;
- если frontend размещён отдельно, укажите его полный `https://` origin без завершающего `/`.

## Метрики

Метрики хранятся в JSON-файле, путь задаётся в `config/metrics.php`.

По умолчанию используется:

```text
storage/app/metrics/contact-ai-api.json
```

При записи используется блокировка файла, чтобы параллельные запросы не повредили данные.

Сейчас считаются:

- `total_requests`
- `successful_requests`
- `failed_requests`
- `ai_fallbacks`

Примечание: на текущем этапе эти счётчики обновляются для запросов, которые дошли до бизнес-обработки `ContactService`. Ошибки валидации и срабатывание rate limit в метрики запроса не включаются.

## Переменные окружения

Сейчас для этапов 1–6 нужны минимум:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-service.up.railway.app

LOG_CHANNEL=stderr
LOG_STACK=stderr

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
OWNER_EMAIL=

AI_PROVIDER=gemini
AI_API_KEY=
AI_MODEL=gemini-3.5-flash-lite
AI_TIMEOUT=10

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync

FRONTEND_URL=https://your-frontend-domain.example
```

Новых переменных для Swagger/OpenAPI не требуется.

Для локальной ручной проверки Vue UI обычно достаточно:

- `APP_URL=http://127.0.0.1:8000`
- `FRONTEND_URL=http://127.0.0.1:8000`

Примечание: текущий Laravel-конфиг использует `MAIL_SCHEME`, но для совместимости с заданием в конфигурации также поддержан `MAIL_ENCRYPTION`.

Для Railway переменная `PORT` приходит автоматически от платформы и вручную в `.env.example` не добавляется.

## Установка и запуск

1. Установить зависимости:

```bash
composer install
npm install
```

2. Создать `.env`:

```bash
copy .env.example .env
```

3. Сгенерировать ключ приложения:

```bash
php artisan key:generate
```

4. Настроить `OWNER_EMAIL`, почтовые параметры, AI-переменные и `FRONTEND_URL` в `.env`.

5. Для локальной разработки frontend запустить два процесса:

```bash
php artisan serve
npm run dev
```

6. Для production-сборки frontend:

```bash
npm run build
```

7. Запустить тесты:

```bash
php artisan test
```

## Тесты

Сейчас покрыты:

- успешный запрос с корректным AI-ответом;
- пустые обязательные поля;
- неправильный email;
- неправильный телефон;
- слишком короткий комментарий;
- fallback при невалидном AI-ответе;
- отправка письма владельцу;
- отправка письма пользователю;
- `503` при сбое почтового сервиса;
- `429` при превышении лимита;
- CORS-заголовки для разрешённого origin;
- логирование начала и завершения запроса;
- `GET /api/health`;
- `GET /api/metrics`;
- обновление метрик после успешных и неуспешных обращений;
- доступность OpenAPI JSON;
- доступность Swagger UI страницы.

В тестах реальные HTTP- и SMTP-запросы не выполняются: используются `Http::fake()` и `Mail::fake()`.

## Деплой

Минимально перед деплоем нужно:

1. Настроить production `.env`
2. Указать рабочие AI credentials
3. Настроить SMTP и `OWNER_EMAIL`
4. Ограничить `FRONTEND_URL` production-доменом
5. Проверить запись в каталог `storage/app/metrics`
6. Прогнать `php artisan test`

### Railway

Проект подготовлен к деплою на Railway без Docker, без отдельной базы и без Redis:

- Railway по официальной документации автоматически детектирует Laravel и запускает его через `php-fpm` и `Caddy`;
- в [railway.json](/E:/project/contact-ai-api/railway.json:1) добавлен healthcheck на `/api/health`;
- приложение доверяет reverse proxy заголовкам в [bootstrap/app.php](/E:/project/contact-ai-api/bootstrap/app.php:1), поэтому корректно работает за HTTPS-терминацией Railway;
- production defaults в [.env.example](/E:/project/contact-ai-api/.env.example:1) переведены на `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.

Рекомендуемые Railway variables:

- `APP_KEY`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<your-domain-or-up-railway-app>`
- `FRONTEND_URL=https://<your-frontend-origin>`
- `LOG_CHANNEL=stderr`
- `LOG_STACK=stderr`
- `OWNER_EMAIL`
- `AI_PROVIDER=gemini`
- `AI_API_KEY`
- `AI_MODEL=gemini-3.5-flash-lite`
- `AI_TIMEOUT=10`
- `MAIL_MAILER`
- `MAIL_SCHEME`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`
- `SESSION_DRIVER=file`
- `SESSION_SECURE_COOKIE=true`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`

Порядок настройки в Railway:

1. Создать сервис из GitHub-репозитория.
2. Сгенерировать публичный Railway domain в Networking.
3. Добавить production variables из списка выше.
4. Убедиться, что `APP_URL` совпадает с публичным доменом сервиса.
5. Убедиться, что `FRONTEND_URL` совпадает с origin фронтенда.
6. После первого деплоя проверить `GET /api/health`, `GET /api/documentation` и `GET /api/openapi.json`.

Текущий deployed URL:

- `https://testapi-production-ae54.up.railway.app/`
- Swagger UI: `https://testapi-production-ae54.up.railway.app/api/documentation`
- OpenAPI JSON: `https://testapi-production-ae54.up.railway.app/api/openapi.json`

Известные ограничения Railway-подготовки:

- файловая система Railway эфемерная, поэтому file-based cache/session подходят, но не являются постоянным хранилищем между пересозданиями инстанса;
- метрики в `storage/app/metrics` тоже эфемерны и будут сбрасываться при полном пересоздании контейнера;
- если Swagger CDN недоступен из браузера клиента, `/api/openapi.json` всё равно останется рабочим.

## Использование AI при разработке

При разработке использовался Codex.

С помощью AI были подготовлены:

- базовая структура endpoint;
- DTO, сервисы, middleware, mailables и документация;
- Vue-страница формы обратной связи;
- отдельные Vue-представления для health и metrics;
- тестовые сценарии;
- черновая версия README и OpenAPI spec.

Промпты и направления, которые использовались при разработке:

- генерация структуры Laravel API для contact form;
- построение AI-интеграции с fallback;
- подготовка тестов для AI, почты, rate limiting, health и metrics;
- подготовка OpenAPI/Swagger-документации и curl-примеров;
- сборка демонстрационного Vue frontend поверх готового API.

Проверялось и исправлялось вручную:

- формат JSON-ответов;
- структура сервисного слоя;
- поведение fallback;
- критичное поведение при сбое почты;
- rate limiting, CORS, health, metrics и документация;
- предупреждение PHP в `bootstrap/app.php` после одного из прогонов;
- отсутствие падения `/` без `public/build/manifest.json`;
- production-сборка `npm run build`;
- `GET /api/health` в production-конфигурации;
- Swagger UI за HTTPS reverse proxy;
- результаты `php artisan test`;
- результаты `php artisan route:list`;
- результаты `vendor/bin/pint --test`.

Отдельно вручную со стороны пользователя было исправлено и подтверждено:

- основной AI-провайдер переведён на `gemini` вместо `openai`;
- проверена локальная `.env`-конфигурация для `OWNER_EMAIL`, Gemini и SMTP;
- через `Test-NetConnection smtp.gmail.com -Port 587` подтверждено, что `503` при отправке письма вызван не Laravel-кодом, а недоступностью Gmail SMTP из текущей сети или proxy-интерфейса `dedproxy`;
- выполнен ручной деплой на Railway по адресу `https://testapi-production-ae54.up.railway.app/`.
