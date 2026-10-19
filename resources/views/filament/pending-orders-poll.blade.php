@php
    $tenant = filament()->getTenant();
@endphp
@if ($tenant)
    <script>
        (function () {
            const companyUuid = @js($tenant->uuid);
            const endpoint = @js(route('admin.pending-orders.count'));
            let lastCount = null;

            function beep() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = 880;
                    gain.gain.setValueAtTime(0.15, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.4);
                } catch (e) {}
            }

            function showToast(message) {
                const toast = document.createElement('div');
                toast.textContent = message;
                toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:#f97316;color:#fff;padding:12px 20px;border-radius:8px;font-family:sans-serif;font-size:14px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.3);max-width:320px;';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 6000);
            }

            async function checkPending() {
                try {
                    const res = await fetch(endpoint + '?company=' + companyUuid, {
                        headers: { Accept: 'application/json' },
                    });

                    if (!res.ok) {
                        return;
                    }

                    const data = await res.json();

                    if (lastCount !== null && data.count > lastCount) {
                        beep();
                        showToast('🔔 Novo pedido pendente na mesa!');
                    }

                    lastCount = data.count;
                } catch (e) {}
            }

            checkPending();
            setInterval(checkPending, 8000);
        })();
    </script>
@endif
