@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
<main class="projects-main dl-page">

  <section class="dl-hero">
    <div class="dl-hero__copy">
      <div class="dl-hero__eyebrow">Логістика проектів</div>
      <div class="projects-title dl-hero__title">🚚 Доставлено на об'єкт</div>
      <div class="dl-hero__subtitle">
        Швидкий огляд активних проектів, де панелі або інверторний комплект уже на місці.
      </div>
    </div>

    <div class="dl-hero__status">
      <div class="dl-hero__status-label">Стан сторінки</div>
      <div id="deliveredHeroStatus" class="dl-hero__status-value">Завантажуємо актуальні дані…</div>
    </div>
  </section>

  <section class="dl-shell">
    <div id="deliveredSummary" class="dl-stats" aria-live="polite"></div>

    <div id="deliveredStageRail" class="dl-stage-rail" aria-live="polite"></div>

    <div class="card dl-toolbar">
      <label class="dl-search" for="deliveredSearch">
        <span class="dl-search__icon">⌕</span>
        <input
          id="deliveredSearch"
          type="search"
          class="dl-search__input"
          placeholder="Пошук по клієнту, менеджеру, етапу або обладнанню"
          autocomplete="off"
        >
      </label>

      <div class="dl-filter-block">
        <div class="dl-filter-caption">Етап</div>
        <div id="deliveredStageFilters" class="dl-filter-row"></div>
      </div>

      <div class="dl-filter-block">
        <div class="dl-filter-caption">Тип доставки</div>
        <div id="deliveredTypeFilters" class="dl-filter-row"></div>
      </div>

      <div id="deliveredResultNote" class="dl-result-note">Готуємо список проєктів…</div>
    </div>

    <div id="deliveredResults" class="dl-results" aria-live="polite"></div>
  </section>

</main>

<style>
.dl-page {
  position: relative;
}

.dl-page::before,
.dl-page::after {
  content: "";
  position: fixed;
  inset: auto;
  pointer-events: none;
  z-index: -1;
  filter: blur(90px);
  opacity: .32;
}

.dl-page::before {
  top: 90px;
  left: -40px;
  width: 220px;
  height: 220px;
  background: radial-gradient(circle, rgba(23,163,112,.55), rgba(23,163,112,0));
}

.dl-page::after {
  top: 180px;
  right: -60px;
  width: 260px;
  height: 260px;
  background: radial-gradient(circle, rgba(64,144,255,.38), rgba(64,144,255,0));
}

.dl-hero {
  position: relative;
  overflow: hidden;
  display: grid;
  grid-template-columns: minmax(0, 1.7fr) minmax(220px, .9fr);
  gap: 18px;
  align-items: stretch;
  margin-bottom: 18px;
  padding: 24px 22px;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 28px;
  background:
    radial-gradient(circle at top right, rgba(255,188,92,.16), rgba(255,188,92,0) 38%),
    radial-gradient(circle at left center, rgba(52,168,126,.20), rgba(52,168,126,0) 36%),
    linear-gradient(145deg, rgba(9,22,18,.98), rgba(13,18,28,.96));
  box-shadow:
    0 22px 52px rgba(0,0,0,.28),
    inset 0 1px 0 rgba(255,255,255,.04);
}

.dl-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    linear-gradient(120deg, rgba(255,255,255,.07), transparent 30%),
    linear-gradient(180deg, transparent, rgba(255,255,255,.03));
  pointer-events: none;
}

.dl-hero__copy,
.dl-hero__status {
  position: relative;
  z-index: 1;
}

.dl-hero__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  margin-bottom: 14px;
  border-radius: 999px;
  border: 1px solid rgba(126,170,255,.18);
  background: rgba(255,255,255,.04);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: rgba(208,225,255,.86);
}

.dl-hero__title {
  margin-bottom: 8px;
  font-size: clamp(22px, 3.5vw, 32px);
  line-height: 1.02;
  text-align: left;
}

.dl-hero__subtitle {
  max-width: 760px;
  font-size: 14px;
  line-height: 1.65;
  color: rgba(232,238,246,.72);
}

.dl-hero__status {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 12px;
  padding: 18px;
  border-radius: 22px;
  border: 1px solid rgba(255,255,255,.08);
  background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03));
  box-shadow: inset 0 1px 0 rgba(255,255,255,.04);
}

.dl-hero__status-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: rgba(255,255,255,.42);
}

.dl-hero__status-value {
  font-size: 14px;
  line-height: 1.55;
  color: rgba(237,243,249,.92);
}

