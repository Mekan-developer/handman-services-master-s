# UI polish — Master Handyman admin

Полировка без редизайна: структура, бренд и логика не меняются, меняются только классы Tailwind.
Эталон стиля — «новое поколение»: `Layouts/AdminLayout.vue`, `Pages/Dashboard.vue`, `Pages/Orders/Index.vue`, `Pages/Auth/Login.vue`.

---

## 1. Reference tokens (снято с new-gen файлов)

### Конфиг (`tailwind.config.js`)
| Ключ | Значение |
|---|---|
| `fontFamily.sans` | `Figtree` → будет `Manrope` (фаза 1) |
| `fontFamily.slab` | `"Roboto Slab"` — только wordmark, не трогаем |
| `brand-navy` / `-from` / `-to` | `#131c2e` / `#10172a` / `#1b2340` |
| `brand-green` / `brand-green-hover` | `#0c8f0a` / `#0a7a08` |
| `shadow-badge` | `0 4px 12px rgba(0,0,0,.3)` |
| `shadow-brand-cta` | `0 6px 16px rgba(12,143,10,.35)` |

### Поверхности
| Слой | Light | Dark |
|---|---|---|
| Фон страницы | `bg-slate-100` | `dark:bg-[#0b1220]` |
| Шапка | `bg-white border-b border-gray-200` | `dark:bg-[#0f1626] dark:border-white/[0.08]` |
| Карточка | `rounded-[14px] border border-gray-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]` | `dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]` |
| Разделитель внутри карточки | `border-[#eef1f5]` | `dark:border-white/[0.08]` |
| Dropdown | `rounded-[10px] border border-gray-200 bg-white shadow-lg` | `dark:border-white/[0.08] dark:bg-[#131b2e]` |
| Поле ввода | `bg-white border-[#dde3ec]` | `dark:bg-[#0d1424] dark:border-white/[0.16]` |

### Текст
| Роль | Классы |
|---|---|
| H1 страницы | `text-[28px] font-extrabold text-brand-navy dark:text-[#f1f5f9]` |
| Подзаголовок страницы | `mt-1.5 text-sm text-slate-500 dark:text-slate-400` |
| Заголовок карточки | `text-lg font-bold text-brand-navy dark:text-[#f1f5f9]` (Dashboard) |
| Основной | `text-slate-800 dark:text-[#f1f5f9]` (имена), `text-slate-700 dark:text-[#e2e8f0]` (ячейки) |
| Вторичный | `text-[12.5px] text-slate-400` (телефон под именем) |
| th | `whitespace-nowrap border-b border-gray-200 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-white/[0.08]` |
| Overline сайдбара | `text-[11px] font-bold uppercase tracking-[0.6px] text-white/[0.38]` |

### Поля, фокус
| Что | Классы |
|---|---|
| Input / select | `rounded-[10px] border border-[#dde3ec] bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424] dark:text-[#f1f5f9]` |
| Label (Login) | `mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300` |
| Ошибка (Login) | `mt-1.5 text-xs text-red-500` |
| Фокус кнопки (Login) | `focus:outline-none focus:ring-2 focus:ring-brand-green focus:ring-offset-2 dark:focus:ring-offset-slate-900` |

### Кнопки
| Вариант | Классы |
|---|---|
| Primary (Orders «Добавить») | `inline-flex items-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-3 text-sm font-semibold text-white shadow-brand-cta transition-colors hover:bg-brand-green-hover` |
| Secondary («Сбросить» в фильтрах) | `inline-flex items-center gap-1.5 rounded-[10px] border border-[#dde3ec] px-3.5 py-2.5 text-sm font-medium text-slate-500 hover:bg-slate-50 dark:border-white/[0.08] dark:text-slate-300 dark:hover:bg-white/[0.05]` |
| Icon (шапка) | `h-[38px] w-[38px] rounded-[10px] text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/[0.08] dark:hover:text-white` |
| Icon (таблица) | `inline-flex p-1.5 text-slate-400 hover:text-slate-700` / удалить `hover:text-red-600` |
| Segmented (активный) | `rounded-full bg-indigo-600 text-white text-[12.5px] font-bold` |

### Таблица и бейджи
| Что | Классы |
|---|---|
| td | `px-5 py-4 text-sm text-slate-700 dark:text-[#e2e8f0]` |
| tr | `border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-white/[0.08] dark:hover:bg-white/[0.03]` |
| Бейдж | `inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold` + `bg-{c}-100 text-{c}-800` + точка `h-1.5 w-1.5 rounded-full bg-{c}-500/600`. Без `ring`, **без dark-вариантов** (кроме cancelled) |

