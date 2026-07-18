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

const isNative = () => !!window.Capacitor?.isNativePlatform?.();

const isAuthenticated = () => !!document.querySelector('[aria-label="Notifications"]');

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function sendTokenToServer(token, platform) {
    await fetch('/api/device-tokens', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ token, platform }),
    });

    localStorage.setItem(TOKEN_STORAGE_KEY, token);
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

/* ---------------------------------------------------------------- *
 * Native (Capacitor Android app) — uses the injected native bridge  *
 * ---------------------------------------------------------------- */

let nativeInitialized = false;

async function initNativePush() {
    if (nativeInitialized || !isAuthenticated()) return;
    nativeInitialized = true;

    const { PushNotifications } = window.Capacitor.Plugins;
    if (!PushNotifications) return;

    await PushNotifications.addListener('registration', ({ value }) => {
        sendTokenToServer(value, 'android').catch((error) =>
            console.error('Device token registration failed:', error));
    });

    await PushNotifications.addListener('pushNotificationReceived', (notification) => {
        const title = notification.title || notification.data?.title;
        if (title) showToast(title, notification.body || notification.data?.body || '');

        window.Livewire?.dispatch('$refresh');
    });

    await PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
        const link = action.notification?.data?.link;
        if (link) window.location.href = link;
    });

    let permission = await PushNotifications.checkPermissions();

    if (permission.receive === 'prompt') {
        permission = await PushNotifications.requestPermissions();
    }

    if (permission.receive === 'granted') {
        // Register on every app open — FCM tokens can rotate.
        await PushNotifications.register();
    }
}

function initNativeShell() {
    const { App } = window.Capacitor.Plugins;

    // Hardware back button: browse back through history; from the landing
    // page, minimize instead of dead-exiting.
    App?.addListener('backButton', ({ canGoBack }) => {
        if (canGoBack) {
            window.history.back();
        } else {
            App.minimizeApp();
        }
    });
}

/* ---------------------------------------------------------------- *
 * Web (browsers) — Firebase JS SDK + service worker                 *
 * ---------------------------------------------------------------- */

let messaging = null;

let webInitialized = false;

async function messagingInstance() {
    if (messaging) return messaging;
    if (!(await isSupported())) return null;

    messaging = getMessaging(initializeApp(firebaseConfig));
    return messaging;
}

async function registerWebToken() {
    const instance = await messagingInstance();
    if (!instance) return false;

    const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');

    const token = await getToken(instance, {
        vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
        serviceWorkerRegistration: registration,
    });

    if (!token) return false;

    await sendTokenToServer(token, 'web');

    onMessage(instance, (payload) => {
        const title = payload.notification?.title || payload.data?.title;
        if (!title) return;

        showToast(title, payload.notification?.body || payload.data?.body || '');

        window.Livewire?.dispatch('$refresh');
    });

    return true;
}

// Called from the "Enable notifications" banner button (web only).
window.hpsEnablePush = async function () {
    if (!('Notification' in window)) return false;

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return false;

    try {
        return await registerWebToken();
    } catch (error) {
        console.error('Push registration failed:', error);
        return false;
    }
};

window.hpsPushState = function () {
    if (isNative()) return 'native'; // Handled automatically; hide the banner.
    if (!('Notification' in window) || !navigator.serviceWorker) return 'unsupported';
    if (localStorage.getItem(DISMISSED_KEY)) return 'dismissed';
    return Notification.permission; // 'default' | 'granted' | 'denied'
};

window.hpsDismissPush = function () {
    localStorage.setItem(DISMISSED_KEY, '1');
};

/* ---------------------------------------------------------------- *
 * Bootstrapping                                                     *
 * ---------------------------------------------------------------- */

function boot() {
    if (isNative()) {
        initNativePush().catch((error) => console.error('Native push init failed:', error));

        return;
    }

    // Web: silently refresh the token when permission was already granted.
    if (!webInitialized
        && isAuthenticated()
        && 'Notification' in window
        && navigator.serviceWorker
        && Notification.permission === 'granted') {
        webInitialized = true;
        registerWebToken().catch((error) => console.error('Push token refresh failed:', error));
    }
}

if (isNative()) {
    initNativeShell();
}

boot();

// Livewire's wire:navigate swaps pages without reloading scripts — re-check
// after each navigation so push initializes right after login.
document.addEventListener('livewire:navigated', boot);

// On logout, unregister this device's token so the next user of the device
// doesn't receive the previous user's pushes.
document.addEventListener('click', (event) => {
    const logoutButton = event.target.closest('button[wire\\:click="logout"]');
    if (!logoutButton) return;

    const token = localStorage.getItem(TOKEN_STORAGE_KEY);
    if (!token) return;

    localStorage.removeItem(TOKEN_STORAGE_KEY);
    nativeInitialized = false;

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
