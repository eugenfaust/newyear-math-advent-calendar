# Advent Calendar

Монорепозиторий: Symfony 7.4 / PHP 8.3+ в `backend`, Next.js 15 / App Router / strict TypeScript в `frontend`, PostgreSQL 16. Требования и план: [ТЗ](ADVENT_CALENDAR_PROJECT.md), [задачи](tasks/README.md). Формат API и контракт аутентификации: [docs/api-contract.md](docs/api-contract.md).

## Локальный запуск

Нужны Docker и Docker Compose. Из корня проекта:

```sh
cp .env.example .env
# Замените APP_SECRET, AUTH_SECRET и POSTGRES_PASSWORD случайными значениями.
make init
make create-admin
curl http://localhost:8000/api/v1/health
curl http://localhost:3000/api/health
```

Для генерации секретов можно использовать `openssl rand -hex 32` два раза. Для `POSTGRES_PASSWORD` в локальной конфигурации используйте URL-безопасные символы, поскольку Compose вставляет пароль в `DATABASE_URL` без URL-кодирования. Сайт открыт на http://localhost:3000, backend на http://localhost:8000 (оба порта привязаны к `127.0.0.1`). Результат обоих health-запросов: `{"status":"ok"}`. Команда `app:create-admin` поддерживает два позиционных аргумента: `login` и `password` (`make create-admin LOGIN=admin PASSWORD=...` или `docker compose exec backend php bin/console app:create-admin LOGIN PASSWORD`). Имя пользователя при таком запуске — «Администратор». Цель `make create-admin` сначала обновляет backend-контейнер, затем вызывает команду. Без аргументов команда запрашивает логин, имя и пароль интерактивно. Пароль в аргументе может остаться в истории оболочки и быть виден в списке процессов; для ручного запуска безопаснее интерактивный режим.

PostgreSQL хранит timestamps в UTC. Контейнеры используют UTC; `APP_TIMEZONE=Europe/Moscow` служит только для вычисления даты открытия дней. База хранится в томе `postgres_data`, файлы — в отдельном томе `uploads`, примонтированном к `/uploads` backend. `docker compose down` сохраняет оба тома; `down -v` удаляет их.

После изменения зависимостей или исходников пересоберите образы: `make up`. Логи: `make logs`. Миграции после обновления: `make migrations`. Остальные цели: `make help`.

Если Docker на занятом хосте сообщает `all predefined address pools have been fully subnetted`, создайте локальный `compose.override.yaml` со свободной подсетью для сети `default`. Этот файл игнорируется Git; в текущей рабочей среде использована `10.91.240.0/24`.

Вход доступен на `/login`. После входа пользователь попадает в `/cabinet`, администратор — в `/admin`; главная страница перенаправляет в доступный раздел. Выход находится в шапке. Symfony принимает `POST /api/v1/auth/login` с `{ "login": "...", "password": "..." }`, выдаёт bearer token на 12 часов, а `POST /api/v1/auth/logout` отзывает его. Защищённые запросы передают `Authorization: Bearer <token>`. В админке `/admin/users` доступны поиск, создание, изменение и деактивация пользователей. Деактивация прекращает доступ и сохраняет историю. Кабинет пока содержит страницу-заготовку для следующих задач.

API библиотеки заданий: `/api/v1/admin/tasks`. Оно поддерживает создание, редактирование, поиск, фильтры и деактивацию; `POST /api/v1/admin/tasks/{uuid}/image` загружает изображение. Подробности запросов и ограничений: [контракт API](docs/api-contract.md). В админке `/admin/tasks` доступны список, поиск и фильтр по статусу, создание и редактирование с загрузкой изображения. Предпросмотр задания использует общий компонент Markdown и KaTeX для будущей пользовательской страницы дня; HTML из Markdown не обрабатывается.

Библиотека игрушек доступна в админке на `/admin/ornaments`: поиск, создание, редактирование, загрузка SVG/PNG/WebP, предпросмотр и деактивация. Изображения сохраняются в томе `uploads`; неактивные игрушки остаются доступными по прежним URL. Новая игрушка становится активной после успешной загрузки изображения. Формат API описан в [контракте](docs/api-contract.md).