.dl-shell {
  display: grid;
  gap: 16px;
}

.dl-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
}

.dl-stat {
  position: relative;
  overflow: hidden;
  min-height: 118px;
  padding: 18px;
  border-radius: 22px;
  border: 1px solid rgba(255,255,255,.08);
  background:
    linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.03)),
    linear-gradient(135deg, rgba(255,255,255,.04), rgba(255,255,255,.01));
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.04),
    0 18px 28px rgba(0,0,0,.18);
}

.dl-stat::after {
  content: "";
  position: absolute;
  inset: auto -24px -24px auto;
  width: 110px;
  height: 110px;
  border-radius: 50%;
  background: radial-gradient(circle, var(--stat-glow, rgba(107,191,255,.20)), rgba(0,0,0,0));
  pointer-events: none;
}

.dl-stat__label {
  position: relative;
  z-index: 1;
  margin-bottom: 12px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(255,255,255,.48);
}

.dl-stat__value {
  position: relative;
  z-index: 1;
  font-size: clamp(24px, 4vw, 32px);
  font-weight: 800;
  letter-spacing: -.04em;
  color: #fff;
}

.dl-stat__hint {
  position: relative;
  z-index: 1;
  margin-top: 10px;
  font-size: 12px;
  line-height: 1.45;
  color: rgba(255,255,255,.6);
}

.dl-toolbar {
  display: grid;
  gap: 14px;
  padding: 18px;
  border-radius: 24px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  box-shadow: 0 16px 36px rgba(0,0,0,.16);
}

.dl-search {
  position: relative;
  display: block;
}

.dl-search__icon {
  position: absolute;
  top: 50%;
  left: 14px;
  transform: translateY(-50%);
  font-size: 16px;
  color: rgba(255,255,255,.38);
  pointer-events: none;
}

.dl-search__input {
  width: 100%;
  min-height: 52px;
  padding: 14px 16px 14px 44px;
  border-radius: 16px;
  border: 1px solid rgba(255,255,255,.08);
  background: rgba(255,255,255,.05);
  color: rgba(244,248,252,.96);
  font-size: 14px;
  transition: border-color .18s ease, background .18s ease, box-shadow .18s ease;
}

.dl-search__input::placeholder {
  color: rgba(255,255,255,.34);
}

.dl-search__input:focus {
  outline: none;
  border-color: rgba(107,191,255,.34);
  background: rgba(255,255,255,.07);
  box-shadow: 0 0 0 4px rgba(107,191,255,.08);
}

.dl-filter-block {
  display: grid;
  gap: 8px;
}

.dl-filter-caption {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(255,255,255,.44);
}

.dl-filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.dl-chip {
  appearance: none;
  border: 1px solid rgba(255,255,255,.09);
  background: rgba(255,255,255,.04);
  color: rgba(236,242,248,.84);
  border-radius: 999px;
  padding: 10px 14px;
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: transform .16s ease, border-color .16s ease, background .16s ease, color .16s ease;
}

.dl-chip:hover {
  transform: translateY(-1px);
  border-color: rgba(255,255,255,.18);
  background: rgba(255,255,255,.07);
}

.dl-chip.is-active {
  border-color: transparent;
  background: linear-gradient(135deg, rgba(94,197,142,.28), rgba(95,174,255,.22));
  color: #fff;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.12);
}

.dl-chip__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  margin-left: 8px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba(255,255,255,.08);
  font-size: 11px;
  color: rgba(255,255,255,.72);
}

.dl-chip.is-active .dl-chip__count {
  background: rgba(255,255,255,.16);
  color: rgba(255,255,255,.96);
}

.dl-result-note {
  font-size: 12px;
  line-height: 1.5;
  color: rgba(233,239,245,.58);
}

.dl-results {
  display: grid;
  gap: 12px;
}

.dl-stage-rail {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 10px;
}

.dl-stage-rail__item {
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 112px;
  padding: 16px;
  border-radius: 20px;
  border: 1px solid rgba(255,255,255,.08);
  background:
    linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03)),
    radial-gradient(circle at top right, color-mix(in srgb, var(--stage-color) 18%, transparent), transparent 46%);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.04);
}

.dl-stage-rail__item::before {
  content: "";
  position: absolute;
  inset: 0 auto 0 0;
  width: 3px;
  background: linear-gradient(180deg, var(--stage-color), rgba(255,255,255,.08));
}

.dl-stage-rail__label {
  padding-left: 8px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(255,255,255,.46);
}

