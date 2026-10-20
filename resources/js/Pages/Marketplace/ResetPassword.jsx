import { Head, useForm } from '@inertiajs/react';
import MarketplaceLayout from '@/Layouts/MarketplaceLayout';
import { KeyRound, AlertCircle } from 'lucide-react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/redefinir-senha');
    };

    return (
        <MarketplaceLayout>
            <Head title="Redefinir senha - Comere" />

            <div className="max-w-md mx-auto mt-12 mb-20 bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
                <div className="bg-gradient-to-r from-red-600 to-red-500 p-8 text-white text-center">
                    <div className="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                        <KeyRound size={32} />
                    </div>
                    <h1 className="text-2xl font-bold">Redefinir senha</h1>
                    <p className="text-red-50 text-sm mt-2">Escolha uma nova senha para sua conta.</p>
                </div>

                <form onSubmit={handleSubmit} className="p-8 flex flex-col gap-6">
                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">E-mail</label>
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none"
                        />
                        {errors.email && <span className="text-xs text-red-500 font-medium px-1 mt-1">{errors.email}</span>}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Nova senha</label>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
                            placeholder="••••••••"
                            className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none"
                        />
                        {errors.password && <span className="text-xs text-red-500 font-medium px-1 mt-1">{errors.password}</span>}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Confirmar nova senha</label>
                        <input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                            placeholder="••••••••"
                            className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-red-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all active:scale-95 uppercase tracking-widest text-sm"
                    >
                        {processing ? 'Salvando...' : 'Redefinir senha'}
                    </button>

                    <div className="flex items-center justify-center gap-2 text-gray-400 text-[10px] font-bold uppercase tracking-wider">
                        <AlertCircle size={14} />
                        <span>Conexão Segura e Criptografada</span>
                    </div>
                </form>
            </div>
        </MarketplaceLayout>
    );
}