---

## 2. Расхождения: эталонные классы из задачи ↔ реальные файлы

По правилу «файлы важнее» беру значения из файлов. Пункты, помеченные **[решение]**, требуют подтверждения.

| # | Что | В задаче | В файлах | Что предлагаю |
|---|---|---|---|---|
| 1 | Цвет th | `text-slate-500` | `text-slate-400` | **[решение]** `slate-500`: `slate-400` на белом даёт ≈2.6:1, это ниже WCAG AA, а `slate-500` ≈4.8:1 |
| 2 | Трекинг H1 | `tracking-tight` | нет | **[решение]** добавить `tracking-tight` везде, включая new-gen: Manrope 28px extrabold без него выглядит рыхлым |
| 3 | Цвет H1 в dark | `dark:text-slate-100` | `dark:text-[#f1f5f9]` | одно и то же (`slate-100` = `#f1f5f9`), использую `dark:text-slate-100` |
| 4 | Бордер карточки | `border-slate-200`, dark `slate-700` | `border-gray-200`, dark `white/[0.08]` | dark: из файлов (`white/[0.08]`). Light: **[решение]** `slate-200` вместо `gray-200`, визуально идентично, зато одна серая палитра |
| 5 | Тень карточки | `shadow-sm` | `shadow-[0_1px_2px_rgba(15,23,42,0.04)]` | из файлов |
| 6 | Dark-поверхность карточки | `{dark-card-surface}` | `#131b2e` | `#131b2e` |
| 7 | Поле: бордер / фокус | `slate-200`, `ring-2 ring-indigo-600/20` | `#dde3ec`, `ring-[3px] ring-indigo-600/15` | из файлов |
| 8 | Поле: dark | `slate-700` / `{dark-input}` | `white/[0.16]` / `#0d1424` | из файлов |
| 9 | Primary: hover | `brightness-95` | `hover:bg-brand-green-hover` есть в конфиге | `hover:bg-brand-green-hover` (задача так и предписывает, если в конфиге есть оттенок) |
| 10 | Primary: размер | `px-4 py-2.5` | шапка `px-5 py-3` + `shadow-brand-cta`; футеры drawer'ов сейчас `px-5 py-2` | **[решение]** CTA в шапке страницы как в Orders. В футерах drawer/модалок оставить `px-5 py-2` (правило 5, плотность) без цветной тени |
| 11 | Цвет focus-ring кнопок | `indigo-600` | на Login `ring-brand-green` | **[решение]** везде `indigo-600`: один цвет фокуса, как у полей |
| 12 | Ring-offset в dark | `{dark-page-bg}` | `slate-900` | `dark:focus-visible:ring-offset-[#0b1220]` |
| 13 | Бейдж | `bg-*-50 ring-1 ring-inset`, `py-0.5` | `bg-*-100`, без ring, `py-1` | из файлов + **добавить dark-варианты** (в Orders/Index их нет) |
| 14 | tr hover в dark | `dark:hover:bg-slate-800/50` | `dark:hover:bg-white/[0.03]` | из файлов |
| 15 | Divider строк | `divide-slate-100` | `border-b border-slate-100` на `tr` | из файлов (`border-b`) |
| 16 | Label | `mb-1.5` | Login `mb-2`; drawer'ы `mb-1.5` | `mb-1.5` (плотность drawer'ов) |
| 17 | Ошибка | `text-red-600 dark:text-red-400 font-medium` | `text-red-500` | **[решение]** `text-red-600 dark:text-red-400`: у `red-500` на белом контраст ≈3.8:1, это ниже AA |

---

## 3. Аудит

