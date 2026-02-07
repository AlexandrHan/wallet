(function () {
  'use strict';

  const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

  function hideSplash() {
    const splash = document.getElementById('appSplash');
    if (!splash) return;

    // якщо вже було позначено як показаний раніше
    try { sessionStorage.setItem('sg_splash_shown', '1'); } catch(e) {}
    document.documentElement.classList.add('no-splash');

    splash.style.opacity = '0';
    splash.style.transition = 'opacity .22s ease';
    setTimeout(() => splash.remove(), 240);
  }

  // ===== CREATE WIZARD =====
  function initCreateWizard() {
    const form = document.getElementById('reclCreateForm');
    if (!form) return false;

    const steps = Array.from(form.querySelectorAll('.wizard-step'));
    let idx = 0;

    const show = (i) => {
      idx = Math.max(0, Math.min(steps.length - 1, i));
      steps.forEach((s, n) => s.classList.toggle('hidden', n !== idx));
    };

    form.addEventListener('click', (e) => {
      const next = e.target.closest('[data-next]');
      const prev = e.target.closest('[data-prev]');
      if (next) { e.preventDefault(); show(idx + 1); }
      if (prev) { e.preventDefault(); show(idx - 1); }
    });

    // loaner segmented
    const seg = form.querySelector('[data-loaner]')?.closest('.segmented');
    const hasLoanerInput = form.querySelector('input[name="has_loaner"]');
    const loanerOrderedInput = form.querySelector('input[name="loaner_ordered"]');
    const loanerBox = document.getElementById('loanerOrderBox');
    const loanerChk = document.getElementById('loanerOrderedChk');

    form.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-loaner]');
      if (!btn) return;

      const val = btn.getAttribute('data-loaner'); // '1' or '0'
      seg?.querySelectorAll('button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      if (hasLoanerInput) hasLoanerInput.value = val;

      const needOrder = (val === '0');
      loanerBox?.classList.toggle('hidden', !needOrder);
      if (!needOrder && loanerOrderedInput) loanerOrderedInput.value = '0';
    });

    loanerChk?.addEventListener('change', () => {
      if (loanerOrderedInput) loanerOrderedInput.value = loanerChk.checked ? '1' : '0';
    });

    show(0);
    return true;
  }

  // ===== SHOW: STEPS =====
  function initStepsModal() {
    const modal = document.getElementById('stepModal');
    if (!modal || !window.RECL) return false;

    const titleEl = document.getElementById('stepTitle');
    const dateEl  = document.getElementById('stepDate');
    const ttnEl   = document.getElementById('stepTTN');
    const dateWrap = document.getElementById('dateWrap');
    const ttnWrap  = document.getElementById('ttnWrap');

    const noteEl  = document.getElementById('stepNote');
    const extraEl = document.getElementById('stepExtra');

    let currentStep = null;

    const openModal = (label, stepKey) => {
      currentStep = stepKey;
      titleEl.textContent = label;

      dateEl.value = '';
      ttnEl.value = '';
      noteEl.value = '';
      extraEl.innerHTML = '';
      // які поля показувати на якому етапі
      const cfg = {
        dismantled:            { date:true,  ttn:false, note:true  },
        where_left:            { date:false, ttn:false, note:false }, // там буде сегмент в extra
        shipped_to_service:    { date:false, ttn:true,  note:true  },
        service_received:      { date:true,  ttn:false, note:true  },
        repaired_shipped_back: { date:false, ttn:true,  note:true  },
        installed:             { date:false, ttn:false, note:true  }, // коментар обовʼязково (бек це вже перевіряє)
        loaner_return:         { date:false, ttn:false, note:false }, // сегмент в extra
        closed:                { date:true,  ttn:false, note:true  },
      };

      const c = cfg[stepKey] || { date:true, ttn:true, note:true };

      // show/hide
      if (dateWrap) dateWrap.classList.toggle('hidden', !c.date);
      if (ttnWrap)  ttnWrap.classList.toggle('hidden', !c.ttn);
      noteEl.classList.toggle('hidden', !c.note);
    if (stepKey === 'reported') {
      // ховаємо стандартні поля
      dateWrap?.classList.add('hidden');
      ttnWrap?.classList.add('hidden');
      noteEl.classList.add('hidden');

  extraEl.innerHTML = `
    <div class="card" style="margin-top:10px;">
      <div class="muted" style="margin-bottom:8px;">Дата звернення</div>
      <input id="rReportedAt" class="btn" type="date" />
    </div>

    <div class="card" style="margin-top:10px;">
      <div class="muted" style="margin-bottom:8px;">Прізвище</div>
      <input id="rLastName" class="btn" placeholder="Напр. Іваненко" />
    </div>

    <div class="card" style="margin-top:10px;">
      <div class="muted" style="margin-bottom:8px;">Населений пункт</div>
      <input id="rCity" class="btn" placeholder="Напр. Черкаси" />
    </div>

    <div class="card" style="margin-top:10px;">
      <div class="muted" style="margin-bottom:8px;">Телефон</div>
      <input id="rPhone" class="btn" placeholder="+380..." />
    </div>

    <div class="card" style="margin-top:10px;">
      <div class="muted" style="margin-bottom:8px;">Опис проблеми</div>
      <textarea id="rProblem" class="btn" placeholder="Що зламалось / що не працює" style="min-height:90px;"></textarea>
    </div>


    <div class="card" style="margin-top:10px;">
      <div class="muted" style="margin-bottom:8px;">Підмінний фонд</div>
      <div class="segmented" style="width:100%;">
        <button type="button" data-loaner="1" class="active">Є</button>
        <button type="button" data-loaner="0">Нема</button>
      </div>
      <label id="rLoanerOrderWrap" class="row" style="margin-top:10px; display:none;">
        <input id="rLoanerOrdered" type="checkbox" />
        <span class="muted">Замовити підмінний</span>
      </label>
    </div>
  `;
}


      if (stepKey === 'where_left') {
        extraEl.innerHTML = `
          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Де залишили</div>
            <div class="segmented" style="width:100%;">
              <button type="button" data-where="warehouse" class="active">На складі</button>
              <button type="button" data-where="service">Відправили на ремонт</button>
            </div>
          </div>
        `;
      }

      if (stepKey === 'loaner_return') {
        extraEl.innerHTML = `
          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Підмінний</div>
            <div class="segmented" style="width:100%;">
              <button type="button" data-loaner-ret="warehouse" class="active">На склад</button>
              <button type="button" data-loaner-ret="supplier">Постачальнику</button>
            </div>
          </div>
        `;
      }

    modal.classList.remove('hidden');
    return;
  }

    const closeModal = () => {
  modal.classList.add('hidden');
  const panel = modal.querySelector('.modal-panel');
  const backdrop = modal.querySelector('.modal-backdrop');
  if (panel) panel.style.transform = '';
  if (panel) panel.classList.remove('dragging');
  if (backdrop) backdrop.style.opacity = '';
};

    // ===== SWIPE DOWN TO CLOSE (mobile friendly) =====
(function setupSwipeToClose(){
  const panel = modal.querySelector('.modal-panel');
  const backdrop = modal.querySelector('.modal-backdrop');
  if (!panel) return;

  let startY = 0;
  let startX = 0;
  let currentY = 0;
  let dragging = false;

  const THRESHOLD = 90;      // скільки протягнути щоб закрити
  const MAX_UP = -12;        // трошки дозволимо "вгору" (пружина)

  const setTranslate = (y) => {
    panel.style.transform = `translate3d(0, ${y}px, 0)`;
  };

  const reset = () => {
    panel.classList.remove('dragging');
    panel.style.transition = 'transform .18s ease';
    setTranslate(0);
    setTimeout(() => { panel.style.transition = ''; }, 200);
  };

  const onPointerDown = (e) => {
    // не стартуємо drag якщо клік в input/textarea/select
    const tag = e.target?.tagName?.toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

    // якщо модалка прихована - нічого
    if (modal.classList.contains('hidden')) return;

    dragging = true;
    startY = e.clientY;
    startX = e.clientX;
    currentY = 0;

    panel.classList.add('dragging');
    panel.style.transition = 'none';
  };

  const onPointerMove = (e) => {
    if (!dragging) return;

    const dy = e.clientY - startY;
    const dx = e.clientX - startX;

    // якщо більше "вбік" ніж "вниз" — відпускаємо, щоб не ламати горизонтальні жести
    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) {
      dragging = false;
      reset();
      return;
    }

    // тягнемо тільки вниз (вгору мінімально)
    currentY = Math.max(MAX_UP, dy);

    // якщо тягнемо вниз — блокуємо скрол під модалкою
    if (currentY > 0) e.preventDefault();

    setTranslate(currentY);
    // трохи затемнення бекдропа менше при тягненні
    if (backdrop) {
      const k = Math.max(0.25, 1 - (currentY / 420));
      backdrop.style.opacity = String(k);
    }
  };

  const onPointerUp = () => {
    if (!dragging) return;
    dragging = false;

    // відновлюємо бекдроп
    if (backdrop) backdrop.style.opacity = '';

    if (currentY > THRESHOLD) {
      // закриваємо
      panel.style.transition = 'transform .18s ease';
      setTranslate(Math.min(currentY + 120, 520));
      setTimeout(() => {
        panel.style.transform = '';
        panel.style.transition = '';
        closeModal();
      }, 170);
      return;
    }

    reset();
  };

  // pointer events (працює і на touch і на миші)
  panel.addEventListener('pointerdown', onPointerDown, { passive: false });
  window.addEventListener('pointermove', onPointerMove, { passive: false });
  window.addEventListener('pointerup', onPointerUp);
  window.addEventListener('pointercancel', onPointerUp);
})();


    document.addEventListener('click', (e) => {
      // 1) закриття модалки
      if (e.target.closest('#stepClose') || e.target.classList.contains('modal-backdrop')) {
        closeModal();
        return;
      }

      // 2) клік по етапу (по всій картці/блоці)
      const stepWrap = e.target.closest('.step[data-step]');
      if (stepWrap) {
        const stepKey = stepWrap.getAttribute('data-step');
        const label = stepWrap.querySelector('.step-title')?.textContent?.trim() || 'Етап';

        openModal(label, stepKey);
        return;
      }
    });


    document.getElementById('stepSave')?.addEventListener('click', async () => {
      if (!currentStep) return;

      let where_left = null;
      let loaner_return_to = null;

      const whereSegBtn = extraEl.querySelector('[data-where].active');
      if (whereSegBtn) where_left = whereSegBtn.getAttribute('data-where');

      const loanerSegBtn = extraEl.querySelector('[data-loaner-ret].active');
      if (loanerSegBtn) loaner_return_to = loanerSegBtn.getAttribute('data-loaner-ret');

      let payload;

      if (currentStep === 'reported') {
        const reported_at = document.getElementById('rReportedAt')?.value || null;
        const last_name   = document.getElementById('rLastName')?.value || '';
        const city        = document.getElementById('rCity')?.value || '';
        const phone       = document.getElementById('rPhone')?.value || '';
        const problem = document.getElementById('rProblem')?.value || '';


        const hasLoanerBtn = extraEl.querySelector('[data-loaner].active');

        const has_loaner = hasLoanerBtn ? hasLoanerBtn.getAttribute('data-loaner') : '1';

        const loaner_ordered = document.getElementById('rLoanerOrdered')?.checked ? '1' : '0';

        payload = { reported_at, last_name, city, phone, problem, has_loaner, loaner_ordered };
      } else {
        payload = {
          done_date: dateEl.value || null,
          ttn: ttnEl.value || null,
          note: noteEl.value || null,
          where_left,
          loaner_return_to,
        };
      }


      const url = window.RECL.saveUrl.replace('__STEP__', currentStep);

      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        let msg = 'Помилка збереження';
        try { msg = (await res.json())?.message || msg; } catch(e){}
        alert(msg);
        return;
      }

      location.reload();
    });

    modal.addEventListener('click', (e) => {
      const w = e.target.closest('[data-where]');
      if (w) {
        const seg = w.closest('.segmented');
        seg.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        w.classList.add('active');
      }

      const lr = e.target.closest('[data-loaner-ret]');
      if (lr) {
        const seg = lr.closest('.segmented');
        seg.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        lr.classList.add('active');
      }
      const loanerBtn = e.target.closest('[data-loaner]');
      if (loanerBtn) {
        const seg = loanerBtn.closest('.segmented');
        seg.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        loanerBtn.classList.add('active');

        const wrap = document.getElementById('rLoanerOrderWrap');
        if (wrap) wrap.style.display = (loanerBtn.getAttribute('data-loaner') === '0') ? 'flex' : 'none';
      }

    });

    return true;
  }

  document.addEventListener('DOMContentLoaded', () => {
    hideSplash();        // важливо, бо інакше splash блокує кліки
    initCreateWizard();  // якщо це сторінка створення
    initStepsModal();    // якщо це сторінка рекламації
  });
})();


