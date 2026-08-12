import { Head } from '@inertiajs/react';
import { Smartphone, QrCode } from 'lucide-react';

export default function DesktopBlocked({ currentUrl }) {
    const qrSrc = `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(currentUrl)}`;

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-10 text-center">
            <Head title="Acesse pelo celular - Motoboy Comere" />

            <div className="w-16 h-16 bg-red-500 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/30 mb-5">
                <Smartphone size={30} className="text-white" />
            </div>

            <h1 className="text-xl font-black text-gray-900 mb-2">Acesse pelo celular</h1>
            <p className="text-sm text-gray-500 max-w-xs mb-6">
                O painel do motoboy foi feito para ser usado no celular, onde fica mais fácil ver suas entregas e ouvir o alerta de pedido novo.
                Escaneie o QR code abaixo com a câmera do seu celular para continuar.
            </p>

            <div className="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <img src={qrSrc} alt="QR code para abrir no celular" width={260} height={260} className="rounded-xl" />
            </div>

            <div className="flex items-center gap-1.5 text-xs text-gray-400 font-medium mt-5">
                <QrCode size={14} />
                Aponte a câmera do celular pra esse código
            </div>
        </div>
    );
}