.dl-stage-rail__value {
  padding-left: 8px;
  font-size: 30px;
  font-weight: 800;
  letter-spacing: -.05em;
  line-height: 1;
  color: #fff;
}

.dl-stage-rail__hint {
  padding-left: 8px;
  font-size: 12px;
  line-height: 1.45;
  color: rgba(233,239,246,.64);
}

.dl-stage-groups {
  display: grid;
  gap: 18px;
}

.dl-stage-section {
  display: grid;
  gap: 12px;
}

.dl-stage-section__head {
  display: grid;
  gap: 10px;
  padding: 18px 20px;
  border-radius: 24px;
  border: 1px solid rgba(255,255,255,.08);
  background:
    linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03)),
    radial-gradient(circle at top right, color-mix(in srgb, var(--stage-color) 16%, transparent), transparent 46%);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.04);
}

.dl-stage-section__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}

.dl-stage-section__title-wrap {
  min-width: 0;
}

.dl-stage-section__eyebrow {
  margin-bottom: 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .15em;
  text-transform: uppercase;
  color: rgba(255,255,255,.42);
}

.dl-stage-section__title {
  margin: 0;
  font-size: clamp(18px, 2vw, 24px);
  font-weight: 800;
  letter-spacing: -.04em;
  color: #fff;
}

.dl-stage-section__count {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 64px;
  padding: 10px 14px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--stage-color) 16%, rgba(255,255,255,.04));
  border: 1px solid color-mix(in srgb, var(--stage-color) 34%, rgba(255,255,255,.08));
  font-size: 12px;
  font-weight: 800;
  color: color-mix(in srgb, var(--stage-color) 88%, #fff);
}

.dl-stage-section__desc {
  margin: 0;
  font-size: 13px;
  line-height: 1.65;
  color: rgba(233,239,245,.7);
}

.dl-stage-section__metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.dl-stage-metric {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 11px;
  border-radius: 12px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.05);
  font-size: 12px;
  color: rgba(238,243,248,.72);
}

.dl-stage-metric strong {
  color: #fff;
  font-weight: 800;
}

.dl-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 14px;
}

.dl-card {
  position: relative;
  overflow: hidden;
  display: grid;
  gap: 14px;
  min-height: 260px;
  padding: 20px;
  border-radius: 24px;
  background:
    radial-gradient(circle at top right, color-mix(in srgb, var(--stage-color) 18%, transparent), transparent 36%),
    linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.03));
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.05),
    0 16px 28px rgba(0,0,0,.16);
  transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
}

.dl-card:hover {
  transform: translateY(-3px);
  border-color: rgba(255,255,255,.12);
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.06),
    0 22px 36px rgba(0,0,0,.22);
}

.dl-card::before {
  content: "";
  position: absolute;
  inset: 0 0 auto 0;
  height: 3px;
  background: linear-gradient(90deg, var(--stage-color), rgba(255,255,255,.15));
  opacity: .9;
}

.dl-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.dl-card__index {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(255,255,255,.38);
}

.dl-stage-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, var(--stage-color) 50%, rgba(255,255,255,.08));
  background: color-mix(in srgb, var(--stage-color) 12%, rgba(255,255,255,.04));
  color: color-mix(in srgb, var(--stage-color) 88%, #f4f8fb);
  font-size: 11px;
  font-weight: 800;
  line-height: 1.2;
}

.dl-card__name {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -.04em;
  line-height: 1.08;
  color: #fff;
}

.dl-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.dl-meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  border-radius: 12px;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.06);
  font-size: 12px;
  color: rgba(233,239,246,.72);
}

.dl-card__label {
  margin-bottom: 8px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(255,255,255,.42);
}

.dl-card__body {
  min-height: 88px;
  padding: 14px;
  border-radius: 18px;
  background: rgba(255,255,255,.035);
  border: 1px solid rgba(255,255,255,.05);
}

.dl-card__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.dl-delivery-pill {
  display: inline-flex;
  align-items: center;
  padding: 10px 12px;
  border-radius: 16px;
  border: 1px solid rgba(255,255,255,.07);
  background: rgba(255,255,255,.05);
  font-size: 12px;
  line-height: 1.45;
  color: rgba(246,249,252,.88);
}

.dl-delivery-pill--panels {
  border-color: rgba(100,191,255,.22);
  background: rgba(80,172,255,.11);
  color: rgba(180,227,255,.96);
}

.dl-delivery-pill--inverter {
  border-color: rgba(255,179,128,.24);
  background: rgba(255,171,111,.10);
  color: rgba(255,219,194,.96);
}

.dl-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: auto;
}