### Статистика (все `.vue`)
| Метрика | Значение |
|---|---|
| Разных размеров шрифта | **19** (9 произвольных `text-[Npx]`) |
| Размеры вне новой шкалы | 75 вхождений в 8 файлах, из них Settings 44, Dashboard 15, Login 6, Orders/Index 4 |
| Primary `bg-blue-600` | ≈28 кнопок в 23 файлах, плюс активные пилюли/табы (Cities, Regions, Users, Requests, Pagination) |
| Primary `bg-indigo-600` | 5 кнопок (ForgotPassword, CategoryContentModal, Profile ×2), плюс сегменты, аватар, счётчик |
| Primary `bg-purple-600` | 1 (CategoryFormModal, режим «дочерняя категория») |
| Primary `bg-brand-green` | 2 (Login, Orders «Добавить») |
| `gray-*` / `slate-*` | 927 / 1045. На старых страницах light-цвета `gray-*`, dark-цвета `slate-*` |
| Паттерны фокуса | `focus:ring-4 ring-blue-500/20` (drawer'ы, 27), `focus:ring-1 ring-blue-500` (фильтры), `focus:ring-[3px] ring-indigo-600/15` (new-gen, 6), `ring-indigo-500` (Breeze); `focus-visible` — **0** |
| `'...'` как loading | 13 файлов (все drawer-формы + ConfirmModal); в Pagination `'...'` — это разрыв страниц, не loading |
| `aria-label` | 1 (PasswordInput) |
| `tabular-nums` | 2 |
| `<style>`-блоки (существующие) | Modal, NotificationPanel, AdminLayout, DeleteUserForm, Settings — не трогаю, новых не добавляю |

### Статусы (реальные, из `app/Enums`)
| Enum | Статусы → `color()` |
|---|---|
| `OrderStatus` | pending→yellow, assigned→blue, in_progress→indigo, completed→green, cancelled→red |
| `MasterStatus` | pending→yellow, approved→green, rejected→red |
| `SubscriptionRequestStatus` | pending→yellow, approved→green, rejected→red |
| `SubscriptionStatus` | pending→yellow, active→green, expired→gray, cancelled→red |
| `UserRole` | administrator→indigo, manager→blue, operator→green |

Заказ: список и карточка сейчас окрашены по-разному.
| Статус | `Orders/Index` (инлайн-мапа по `status`) | `Orders/Show` → `OrderStatusBadge` (по `status_color`) | Целевой (фаза 5) |
|---|---|---|---|
| pending | amber | yellow | **amber** |
| assigned | indigo | blue | **blue** |
| in_progress | indigo | indigo | **indigo** |
| completed | green | green | **green** |
| cancelled | **slate** | **red** | **red** |
| + «нужно ручное назначение» | amber, иконка | — | amber (без изменений) |
| + «идёт поиск · N км» | slate | — | slate (без изменений) |

Бэкенд отдаёт `yellow`. Чтобы не трогать enum, во фронтовом хелпере `yellow` будет рисоваться как amber. Тот же хелпер стоит применить к мапам в `Subscriptions/Index`, `Subscriptions/Requests` и `OrderStatusBadge`, чтобы pending был amber во всех разделах.

### Что не по стандарту, по файлам
| Файл(ы) | Отклонения |
|---|---|
| **Layout / shared** | |
| `AdminLayout` | Тосты: `green-500`/`blue-500` с белым текстом (низкий контраст), без иконок. `title="Уведомления"` захардкожен. У иконочных кнопок нет `aria-label` и `focus-visible`. Аватар/язык на indigo (это ок) |
| `Modal` | Dark-поверхность `slate-800 ring-slate-700` вместо `#131b2e` |
| `ConfirmModal` | `gray-*`, `'...'`, dark `slate-700/800`, кнопки `rounded-lg` |
| `Pagination` | Активная страница `bg-blue-600`, `gray-*`, стрелки без `aria-label`, нет `tabular-nums` |
| `PhoneInput` | `rounded-xl`, `gray-*`, фокус `ring-4 blue` |
| `PasswordInput` | Ок (есть aria-label), иконка `gray-*` |
| `OblastCitySelect`, `CategoryPicker`, `IconPicker` | `gray-*`, фокус blue, dark `slate-*` |
| `NotificationPanel` | Хардкод «Уведомления», «Уведомлений нет», «Удалить». `gray-*`. Пустое состояние только текстом |
| `PendingOtpPanel` | Сверить с таблицами |
| `PrimaryButton`, `TextInput`, `InputLabel`, `InputError`, `GuestLayout` | Breeze, без dark. Используются только в Auth/ConfirmPassword, ResetPassword, VerifyEmail (`InputError` ещё в Create/EditOrderModal) |
| **Списки (старое поколение)** | |
| Clients, Masters/Index, Masters/Applications, Oblasts, Cities, Regions, Categories, Banners, Users, Subscriptions/Index, Subscriptions/Requests | H1 `text-xl font-semibold text-gray-900`. Карточка `rounded-xl shadow-sm`, dark `slate-800`. th `text-xs font-semibold tracking-wider text-gray-500 bg-gray-50`, ячейки `px-6`, `divide-y`. Фильтры `rounded-lg border-gray-200 focus:ring-1 blue`. Primary `bg-blue-600 rounded-lg font-medium`. Пустое состояние только текстом. Иконочные кнопки без `aria-label` |
| Masters/Index | hover строки `bg-blue-50/60` (выбивается) |
| Oblasts/Index | Мелкие primary `bg-blue-600 text-xs` внутри строк (добавить город/район) |
| Cities, Regions, Users, Requests | Активная пилюля-фильтр `bg-blue-600` |
| Masters/Map | Легенда/оверлеи `gray-*` |
| **Формы (drawer'ы)** | |
| Все `*FormModal`, Create/Edit/ChangeStatus | Заголовок `text-base font-semibold text-gray-900`. Поля `rounded-xl bg-gray-50 ring-4 blue`. Submit `bg-blue-600`, loading `'...'`. Крестик без `aria-label`. Дубли `InputError` |
| CategoryFormModal | `bg-purple-600` для «дочерней» |
| CategoryContentModal | `bg-indigo-600` |
| **Детальные / прочие** | |
| Orders/Show | Компенсирует паддинг через `-m-4 lg:-m-6`, а у layout `lg:p-8`, поэтому на ≥1024px остаётся зазор 8px. Шапка/карточки `slate-800`/`gray-*`. Хардкод «при скорости N км/ч». Нет `tabular-nums` у дистанции/ETA |
| Settings | 44 размера вне шкалы, 108 `gray-*`, свой `<style>` тулбара |
| Dashboard | 15 нестандартных размеров (10.5/12.5/13px). KPI `text-[30px]` → `text-3xl` (30px, то же самое) |
| Login | 15/32/36px. Слоган — маркетинговый блок, предлагаю **оставить как есть** |
| ForgotPassword | indigo-кнопка и индиго-иконка |
| ResetPassword, ConfirmPassword, VerifyEmail | Breeze + GuestLayout с лого Laravel, **нет dark вовсе** |
| Profile/* | indigo-кнопки, `gray-*` |

---

## 4. План фаз 1–8

| Фаза | Файлы |
|---|---|
| **1. Шрифт и шкала** | `app.blade.php`, `tailwind.config.js`. Перевод размеров на шкалу в 8 файлах (AdminLayout, Login, ForgotPassword, Dashboard, Orders/Index, CreateOrderModal, Settings, Subscriptions/Index). Роль H1 на всех 13 страницах-списках + Orders/Show. `tabular-nums` во всех таблицах/KPI/пагинации/телефонах (точечно). Проверка TK на переносы |
| **2. Цвета, кнопки, поля, фокус** | Shared: Pagination, PhoneInput, PasswordInput, OblastCitySelect, ConfirmModal. Пилот: Orders/Index, Orders/Show, Create/Edit/ChangeStatusModal, Clients/Index, ClientFormModal |
| **3. Карточки, таблицы, фильтры** | Orders/Index (доводка dark), Orders/Show (карточки + фикс `lg:-m-8`), Clients/Index |
| **4. Drawer'ы, модалки, dropdown, пагинация** | Modal, ConfirmModal, AdminLayout (dropdown), Pagination, NotificationPanel, ClientFormModal + 3 модалки заказа |
| **5. Статусы и тосты** | Новый `resources/js/utils/orderStatus.js` (папка `utils` уже есть, в нижнем регистре). OrderStatusBadge, Orders/Index, тосты в AdminLayout. Мапы в Subscriptions/Index, Subscriptions/Requests (yellow→amber) |
| **6. Состояния и a11y** | Спиннер вместо `'...'` (13 файлов), `disabled:`, пустые состояния, `aria-label`/`title` в shared + пилоте. Новые ключи в `lang/ru|tk/layout.php` (+ разделы) |
| **7. Остальные страницы** (группами, стоп после каждой) | **A** Masters/Index, Applications, Map, MasterFormModal, MasterLocationModal · **B** Oblasts, Cities, Regions + 3 модалки · **C** Categories + 2 модалки, IconPicker, CategoryPicker, Banners + BannerFormModal · **D** Subscriptions/Index, Requests, PlanFormModal, SubscriptionFormModal, Users/Index, UserFormModal · **E** Settings, PendingOtpPanel, Profile/*, Auth (Forgot/Reset/Confirm/Verify + GuestLayout + Breeze-компоненты) |
| **8. Проверка** | grep-проверки, RU/TK, dark, регрессия, `npm run build`, финальное обновление этого файла |

**Вне скоупа (структурные, только в отчёт):** переписать Auth-страницы Breeze на split-layout как у Login; вынести кнопки/поля в общие компоненты; убрать дублирование заголовка в шапке. Клавиатурный доступ к `<tr @click>` разрешён и сделан (Orders/Index, карточки Oblasts).

---

## 5. Итог (фазы 1–8 выполнены)

### Принятые решения (из раздела 2)
th `text-slate-500` · `tracking-tight` на H1 · бордер карточки `slate-200` · primary в футерах `px-5 py-2` без тени · фокус везде `indigo-600` · ошибки `text-red-600 dark:text-red-400`.

### Итоговые рецепты
| Что | Классы |
|---|---|
| Карточка | `rounded-[14px] border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-white/[0.08] dark:bg-[#131b2e] dark:shadow-[0_1px_2px_rgba(0,0,0,0.3)]` |
| Поле | `rounded-[10px] border-[#dde3ec] bg-white px-3.5 py-2.5 focus:border-indigo-600 focus:ring-[3px] focus:ring-indigo-600/15 dark:border-white/[0.16] dark:bg-[#0d1424]` |
| Primary (футер) | `inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] bg-brand-green px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-green-hover` + FV + `disabled:cursor-not-allowed disabled:opacity-50` |
| Danger | то же, `bg-red-600 hover:bg-red-700 focus-visible:ring-red-500` |
| FV (фокус) | `focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600`, у залитых и иконочных кнопок ещё `focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#131b2e]` (`#0f1626` в шапке, `white/60` на тёмном сайдбаре и в лайтбоксе) |
| Имя в таблице | `text-sm font-semibold text-slate-800 dark:text-[#f1f5f9]` |
| Вторичный текст | `text-slate-500 dark:text-slate-400` |
| Пустое состояние | иконка в `flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-white/[0.06] dark:text-slate-500` + `text-sm text-slate-500 dark:text-slate-400` |
| Loading | SVG-спиннер `h-4 w-4 animate-spin` + `t('layout.actions.saving' / 'processing')` |
| Переключатель статуса | `role="switch"` + `:aria-checked`, трек `bg-brand-green` |

### Бейджи
Палитра и хелпер лежат в `Pages/Orders/Partials/OrderStatusBadge.vue`: именованные экспорты `badgeTones` и `orderStatusBadge(status, color)`. План был положить их в `utils/orderStatus.js`, но Tailwind `content` сканирует только `*.vue`, и классы из `.js` не попали бы в сборку.
Тон: `bg-{c}-100 dark:bg-{c}-500/15`, текст `text-{c}-800 dark:text-{c}-300`, точка `bg-{c}-500 dark:bg-{c}-400` (green `600/400`, slate `bg-slate-100 dark:bg-white/[0.08]`).
Заказы: pending→amber, assigned→blue, in_progress→indigo, completed→green, cancelled→red, остальное→slate. `yellow` с бэкенда рисуется как amber, `gray` как slate.

### Новые ключи локализации (RU + TK)
- `layout.actions.*`: saving, processing, edit, delete, close, menu, home, open_link, open_photo, remove_photo, prev_photo, next_photo, toggle_active
- `layout.editor.*`: bullet_list, numbered_list, clear
- `layout.notifications.*`: title, mark_all_read, delete_all, empty
- `masters.live`; `categories.type_label`, `categories.content_image_new`
- `auth.confirm_password.*`, `auth.reset_password.*`, `auth.verify_email.*`

Проверка: все 540 статических ключей `t()` есть и в RU, и в TK.

### Сознательно не тронуто
| Что | Почему |
|---|---|
| Login: слоган, wordmark | эталон бренда |
| Цвета маркеров на карте (Masters/Map) | заданы в JS (Leaflet), а не классами |
| `py-2` у полей в Applications и IconPicker | плотность (правило 5) |
| H1 на Oblasts | на странице его нет; добавить = изменить структуру |
| Своя пагинация в Cities и Regions | замена на `Pagination` = смена компонента |
| «Область» vs «Велаят» в подписях | нужна правка текстов, решать продукту |
| Синий/фиолетовый в типах категорий | смысловые цвета (корневая / дочерняя) |
| Цветные плитки-иконки секций в Settings | смысловое различие секций |
| Существующие `<style>`-блоки | правило 4: не трогаем, новых не добавляем |
| Индиго-плитка лого на ForgotPassword | бренд-элемент страницы |
| Сегменты `p-[3px] dark:bg-slate-700` (Dashboard, язык в шапке) | совпадают с эталоном new-gen |
| Master Handyman, Reverb, WebSocket | имена собственные, не переводятся |
