# Advent Calendar — Project Specification for Coding Agent

## 1. Project overview

**Working name:** Advent Calendar

Веб-приложение — математический адвент-календарь к Новому году.

Пользователь авторизуется по логину/паролю и получает персональный адвент-календарь на декабрь. С 1 по 31 декабря ежедневно открывается новое математическое задание. Пропущенный день можно выполнить в течение двух следующих календарных дней; затем незавершённое задание остаётся в календаре со статусом `expired`.

За выполнение каждого задания пользователь получает новогоднюю игрушку. Полученную игрушку можно самостоятельно разместить на своей виртуальной ёлке. На ёлке предусмотрено 31 место (slot), поэтому к концу календаря пользователь может собрать полностью украшенную персональную ёлку.

Администратор управляет пользователями, заданиями, игрушками и шаблонами адвент-календарей. Шаблон представляет собой набор из 31 задания, распределённых по дням декабря. Шаблон назначается конкретному пользователю через экземпляр календаря.

Главная идея архитектуры: отдельно хранить:
1. библиотеку заданий;
2. шаблоны календарей;
3. назначенные пользователям календари;
4. прогресс прохождения;
5. полученные игрушки;
6. индивидуальное состояние ёлки пользователя.

---

## 2. Technology stack

### Frontend

- Next.js 15
- App Router
- TypeScript
- React
- Astryx Design System ([astryx.atmeta.com](https://astryx.atmeta.com/)) для UI-компонентов и темизации
- TanStack Query v5
- React Hook Form
- Zod
- SCSS Modules
- Tabler Icons
- Motion (`motion/react`) для UI-анимаций
- SVG для ёлки и интерактивных элементов
- Markdown renderer для текстов заданий
- KaTeX / remark-math / rehype-katex для математических формул

### Authentication

- NextAuth v5
- Credentials provider
- login/password authentication

### Backend

- PHP 8.3+
- Symfony 7
- Doctrine ORM
- PostgreSQL 16
- Symfony Validator
- Symfony Serializer
- Symfony Security
- REST JSON API

### Deploy
 - Docker
 - docker-compose
 
### Files

На MVP:

- локальное хранилище `/uploads`

Архитектура загрузки файлов должна позволять в будущем заменить filesystem на:

- S3
- MinIO

без изменения доменной модели.

---

# 3. Roles

## ROLE_USER

Обычный пользователь.

Может:

- авторизоваться;
- просматривать свой календарь;
- открывать доступные задания;
- выполнять задания;
- получать игрушки;
- просматривать полученные игрушки;
- размещать игрушки на ёлке;
- перемещать уже размещённые игрушки между свободными слотами;
- смотреть итоговую ёлку.

Не может:

- видеть календари других пользователей;
- получать доступ к будущим заданиям;
- использовать чужие игрушки;
- занимать один slot несколькими игрушками.

## ROLE_ADMIN

Администратор.

Может:

- управлять пользователями;
- создавать/редактировать задания;
- создавать/редактировать игрушки;
- создавать шаблоны календарей;
- распределять задания по дням шаблона;
- дублировать шаблоны;
- назначать шаблон пользователю;
- смотреть прогресс пользователя;
- смотреть ёлку пользователя.

---

# 4. Core domain model

Предполагаемые основные сущности:

1. User
2. Task
3. AdventTemplate
4. AdventTemplateTask
5. AdventCalendar
6. AdventDayProgress
7. Ornament
8. UserOrnament

Отдельная сущность Tree на MVP не требуется.

Состояние ёлки определяется набором `UserOrnament.treeSlot`.

Геометрические координаты слотов хранятся на frontend, а не в БД.

---

# 5. Entity: User

Основная сущность пользователя.

Suggested fields:

```text
id
uuid
login
email nullable
password
name
roles
isActive
isTestUser
createdAt
updatedAt
```

Constraints:

- `uuid` unique
- `login` unique
- password хранится только в hash
- деактивированный пользователь не может авторизоваться

Relations:

```text
User
  1:N AdventCalendar
```

---

# 6. Entity: Task

Библиотека математических заданий.

Suggested fields:

```text
id
uuid
title
content
imagePath nullable
answerType
correctAnswer nullable
solution nullable
isActive
createdAt
updatedAt
```

`content` хранится в Markdown.

Это позволяет использовать:

- обычный текст;
- списки;
- математические формулы;
- смешанный контент.

Пример:

```md
Вычислите значение выражения:

$$
\frac{3}{4} + \frac{5}{8}
$$
```

## AnswerType

На первой версии предусмотреть enum:

```text
NONE
NUMBER
TEXT
MULTIPLE_CHOICE
```

Однако MVP допускает использование только `NONE`.

При `NONE` пользователь самостоятельно решает задачу и нажимает кнопку:

> Задание выполнено

Архитектура должна позволять позже включить автоматическую проверку ответа.

`solution` — необязательное решение/объяснение, которое потенциально можно показать после выполнения.

---

# 7. Entity: AdventTemplate

Шаблон адвент-календаря.

Примеры:

```text
7 класс — базовый
7 класс — сложный
Новогоднее повторение 6 класса
Подготовка к ОГЭ
```

Fields:

```text
id
uuid
name
description nullable
year nullable
isActive
createdAt
updatedAt
```

Relation:

```text
AdventTemplate
  1:N AdventTemplateTask
```

Шаблон не является пользовательским календарём.

Он является только reusable-конфигурацией.

---

# 8. Entity: AdventTemplateTask

Связывает день календаря с заданием.

Fields:

```text
id
template
task
day
createdAt
```

Constraints:

```text
day >= 1
day <= 31

UNIQUE(template_id, day)
```

Пример:

```text
Template "7 класс — базовый"

day 1  -> Task #52
day 2  -> Task #93
day 3  -> Task #17
...
day 31 -> Task #112
```

Один Task может использоваться в нескольких шаблонах.

---

# 9. Entity: AdventCalendar

Конкретный экземпляр календаря конкретного пользователя.

Fields:

```text
id
uuid
user
template
year
startedAt nullable
createdAt
updatedAt
```

Relations:

```text
User
  1:N AdventCalendar

AdventTemplate
  1:N AdventCalendar
```

Пример:

```text
User: Иван
Year: 2026
Template: "7 класс — базовый"
```

Нельзя использовать прямую связь `User -> AdventTemplate` вместо AdventCalendar.

Причина: пользователь должен иметь независимую историю прохождения календарей за разные годы.

Recommended constraint:

```text
UNIQUE(user_id, year)
```

если бизнес-правило предполагает максимум один календарь пользователя в год.

---

# 10. Snapshot strategy

Важно учитывать изменение шаблонов после назначения пользователю.

Необходимо избежать ситуации:

1. пользователь начал календарь;
2. выполнил несколько заданий;
3. администратор поменял задания в шаблоне;
4. календарь пользователя неожиданно изменился.

Предпочтительная стратегия:

при назначении `AdventTemplate` пользователю создать `AdventCalendar` и snapshot 31 задания в `AdventDayProgress`.

Таким образом `AdventDayProgress.task` фиксируется при создании календаря.

Изменения исходного шаблона после назначения не должны менять уже созданный пользовательский календарь.

---

# 11. Entity: AdventDayProgress

Состояние конкретного дня конкретного пользовательского календаря.

Fields:

```text
id
calendar
day
task
openedAt nullable
completedAt nullable
createdAt
updatedAt
```

Constraints:

```text
UNIQUE(calendar_id, day)
```

Отдельный status хранить необязательно.

Status вычисляется:

```text
COMPLETED:
    completedAt != null

AVAILABLE:
    currentDate >= December(day, calendar.year)
    AND completedAt == null

LOCKED:
    currentDate < December(day, calendar.year)
```

При первом открытии задания:

```text
openedAt = now()
```

если значение ещё отсутствует.

---

# 12. Calendar availability rules

Для календаря `year = 2026`:

```text
01.12.2026 -> доступен day 1
02.12.2026 -> доступны day 1..2
03.12.2026 -> доступны day 1..3
04.12.2026 -> day 1 expired; доступны day 2..4
...
31.12.2026 -> доступны незавершённые day 29..31
01.01.2027 -> доступны незавершённые day 30..31
02.01.2027 -> доступен незавершённый day 31
03.01.2027 -> все незавершённые дни expired
```

Незавершённый день доступен в дату открытия и следующие два календарных дня в `APP_TIMEZONE`. После этого он получает статус `expired` и не может быть открыт или выполнен. Уже выполненный день остаётся `completed` и доступен для просмотра.

Например 10 декабря пользователь выполнил:

```text
1
2
3
5
7
```

Состояние:

```text
01 ✓
02 ✓
03 ✓
04 expired
05 ✓
06 expired
07 ✓
08 available
09 available
10 available

11 locked
12 locked
...
31 locked
```

После 31 декабря дни 30 и 31 ещё доступны до конца своего трёхдневного окна. История календаря и выполненные задания сохраняются.

Дата доступности обязательно проверяется backend.

Frontend не является источником истины для блокировки дней.

Для тестирования администратор может включить `User.isTestUser`. Тогда все дни назначенного этому пользователю календаря доступны независимо от даты; проверка остаётся на backend, а часы браузера ни на что не влияют. После выключения флага незавершённые дни снова подчиняются обычному правилу даты. Завершённые дни и награды сохраняются.

---

# 13. Entity: Ornament

Справочник доступных игрушек.

Fields:

```text
id
uuid
name
imagePath
rarity nullable
weight nullable
isActive
createdAt
updatedAt
```

Примеры:

```text
Красный шар
Снеговик
Пряник
Колокольчик
Звёздочка
Олень
Снежинка
Конфета
Подарок
```

Игрушек может быть больше 31.

Например:

```text
50–100 разных Ornament
```

Это позволит пользователям получать разные наборы и создавать визуально разные ёлки.

---

# 14. Ornament reward strategy

При завершении задания пользователь получает одну игрушку.

Backend отвечает за выбор и выдачу награды.

MVP strategy:

- случайная активная игрушка;
- желательно не выдавать пользователю одинаковую игрушку повторно, пока есть неполученные варианты.

В будущем можно использовать:

```text
rarity
weight
collections
special ornaments
```

Пример rarity:

```text
COMMON
RARE
EPIC
```

Однако rarity не является обязательной частью MVP.

---

# 15. Entity: UserOrnament

Игрушка, полученная пользователем.

Fields:

```text
id
uuid
calendar
ornament
dayProgress
treeSlot nullable
receivedAt
placedAt nullable
updatedAt
```

Relations:

```text
AdventCalendar
  1:N UserOrnament

Ornament
  1:N UserOrnament

AdventDayProgress
  1:1 UserOrnament
```

Recommended constraints:

```text
UNIQUE(day_progress_id)
UNIQUE(calendar_id, tree_slot) WHERE tree_slot IS NOT NULL
```

Логически:

- за одно выполненное задание можно получить только одну награду;
- один slot может содержать только одну игрушку.

---

# 16. Task completion transaction

`complete task` должен быть атомарной backend-операцией.

Алгоритм:

```text
BEGIN TRANSACTION

1. Найти AdventCalendar пользователя.
2. Найти AdventDayProgress.
3. Проверить доступность дня.
4. Если completedAt уже установлен:
       вернуть существующий результат без второй награды.
5. Установить completedAt.
6. Выбрать Ornament.
7. Создать UserOrnament.
8. COMMIT.

RETURN progress + reward.
```

Endpoint должен быть idempotent с точки зрения награды.

Повторный запрос не должен создавать вторую игрушку.

---

# 17. Tree model

На ёлке имеется ровно:

```text
31 slots
```

Каждый slot имеет frontend-конфигурацию:

```ts
export type TreeSlot = {
    id: number;
    x: number;
    y: number;
    scale?: number;
    rotation?: number;
};
```

Пример:

```ts
export const TREE_SLOTS: TreeSlot[] = [
    { id: 1, x: 50, y: 16, scale: 0.75 },
    { id: 2, x: 38, y: 24, scale: 0.9 },
    { id: 3, x: 62, y: 25, scale: 0.85 },
];
```

Координаты желательно задавать относительно SVG viewBox / процентов, чтобы ёлка была responsive.

Backend хранит только:

```text
treeSlot = 1..31
```

Backend НЕ хранит:

```text
x
y
rotation
scale
```

Это presentation-level configuration.

Так дизайн ёлки можно менять без миграции БД.

---

# 18. Placing ornament

Flow:

```text
User completes task
        ↓
Receives ornament
        ↓
Ornament appears in inventory
        ↓
User selects ornament
        ↓
Free tree slots are highlighted
        ↓
User selects slot
        ↓
Frontend sends placement request
        ↓
Backend validates
        ↓
treeSlot saved
        ↓
Placement animation
```

Validation:

```text
ornament belongs to current user
ornament belongs to current calendar
slot >= 1 && slot <= 31
slot is free
```

---

# 19. Moving ornament

Пользователь должен иметь возможность изменить решение.

Example:

```http
POST /api/v1/tree/ornaments/{uuid}/place
```

Body:

```json
{
  "slot": 17
}
```

Если игрушка уже размещена, endpoint может использоваться для перемещения.

Backend должен проверить, что новый slot свободен.

Optional endpoint:

```http
DELETE /api/v1/tree/ornaments/{uuid}/placement
```

для возвращения игрушки в inventory.

---

# 20. Final reward

После выполнения всех 31 заданий календарь считается завершённым.

Отдельную обычную Ornament для финальной звезды делать не обязательно.

Frontend получает:

```text
completedDays === 31
```

и показывает специальную финальную звезду на макушке.

После последнего задания:

```text
Календарь завершён!
```

Можно добавить отдельную праздничную animation sequence.

В будущем можно добавить:

```text
completedAt
finalReward
achievement
certificate
```

---

# 21. User frontend

Основные маршруты:

```text
/login

/cabinet
/cabinet/day/[day]
/cabinet/tree
```

---

# 22. Cabinet dashboard

Route:

```text
/cabinet
```

Показывает:

- имя пользователя;
- текущий календарь;
- количество выполненных заданий;
- количество доступных заданий;
- количество полученных игрушек;
- календарную сетку 1–31;
- CTA на ёлку.

Example:

```text
До Нового года осталось 20 дней

Выполнено:
8 / 10 доступных заданий

[ 1✓ ][ 2✓ ][ 3✓ ][ 4✓ ][ 5✓ ][ 6✓ ][ 7✓ ]
[ 8✓ ][ 9● ][10● ][11🔒][12🔒][13🔒][14🔒]
...
```

States:

```text
completed
available
locked
expired
```

---

# 23. Task page

Route:

```text
/cabinet/day/[day]
```

Displays:

- номер дня;
- дата;
- title;
- Markdown content;
- математические формулы;
- optional image;
- completion control.

MVP button:

```text
Задание выполнено
```

После успешного запроса:

1. показать reward modal;
2. показать полученную игрушку;
3. предложить перейти к ёлке.

Example:

```text
Задание выполнено!

Ты получил новую игрушку:

[Пряничный человечек]

[Повесить на ёлку]
```

---

# 24. Tree page

Route:

```text
/cabinet/tree
```

Это главный визуальный экран проекта.

Composition:

```text
background
snow
tree SVG
garland
31 interactive slots
placed ornaments
star
inventory
```

Desktop:

```text
┌─────────────────────────────────────────────┐
│                                             │
│              TREE                           │
│                                             │
│                       Inventory             │
│                       [ornament]             │
│                       [ornament]             │
│                       [ornament]             │
│                                             │
└─────────────────────────────────────────────┘
```

Mobile:

```text
TREE

↓ scroll / drawer

Inventory
```

---

# 25. Tree graphics technology

Preferred implementation:

```text
SVG + React + Motion + CSS
```

Не использовать без необходимости:

- Three.js
- WebGL
- Canvas
- PixiJS

Причина:

- сцена простая;
- всего 31 интерактивная позиция;
- SVG responsive;
- легко обрабатывать click/hover;
- легко позиционировать ornaments;
- SVG хорошо масштабируется;
- проще поддерживать;
- проще делать мобильную версию.

Ёлка может быть одним SVG background/component.

Игрушки:

```text
SVG preferred
PNG/WebP allowed
```

---

# 26. Animations

Использовать Motion.

Required basic animations:

### Reward

```text
scale 0 -> 1
rotate -15 -> 0
opacity 0 -> 1
```

### Placement

Игрушка визуально перемещается из inventory к выбранному slot.

### Hanging ornament

После размещения допустимо лёгкое покачивание.

Не использовать тяжёлые постоянные анимации для всех 31 объектов одновременно.

### Garland

Медленное изменение opacity отдельных лампочек.

### Snow

Лёгкая декоративная анимация.

Не создавать сотни DOM-элементов.

### Final completion

После 31-го задания:

- праздничный эффект;
- появление звезды;
- короткая анимация ёлки/гирлянды.

---

# 27. Admin frontend

Routes:

```text
/admin
/admin/users
/admin/users/[uuid]

/admin/tasks
/admin/tasks/create
/admin/tasks/[uuid]

/admin/templates
/admin/templates/create
/admin/templates/[uuid]

/admin/ornaments
/admin/ornaments/create
/admin/ornaments/[uuid]
```

---

# 28. Admin — users

List:

```text
Name
Login
Calendar
Progress
Status
```

User page:

```text
User information

Current Advent Calendar
Template
Year

Progress:
18 / 31

[View calendar]
[View tree]
```

Admin can assign calendar/template.

---

# 29. Admin — tasks

Task library.

Features:

- create;
- edit;
- deactivate;
- preview;
- search;
- filter.

Editor:

```text
Title

Markdown content

Image upload

Answer type

Correct answer

Solution

[Preview]
[Save]
```

Preview should render exactly as task is rendered for user.

---

# 30. Admin — template editor

Template editor should display all 31 days.

Example:

```text
Template: 7 класс — базовый

01 декабря  [Task #51] [Change]
02 декабря  [Task #32] [Change]
03 декабря  [+ Select task]
...
31 декабря  [Task #92] [Change]
```

Features:

- select task;
- replace task;
- clear task;
- preview task;
- duplicate template;
- validate that all 31 days are configured.

A template should only be assignable when all required days are populated.

---

# 31. Duplicate template

Important admin feature.

Flow:

```text
7 класс — базовый
        ↓ Duplicate
7 класс — базовый (copy)
```

All `AdventTemplateTask` entries are copied.

Admin can then replace only selected tasks.

This supports creation of personalized variants without rebuilding all 31 days.

---

# 32. Admin — ornaments

Features:

- create ornament;
- upload SVG/PNG/WebP;
- edit name;
- enable/disable;
- optional rarity;
- preview.

The frontend should not trust arbitrary SVG markup returned as HTML.

Prefer serving SVG as an image resource instead of injecting unsanitized SVG into DOM.

---

# 33. API proposal

Prefix:

```text
/api/v1
```

## User calendar

```http
GET /api/v1/calendar
```

Returns current calendar summary.

Example:

```json
{
  "uuid": "...",
  "year": 2026,
  "completedDays": 8,
  "availableDays": 10,
  "totalDays": 31,
  "days": []
}
```

---

## Day

```http
GET /api/v1/calendar/days/{day}
```

Backend validates availability.

Future locked day:

```http
403 Forbidden
```

or domain-specific error:

```json
{
  "code": "ADVENT_DAY_LOCKED"
}
```

---

## Complete

```http
POST /api/v1/calendar/days/{day}/complete
```

Response:

```json
{
  "completed": true,
  "completedAt": "2026-12-09T14:52:00+03:00",
  "reward": {
    "uuid": "...",
    "name": "Пряничный человечек",
    "imageUrl": "/uploads/ornaments/gingerbread.svg"
  }
}
```

Repeated call must NOT generate another reward.

---

## Tree

```http
GET /api/v1/tree
```

Example response:

```json
{
  "completed": false,
  "ornaments": [
    {
      "uuid": "...",
      "name": "Красный шар",
      "imageUrl": "...",
      "treeSlot": 5
    },
    {
      "uuid": "...",
      "name": "Снеговик",
      "imageUrl": "...",
      "treeSlot": null
    }
  ]
}
```

---

## Place/move ornament

```http
POST /api/v1/tree/ornaments/{uuid}/place
```

Body:

```json
{
  "slot": 17
}
```

---

## Remove ornament from tree

Optional:

```http
DELETE /api/v1/tree/ornaments/{uuid}/placement
```

---

# 34. Admin API

Suggested endpoints:

```http
GET    /api/v1/admin/users
GET    /api/v1/admin/users/{uuid}

GET    /api/v1/admin/tasks
POST   /api/v1/admin/tasks
GET    /api/v1/admin/tasks/{uuid}
PATCH  /api/v1/admin/tasks/{uuid}

GET    /api/v1/admin/templates
POST   /api/v1/admin/templates
GET    /api/v1/admin/templates/{uuid}
PATCH  /api/v1/admin/templates/{uuid}
POST   /api/v1/admin/templates/{uuid}/duplicate

PUT    /api/v1/admin/templates/{uuid}/days/{day}

POST   /api/v1/admin/users/{uuid}/calendar

GET    /api/v1/admin/ornaments
POST   /api/v1/admin/ornaments
PATCH  /api/v1/admin/ornaments/{uuid}
```

Exact API shape can be adjusted during implementation, but domain boundaries should remain.

---

# 35. Security rules

Backend is the source of truth.

Never rely only on frontend checks.

Backend must validate:

- authenticated user;
- ownership of calendar;
- day availability;
- completion state;
- reward ownership;
- tree slot range;
- tree slot uniqueness;
- admin role for admin endpoints.

Never expose:

- password hash;
- correctAnswer before completion when automatic answers are implemented;
- another user's progress/tree.

---

# 36. Time handling

All dates in DB should use consistent timezone strategy.

Recommended:

```text
DB timestamps: UTC
API timestamps: ISO 8601
```

Calendar day availability must be calculated using configured application/business timezone.

Add configuration:

```env
APP_TIMEZONE=Europe/Moscow
```

Do not use browser local time as the source of truth for unlocking days.

This prevents users from unlocking future tasks by changing device time/timezone.

---

# 37. Concurrency

Important for:

```text
POST /complete
POST /place
```

Protect against duplicate requests.

Task completion should use transaction + uniqueness constraint.

Tree placement should also be protected by DB constraint so two simultaneous requests cannot occupy the same slot.

Application validation alone is not sufficient.

---

# 38. Recommended backend modules

Suggested Feature-Sliced/domain-oriented structure:

```text
src/
  User/

  Advent/
    Template/
    Calendar/
    Progress/

  Task/

  Ornament/

  Shared/
```

Each domain may contain:

```text
Controller/
DTO/
Entity/
Repository/
Service/
Enum/
Exception/
```

Do not put all business logic into controllers.

Important services:

```text
AssignAdventCalendarService
CompleteAdventDayService
OrnamentRewardService
PlaceOrnamentService
DuplicateAdventTemplateService
```

---

# 39. Recommended frontend structure

Feature-oriented/FSD-style:

```text
src/
  app/

  entities/
    user/
    task/
    advent-calendar/
    ornament/

  features/
    auth/
    complete-task/
    place-ornament/
    move-ornament/
    assign-calendar/

  widgets/
    advent-calendar/
    christmas-tree/
    ornament-inventory/
    task-card/

  shared/
    api/
    ui/
    config/
    lib/
```

Avoid putting business logic directly into page components.

---

# 40. Frontend tree component

Suggested component structure:

```text
ChristmasTree
├── TreeBackground
├── Garland
├── TreeSlots
│   └── TreeSlot
├── PlacedOrnaments
│   └── TreeOrnament
├── FinalStar
├── Snow
└── OrnamentInventory
```

State from backend:

```text
UserOrnament[]
```

Static frontend configuration:

```text
TREE_SLOTS
```

Rendering:

```text
treeSlot === null
    -> inventory

treeSlot !== null
    -> render at TREE_SLOTS[treeSlot]
```

---

# 41. Responsive design

Primary targets:

```text
mobile
tablet
desktop
```

Tree should use a stable SVG `viewBox`.

Avoid absolute pixel coordinates tied to one screen resolution.

Coordinates should be relative to SVG space.

Touch interaction is mandatory.

Do not implement placement only via drag-and-drop.

Required interaction:

```text
select ornament
        ↓
select slot
```

Drag-and-drop may be added as an optional desktop enhancement.

This is important because click/tap placement works reliably on phones.

---

# 42. UX details

## Locked day

Show:

```text
Откроется 15 декабря
```

## Missed day

Do NOT show it as failure.

Show it as available through the end of its two additional calendar days. After that show `expired` without task content.

## Completed

Clearly mark:

```text
✓ Выполнено
```

## Unplaced reward

After login/dashboard, if there are unplaced ornaments:

```text
У тебя есть 3 игрушки, которые ещё не висят на ёлке.
```

CTA:

```text
Украсить ёлку
```

---

# 43. Error codes

Prefer stable domain error codes.

Examples:

```text
ADVENT_CALENDAR_NOT_FOUND
ADVENT_DAY_NOT_FOUND
ADVENT_DAY_LOCKED
ADVENT_DAY_ALREADY_COMPLETED
ORNAMENT_NOT_FOUND
ORNAMENT_NOT_OWNED
TREE_SLOT_INVALID
TREE_SLOT_OCCUPIED
TEMPLATE_INCOMPLETE
CALENDAR_ALREADY_ASSIGNED
```

Frontend should react to `code`, not parse human-readable error messages.

---

# 44. MVP scope

First production MVP should include:

- login/password auth;
- ROLE_USER;
- ROLE_ADMIN;
- users CRUD;
- tasks CRUD;
- image upload;
- Markdown + math rendering;
- ornaments CRUD;
- templates CRUD;
- 31 template days;
- template duplication;
- assign template to user;
- snapshot calendar generation;
- date-based day unlocking;
- task completion;
- one reward per completed task;
- tree inventory;
- 31 tree slots;
- ornament placement;
- ornament moving;
- responsive tree;
- basic animations;
- progress display;
- admin user progress view.

---

# 45. Features NOT required for MVP

Do not implement prematurely:

- chat;
- AI task generation;
- complex answer checking;
- teacher/student hierarchy;
- multiplayer;
- comments;
- likes;
- leaderboard;
- WebSockets;
- Three.js;
- PixiJS;
- complex gamification;
- payment system;
- push notifications;
- rarity economy.

Architecture should not deliberately prevent future expansion, but do not over-engineer these features now.

---

# 46. Future roadmap

Potential V2:

### Automatic answers

```text
NUMBER
TEXT
MULTIPLE_CHOICE
```

### Attempts

```text
attemptCount
maxAttempts
```

### Hints

Task hints after failed attempts.

### Ornament rarity

```text
COMMON
RARE
EPIC
LEGENDARY
```

### Ornament collections

Example:

```text
Сладости
Снежинки
Животные
Классические игрушки
```

### Different tree designs

```text
classic
snowy
minimal
gold
```

### Achievements

```text
7 days completed
14 days completed
no missed days
31/31
```

### Final image

Generate/export final decorated tree as image.

### Share

Public read-only link to final tree.

### Previous years

User can browse:

```text
2026
2027
2028
```

without losing previous trees.

---

# 47. Important architectural decisions

The coding agent should preserve these decisions unless explicitly instructed otherwise.

## Decision 1

`AdventTemplate` and `AdventCalendar` are different concepts.

Template = reusable configuration.

Calendar = assigned user instance.

## Decision 2

Assigned calendars use a task snapshot.

Changing a template must not silently change an already assigned calendar.

## Decision 3

Task completion and reward creation are one atomic operation.

## Decision 4

One completed day = maximum one reward.

## Decision 5

Tree slot coordinates are frontend configuration, not database data.

## Decision 6

Backend stores only slot number.

## Decision 7

User chooses where to place an ornament.

## Decision 8

Missing a day leaves two additional calendar days to complete it. On the fourth date an incomplete day expires.

## Decision 9

Backend determines whether a day is unlocked.

## Decision 10

Changing client clock must not unlock tasks.

## Decision 11

SVG + React is the primary tree rendering technology.

## Decision 12

Tap/click placement is the primary interaction. Drag-and-drop is optional.

---

# 48. Suggested implementation order

## Phase 1 — foundation

1. Initialize backend.
2. Initialize frontend.
3. PostgreSQL.
4. Authentication.
5. User entity.
6. roles/security.
7. API client.
8. admin layout.
9. cabinet layout.

## Phase 2 — content

1. Task entity.
2. task CRUD.
3. uploads.
4. Markdown rendering.
5. KaTeX rendering.
6. task preview.
7. Ornament entity.
8. ornament CRUD.

## Phase 3 — templates

1. AdventTemplate.
2. AdventTemplateTask.
3. template CRUD.
4. 31-day editor.
5. task selector.
6. validation.
7. duplication.

## Phase 4 — user calendars

1. AdventCalendar.
2. AdventDayProgress.
3. assignment service.
4. task snapshot creation.
5. calendar summary API.
6. date availability.
7. cabinet calendar UI.

## Phase 5 — completion/rewards

1. CompleteAdventDayService.
2. transaction/idempotency.
3. OrnamentRewardService.
4. UserOrnament.
5. reward modal.
6. inventory.

## Phase 6 — tree

1. tree SVG.
2. define 31 slots.
3. TreeSlot component.
4. render placed ornaments.
5. inventory.
6. place API.
7. move API.
8. animations.
9. responsive/mobile interaction.

## Phase 7 — polish

1. snow.
2. garland.
3. reward animation.
4. final star.
5. completion animation.
6. empty/error/loading states.
7. admin progress screen.

## Phase 8 — tests

Backend:

- day availability;
- template snapshot;
- duplicate completion;
- duplicate reward prevention;
- slot collision;
- foreign ornament placement;
- future task access;
- template duplication.

Frontend:

- calendar states;
- tree rendering;
- inventory;
- placement;
- responsive behavior.

---

# 49. Definition of Done for MVP

Project is MVP-ready when:

1. Admin can create a user.
2. Admin can create at least 31 tasks.
3. Admin can upload task images.
4. Admin can create ornaments.
5. Admin can create a template.
6. Admin can fill all 31 days.
7. Admin can duplicate a template.
8. Admin can assign a template to a user.
9. User can log in.
10. User sees only unlocked days.
11. User can open previously missed days during the two following calendar days; later incomplete days remain expired.
12. User cannot open a future day via direct API request.
13. User can complete an available task.
14. Exactly one ornament is issued.
15. Duplicate completion requests do not create duplicate rewards.
16. User sees unplaced ornaments.
17. User can select an ornament.
18. User can select one of 31 free tree slots.
19. Ornament remains in that slot after page reload.
20. User can move an ornament.
21. Two ornaments cannot occupy one slot.
22. User cannot manipulate another user's ornaments.
23. Tree works on mobile and desktop.
24. Completing all 31 tasks displays the final star/state.
25. Admin can inspect user's progress.

---

# 50. Agent instructions

When implementing this project:

- use strict TypeScript;
- avoid `any` unless unavoidable;
- use DTO validation on backend;
- keep controllers thin;
- put domain logic in services;
- use Doctrine migrations for schema changes;
- use DB constraints in addition to application validation;
- use transactions for completion/reward operations;
- keep API responses typed;
- use TanStack Query for server state;
- do not duplicate backend domain state unnecessarily in global frontend stores;
- use React Server Components where appropriate, but interactive tree/task flows are client components;
- do not trust client dates or permissions;
- preserve idempotency for mutation endpoints;
- optimize images and SVG assets;
- keep the tree implementation independent enough that its visual SVG can later be replaced without changing backend data;
- do not add dependencies without a concrete reason;
- do not introduce Three.js/Canvas/WebGL for the MVP;
- write tests for critical domain invariants.

Before implementing a feature, check whether it changes one of the architectural decisions in section 47. If it does, explicitly flag the conflict instead of silently changing the model.

---

# 51. Product principle

The tree is not merely a progress indicator.

It is the central reward/customization mechanic.

The desired loop is:

```text
Come back today
      ↓
Solve a math task
      ↓
Receive an ornament
      ↓
Choose where to hang it
      ↓
Watch your personal tree gradually fill
      ↓
Come back tomorrow
```

The finished tree should feel personal because different users can receive and position different ornaments.

The project should prioritize this simple loop over unnecessary complexity.
