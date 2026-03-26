importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey:            "{{ $config['apiKey'] }}",
    authDomain:        "{{ $config['authDomain'] }}",
    projectId:         "{{ $config['projectId'] }}",
    messagingSenderId: "{{ $config['messagingSenderId'] }}",
    appId:             "{{ $config['appId'] }}"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    const notification = payload.notification ?? {};
    const data         = payload.data ?? {};

    const title      = notification.title || data.title || 'SolarGlass';
    const body       = notification.body  || data.body  || '';
    const url        = data.url || '/';
    const badgeCount = Number(data.badge) || 0;

    if ('setAppBadge' in navigator && badgeCount > 0) {
        navigator.setAppBadge(badgeCount).catch(() => {});
    }

    self.registration.showNotification(title, {
        body,
        icon:     '/img/logo.png',
        badge:    '/img/logo.png',
        data:     { url, badge: badgeCount },
        tag:      data.notification_id || 'sg-push',
        renotify: true,
    });
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if (new URL(client.url).pathname === new URL(url, self.location.origin).pathname) {
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});
