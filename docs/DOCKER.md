# Docker: как это работает и как запустить проект

Инструкция написана так, чтобы ты мог поднять приложение с нуля, ни у кого ничего не спрашивая.
Все команды выполняются **из корня проекта** (`c:\Users\User\Desktop\nury-sowda\project`) в PowerShell.

---

## 1. Что вообще происходит

Docker поднимает не «приложение целиком», а **окружение** для него: веб-сервер, PHP и базу данных.
Сам код проекта в образ **не копируется** — он подключается с твоего диска через `volumes`
(«проброс папки»). Поэтому меняешь PHP-файл на хосте → изменение сразу видно в контейнере,
пересобирать ничего не нужно.

### Схема

```
Браузер
   │  http://localhost:8000
   ▼
┌─────────────────────┐
│  nginx (порт 80)    │  handyman_nginx
│  корень: public/    │
└─────────┬───────────┘
          │  всё, что *.php → fastcgi_pass php:9000
          ▼
┌─────────────────────┐        ┌──────────────────────┐
│  php-fpm 8.3        │───────▶│  mysql 8.0           │  handyman_db
│  handyman_php       │  db:3306│  порт наружу: 3316   │
└─────────────────────┘        └──────────────────────┘
          ▲
          │  (одноразовые запуски)
┌─────────────────────┐
│  artisan            │  контейнер, который выполняет одну команду и умирает
└─────────────────────┘

Общий том: ./  →  /var/www/laravel   (во всех PHP/nginx контейнерах)
Том данных БД: db_data → /var/lib/mysql  (переживает пересоздание контейнеров)
```

### Ключевая идея сети Docker

Контейнеры видят друг друга **по имени сервиса**, а не по `localhost`.
Поэтому в `.env` стоит `DB_HOST=db` — это имя сервиса из `docker-compose.yml`.
Порт при этом внутренний: `3306`, а не `3316`.

| Откуда подключаешься | Хост | Порт |
|---|---|---|
| Из PHP-контейнера (само приложение) | `db` | `3306` |
| С твоего компьютера (DBeaver, TablePlus, HeidiSQL) | `127.0.0.1` | `3316` |

---

## 2. Разбор файлов

### `docker-compose.yml` — описание сервисов

| Сервис | Образ / сборка | Имя контейнера | Зачем |
|---|---|---|---|
| `nginx` | `nginx:1.30-alpine` | `handyman_nginx` | Принимает HTTP на `localhost:8000`, отдаёт статику, PHP отдаёт в php-fpm |
| `php` | сборка из `docker/php/Dockerfile` | `handyman_php` | php-fpm 8.3, обрабатывает запросы. Порт 9000 наружу **не** торчит — он нужен только nginx |
| `db` | `mysql:8.0` | `handyman_db` | База. Данные в именованном томе `db_data` |
| `artisan` | та же сборка, что и `php` | — | Не сервис-демон. Его `entrypoint` — `php artisan`, поэтому запускается разово через `docker compose run` |

Проброс портов читается как `хост:контейнер`:
- `8000:80` → твой `localhost:8000` уходит в 80-й порт nginx.
- `3316:3306` → твой `localhost:3316` уходит в 3306 MySQL. Нестандартный 3316 взят,
  чтобы не конфликтовать с локально установленным MySQL, если он есть.

### `docker/php/Dockerfile`

```dockerfile
FROM php:8.3-fpm
WORKDIR /var/www/laravel
RUN docker-php-ext-install pdo pdo_mysql
```

Базовый php-fpm + расширения для работы с MySQL. Composer, git, node **внутри не установлены** —
см. раздел 3.

### `docker/nginx/conf.d/nginx.conf`

```nginx
root /var/www/laravel/public;              # корень — public/, а не корень проекта
try_files $uri $uri/ /index.php?$query_string;   # фронт-контроллер Laravel
fastcgi_pass php:9000;                     # php — это имя сервиса из compose
```

Файл монтируется в контейнер как `:ro` (read-only). После правки этого файла нужен
**рестарт nginx**, сборка не требуется.

### `docker/db.env` — креды MySQL

