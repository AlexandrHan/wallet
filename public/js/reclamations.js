(function () {
  'use strict';

  const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

  // =========================
  // Splash
  // =========================
  function hideSplash() {
    const splash = document.getElementById('appSplash');
    if (!splash) return;

    try { sessionStorage.setItem('sg_splash_shown', '1'); } catch (e) {}
    document.documentElement.classList.add('no-splash');

    splash.style.transition = 'opacity .22s ease';
    splash.style.opacity = '0';
    setTimeout(() => splash.remove(), 240);
  }

  // =========================
  // Create Wizard (create page)
  // =========================
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

    // loaner segmented (create page)
    const seg = form.querySelector('[data-loaner]')?.closest('.segmented');
    const hasLoanerInput = form.querySelector('input[name="has_loaner"]');
    const loanerOrderedInput = form.querySelector('input[name="loaner_ordered"]');
    const loanerBox = document.getElementById('loanerOrderBox');
    const loanerChk = document.getElementById('loanerOrderedChk');

    form.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-loaner]');
      if (!btn) return;

      const val = btn.getAttribute('data-loaner'); // '1' | '0'
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

  // =========================
  // Steps modal (show page)
  // =========================
  function initStepsModal() {
    const modal = document.getElementById('stepModal');
    if (!modal || !window.RECL) return false;

    const panel = modal.querySelector('.modal-panel');
    const backdrop = modal.querySelector('.modal-backdrop');

    const titleEl = document.getElementById('stepTitle');

    // wrappers
    const dateWrap = document.getElementById('dateWrap');
    const ttnWrap  = document.getElementById('ttnWrap');

    // inputs
    const dateEl = document.getElementById('stepDate');
    const ttnEl  = document.getElementById('stepTTN');
    const noteEl = document.getElementById('stepNote');
    const extraEl = document.getElementById('stepExtra');

    let currentStep = null;

    const resetPanelTransform = () => {
      if (panel) {
        panel.style.transform = '';
        panel.style.transition = '';
        panel.classList.remove('dragging');
      }
      if (backdrop) backdrop.style.opacity = '';
    };

    const closeModal = () => {
      modal.classList.add('hidden');
      resetPanelTransform();
    };

    const openModal = (label, stepKey) => {
      currentStep = stepKey;
      if (titleEl) titleEl.textContent = label || 'Етап';

      if (extraEl) extraEl.innerHTML = '';

      const stepData = window.RECL?.steps?.[stepKey] || null;

      // не затираємо, а підставляємо з БД
      if (dateEl) dateEl.value = stepData?.done_date || '';
      if (ttnEl)  ttnEl.value  = stepData?.ttn || '';
      if (noteEl) noteEl.value = stepData?.note || '';





      // звичайні поля (для більшості етапів)
      if (stepData) {
        if (dateEl && stepData.done_date) dateEl.value = stepData.done_date;
        if (ttnEl && stepData.ttn) ttnEl.value = stepData.ttn;
        if (noteEl && stepData.note) noteEl.value = stepData.note;
      }


      // default show/hide
      const cfg = {
        reported:              { date:false, ttn:false, note:false, photos:false },
        dismantled:            { date:true,  ttn:false, note:true,  photos:true  },
        where_left:            { date:false, ttn:false, note:false, photos:false },
        shipped_to_service:    { date:false, ttn:true,  note:true,  photos:false },
        service_received:      { date:true,  ttn:false, note:true,  photos:true  },
        repaired_shipped_back: { date:false, ttn:true,  note:true,  photos:true  },
        installed:             { date:false, ttn:false, note:true,  photos:true  },
        loaner_return:         { date:false, ttn:false, note:false, photos:false },
        closed:                { date:false, ttn:false, note:false, photos:false },
      };

      const c = cfg[stepKey] || { date:true, ttn:true, note:true, photos:false };

      if (dateWrap) dateWrap.classList.toggle('hidden', !c.date);
      if (ttnWrap)  ttnWrap.classList.toggle('hidden', !c.ttn);
      if (noteEl)   noteEl.classList.toggle('hidden', !c.note);

      // photos UI (не затираємо special UIs, тому тільки додаємо, якщо extraEl ще не переписаний)
      if (extraEl && c.photos) {
        extraEl.innerHTML += `
          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Фото</div>
            <input id="stepPhoto" type="file" accept="image/*" capture="environment" class="btn" />
            <button type="button" id="stepPhotoUpload" class="btn primary" style="margin-top:10px; width:100%;">Додати фото</button>
            <div id="stepPhotoMsg" class="muted" style="margin-top:8px;"></div>
          </div>
        `;
      }
      // показ вже завантажених фото
      if (stepData?.files?.length) {

        extraEl.innerHTML += `
          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Завантажені фото</div>
            <div class="step-photos"></div>
          </div>
        `;

        const box = extraEl.querySelector('.step-photos');

        stepData.files.forEach(path => {
          const url = '/storage/' + path;

        box.innerHTML += `
          <a href="${url}" data-img-viewer>
            <img src="${url}" class="step-photo-preview">
          </a>
        `;

        });
      }


      // =========================
      // special UIs (переписують extraEl повністю)
      // =========================
      if (stepKey === 'reported' && extraEl) {
        dateWrap?.classList.add('hidden');
        ttnWrap?.classList.add('hidden');
        noteEl?.classList.add('hidden');

        extraEl.innerHTML = `
          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Дата звернення</div>
            <input id="rReportedAt" class="btn btn-modal" type="date" />
          </div>

          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Прізвище</div>
            <input id="rLastName" class="btn btn-modal" placeholder="Напр. Іваненко" />
          </div>

          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Населений пункт</div>
            <input id="rCity" class="btn btn-modal" placeholder="Напр. Черкаси" />
          </div>

          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Телефон</div>
            <input id="rPhone" class="btn btn-modal" placeholder="+380..." />
          </div>

          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Серійний номер</div>
            <input id="rSerialNumber" class="btn btn-modal" placeholder="Напр. DEY-8K-39420" />
          </div>

          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Опис проблеми</div>
            <textarea id="rProblem" class="btn btn-modal" placeholder="Що зламалось / що не працює" style="min-height:90px;"></textarea>
          </div>

          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Підмінний фонд</div>
            <div class="segmented" style="width:100%;">
              <button type="button" data-loaner="1" class="active">Є</button>
              <button type="button" data-loaner="0">Нема</button>
            </div>

            <div id="rLoanerOrderWrap" class="loaner-order hidden" style="margin-top:10px;">
              <button type="button" id="rLoanerOrderToggle" class="toggle" aria-pressed="false">
                <span class="dot"></span>
              </button>
              <div class="loaner-order-text">
                <div style="font-weight:900;">Замовити підмінний</div>
                <div class="muted" style="font-size:12px; margin-top:2px;">Якщо немає в наявності</div>
              </div>
            </div>

            <input type="hidden" id="rLoanerOrdered" value="0" />
          </div>
        `;
        // prefill reported inputs
          const r = window.RECL?.rec || {};
          document.getElementById('rReportedAt') && (document.getElementById('rReportedAt').value = r.reported_at || '');
          document.getElementById('rLastName') && (document.getElementById('rLastName').value = r.last_name || '');
          document.getElementById('rCity') && (document.getElementById('rCity').value = r.city || '');
          document.getElementById('rPhone') && (document.getElementById('rPhone').value = r.phone || '');
          document.getElementById('rSerialNumber') && (document.getElementById('rSerialNumber').value = r.serial_number || '');
          document.getElementById('rProblem') && (document.getElementById('rProblem').value = r.problem || '');

          // segmented has_loaner
          const loanerSeg = extraEl.querySelector('.segmented');
          if (loanerSeg) {
            loanerSeg.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            const want = (r.has_loaner ? '1' : '0');
            const activeBtn = loanerSeg.querySelector(`[data-loaner="${want}"]`);
            activeBtn?.classList.add('active');

            const wrap = document.getElementById('rLoanerOrderWrap');
            const hidden = document.getElementById('rLoanerOrdered');
            const tgl = document.getElementById('rLoanerOrderToggle');

            const needOrder = want === '0';
            wrap?.classList.toggle('hidden', !needOrder);

            if (hidden) hidden.value = r.loaner_ordered ? '1' : '0';
            if (tgl) tgl.setAttribute('aria-pressed', r.loaner_ordered ? 'true' : 'false');
          }

      }

      if (stepKey === 'where_left' && extraEl) {
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

      if (stepKey === 'loaner_return' && extraEl) {
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

      if (stepKey === 'closed' && extraEl) {
        dateWrap?.classList.add('hidden');
        ttnWrap?.classList.add('hidden');
        noteEl?.classList.add('hidden');

        extraEl.innerHTML = `
          <div class="card" style="margin-top:10px;">
            <div class="muted" style="margin-bottom:8px;">Закрити рекламацію</div>

            <div class="segmented" style="width:100%;">
              <button type="button" data-close="1" class="active">Так</button>
              <button type="button" data-close="0">Ні</button>
            </div>

            <div class="muted" style="font-size:12px; margin-top:8px;">
              “Так” поставить статус “Завершено” і дату закриття.
            </div>
          </div>
        `;
      }

      modal.classList.remove('hidden');
    };

    // =========================
    // Swipe down to close
    // =========================
    (function setupSwipeToClose() {
      if (!panel) return;

      let startY = 0;
      let startX = 0;
      let currentY = 0;
      let dragging = false;

      const THRESHOLD = 90;
      const MAX_UP = -12;

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
        if (modal.classList.contains('hidden')) return;

        // ⛔ НЕ стартуємо свайп, якщо клік по будь-якому інтерактиву
        if (e.target.closest('button, a, input, textarea, select, label, [role="button"], .segmented, .toggle')) {
          return;
        }

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

        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) {
          dragging = false;
          reset();
          return;
        }

        currentY = Math.max(MAX_UP, dy);
        if (currentY > 0) e.preventDefault();

        setTranslate(currentY);

        if (backdrop) {
          const k = Math.max(0.25, 1 - (currentY / 420));
          backdrop.style.opacity = String(k);
        }
      };

      const onPointerUp = () => {
        if (!dragging) return;
        dragging = false;

        if (backdrop) backdrop.style.opacity = '';

        if (currentY > THRESHOLD) {
          panel.style.transition = 'transform .18s ease';
          setTranslate(Math.min(currentY + 120, 520));
          setTimeout(() => {
            resetPanelTransform();
            closeModal();
          }, 170);
          return;
        }

        reset();
      };

      panel.addEventListener('pointerdown', onPointerDown, { passive: false });
      window.addEventListener('pointermove', onPointerMove, { passive: false });
      window.addEventListener('pointerup', onPointerUp);
      window.addEventListener('pointercancel', onPointerUp);
    })();

    // =========================
    // Open/close by click
    // =========================
    document.addEventListener('click', (e) => {
      if (e.target.closest('#stepClose') || e.target.classList.contains('modal-backdrop')) {
        closeModal();
        return;
      }

      const stepWrap = e.target.closest('.step[data-step]');
      if (stepWrap) {
        const stepKey = stepWrap.getAttribute('data-step');
        const label = stepWrap.querySelector('.step-title')?.textContent?.trim() || 'Етап';
        if (stepKey) openModal(label, stepKey);
      }
    });

    // =========================
    // Modal interactions (segmented + toggle + upload)
    // =========================
    modal.addEventListener('click', async (e) => {
      // where_left segmented
      const w = e.target.closest('[data-where]');
      if (w) {
        const seg = w.closest('.segmented');
        seg?.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        w.classList.add('active');
      }

      // loaner_return segmented
      const lr = e.target.closest('[data-loaner-ret]');
      if (lr) {
        const seg = lr.closest('.segmented');
        seg?.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        lr.classList.add('active');
      }

      // closed segmented
      const cbtn = e.target.closest('[data-close]');
      if (cbtn) {
        const seg = cbtn.closest('.segmented');
        seg?.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        cbtn.classList.add('active');
      }

      // reported: has loaner segmented
      const loanerBtn = e.target.closest('[data-loaner]');
      if (loanerBtn) {
        const seg = loanerBtn.closest('.segmented');
        seg?.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        loanerBtn.classList.add('active');

        const wrap = document.getElementById('rLoanerOrderWrap');
        const hidden = document.getElementById('rLoanerOrdered');
        const tgl = document.getElementById('rLoanerOrderToggle');

        const needOrder = loanerBtn.getAttribute('data-loaner') === '0';
        wrap?.classList.toggle('hidden', !needOrder);

        if (!needOrder) {
          if (hidden) hidden.value = '0';
          if (tgl) tgl.setAttribute('aria-pressed', 'false');
        }
      }

      // reported: toggle "loaner ordered"
      const tgl = e.target.closest('button#rLoanerOrderToggle');
      if (tgl) {
        const pressed = tgl.getAttribute('aria-pressed') === 'true';
        tgl.setAttribute('aria-pressed', pressed ? 'false' : 'true');
        const hidden = document.getElementById('rLoanerOrdered');
        if (hidden) hidden.value = pressed ? '0' : '1';
      }

      // Upload photo (кнопка)
      const upBtn = e.target.closest('#stepPhotoUpload');
      if (upBtn) {
        if (!currentStep) return;

        const fileInput = document.getElementById('stepPhoto');
        const msg = document.getElementById('stepPhotoMsg');
        const file = fileInput?.files?.[0];

        if (!file) {
          if (msg) msg.textContent = 'Вибери фото';
          return;
        }

        upBtn.disabled = true;
        if (msg) msg.textContent = 'Завантаження…';

        const fd = new FormData();
        fd.append('step_key', currentStep);
        fd.append('file', file);

        const res = await fetch(window.RECL.uploadUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
          body: fd,
        });

        upBtn.disabled = false;

        if (!res.ok) {
          let text = 'Помилка завантаження';
          try { text = (await res.json())?.message || text; } catch (err) {}
          if (msg) msg.textContent = text;
          return;
        }

        if (msg) msg.textContent = 'Готово ✅';
        setTimeout(() => location.reload(), 250);
      }
    });

    // =========================
    // Save
    // =========================
    document.getElementById('stepSave')?.addEventListener('click', async () => {
      if (!currentStep) return;

      let where_left = null;
      let loaner_return_to = null;

      const whereSegBtn = extraEl?.querySelector('[data-where].active');
      if (whereSegBtn) where_left = whereSegBtn.getAttribute('data-where');

      const loanerSegBtn = extraEl?.querySelector('[data-loaner-ret].active');
      if (loanerSegBtn) loaner_return_to = loanerSegBtn.getAttribute('data-loaner-ret');

      // close toggle (тільки для closed)
      let close = null;
      if (currentStep === 'closed') {
        const closeBtn = extraEl?.querySelector('[data-close].active');
        close = closeBtn ? closeBtn.getAttribute('data-close') : '1';
      }

      let payload;

      if (currentStep === 'reported') {
        const reported_at = document.getElementById('rReportedAt')?.value || null;
        const last_name   = (document.getElementById('rLastName')?.value || '').trim();
        const city        = (document.getElementById('rCity')?.value || '').trim();
        const phone       = (document.getElementById('rPhone')?.value || '').trim();
        const serial_number = (document.getElementById('rSerialNumber')?.value || '').trim();
        const problem     = (document.getElementById('rProblem')?.value || '').trim();

        const hasLoanerBtn = extraEl?.querySelector('[data-loaner].active');
        const has_loaner = hasLoanerBtn ? hasLoanerBtn.getAttribute('data-loaner') : '1';

        const loaner_ordered = document.getElementById('rLoanerOrdered')?.value || '0';

        payload = { reported_at, last_name, city, phone, serial_number, problem, has_loaner, loaner_ordered };
      } else {
        payload = {
          done_date: dateEl?.value || null,
          ttn: ttnEl?.value || null,
          note: noteEl?.classList.contains('hidden') ? null : (noteEl?.value || null),
          where_left,
          loaner_return_to,
          close, // важливо для closed
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
        try { msg = (await res.json())?.message || msg; } catch (err) {}
        alert(msg);
        return;
      }

      location.reload();
    });


    return true;
  }

  function initImageViewer(){
    const viewer = document.getElementById('imgViewer');
    const img = document.getElementById('imgViewerImg');
    if (!viewer || !img) return;

    const open = (src) => {
      img.src = src;
      viewer.classList.remove('hidden');
      document.documentElement.classList.add('no-scroll');
      document.body.classList.add('no-scroll');
    };

    const close = () => {
      viewer.classList.add('hidden');
      img.src = '';
      document.documentElement.classList.remove('no-scroll');
      document.body.classList.remove('no-scroll');
    };

    // делегований клік по всім фото
    document.addEventListener('click', (e) => {
      const a = e.target.closest('a[data-img-viewer]');
      if (!a) return;

      e.preventDefault();
      const src = a.getAttribute('href');
      if (src) open(src);
    });

    // закриття: клік по фону або кнопці
    viewer.addEventListener('click', (e) => {
      // закриваємо по кліку на фон, хрестик або будь-де поза картинкою
      if (
        e.target.closest('.img-viewer-close') ||
        e.target.classList.contains('img-viewer-backdrop') ||
        (!e.target.closest('.img-viewer-img') && !e.target.closest('#imgViewerImg'))
      ) {
        close();
      }
    });


    // Esc
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !viewer.classList.contains('hidden')) close();
    });
  }


  // =========================
  // Client history accordion
  // =========================
  function initClientHistory(){
    const card = document.getElementById('clientCard');
    const box  = document.getElementById('clientHistory');
    if (!card || !box || !window.RECL?.id) return;

    let loaded = false;
    let open = false;

    const toggle = async () => {
      open = !open;
      box.classList.toggle('hidden', !open);

      if (open && !loaded) {
        box.innerHTML = '<div class="muted">Завантаження…</div>';

        const res = await fetch(`/reclamations/${window.RECL.id}/history`, {
          headers: { 'Accept': 'application/json' }
        });

        if (!res.ok) {
          box.innerHTML = '<div class="muted">Не вдалося завантажити історію.</div>';
          return;
        }

        const data = await res.json();
        box.innerHTML = data.html || '<div class="muted">Історія порожня.</div>';
        loaded = true;
      }
    };

    card.addEventListener('click', (e) => {
      e.preventDefault();
      toggle();
    });
  }

  // =========================
  // Boot
  // =========================
  document.addEventListener('DOMContentLoaded', () => {
    hideSplash();
    initCreateWizard();
    initStepsModal();
    initClientHistory();
    initIndexSearchPanel(); 
    initImageViewer();

  });

    function initIndexSearchPanel(){
      const toggleBtn = document.getElementById('searchToggleBtn');
      const panel = document.getElementById('searchPanel');
      if (!toggleBtn || !panel) return;

      const statusWrap = document.getElementById('statusFilters');
      const statusInput = document.getElementById('statusInput');

      statusWrap?.addEventListener('click', (e) => {
        const pill = e.target.closest('[data-status]');
        if (!pill) return;

        const val = pill.getAttribute('data-status');

        const isActive = pill.classList.contains('active');
        statusWrap.querySelectorAll('button').forEach(b => b.classList.remove('active'));

        if (isActive) {
          if (statusInput) statusInput.value = '';
        } else {
          pill.classList.add('active');
          if (statusInput) statusInput.value = val;
        }
      });

      toggleBtn.addEventListener('click', () => {
        panel.classList.toggle('hidden');
      });
    }



})();
