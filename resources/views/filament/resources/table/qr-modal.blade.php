<div
    x-data="{
        name: @js($record->name),
        slug: @js(\Illuminate\Support\Str::slug($record->name)),
        imgId: @js('qr-img-' . $record->uuid),
        download() {
            var img = document.getElementById(this.imgId);
            var doExport = (imgEl) => {
                var p = 40, qr = 400, hH = 110, fH = 60;
                var w = qr + p * 2, h = qr + hH + fH + p * 2;
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');

                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);

                ctx.strokeStyle = '#e5e7eb';
                ctx.lineWidth = 2;
                ctx.strokeRect(1, 1, w - 2, h - 2);

                ctx.fillStyle = '#111827';
                ctx.font = 'bold 34px system-ui, -apple-system, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(this.name, w / 2, p + 44);

                ctx.fillStyle = '#6b7280';
                ctx.font = '17px system-ui, -apple-system, sans-serif';
                ctx.fillText('Escaneie para pedir', w / 2, p + 74);

                ctx.drawImage(imgEl, p, p + hH, qr, qr);

                var a = document.createElement('a');
                a.download = 'qr-' + this.slug + '.png';
                a.href = canvas.toDataURL('image/png');
                a.click();
            };

            if (img && img.complete) {
                doExport(img);
            } else if (img) {
                img.onload = () => doExport(img);
            }
        }
    }"
    class="flex flex-col items-center gap-4 py-4"
>
    <img
        :id="imgId"
        src="{{ route('table.qr-image', $record->uuid) }}"
        alt="QR Code"
        class="w-56 h-56 rounded-lg"
    />

    <p class="text-xs text-gray-400 break-all text-center max-w-xs">{{ $url }}</p>

    <button
        @click="download()"
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
        Baixar PNG
    </button>
</div>
