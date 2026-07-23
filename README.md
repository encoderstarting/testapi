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
- написаны feature-тесты для валидации, AI, почты, rate limiting и CORS.

Пока не реализованы:

- `GET /api/health`;
- `GET /api/metrics`;
- Swagger/OpenAPI;
- хранение и выдача метрик.

## Текущий стек

- PHP `^8.3` по текущему `composer.json`
- Laravel `^13.8` по текущему `composer.json`
- Laravel HTTP Client
- Laravel Mail
- PHPUnit

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
```

Контроллер остаётся тонким: он принимает запрос, создаёт DTO, вызывает сервис и возвращает JSON.

## Endpoint

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

Промпт ограничивает модель жёстким JSON-форматом:

```json
{
  "category": "project_request|support|cooperation|question|other",
  "sentiment": "positive|neutral|negative",
  "priority": "low|medium|high",
  "summary": "Краткое описание обращения до 150 символов"
}
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

При fallback сервис пишет предупреждение в лог и маскирует email/телефон.

## Почта

После AI-анализа сервис отправляет два письма:

1. Владельцу сайта на `OWNER_EMAIL`
2. Пользователю на email из запроса

Для писем используются:

- `App\Mail\ContactOwnerMail`
- `App\Mail\ContactUserMail`
- Blade-шаблоны в `resources/views/emails`

Если отправка почты не удалась, API возвращает `503`, а внутренний текст SMTP-ошибки пользователю не показывается.

## Rate limiting

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

## Переменные окружения

Сейчас для этапов 1–4 нужны минимум:

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
- логирование начала и завершения запроса.

В тестах реальные HTTP- и SMTP-запросы не выполняются: используются `Http::fake()` и `Mail::fake()`.

## Что ещё не сделано

Следующие этапы задания пока не реализованы:

- `GET /api/health`;
- `GET /api/metrics`;
- хранение метрик;
- Swagger/OpenAPI-документация;
- curl-примеры для всех конечных точек;
- финальная проверка деплойного состояния.

## Использование AI при разработке

При разработке использовался Codex.

С помощью AI были подготовлены:

- базовая структура endpoint;
- DTO, сервисы, middleware и mailables;
- тестовые сценарии;
- черновая документация.

Проверялось и исправлялось вручную:

- формат JSON-ответов;
- структура сервисного слоя;
- поведение fallback;
- критичное поведение при сбое почты;
- rate limiting и CORS;
- результаты `php artisan test`;
- результаты `php artisan route:list`;
- результаты `vendor/bin/pint --test`.
