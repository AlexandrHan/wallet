(function () {
  'use strict';

  const cfg = window.sgPushConfig;

  if (!cfg || !cfg.apiKey || !cfg.projectId) {
    console.log('[Push] sgPushConfig missing — skip');
    return;
  }
  if (typeof firebase === 'undefined') {
    console.log('[Push] Firebase SDK not loaded — skip');
    return;
  }
  if (!('serviceWorker' in navigator) || !('Notification' in window)) {
    console.log('[Push] Not supported — skip');
    return;
  }

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
    console.log('[Push] Firebase initialised');
  }

  let messaging = null;
  let swReg     = null;

  // ── Register SW + init messaging ──────────────────────────────────────────
  async function boot() {
    // ── Handle denied / iOS-non-standalone BEFORE registering SW ──────────
    if (Notification.permission === 'denied') {
      showPushBtn('denied');
      console.log('[Push] Permission denied — showing guidance');
      return;
    }

    if (isIOS && !isStandalone) {
      // iOS Safari without PWA: Web Push requires Add to Home Screen
      showPushBtn('ios');
      console.log('[Push] iOS non-standalone — showing PWA hint');
      return;
    }

    try {
      swReg = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
      await navigator.serviceWorker.ready;
      console.log('[Push] SW ready');
    } catch (e) {
      console.warn('[Push] SW failed:', e.message);
      return;
    }

    try {
      messaging = firebase.messaging();
      console.log('[Push] Messaging initialised');
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
      console.log('[Push] Foreground message type=' + (data.type || '?') + ' notif_id=' + notifId, payload);

      // Skip if already on the target page (WS handler already plays sound)
      const onTargetPage = window.location.pathname.startsWith(targetPath) && targetPath !== '/';

      // Dedup sound only — WS already played it, but we still need to show the notification
      const wsAlreadyPlayed = notifId && notifId === window._sgLastNotifId;

      if (!onTargetPage && !wsAlreadyPlayed) {
        const soundFile = (data.type === 'message') ? '/sounds/chat.mp3' : '/sounds/moneta.mp3';
        console.log('[Push] Playing sound from FCM foreground:', soundFile);
        if (window._sgPlaySound) {
          window._sgPlaySound(soundFile);
        } else {
          try { new Audio(soundFile).play(); } catch {}
        }
      } else {
        console.log('[Push] Sound skipped — onTargetPage=' + onTargetPage + ' wsAlreadyPlayed=' + wsAlreadyPlayed);
      }

      if (badgeCount > 0 && 'setAppBadge' in navigator) {
        navigator.setAppBadge(badgeCount).catch(() => {});
      }
      const title = payload.notification?.title || data.title || 'SolarGlass';
      const body  = payload.notification?.body  || data.body  || '';
      if (Notification.permission === 'granted') {
        swReg.showNotification(title, {
          body, icon: '/img/logo.png', badge: '/img/logo.png',
          data: { url: targetPath }, tag: data.notification_id || 'sg-fg',
        });
      }
    });

    // ── Permission check ──────────────────────────────────────────────────
    if (Notification.permission === 'granted') {
      // Always re-save token so stale tokens are refreshed on each session
      localStorage.removeItem('sg_push_token');
      await saveToken();
      hidePushBtn();
      return;
    }

    // Default (not yet asked) — show enable button
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
    if (!token) { console.warn('[Push] empty token'); return; }
    console.log('[Push] Token received:', token.substring(0, 20) + '…');

    const saved = localStorage.getItem('sg_push_token');
    if (saved === token) { console.log('[Push] Token unchanged, re-sending to sync'); }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    try {
      const resp = await fetch('/api/push-token', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body:    JSON.stringify({ token }),
      });
      if (resp.ok) {
        localStorage.setItem('sg_push_token', token);
        console.log('[Push] Token sent to backend ✅');
      }
    } catch (e) {
      console.warn('[Push] token save failed:', e.message);
    }
  }

  // ── Button helpers ────────────────────────────────────────────────────────
  function showPushBtn(state) {
    const btn = document.getElementById('sgEnablePushBtn');
    if (!btn) return;

    if (state === 'denied') {
      btn.innerHTML = '🔕 Сповіщення заблоковані';
      btn.title     = 'Щоб увімкнути: Налаштування браузера → Сповіщення → Дозволити';
      btn.onclick   = () => alert(
        'Сповіщення заблоковані браузером.\n\n' +
        'Щоб увімкнути:\n' +
        '• Chrome: 🔒 у рядку адреси → Сповіщення → Дозволити\n' +
        '• Safari: Налаштування → Сайти → Сповіщення'
      );
      btn.style.display = 'flex';
      btn.style.opacity = '0.6';
      return;
    }

    if (state === 'ios') {
      btn.innerHTML = '📲 Додайте на головний екран';
      btn.title     = 'Для сповіщень на iPhone: Поділитись → Додати на головний екран';
      btn.onclick   = () => alert(
        'Push сповіщення на iPhone потребують PWA:\n\n' +
        '1. Натисніть кнопку "Поділитись" (□↑) в Safari\n' +
        '2. Виберіть "Додати на головний екран"\n' +
        '3. Відкрийте додаток з головного екрану\n' +
        '4. Натисніть "🔔 Увімкнути сповіщення"'
      );
      btn.style.display = 'flex';
      return;
    }

    // default state — normal enable button
    btn.innerHTML = '🔔 Увімкнути сповіщення';
    btn.onclick   = window.sgEnablePush;
    btn.style.display = 'flex';
    btn.style.opacity = '1';
  }

  function hidePushBtn() {
    const btn = document.getElementById('sgEnablePushBtn');
    if (btn) btn.style.display = 'none';
  }

  // ── Public: called by button click (user gesture) ─────────────────────────
  window.sgEnablePush = async function () {
    console.log('[Push] User clicked enable');
    let perm;
    try {
      perm = await Notification.requestPermission();
      console.log('[Push] Permission:', perm);
    } catch (e) {
      console.warn('[Push] requestPermission failed:', e.message);
      return;
    }
    if (perm !== 'granted') {
      showPushBtn('denied');
      return;
    }
    console.log('[Push] Permission granted');
    await saveToken();
    hidePushBtn();
    console.log('[Push] Push fully enabled ✅');
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
