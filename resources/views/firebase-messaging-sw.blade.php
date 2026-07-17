importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

firebase.initializeApp(@json(config('services.firebase_web')));

const messaging = firebase.messaging();

// Notification-type FCM messages are displayed automatically by the SDK
// (with the click-through link from fcm_options). This handler covers
// data-only messages so they are never silently dropped.
messaging.onBackgroundMessage((payload) => {
    if (payload.notification) {
        return;
    }

    const title = payload.data?.title || 'HPS Staff';

    self.registration.showNotification(title, {
        body: payload.data?.body || '',
        data: { link: payload.data?.link || '/' },
    });
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const link = event.notification.data?.link;
    if (link) {
        event.waitUntil(clients.openWindow(link));
    }
});
