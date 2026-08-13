import { Head, Link, useForm } from '@inertiajs/react';
import { Bike, Phone, Lock } from 'lucide-react';

export default function DriverLogin() {
    const { data, setData, post, processing, errors } = useForm({
        phone: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/drivers/login');
    };

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-10">
            <Head title="Entrar - Motoboy Comere" />

            <div className="w-full max-w-sm">
                <div className="flex flex-col items-center mb-6">
                    <div className="w-14 h-14 bg-red-500 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/30 mb-3">
                        <Bike size={28} className="text-white" />
                    </div>
                    <h1 className="text-xl font-black text-gray-900">Painel do motoboy</h1>
                    <p className="text-sm text-gray-500 text-center mt-1">Entre para ver suas entregas e convites de lojas.</p>
                </div>

                <form onSubmit={handleSubmit} className="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Telefone</label>
                        <div className="relative">
                            <Phone size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" />
                            <input
                                type="tel"
                                value={data.phone}
                                onChange={e => setData('phone', e.target.value)}
                                autoFocus
                                className="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-11 pr-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none text-sm font-medium"
                                required
                            />
                        </div>
                        {errors.phone && <span className="text-xs text-red-500 font-medium px-1">{errors.phone}</span>}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Senha</label>
                        <div className="relative">
                            <Lock size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" />
                            <input
                                type="password"
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                className="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-11 pr-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none text-sm font-medium"
                                required
                            />
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-gray-600 px-1">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={e => setData('remember', e.target.checked)}
                            className="rounded border-gray-300 text-red-500 focus:ring-red-500"
                        />
                        Manter conectado
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-red-500/20 transition-all active:scale-95 disabled:opacity-50 mt-1"
                    >
                        {processing ? 'Entrando...' : 'Entrar'}
                    </button>
                </form>

                <p className="text-center text-sm text-gray-500 mt-5">
                    Ainda não tem conta? <Link href="/drivers/cadastro" className="font-bold text-red-500">Cadastre-se</Link>
                </p>
            </div>
        </div>
    );
}
