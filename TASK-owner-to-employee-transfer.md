# Задача: Інтеграція передачі коштів співробітнику в основний UI гаманця

## Поточний стан

Бекенд **вже реалізовано** і працює:
- `EmployeeTransferController.php` — 6 методів (store, pending, accept, decline, cancel, history)
- Маршрути `/api/employee-transfers/*` — зареєстровано
- Модель `CashTransfer` — оновлена (transfer_type, employee_user_id, comment, cancelled_at/by)
- Міграція — виконана

UI **частково реалізовано**, але потребує переробки:
- `employee-transfer.js` — окрема логіка рендерингу
- `wallet.blade.php` — кнопка «💸 Передати», модалка `etModal`, окремий банер `etPendingBanner`, окрема секція `etHistorySection`

---

## Що потрібно змінити

### Проблема
Зараз передачі співробітникам відображаються **окремою секцією** (карточки в `etHistorySection`), а pending трансфери — окремим банером (`etPendingBanner`). Потрібно щоб:

1. **Операції передачі** з'являлись **в загальному списку entries** (таблиця `<tbody id="entries">`) разом з усіма іншими доходами/витратами
2. **Скасування та видалення** відбувались через **довге натискання** (long press) на рядку операції, а не через click→expand як зараз
3. Кнопка «Передати» та модалка залишаються як є

---

## Детальна специфікація змін

### 1. Backend: Додати `cash_transfer_id` у відповідь entries API

**Файл:** `routes/api.php`, ендпоінт `GET /api/wallets/{walletId}/entries` (≈ рядок 1082)

Додати в map:
```php
'cash_transfer_id' => $e->cash_transfer_id ?? null,
```

Це дозволить фронтенду визначити які entries створені через трансфер (не можна редагувати/видаляти вручну).

### 2. Frontend: Замінити click-to-expand на long press для entry actions

**Файл:** `public/js/wallet.js`, функція `renderEntries()` (≈ рядок 630)

#### 2.1 Прибрати поточну логіку click→active→show buttons

Зараз:
```javascript
tr.onclick = (ev) => {
  state.activeEntryId = (state.activeEntryId === e.id) ? null : e.id;
  renderEntries();
};
```

#### 2.2 Додати long press (touchstart/touchend + mousedown/mouseup)

Реалізувати:
- **Long press (≥500ms)** → показати контекстне меню / action overlay з кнопками «✏️ Редагувати» та «🗑 Видалити» (для записів де editable=true)
- **Для записів з cash_transfer_id** (створені через трансфер): показувати тільки кнопку «↩ Скасувати» якщо це owner та операція сьогоднішня
- **Короткий тап** → нічого не робити (або закрити вже відкрите меню)
- **Свайп/скрол** → скасувати long press timer

Приклад реалізації:
```javascript
let longPressTimer = null;
let longPressTriggered = false;

tr.addEventListener('touchstart', (ev) => {
  longPressTriggered = false;
  longPressTimer = setTimeout(() => {
    longPressTriggered = true;
    showEntryActions(e, tr);
    // Вібрація (якщо доступна)
    if (navigator.vibrate) navigator.vibrate(30);
  }, 500);
}, { passive: true });

tr.addEventListener('touchmove', () => {
  clearTimeout(longPressTimer);
}, { passive: true });

tr.addEventListener('touchend', (ev) => {
  clearTimeout(longPressTimer);
  if (longPressTriggered) {
    ev.preventDefault();
  }
});

// Для десктопу — mousedown/mouseup
tr.addEventListener('mousedown', (ev) => {
  longPressTimer = setTimeout(() => {
    longPressTriggered = true;
    showEntryActions(e, tr);
  }, 500);
});

tr.addEventListener('mouseup', () => { clearTimeout(longPressTimer); });
tr.addEventListener('mouseleave', () => { clearTimeout(longPressTimer); });
```

#### 2.3 Функція `showEntryActions(entry, rowElement)`

Показує overlay поверх рядка (або bottom sheet) з діями:

**Для звичайних записів (editable, без cash_transfer_id):**
- ✏️ Редагувати → викликає існуючий `editEntry(id)`
- 🗑 Видалити → викликає існуючий `deleteEntry(id)`

**Для записів з cash_transfer_id (трансфер):**
- ↩ Скасувати передачу → `POST /api/employee-transfers/{cash_transfer_id}/cancel`
- Тільки для owner, тільки сьогоднішні

**Для read-only записів (не editable):**
- Long press не показує нічого або показує "Операцію не можна змінити"

Візуальний стиль — аналогічний існуючим `.entry-actions`, але з'являється як overlay:
```css
.entry-longpress-menu {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 16px;
  background: var(--panel);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-top: 1px solid var(--stroke);
  border-radius: 18px 18px 0 0;
  z-index: 1000;
  animation: slideUp 0.2s ease;
}
```

### 3. Frontend: Помітити записи від трансферів у списку entries

**Файл:** `public/js/wallet.js`, функція `renderEntries()`

В рядках де `e.cash_transfer_id` існує — додати візуальну позначку:

```javascript
const isTransfer = !!e.cash_transfer_id;

// У renderComment або в entry-comment td:
const transferBadge = isTransfer
  ? '<span style="font-size:11px; color:var(--blue); margin-left:4px;">💸 трансфер</span>'
  : '';
```

Це дозволить власнику і співробітнику бачити які записи були створені автоматично через трансфер.

### 4. Frontend: Прибрати окрему секцію історії трансферів

**Файл:** `wallet.blade.php`

**Прибрати** з HTML:
```html
{{-- Це більше не потрібно — трансфери видно в загальному списку entries --}}
<div id="etHistorySection">...</div>
```

