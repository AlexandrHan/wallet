(function () {
  'use strict';

  const cfg = window.sgPushConfig;

  if (!cfg || !cfg.apiKey || !cfg.projectId) return;
  if (typeof firebase === 'undefined') return;
  if (!('serviceWorker' in navigator) || !('Notification' in window)) return;

  const isIOS        = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.navigator.standalone === true
    || window.matchMedia('(display-mode: standalone)').matches;

  // ── Init Firebase ─────────────────────────────────────────────────────────
  if (!firebase.apps.length) {
    firebase.initializeApp({
      apiKey:            cfg.apiKey,
      authDomain:        cfg.authDomain,
      projectId:         cfg.projectId,
      messagingSenderId: cfg.messagingSenderId,
      appId:             cfg.appId,
    });
  }

  let messaging = null;
  let swReg     = null;

  // ── Register SW + init messaging ──────────────────────────────────────────
  async function boot() {
    if (Notification.permission === 'denied') {
      showPushBtn('denied');
      return;
    }

    if (isIOS && !isStandalone) {
      showPushBtn('ios');
      return;
    }

    try {
      swReg = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
      await navigator.serviceWorker.ready;
    } catch (e) {
      console.warn('[Push] SW failed:', e.message);
      return;
    }

    try {
      messaging = firebase.messaging();
    } catch (e) {
      console.warn('[Push] messaging() failed:', e.message);
      return;
    }

    // Foreground handler — fires when app is open/focused
    messaging.onMessage((payload) => {
      const data       = payload.data ?? {};
      const targetPath = data.url || '/';
      const badgeCount = Number(data.badge) || 0;
      const notifId    = Number(data.notification_id) || 0;

      // Always show the system notification (needed for on-screen alerts on mobile)
      if (badgeCount > 0 && 'setAppBadge' in navigator) {
        navigator.setAppBadge(badgeCount).catch(() => {});
      }
      const title = payload.notification?.title || data.title || 'SolarGlass';
      const body  = payload.notification?.body  || data.body  || '';

      // Skip visual + sound if WebSocket already handled this notification
      const onTargetPage    = window.location.pathname.startsWith(targetPath) && targetPath !== '/';
      const wsAlreadyPlayed = notifId && notifId === window._sgLastNotifId;
      if (onTargetPage || wsAlreadyPlayed) return;

      if (Notification.permission === 'granted') {
        swReg.showNotification(title, {
          body, icon: '/img/logo.png', badge: '/img/logo.png',
          data: { url: targetPath }, tag: 'sg-notif-' + (data.notification_id || 'fg'),
        });
      }

      const soundFile = (data.type === 'income') ? '/sounds/moneta.mp3' : '/sounds/chat.mp3';
      if (window._sgPlaySound) {
        window._sgPlaySound(soundFile);
      } else {
        try { new Audio(soundFile).play(); } catch {}
      }
    });

    // ── Permission check ──────────────────────────────────────────────────
    if (Notification.permission === 'granted') {
      localStorage.removeItem('sg_push_token');
      await saveToken();
      hidePushBtn();
      return;
    }

    if (Notification.permission === 'default') {
      showPushBtn('default');
    }
  }

  // ── Save token to server ──────────────────────────────────────────────────
  async function saveToken() {
    if (!cfg.vapidKey || !messaging || !swReg) return;
    let token;
    try {
      token = await messaging.getToken({
        vapidKey:                  cfg.vapidKey,
        serviceWorkerRegistration: swReg,
      });
    } catch (e) {
      console.warn('[Push] getToken failed:', e.message);
      return;
    }
    if (!token) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    try {
      const resp = await fetch('/api/push-token', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body:    JSON.stringify({ token }),
      });
      if (resp.ok) localStorage.setItem('sg_push_token', token);
    } catch (e) {
      console.warn('[Push] token save failed:', e.message);
    }
  }

  // ── Button helpers ────────────────────────────────────────────────────────
  function showPushBtn(state) {
    const btn = document.getElementById('sgEnablePushBtn');
    if (!btn) return;

    if (state === 'denied') {
      btn.innerHTML   = '🔕 Сповіщення заблоковані';
      btn.title       = 'Щоб увімкнути: Налаштування браузера → Сповіщення → Дозволити';
      btn.onclick     = () => alert(
        'Сповіщення заблоковані браузером.\n\n' +
        'Щоб увімкнути:\n' +
        '• Chrome: 🔒 у рядку адреси → Сповіщення → Дозволити\n' +
        '• Safari: Налаштування → Сайти → Сповіщення'
      );
      btn.style.cssText += ';display:flex;opacity:0.6';
      return;
    }

    if (state === 'ios') {
      btn.innerHTML = '📲 Додайте на головний екран';
      btn.title     = 'Для сповіщень на iPhone: Поділитись → Додати на головний екран';
      btn.onclick   = () => alert(
        'Push сповіщення на iPhone потребують PWA:\n\n' +
        '1. Натисніть "Поділитись" (□↑) в Safari\n' +
        '2. Виберіть "Додати на головний екран"\n' +
        '3. Відкрийте додаток з головного екрану\n' +
        '4. Натисніть "🔔 Увімкнути сповіщення"'
      );
      btn.style.display = 'flex';
      return;
    }

    btn.innerHTML         = '🔔 Увімкнути сповіщення';
    btn.onclick           = window.sgEnablePush;
    btn.style.display     = 'flex';
    btn.style.opacity     = '1';
  }

  function hidePushBtn() {
    const btn = document.getElementById('sgEnablePushBtn');
    if (btn) btn.style.display = 'none';
  }

  // ── Public: called by button click ────────────────────────────────────────
  window.sgEnablePush = async function () {
    let perm;
    try {
      perm = await Notification.requestPermission();
    } catch (e) {
      console.warn('[Push] requestPermission failed:', e.message);
      return;
    }
    if (perm !== 'granted') { showPushBtn('denied'); return; }
    await saveToken();
    hidePushBtn();
  };

  // ── Badge management ──────────────────────────────────────────────────────
  function clearBadge() {
    if ('clearAppBadge' in navigator) navigator.clearAppBadge().catch(() => {});
  }
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') clearBadge();
  });
  window.addEventListener('focus', clearBadge);
  clearBadge();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