```
MYSQL_DATABASE=handyman_nury
MYSQL_USER=admin
MYSQL_PASSWORD=secret
MYSQL_ROOT_PASSWORD=password
```

Эти значения MySQL применяет **только при самом первом создании тома `db_data`**.
Поменял пароль в файле, а он не подхватился — значит том уже создан (см. раздел 8).

### `docker/composer/Dockerfile`

Существует, но **в `docker-compose.yml` не подключён** — сервиса `composer` нет.
Зависимости ставим иначе, см. ниже.

---

## 3. Что НЕ живёт в Docker

Держи это в голове, иначе будешь искать несуществующие команды:

| Что | Где запускать | Почему |
|---|---|---|
| `composer install` | на хосте (у тебя PHP 8.3 и Composer 2.10 уже стоят) | в php-контейнере нет composer/git/unzip |
| `npm install`, `npm run dev/build` | на хосте (Node 22) | node-сервиса в compose нет |
| Reverb (WebSocket, порт 8880) | на хосте | порт 8880 в compose не проброшен |
| `socket-server/` (Socket.IO шлюз OTP, порт 3000) | на хосте | не заведён в compose |
| Очередь `queue:work` | **в Docker** | нужен доступ к БД по хосту `db` |

То есть схема гибридная: **Docker даёт nginx + PHP + MySQL, остальное — на хосте.**

Приложение без фоновых процессов откроется, но не будет работать: конвертация картинок,
уведомления админам и отправка OTP идут через очередь, а живая карта и алерты о заказах — через Reverb.

---

## 4. Первый запуск с нуля

Выполняй по порядку.

### Шаг 1. Убедись, что Docker Desktop запущен

```powershell
docker compose version
```

Должна вывестись версия. Если ошибка — открой Docker Desktop и дождись статуса «Engine running».

### Шаг 2. Создай `.env`

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Затем **обязательно** приведи блок БД к докерным значениям (в `.env.example` по умолчанию sqlite):

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=handyman_nury
DB_USERNAME=admin
DB_PASSWORD=secret
```

> `DB_HOST=db`, а не `127.0.0.1` — это критично. С `127.0.0.1` PHP-контейнер будет
> искать базу внутри самого себя и упадёт с «Connection refused».

### Шаг 3. Поставь зависимости (на хосте)

```powershell
composer install
npm install
```

### Шаг 4. Собери фронтенд

```powershell
npm run build
```

Или на время разработки, в отдельном окне терминала, оставь работать:

```powershell
npm run dev
```

### Шаг 5. Подними контейнеры

```powershell
docker compose up -d --build
```

- `--build` — собрать образ php из Dockerfile (нужен только первый раз или после правки Dockerfile).
- `-d` — в фоне, терминал не занимается.

Проверь:

```powershell
docker compose ps
```

Ожидаемо: `handyman_nginx`, `handyman_php`, `handyman_db` со статусом `Up`.

> Сервис `artisan` в списке не появится — это нормально. Он стартует, печатает
> справку `php artisan` и завершается. Так и задумано (см. раздел 9 — это стоит починить).

### Шаг 6. Сгенерируй ключ приложения

```powershell
docker compose run --rm artisan key:generate
```

### Шаг 7. Прогони миграции и сидеры

```powershell
docker compose run --rm artisan migrate --seed
```

### Шаг 8. Симлинк на storage (для загруженных фото)

```powershell
docker compose run --rm artisan storage:link
```

### Шаг 9. Подними фоновые процессы

Каждую команду — в **отдельном окне терминала**, они работают постоянно.

```powershell
# Обработчик очередей — обязателен (картинки, уведомления, OTP)
docker compose run --rm artisan queue:work

# WebSocket-сервер — обязателен (живая карта, алерты о заказах)
php artisan reverb:start

