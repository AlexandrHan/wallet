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

  // ── Register SW + init messaging (no permission needed) ───────────────────
  async function boot() {
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

    // Foreground handler
    messaging.onMessage((payload) => {
      const data       = payload.data ?? {};
      const targetPath = data.url || '/';
      const badgeCount = Number(data.badge) || 0;
      console.log('[Push] Foreground message', payload);
      if (window.location.pathname.startsWith(targetPath) && targetPath !== '/') return;
      const soundFile = (data.type === 'message') ? '/sounds/chat.mp3' : '/sounds/moneta.mp3';
      try { new Audio(soundFile).play(); } catch {}
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

    // If already granted — silently refresh token
    if (Notification.permission === 'granted') {
      await saveToken();
      hidePushBtn();
      return;
    }

    // Show button if permission not yet decided
    if (Notification.permission === 'default') {
      // On iOS non-standalone — hide button (push won't work anyway)
      if (isIOS && !isStandalone) {
        hidePushBtn();
        return;
      }
      showPushBtn();
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
    if (saved === token) { console.log('[Push] Token unchanged'); return; }

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
  function showPushBtn() {
    const btn = document.getElementById('sgEnablePushBtn');
    if (btn) btn.style.display = 'flex';
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
    if (perm !== 'granted') return;
    console.log('[Push] Permission granted');
    await saveToken();
    hidePushBtn();
    console.log('[Push] Push fully enabled ✅');
  };

  // ── Badge management ──────────────────────────────────────────────────────
  function clearBadge() {
    if ('clearAppBadge' in navigator) navigator.clearAppBadge().catch(() => {});
  }

  // Clear badge when app comes into focus
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') clearBadge();
  });
  window.addEventListener('focus', clearBadge);
  clearBadge(); // clear on initial load

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