.dl-card__map {
  margin-bottom: 0;
  min-height: 44px;
  padding: 10px 16px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(65,151,255,.26), rgba(65,151,255,.12));
  border-color: rgba(65,151,255,.26);
}

.dl-card__map:hover {
  border-color: rgba(114,186,255,.42);
}

.dl-card__ghost {
  font-size: 12px;
  color: rgba(255,255,255,.36);
}

.dl-empty,
.dl-error,
.dl-loading {
  padding: 26px 22px;
  border-radius: 24px;
  border: 1px solid rgba(255,255,255,.08);
  background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03));
}

.dl-empty__title,
.dl-error__title {
  margin: 0 0 8px;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -.03em;
}

.dl-empty__text,
.dl-error__text {
  margin: 0;
  font-size: 14px;
  line-height: 1.65;
  color: rgba(233,239,245,.68);
}

.dl-empty__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 16px;
}

.dl-link-btn {
  min-height: 42px;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,.08);
  background: rgba(255,255,255,.05);
  color: rgba(244,248,252,.88);
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
}

.dl-loading {
  display: grid;
  gap: 12px;
}

.dl-loading__row {
  height: 14px;
  border-radius: 999px;
  background: linear-gradient(90deg, rgba(255,255,255,.05), rgba(255,255,255,.14), rgba(255,255,255,.05));
  background-size: 220% 100%;
  animation: dl-shimmer 1.4s linear infinite;
}

.dl-loading__row:nth-child(1) { width: 36%; }
.dl-loading__row:nth-child(2) { width: 84%; }
.dl-loading__row:nth-child(3) { width: 72%; }

@keyframes dl-shimmer {
  from { background-position: 200% 0; }
  to   { background-position: -20% 0; }
}

@media (max-width: 900px) {
  .dl-hero {
    grid-template-columns: 1fr;
  }

  .dl-hero__status {
    padding: 16px;
  }
}