# Socket.IO шлюз для SMS — опционально в разработке
cd socket-server ; npm install ; npm start
```

### Шаг 10. Открой приложение

http://localhost:8000

Вход в админку: `admin@gmail.com` / `password` (создаётся в `DatabaseSeeder`).

---

## 5. Ежедневный запуск (когда всё уже настроено)

Четыре окна терминала:

```powershell
docker compose up -d                        # 1. окружение
npm run dev                                 # 2. фронтенд
docker compose run --rm artisan queue:work  # 3. очередь
php artisan reverb:start                    # 4. websocket
```

Остановить:

```powershell
docker compose stop       # остановить, данные и контейнеры на месте
docker compose down       # остановить и удалить контейнеры (БД в томе сохранится)
```

---

## 6. Миграции

Главное правило: **artisan запускается только внутри Docker**, потому что база доступна
по хосту `db`, которого на твоём компьютере не существует.

Шаблон команды:

```powershell
docker compose run --rm artisan <любая artisan-команда>
```

`--rm` = удалить одноразовый контейнер после выполнения, чтобы не копился мусор.

| Задача | Команда |
|---|---|
| Применить новые миграции | `docker compose run --rm artisan migrate` |
| Посмотреть статус миграций | `docker compose run --rm artisan migrate:status` |
| Откатить последнюю пачку | `docker compose run --rm artisan migrate:rollback` |
| Откатить N шагов | `docker compose run --rm artisan migrate:rollback --step=2` |
| Снести всё и накатить заново | `docker compose run --rm artisan migrate:fresh` |
| Снести, накатить и засеять | `docker compose run --rm artisan migrate:fresh --seed` |
| Создать новую миграцию | `docker compose run --rm artisan make:migration create_foo_table` |

> `migrate:fresh` **удаляет все данные**. На локалке это норм, но привыкай сначала
> смотреть `migrate:status`.

---

## 7. Сидеры

`DatabaseSeeder` создаёт админа (`admin@gmail.com` / `password`) и вызывает по цепочке:
`OblastSeeder` → `CitySeeder` → `CategorySeeder`.

Порядок важен: города ссылаются на области.
Отдельно доступен `SettingSeeder` (в общую цепочку не входит).

Демо-данные (мастера, клиенты, заказы) больше не сидируются: мастера заводятся заявкой
из мобильного приложения и подтверждаются в админке, тарифы подписок создаются там же.

| Задача | Команда |
|---|---|
| Запустить всё (`DatabaseSeeder`) | `docker compose run --rm artisan db:seed` |
| Запустить один сидер | `docker compose run --rm artisan db:seed --class=CategorySeeder` |
| Полный ресет базы + сидеры | `docker compose run --rm artisan migrate:fresh --seed` |
| Создать новый сидер | `docker compose run --rm artisan make:seeder FooSeeder` |

Если сидер падает на дублях — почти наверняка данные уже есть. Либо делай
`migrate:fresh --seed`, либо переписывай сидер на `updateOrCreate()`, как это сделано
для админа в `DatabaseSeeder`.

---

## 8. Шпаргалка команд

### Контейнеры

```powershell
docker compose up -d              # поднять
docker compose up -d --build      # поднять с пересборкой php-образа
docker compose ps                 # что запущено
docker compose stop               # остановить
docker compose down               # остановить + удалить контейнеры
docker compose restart nginx      # перезапустить один сервис
```

### Логи

```powershell
docker compose logs -f            # логи всех сервисов, поток
docker compose logs -f php        # только php
docker compose logs --tail=100 nginx
```

Логи самого Laravel — не тут, а в файле `storage/logs/laravel.log` на хосте.

### Зайти внутрь контейнера

```powershell
docker compose exec php sh        # shell внутри php-контейнера
docker compose exec db mysql -u admin -psecret handyman_nury   # MySQL-консоль
```

Выйти: `exit`.

### Частые artisan-команды

```powershell
docker compose run --rm artisan route:list
docker compose run --rm artisan optimize:clear     # сбросить все кеши
docker compose run --rm artisan config:clear
docker compose run --rm artisan queue:work         # обработчик очередей (Ctrl+C для выхода)
docker compose run --rm artisan tinker
```

### Тесты

Тесты гоняй **на хосте** — у тебя стоит PHP 8.3, а `phpunit.xml` использует отдельную
тестовую БД, не докерную:

```powershell
php artisan test --compact
php artisan test --compact --filter=OrderTest
```

---

## 9. Если что-то сломалось

### `localhost:8000` не открывается / ERR_CONNECTION_REFUSED

```powershell
docker compose ps          # nginx точно Up?
docker compose logs nginx
```

Если порт 8000 занят другим приложением — поменяй в `docker-compose.yml` левую часть:
`8001:80`, и подними заново.

### `SQLSTATE[HY000] [2002] Connection refused`

Три причины по частоте:
1. В `.env` стоит `DB_HOST=127.0.0.1` вместо `DB_HOST=db`.
2. Контейнер `db` ещё поднимается — MySQL стартует ~10–20 секунд. Подожди и повтори.
3. Не сброшен кеш конфига: `docker compose run --rm artisan config:clear`.

### `Access denied for user 'admin'`

Ты поменял значения в `docker/db.env`, но том `db_data` уже создан со старыми кредами.
MySQL применяет эти переменные только при первой инициализации. Полный ресет базы:

```powershell
docker compose down -v      # -v удаляет тома → БАЗА УДАЛЯЕТСЯ ПОЛНОСТЬЮ
docker compose up -d
docker compose run --rm artisan migrate --seed
```

### `Unable to locate file in Vite manifest`

Не собран фронт. `npm run build` или запусти `npm run dev`.

### 500-я ошибка, в логах `Permission denied` на `storage/`

```powershell
docker compose exec php chmod -R 777 storage bootstrap/cache
```

### Изменил `Dockerfile` — изменения не видны

Нужна пересборка образа:

```powershell
docker compose up -d --build
```

### Изменил `nginx.conf` — изменения не видны

```powershell
docker compose restart nginx
```

### Изменил PHP-код — нужна ли пересборка?

Нет. Код смонтирован через volume, обновляется мгновенно.
Если поменял `.env` или `config/*` — сбрось кеш:
`docker compose run --rm artisan optimize:clear`.

### Полная «переустановка» окружения

```powershell
docker compose down -v
docker compose up -d --build
docker compose run --rm artisan key:generate
docker compose run --rm artisan migrate --seed
docker compose run --rm artisan storage:link
```

---

## 10. Известные слабые места конфигурации

Сейчас всё работает, но эти вещи стоит поправить — знай о них:

1. **Сервис `artisan` стартует вместе со всеми.** При `docker compose up -d` он поднимается,
   печатает справку artisan и умирает. Лечится добавлением `profiles: ["cli"]` в его описание —
   тогда он будет подниматься только через `docker compose run`.
2. **У `php` нет `depends_on: db`.** Compose не гарантирует порядок старта, отсюда
   «Connection refused» на первых секундах. Правильно — `depends_on` с `condition: service_healthy`
   и `healthcheck` на сервисе `db`.
3. **В php-образе нет composer.** Из-за этого зависимости ставятся на хосте, и версия PHP на
   хосте должна совпадать с версией в контейнере. Сейчас совпадает (8.3), но это хрупко.
   `docker/composer/Dockerfile` написан, но не подключён.
4. **Нет `restart: unless-stopped`.** После перезагрузки компьютера контейнеры придётся
   поднимать руками.
5. **Нет сервиса для node/vite, Reverb и `socket-server`.** Фронт, WebSocket и Socket.IO-шлюз
   живут вне Docker, то есть окружение не самодостаточно. Порты 8880 (Reverb) и 3000 (шлюз)
   в compose не проброшены.
6. **`127.0.0.1` в `.env` ломается из контейнера.** `SMS_GATEWAY_URL=http://127.0.0.1:3000` и
   `REVERB_HOST=127.0.0.1` PHP читает **изнутри контейнера**, где `127.0.0.1` — это сам контейнер,
   а не твой компьютер. Если OTP не долетают до шлюза — меняй серверные адреса на
   `host.docker.internal`, оставляя `VITE_REVERB_HOST=127.0.0.1` для браузера (это разные
   переменные, их придётся расцепить).
7. **Пароли БД лежат в репозитории** (`docker/db.env`). Для локалки терпимо, для продакшена — нет.
