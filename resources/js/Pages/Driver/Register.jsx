import { Head, Link, useForm } from '@inertiajs/react';
import { Bike, User, Phone, IdCard, Lock } from 'lucide-react';

export default function DriverRegister() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        phone: '',
        cpf: '',
        vehicle_type: 'motoboy',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('motoboy.register'));
    };

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-10">
            <Head title="Cadastro de Motoboy - Comere" />

            <div className="w-full max-w-sm">
                <div className="flex flex-col items-center mb-6">
                    <div className="w-14 h-14 bg-red-500 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/30 mb-3">
                        <Bike size={28} className="text-white" />
                    </div>
                    <h1 className="text-xl font-black text-gray-900">Cadastro de motoboy</h1>
                    <p className="text-sm text-gray-500 text-center mt-1">Receba entregas das lojas parceiras do Comere.</p>
                </div>

                <form onSubmit={handleSubmit} className="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Nome completo</label>
                        <div className="relative">
                            <User size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" />
                            <input
                                type="text"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                className="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-11 pr-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none text-sm font-medium"
                                required
                            />
                        </div>
                        {errors.name && <span className="text-xs text-red-500 font-medium px-1">{errors.name}</span>}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Telefone (WhatsApp)</label>
                        <div className="relative">
                            <Phone size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" />
                            <input
                                type="tel"
                                value={data.phone}
                                onChange={e => setData('phone', e.target.value)}
                                placeholder="(11) 91234-5678"
                                className="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-11 pr-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none text-sm font-medium"
                                required
                            />
                        </div>
                        {errors.phone && <span className="text-xs text-red-500 font-medium px-1">{errors.phone}</span>}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">CPF (opcional)</label>
                        <div className="relative">
                            <IdCard size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" />
                            <input
                                type="text"
                                value={data.cpf}
                                onChange={e => setData('cpf', e.target.value)}
                                className="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-11 pr-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none text-sm font-medium"
                            />
                        </div>
                        {errors.cpf && <span className="text-xs text-red-500 font-medium px-1">{errors.cpf}</span>}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Veículo</label>
                        <div className="grid grid-cols-2 gap-2">
                            {[{ value: 'motoboy', label: 'Moto' }, { value: 'car', label: 'Carro' }].map(opt => (
                                <button
                                    key={opt.value}
                                    type="button"
                                    onClick={() => setData('vehicle_type', opt.value)}
                                    className={`py-3 rounded-xl text-sm font-bold transition-all border ${
                                        data.vehicle_type === opt.value
                                            ? 'bg-red-500 text-white border-red-500 shadow-md shadow-red-500/20'
                                            : 'bg-gray-50 text-gray-500 border-gray-200'
                                    }`}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Senha</label>
                        <div className="relative">
                            <Lock size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" />
                            <input
                                type="password"
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                minLength={6}
                                className="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-11 pr-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none text-sm font-medium"
                                required
                            />
                        </div>
                        {errors.password && <span className="text-xs text-red-500 font-medium px-1">{errors.password}</span>}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Confirmar senha</label>
                        <div className="relative">
                            <Lock size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" />
                            <input
                                type="password"
                                value={data.password_confirmation}
                                onChange={e => setData('password_confirmation', e.target.value)}
                                minLength={6}
                                className="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-11 pr-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none text-sm font-medium"
                                required
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-red-500/20 transition-all active:scale-95 disabled:opacity-50 mt-1"
                    >
                        {processing ? 'Criando conta...' : 'Criar conta'}
                    </button>
                </form>

                <p className="text-center text-sm text-gray-500 mt-5">
                    Já tem conta? <Link href={route('motoboy.login.show')} className="font-bold text-red-500">Entrar</Link>
                </p>
            </div>
        </div>
    );
}