@media (max-width: 640px) {
  .dl-hero {
    padding: 20px 18px;
    border-radius: 24px;
  }

  .dl-hero__eyebrow {
    margin-bottom: 12px;
  }

  .dl-hero__title {
    font-size: 24px;
  }

  .dl-toolbar,
  .dl-card,
  .dl-stage-section__head,
  .dl-empty,
  .dl-error,
  .dl-loading {
    padding: 16px;
  }

  .dl-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dl-stage-rail {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dl-stat {
    min-height: 108px;
    padding: 16px;
  }

  .dl-grid {
    grid-template-columns: 1fr;
  }

  .dl-card__head,
  .dl-card__footer,
  .dl-stage-section__top {
    flex-direction: column;
    align-items: flex-start;
  }

  .dl-card__map {
    width: 100%;
    justify-content: center;
  }

  .dl-search__input {
    min-height: 48px;
  }
}
</style>

<script>
function esc(v) {
  return String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', () => {
  const summaryEl      = document.getElementById('deliveredSummary');
  const stageRailEl    = document.getElementById('deliveredStageRail');
  const resultsEl      = document.getElementById('deliveredResults');
  const stageFiltersEl = document.getElementById('deliveredStageFilters');
  const typeFiltersEl  = document.getElementById('deliveredTypeFilters');
  const resultNoteEl   = document.getElementById('deliveredResultNote');
  const searchInput    = document.getElementById('deliveredSearch');
  const heroStatusEl   = document.getElementById('deliveredHeroStatus');

  const stageOrder = [
    'Частково оплатив',
    'Комплектація',
    'Очікування доставки',
    'Заплановане будівництво',
    'Монтаж панелей',
    'Електрична частина',
    'Здача проекту',
  ];

  const stageColors = {
    'Частково оплатив': '#9aa6b2',
    'Комплектація': '#72c69c',
    'Очікування доставки': '#f6b45f',
    'Заплановане будівництво': '#5fb7ff',
    'Монтаж панелей': '#4fd1c5',
    'Електрична частина': '#6f8dff',
    'Здача проекту': '#50d890',
  };

  const stageDescriptions = {
    'Частково оплатив': 'Проєкти з підтвердженим стартом, де логістика вже готова підхопити постачання.',
    'Комплектація': 'Команда збирає обладнання й фіналізує склад майбутньої поставки.',
    'Очікування доставки': 'Усе готово до виїзду, тож важливо тримати під рукою маршрут і склад поставки.',
    'Заплановане будівництво': 'Обʼєкт уже має доставку й переходить у фазу підготовки до виїзду бригади.',
    'Монтаж панелей': 'Панелі на майданчику, а фокус команди переходить у виконання монтажу.',
    'Електрична частина': 'Інверторний комплект і суміжне обладнання підтримують завершення електрики на обʼєкті.',
    'Здача проекту': 'Фінальна стадія з уже доставленим комплектом і підготовкою до передачі клієнту.',
  };

  const typeMeta = {
    all:      { label: 'Усе доставлене', hint: 'Усі види комплектів' },
    panels:   { label: 'Лише панелі', hint: 'На обʼєкті вже є ФЕМ' },
    inverter: { label: 'Лише інверторний комплект', hint: 'Інвертор, АКБ або BMS' },
    both:     { label: 'Повний комплект', hint: 'І панелі, і інверторне обладнання' },
  };

  const state = {
    rows: [],
    stage: 'all',
    type: 'all',
    query: '',
    loadedAt: null,
  };

  const collator = new Intl.Collator('uk-UA', { sensitivity: 'base' });

  function pluralize(value, one, few, many) {
    const abs = Math.abs(Number(value)) || 0;
    const mod10 = abs % 10;
    const mod100 = abs % 100;

    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
  }

  function formatProjects(value) {
    return `${value} ${pluralize(value, 'проєкт', 'проєкти', 'проєктів')}`;
  }

  function formatStages(value) {
    return `${value} ${pluralize(value, 'етап', 'етапи', 'етапів')}`;
  }

  function formatMaps(value) {
    return `${value} ${pluralize(value, 'геомітка', 'геомітки', 'геоміток')}`;
  }

  function formatLoadedAt(value) {
    if (!value) return 'без часу оновлення';
    return new Intl.DateTimeFormat('uk-UA', {
      day: '2-digit',
      month: 'long',
      hour: '2-digit',
      minute: '2-digit',
    }).format(value);
  }

  function stageWeight(stage) {
    const index = stageOrder.indexOf(stage);
    return index === -1 ? 999 : index;
  }

  function sortStages(stages) {
    return [...stages].sort((a, b) => {
      const weightDiff = stageWeight(a) - stageWeight(b);
      return weightDiff !== 0 ? weightDiff : collator.compare(a, b);
    });
  }

  function classify(row) {
    const delivered = Array.isArray(row.delivered_what) ? row.delivered_what : [];
    const hasPanels = delivered.some(item => item.startsWith('Панелі'));
    const hasInverter = delivered.some(item => item.startsWith('Інвертор'));

    let type = 'all';
    if (hasPanels && hasInverter) type = 'both';
    else if (hasPanels) type = 'panels';
    else if (hasInverter) type = 'inverter';

    return {
      ...row,
      _delivered: delivered,
      _hasPanels: hasPanels,
      _hasInverter: hasInverter,
      _type: type,
      _search: [
        row.client_name,
        row.manager,
        row.stage,
        ...delivered,
      ].join(' ').toLocaleLowerCase('uk-UA'),
    };
  }

  function getFilteredRows() {
    const query = state.query.trim().toLocaleLowerCase('uk-UA');

    return state.rows.filter(row => {
      if (state.stage !== 'all' && row.stage !== state.stage) return false;
      if (state.type !== 'all' && row._type !== state.type) return false;
      if (query && !row._search.includes(query)) return false;
      return true;
    });
  }

  function countByStage(rows) {
    return rows.reduce((acc, row) => {
      acc[row.stage] = (acc[row.stage] || 0) + 1;
      return acc;
    }, {});
  }

  function countByType(rows) {
    return rows.reduce((acc, row) => {
      acc[row._type] = (acc[row._type] || 0) + 1;
      return acc;
    }, { panels: 0, inverter: 0, both: 0 });
  }

  function getVisibleStages(rows) {
    return sortStages(new Set(rows.map(row => row.stage)));
  }

  function renderSummary(filteredRows) {
    const total = state.rows.length;
    const visible = filteredRows.length;
    const typeCounts = countByType(filteredRows);
    const maps = filteredRows.filter(row => !!row.geo_link).length;
    const managers = new Set(
      filteredRows
        .map(row => row.manager)
        .filter(name => name && name !== '—')
    ).size;

    const stats = [
      {
        label: 'На екрані',
        value: visible,
        hint: total === visible ? 'Показано всі активні проєкти з доставкою' : `Із ${total} проєктів після фільтрів`,
        glow: 'rgba(106,196,255,.28)',
      },
      {
        label: 'Панелі на обʼєкті',
        value: typeCounts.panels + typeCounts.both,
        hint: 'Проєкти, де ФЕМ уже доставлено',
        glow: 'rgba(101,191,255,.22)',
      },
      {
        label: 'Інверторний комплект',
        value: typeCounts.inverter + typeCounts.both,
        hint: 'Інвертор, АКБ або BMS уже на місці',
        glow: 'rgba(255,182,124,.20)',
      },
      {
        label: 'Повний комплект',
        value: typeCounts.both,
        hint: 'Одразу панелі та інверторне обладнання',
        glow: 'rgba(81,209,146,.24)',
      },
      {
        label: 'З геоміткою',
        value: maps,
        hint: managers > 0 ? `Задіяно менеджерів: ${managers}` : 'Менеджер ще не вказаний',
        glow: 'rgba(115,141,255,.22)',
      },
    ];

    summaryEl.innerHTML = stats.map(stat => `
      <article class="dl-stat" style="--stat-glow:${stat.glow};">
        <div class="dl-stat__label">${esc(stat.label)}</div>
        <div class="dl-stat__value">${esc(stat.value)}</div>
        <div class="dl-stat__hint">${esc(stat.hint)}</div>
      </article>
    `).join('');
  }

  function renderStageFilters() {
    const counts = countByStage(state.rows);
    const stages = getVisibleStages(state.rows);

    stageFiltersEl.innerHTML = [
      {
        key: 'all',
        label: 'Усі етапи',
        count: state.rows.length,
      },
      ...stages.map(stage => ({
        key: stage,
        label: stage,
        count: counts[stage] || 0,
      })),
    ].map(item => `
      <button
        type="button"
        class="dl-chip ${state.stage === item.key ? 'is-active' : ''}"
        data-stage="${esc(item.key)}"
      >
        ${esc(item.label)}
        <span class="dl-chip__count">${esc(item.count)}</span>
      </button>
    `).join('');

    stageFiltersEl.querySelectorAll('[data-stage]').forEach(button => {
      button.addEventListener('click', () => {
        state.stage = button.dataset.stage || 'all';
        render();
      });
    });
  }

  function renderTypeFilters() {
    const counts = countByType(state.rows);
    const items = [
      { key: 'all', count: state.rows.length },
      { key: 'panels', count: counts.panels },
      { key: 'inverter', count: counts.inverter },
      { key: 'both', count: counts.both },
    ];

    typeFiltersEl.innerHTML = items.map(item => `
      <button
        type="button"
        class="dl-chip ${state.type === item.key ? 'is-active' : ''}"
        data-type="${esc(item.key)}"
      >
        ${esc(typeMeta[item.key].label)}
        <span class="dl-chip__count">${esc(item.count)}</span>
      </button>
    `).join('');

    typeFiltersEl.querySelectorAll('[data-type]').forEach(button => {
      button.addEventListener('click', () => {
        state.type = button.dataset.type || 'all';
        render();
      });
    });
  }

  function renderHeroStatus(filteredRows) {
    const pieces = [];
    pieces.push(filteredRows.length === state.rows.length
      ? `Показано всі ${state.rows.length} проєктів`
      : `Показано ${filteredRows.length} із ${state.rows.length} проєктів`);

    if (state.query.trim()) {
      pieces.push(`пошук: «${state.query.trim()}»`);
    }

    if (state.loadedAt) {
      pieces.push(`оновлено ${formatLoadedAt(state.loadedAt)}`);
    }

    heroStatusEl.textContent = pieces.join(' · ');
  }

  function renderStageRail(filteredRows) {
    if (!filteredRows.length) {
      stageRailEl.innerHTML = '';
      return;
    }

    const total = filteredRows.length;
    const stageCounts = countByStage(filteredRows);

    stageRailEl.innerHTML = getVisibleStages(filteredRows).map(stage => {
      const count = stageCounts[stage] || 0;
      const share = Math.round((count / total) * 100);
      const stageColor = stageColors[stage] || '#9aa6b2';
      const hint = `${formatProjects(count)} · ${share}% поточної вибірки`;

      return `
        <article class="dl-stage-rail__item" style="--stage-color:${stageColor};">
          <div class="dl-stage-rail__label">${esc(stage)}</div>
          <div class="dl-stage-rail__value">${esc(count)}</div>
          <div class="dl-stage-rail__hint">${esc(hint)}</div>
        </article>
      `;
    }).join('');
  }

  function renderResultNote(filteredRows) {
    if (!filteredRows.length) {
      resultNoteEl.textContent = 'Фільтри активні, але зараз у вибірці немає жодного проєкту.';
      return;
    }

    const parts = [
      `Показано ${formatProjects(filteredRows.length)}`,
      formatStages(getVisibleStages(filteredRows).length),
      formatMaps(filteredRows.filter(row => !!row.geo_link).length),
    ];

    if (state.stage !== 'all') {
      parts.push(`етап: ${state.stage}`);
    }

    if (state.type !== 'all') {
      parts.push(typeMeta[state.type].label.toLocaleLowerCase('uk-UA'));
    }

    if (state.query.trim()) {
      parts.push(`пошук: «${state.query.trim()}»`);
    }

    resultNoteEl.textContent = parts.join(' • ');
  }

  function renderEmpty() {
    resultsEl.innerHTML = `
      <div class="dl-empty">
        <h3 class="dl-empty__title">Нічого не знайдено</h3>
        <p class="dl-empty__text">
          Спробуйте змінити пошук або скинути фільтри. Дані по доставці залишилися,
          просто зараз вони не збігаються з вибраними умовами.
        </p>
        <div class="dl-empty__actions">
          <button type="button" class="btn" id="deliveredResetFilters">Скинути фільтри</button>
          <a href="/projects/project" class="dl-link-btn">Перейти до проектів</a>
        </div>
      </div>
    `;

    document.getElementById('deliveredResetFilters')?.addEventListener('click', () => {
      state.stage = 'all';
      state.type = 'all';
      state.query = '';
      searchInput.value = '';
      render();
    });
  }

  function renderCards(filteredRows) {
    const stageCounts = countByStage(filteredRows);

    resultsEl.innerHTML = `
      <div class="dl-stage-groups">
        ${getVisibleStages(filteredRows).map(stage => {
          const rows = filteredRows.filter(row => row.stage === stage);
          const stageColor = stageColors[stage] || '#9aa6b2';
          const withPanels = rows.filter(row => row._hasPanels).length;
          const withInverter = rows.filter(row => row._hasInverter).length;
          const withMap = rows.filter(row => !!row.geo_link).length;

          return `
            <section class="dl-stage-section" style="--stage-color:${stageColor};">
              <div class="dl-stage-section__head">
                <div class="dl-stage-section__top">
                  <div class="dl-stage-section__title-wrap">
                    <div class="dl-stage-section__eyebrow">Етап логістики</div>
                    <h2 class="dl-stage-section__title">${esc(stage)}</h2>
                  </div>
                  <div class="dl-stage-section__count">${esc(formatProjects(rows.length))}</div>
                </div>

                <p class="dl-stage-section__desc">${esc(stageDescriptions[stage] || 'Проєкти на поточному етапі доставки та монтажної підготовки.')}</p>

                <div class="dl-stage-section__metrics">
                  <span class="dl-stage-metric"><strong>${esc(withPanels)}</strong> з панелями</span>
                  <span class="dl-stage-metric"><strong>${esc(withInverter)}</strong> з інверторним комплектом</span>
                  <span class="dl-stage-metric"><strong>${esc(withMap)}</strong> з картою</span>
                </div>
              </div>

              <div class="dl-grid">
                ${rows.map((row, index) => {
                  const deliveryTags = row._delivered.map(item => {
                    const cls = item.startsWith('Панелі') ? 'dl-delivery-pill--panels' : 'dl-delivery-pill--inverter';
                    return `<span class="dl-delivery-pill ${cls}">${esc(item)}</span>`;
                  }).join('');

                  return `
                    <article class="card dl-card" style="--stage-color:${stageColor};">
                      <div class="dl-card__head">
                        <span class="dl-card__index">Проєкт ${index + 1}</span>
                        <span class="dl-stage-pill">${esc(row.stage)}</span>
                      </div>

                      <div>
                        <h3 class="dl-card__name">${esc(row.client_name)}</h3>
                      </div>

                      <div class="dl-card__meta">
                        <span class="dl-meta-chip">👤 ${esc(row.manager || '—')}</span>
                        <span class="dl-meta-chip">📦 ${esc(row._delivered.length)} ${esc(pluralize(row._delivered.length, 'позиція', 'позиції', 'позицій'))}</span>
                        <span class="dl-meta-chip">📍 ${esc(stageCounts[row.stage] || 0)} на цьому етапі</span>
                      </div>

                      <div class="dl-card__body">
                        <div class="dl-card__label">Доставлено на обʼєкт</div>
                        <div class="dl-card__tags">${deliveryTags}</div>
                      </div>

                      <div class="dl-card__footer">
                        ${row.geo_link
                          ? `<a class="btn dl-card__map" href="${esc(row.geo_link)}" target="_blank" rel="noopener">📍 Відкрити карту</a>`
                          : `<span class="dl-card__ghost">Геолокація ще не додана</span>`}
                      </div>
                    </article>
                  `;
                }).join('')}
              </div>
            </section>
          `;
        }).join('')}
      </div>
    `;
  }

  function render() {
    renderStageFilters();
    renderTypeFilters();

    const filteredRows = getFilteredRows();
    renderSummary(filteredRows);
    renderStageRail(filteredRows);
    renderResultNote(filteredRows);
    renderHeroStatus(filteredRows);

    if (!filteredRows.length) {
      renderEmpty();
      return;
    }

    renderCards(filteredRows);
  }

  function renderLoading() {
    summaryEl.innerHTML = `
      <article class="dl-stat"><div class="dl-loading"><div class="dl-loading__row"></div><div class="dl-loading__row"></div><div class="dl-loading__row"></div></div></article>
      <article class="dl-stat"><div class="dl-loading"><div class="dl-loading__row"></div><div class="dl-loading__row"></div><div class="dl-loading__row"></div></div></article>
      <article class="dl-stat"><div class="dl-loading"><div class="dl-loading__row"></div><div class="dl-loading__row"></div><div class="dl-loading__row"></div></div></article>
      <article class="dl-stat"><div class="dl-loading"><div class="dl-loading__row"></div><div class="dl-loading__row"></div><div class="dl-loading__row"></div></div></article>
      <article class="dl-stat"><div class="dl-loading"><div class="dl-loading__row"></div><div class="dl-loading__row"></div><div class="dl-loading__row"></div></div></article>
    `;

    stageRailEl.innerHTML = `
      <article class="dl-stage-rail__item"><div class="dl-loading"><div class="dl-loading__row"></div><div class="dl-loading__row"></div><div class="dl-loading__row"></div></div></article>
      <article class="dl-stage-rail__item"><div class="dl-loading"><div class="dl-loading__row"></div><div class="dl-loading__row"></div><div class="dl-loading__row"></div></div></article>
      <article class="dl-stage-rail__item"><div class="dl-loading"><div class="dl-loading__row"></div><div class="dl-loading__row"></div><div class="dl-loading__row"></div></div></article>
    `;

    resultNoteEl.textContent = 'Готуємо список проєктів…';

    resultsEl.innerHTML = `
      <div class="dl-loading">
        <div class="dl-loading__row"></div>
        <div class="dl-loading__row"></div>
        <div class="dl-loading__row"></div>
      </div>
    `;
  }

  function renderError() {
    summaryEl.innerHTML = '';
    stageRailEl.innerHTML = '';
    resultNoteEl.textContent = 'Не вдалося побудувати огляд доставок.';
    resultsEl.innerHTML = `
      <div class="dl-error">
        <h3 class="dl-error__title">Не вдалося завантажити сторінку</h3>
        <p class="dl-error__text">
          API не повернув дані по доставці. Оновіть сторінку або перевірте доступ до проєктів.
        </p>
      </div>
    `;
    heroStatusEl.textContent = 'Сталася помилка під час завантаження';
  }

  async function loadDelivered() {
    renderLoading();

    const response = await fetch('/api/projects/delivered', {
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
      throw new Error('Request failed');
    }

    const rows = await response.json();
    state.rows = rows
      .map(classify)
      .sort((a, b) => {
        const weightDiff = stageWeight(a.stage) - stageWeight(b.stage);
        return weightDiff !== 0 ? weightDiff : collator.compare(a.client_name, b.client_name);
      });
    state.loadedAt = new Date();

    if (!state.rows.length) {
      summaryEl.innerHTML = '';
      stageRailEl.innerHTML = '';
      resultsEl.innerHTML = `
        <div class="dl-empty">
          <h3 class="dl-empty__title">Поки що немає доставлених проектів</h3>
          <p class="dl-empty__text">
            Щойно на активному проєкті зʼявиться доставлене обладнання, він одразу зʼявиться тут.
          </p>
        </div>
      `;
      heroStatusEl.textContent = 'Активних доставок на обʼєкти ще немає';
      resultNoteEl.textContent = 'Щойно обладнання зʼявиться на обʼєкті, сторінка заповниться автоматично.';
      stageFiltersEl.innerHTML = '';
      typeFiltersEl.innerHTML = '';
      return;
    }

    render();
  }

  searchInput.addEventListener('input', event => {
    state.query = event.target.value || '';
    render();
  });

  loadDelivered().catch(renderError);
});
</script>

@include('partials.nav.bottom')
@endsection
