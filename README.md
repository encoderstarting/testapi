# Contact AI API

Backend API для формы обратной связи на Laravel.

Текущий статус репозитория:

- реализован `POST /api/contact`;
- работает валидация входных данных;
- подключён AI-анализ комментария;
- реализован graceful fallback при ошибке AI;
- написаны feature-тесты для валидации, успешного AI-анализа и fallback.

Пока не реализованы:

- отправка писем;
- `GET /api/health`;
- `GET /api/metrics`;
- rate limiting;
- Swagger/OpenAPI.

## Текущий стек

- PHP `^8.3` по текущему `composer.json`
- Laravel `^13.8` по текущему `composer.json`
- Laravel HTTP Client
- PHPUnit

Задание в [TASK.md](/TASK.md) описывает целевой стек как Laravel 12 / PHP 8.4, но текущая кодовая база на момент реализации уже была создана на Laravel 13.8 и PHP ^8.3. Изменения вносились поверх существующего состояния репозитория.

## Архитектура текущей реализации

Для `POST /api/contact` сейчас используется цепочка:

```text
Route
  -> StoreContactRequest
  -> ContactController
  -> ContactData DTO
  -> ContactService
  -> AiServiceInterface
  -> HttpAiService
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

## Валидация

Используется `StoreContactRequest`.

Проверяются:

- `name`: от 2 до 100 символов
- `phone`: от 7 до 20 символов, только цифры, пробелы, скобки, дефисы и `+`
- `email`: корректный email, максимум 255 символов
- `comment`: от 10 до 3000 символов

Сообщения валидации возвращаются на русском языке.

## AI-интеграция

На этапе 2 AI анализирует только поле `comment`.

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

4. При необходимости настроить AI-переменные в `.env`.

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
- fallback при невалидном AI-ответе.

В тестах реальные HTTP-запросы не выполняются: используется `Http::fake()`.

## Использование AI при разработке

При разработке использовался Codex.

С помощью AI были подготовлены:

- базовая структура endpoint;
- DTO и сервисы;
- тестовые сценарии;
- черновая документация.

После генерации код проверялся вручную, запускался локально и исправлялся по результатам `php artisan test`, `php artisan route:list` и `vendor/bin/pint --test`.