**Залишити:**
- Кнопку `#btnEmployeeTransfer` (💸 Передати)
- Модалку `#etModal`
- Банер `#etPendingBanner` для non-owner (pending підтвердження)

### 5. Frontend: Спрощення employee-transfer.js

**Файл:** `public/js/employee-transfer.js`

**Прибрати:**
- Функцію `loadOwnerHistory()` та весь рендеринг `etHistoryList`
- Посилання на `historySection`, `historyList`

**Залишити:**
- Логіку модалки (openModal, closeModal, createTransfer)
- loadStaffWallets
- loadPendingForEmployee (для банера підтвердження)
- accept/decline логіку

### 6. CSS: Стилі long-press меню

**Файл:** `public/css/wallet.css`

Додати:
```css
/* ================== LONG PRESS ACTION MENU ================== */
.longpress-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.4);
  z-index: 999;
  opacity: 0;
  transition: opacity .2s;
}
.longpress-overlay.show { opacity: 1; }

.longpress-menu {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  max-width: 560px;
  margin: 0 auto;
  padding: 12px 16px calc(env(safe-area-inset-bottom, 0px) + 12px);
  background: rgba(28,32,45,.92);
  backdrop-filter: blur(24px) saturate(160%);
  -webkit-backdrop-filter: blur(24px) saturate(160%);
  border-top: 1px solid var(--stroke);
  border-radius: 18px 18px 0 0;
  z-index: 1000;
  transform: translateY(100%);
  transition: transform .25s ease;
}
.longpress-menu.show { transform: translateY(0); }

.longpress-menu .lp-info {
  text-align: center;
  margin-bottom: 12px;
  font-size: .85rem;
  color: var(--muted);
}

.longpress-menu .lp-amount {
  font-size: 1.2rem;
  font-weight: 700;
  text-align: center;
  margin-bottom: 14px;
}

.longpress-menu button {
  width: 100%;
  padding: 14px;
  margin-bottom: 8px;
  border: none;
  border-radius: 12px;
  font-size: .95rem;
  font-weight: 600;
  cursor: pointer;
  background: rgba(255,255,255,.06);
  color: var(--text);
  transition: background .15s, transform .1s;
}
.longpress-menu button:active { transform: scale(0.97); background: rgba(255,255,255,.12); }
.longpress-menu button.edit { color: var(--blue); }
.longpress-menu button.delete { color: var(--red); }
.longpress-menu button.cancel-transfer { color: #f59e0b; }
.longpress-menu button.close-lp {
  background: transparent;
  color: var(--muted);
  margin-bottom: 0;
}
```

### 7. Підсумок: Як це буде виглядати

**Для власника:**
1. Відкриває гаманець → бачить список ВСІХ операцій (включаючи передачі)
2. Операції від трансферів позначені бейджем 💸
3. Натискає «💸 Передати» → модалка → вибір співробітника → сума → «Передати»
4. **Довге натискання** на будь-якій операції →
   - Якщо звичайна операція (сьогодні): «Редагувати» / «Видалити»
   - Якщо трансфер (сьогодні, accepted): «Скасувати передачу»
   - Якщо стара операція: bottom sheet не з'являється або пише "Не можна змінити"

**Для співробітника:**
1. Відкриває гаманець → бачить банер «Вам передають кошти» якщо є pending
2. Натискає «Отримати» → entry з'являється в списку як income з позначкою 💸
3. **Довге натискання** на операції від трансферу → нічого (не може редагувати/видаляти)
4. Довге натискання на власну операцію (сьогодні) → «Редагувати» / «Видалити» як звичайно

---

## Файли які потрібно змінити

| Файл | Що змінити |
|------|-----------|
| `routes/api.php` (~рядок 1090) | Додати `cash_transfer_id` у відповідь entries |
| `public/js/wallet.js` (renderEntries ~630) | Замінити click→longpress, додати badge трансферу, додати longpress menu |
| `public/css/wallet.css` | Додати стилі `.longpress-menu`, `.longpress-overlay` |
| `resources/views/wallet.blade.php` | Прибрати `#etHistorySection`, залишити `#etPendingBanner` та модалку |
| `public/js/employee-transfer.js` | Прибрати `loadOwnerHistory`, залишити модалку та pending логіку |

## Файли які НЕ змінювати

| Файл | Причина |
|------|---------|
| `EmployeeTransferController.php` | Бекенд готовий, не чіпати |
| `CashTransfer.php` (модель) | Вже оновлена |
| `CashTransferController.php` | Логіка owner↔owner, не чіпати |
| Міграції | Вже виконані |

---

## Порядок реалізації

1. **`routes/api.php`** — додати `cash_transfer_id` в entries response (1 рядок)
2. **`wallet.css`** — додати CSS для longpress menu
3. **`wallet.js`** → `renderEntries()`:
   - Додати badge 💸 для записів з `cash_transfer_id`
   - Прибрати `tr.onclick` з toggle active
   - Додати longpress listener (touch + mouse)
   - Створити функцію `showEntryActions(entry)` — рендерить bottom sheet
   - Створити функцію `hideEntryActions()` — ховає bottom sheet
   - В bottom sheet: для записів з `cash_transfer_id` — кнопка «Скасувати передачу» (POST `/api/employee-transfers/{cash_transfer_id}/cancel`)
   - Для звичайних записів — «Редагувати» + «Видалити»
4. **`wallet.blade.php`** — прибрати `etHistorySection`, додати div для longpress overlay/menu
5. **`employee-transfer.js`** — прибрати `loadOwnerHistory`, прибрати references на historySection/historyList
6. Протестувати:
   - Довге натискання на мобільному (touch) та десктопі (mouse)
   - Скасування трансферу через longpress
   - Редагування/видалення звичайної операції через longpress
   - Що pending банер для співробітника працює як раніше
   - Що скрол не тригерить longpress
