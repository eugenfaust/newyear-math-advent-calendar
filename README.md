# Advent Calendar

Монорепозиторий: Symfony 7.4 / PHP 8.3+ в `backend`, Next.js 15 / App Router / strict TypeScript в `frontend`, PostgreSQL 16. Требования и план: [ТЗ](ADVENT_CALENDAR_PROJECT.md), [задачи](tasks/README.md). Формат API и контракт аутентификации: [docs/api-contract.md](docs/api-contract.md).

## Локальный запуск

Нужны Docker и Docker Compose. Из корня проекта:

```sh
cp .env.example .env
# Замените APP_SECRET, AUTH_SECRET и POSTGRES_PASSWORD случайными значениями.
docker compose up --build -d
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
curl http://localhost:8000/api/v1/health
curl http://localhost:3000/api/health
```

Для генерации секретов можно использовать `openssl rand -hex 32` два раза. Для `POSTGRES_PASSWORD` в локальной конфигурации используйте URL-безопасные символы, поскольку Compose вставляет пароль в `DATABASE_URL` без URL-кодирования. Сайт открыт на http://localhost:3000, backend на http://localhost:8000 (оба порта привязаны к `127.0.0.1`). Результат обоих health-запросов: `{"status":"ok"}`. Сейчас миграция фиксирует пустую начальную схему; первая миграция предметной модели появится вместе с User в задаче 02.

PostgreSQL хранит timestamps в UTC. Контейнеры используют UTC; `APP_TIMEZONE=Europe/Moscow` служит только для вычисления даты открытия дней. База хранится в томе `postgres_data`, файлы — в отдельном томе `uploads`, примонтированном к `/uploads` backend. `docker compose down` сохраняет оба тома; `down -v` удаляет их.

После изменения зависимостей или исходников пересоберите образы: `docker compose up --build -d`. Логи: `docker compose logs -f backend frontend`. Миграции после обновления: `docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction`.

Если Docker на занятом хосте сообщает `all predefined address pools have been fully subnetted`, создайте локальный `compose.override.yaml` со свободной подсетью для сети `default`. Этот файл игнорируется Git; в текущей рабочей среде использована `10.91.240.0/24`.

Библиотеки UI, авторизация и предметные модели добавляются в задачах 02–18 по мере использования. Текущая страница — простой маркер работоспособности frontend.
