<script>
(function () {
    const VAPID_PUBLIC_KEY = @json(config('webpush.vapid.public_key'));
    const SUBSCRIBE_URL = '{{ route('push.subscribe') }}';
    const UNSUBSCRIBE_URL = '{{ route('push.unsubscribe') }}';
    const CSRF_TOKEN = '{{ csrf_token() }}';

    @if(filament()->getTenant())
    const COMPANY_ID = {{ filament()->getTenant()->id }};
    @else
    const COMPANY_ID = null;
    @endif

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !VAPID_PUBLIC_KEY || !COMPANY_ID) {
        return;
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        return Uint8Array.from([...rawData].map(c => c.charCodeAt(0)));
    }

    async function subscribe(registration) {
        try {
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });

            const key = subscription.getKey('p256dh');
            const auth = subscription.getKey('auth');

            await fetch(SUBSCRIBE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    public_key: key ? btoa(String.fromCharCode(...new Uint8Array(key))) : null,
                    auth_token: auth ? btoa(String.fromCharCode(...new Uint8Array(auth))) : null,
                    company_id: COMPANY_ID,
                }),
            });
        } catch (err) {
            console.warn('[Comere Push] Subscription failed:', err);
        }
    }

    async function init() {
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return;

        const existing = await registration.pushManager.getSubscription();
        if (existing) return;

        await subscribe(registration);
    }

    window.addEventListener('load', () => {
        init().catch(console.error);
    });
})();
</script>
