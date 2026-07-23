# Contact AI API

Backend API для формы обратной связи на Laravel.

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
- написаны feature-тесты для валидации, AI, почты, rate limiting, CORS, health, metrics и документации.

Пока не реализованы:

- финальная деплойная проверка из этапа 7.

## Текущий стек

- PHP `^8.3` по текущему `composer.json`
- Laravel `^13.8` по текущему `composer.json`
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

Контроллеры остаются тонкими: принимают запрос, вызывают нужный сервис и возвращают JSON или view.

## Endpoints

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

Поддержаны два режима провайдера:

- `openai`
- `gemini`

Конфигурация хранится в `config/services.php` и читается из `.env`.

Требуемые переменные:

```env
AI_PROVIDER=openai
AI_API_KEY=
AI_MODEL=
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
FRONTEND_URL=http://localhost:3000
```

Конфигурация находится в `config/cors.php` и применяется только к `api/*`.

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
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
OWNER_EMAIL=

AI_PROVIDER=openai
AI_API_KEY=
AI_MODEL=
AI_TIMEOUT=10

FRONTEND_URL=http://localhost:3000
```

Новых переменных для Swagger/OpenAPI не требуется.

Примечание: текущий Laravel-конфиг использует `MAIL_SCHEME`, но для совместимости с заданием в конфигурации также поддержан `MAIL_ENCRYPTION`.

## Установка и запуск

1. Установить зависимости:

```bash
composer install
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

5. Запустить тесты:

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

## Что ещё не сделано

Следующий этап задания пока не реализован:

- финальная проверка деплойного состояния из этапа 7.

## Использование AI при разработке

При разработке использовался Codex.

С помощью AI были подготовлены:

- базовая структура endpoint;
- DTO, сервисы, middleware, mailables и документация;
- тестовые сценарии;
- черновая версия README и OpenAPI spec.

Промпты и направления, которые использовались при разработке:

- генерация структуры Laravel API для contact form;
- построение AI-интеграции с fallback;
- подготовка тестов для AI, почты, rate limiting, health и metrics;
- подготовка OpenAPI/Swagger-документации и curl-примеров.

Проверялось и исправлялось вручную:

- формат JSON-ответов;
- структура сервисного слоя;
- поведение fallback;
- критичное поведение при сбое почты;
- rate limiting, CORS, health, metrics и документация;
- предупреждение PHP в `bootstrap/app.php` после одного из прогонов;
- результаты `php artisan test`;
- результаты `php artisan route:list`;
- результаты `vendor/bin/pint --test`.
