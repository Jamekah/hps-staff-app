import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage, isSupported } from 'firebase/messaging';

const TOKEN_STORAGE_KEY = 'hps-fcm-token';
const DISMISSED_KEY = 'hps-push-dismissed';

const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

let messaging = null;

async function messagingInstance() {
    if (messaging) return messaging;
    if (!(await isSupported())) return null;

    messaging = getMessaging(initializeApp(firebaseConfig));
    return messaging;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function registerToken() {
    const instance = await messagingInstance();
    if (!instance) return false;

    const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');

    const token = await getToken(instance, {
        vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
        serviceWorkerRegistration: registration,
    });

    if (!token) return false;

    await fetch('/api/device-tokens', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ token, platform: 'web' }),
    });

    localStorage.setItem(TOKEN_STORAGE_KEY, token);

    listenForForegroundMessages(instance);

    return true;
}

function listenForForegroundMessages(instance) {
    onMessage(instance, (payload) => {
        const title = payload.notification?.title || payload.data?.title;
        if (!title) return;

        showToast(title, payload.notification?.body || payload.data?.body || '');

        // Nudge the bell to refresh its count.
        window.Livewire?.dispatch('$refresh');
    });
}

function showToast(title, body) {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-4 end-4 z-[100] max-w-sm bg-gray-900 text-white rounded-lg shadow-lg p-4 cursor-pointer';
    toast.innerHTML = `<p class="text-sm font-semibold"></p><p class="text-xs mt-0.5 text-gray-300"></p>`;
    toast.children[0].textContent = title;
    toast.children[1].textContent = body;
    toast.addEventListener('click', () => toast.remove());
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 8000);
}

// Called from the "Enable notifications" banner button.
window.hpsEnablePush = async function () {
    if (!('Notification' in window)) return false;

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return false;

    try {
        return await registerToken();
    } catch (error) {
        console.error('Push registration failed:', error);
        return false;
    }
};

window.hpsPushState = function () {
    if (!('Notification' in window) || !navigator.serviceWorker) return 'unsupported';
    if (localStorage.getItem(DISMISSED_KEY)) return 'dismissed';
    return Notification.permission; // 'default' | 'granted' | 'denied'
};

window.hpsDismissPush = function () {
    localStorage.setItem(DISMISSED_KEY, '1');
};

// On page load: if permission was already granted, silently re-register so a
// refreshed FCM token always replaces the stale one.
if ('Notification' in window && navigator.serviceWorker && Notification.permission === 'granted') {
    registerToken().catch((error) => console.error('Push token refresh failed:', error));
}

// On logout, unregister this device's token so the next user of the browser
// doesn't receive the previous user's pushes.
document.addEventListener('click', (event) => {
    const logoutButton = event.target.closest('button[wire\\:click="logout"]');
    if (!logoutButton) return;

    const token = localStorage.getItem(TOKEN_STORAGE_KEY);
    if (!token) return;

    localStorage.removeItem(TOKEN_STORAGE_KEY);

    fetch('/api/device-tokens', {
        method: 'DELETE',
        keepalive: true,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ token }),
    });
});
