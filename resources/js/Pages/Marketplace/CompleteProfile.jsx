import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MarketplaceLayout from '@/Layouts/MarketplaceLayout';
import { ShieldCheck, UserCheck, AlertCircle } from 'lucide-react';
export default function CompleteProfile() {
    const { data, setData, post, processing, errors } = useForm({
        cpf: '',
    });

    const [agreed, setAgreed] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!agreed) return;
        post('/complete-profile');
    };

    return (
        <MarketplaceLayout>
            <Head title="Completar Cadastro - Comere" />

            <div className="max-w-md mx-auto mt-12 mb-20 bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
                <div className="bg-gradient-to-r from-red-600 to-red-500 p-8 text-white text-center">
                    <div className="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                        <UserCheck size={32} />
                    </div>
                    <h1 className="text-2xl font-bold">Falta pouco!</h1>
                    <p className="text-red-50 text-sm mt-2">
                        Precisamos apenas do seu CPF para validar sua identidade.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="p-8 flex flex-col gap-6">
                    <div className="flex flex-col gap-1.5">
                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Seu CPF</label>
                        <div className="relative">
                            <input
                                type="text"
                                value={data.cpf}
                                onChange={e => {
                                    const value = e.target.value.replace(/\D/g, '').slice(0, 11);
                                    setData('cpf', value);
                                }}
                                placeholder="000.000.000-00"
                                className="w-full bg-gray-50 border-gray-200 rounded-xl py-4 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-lg font-medium outline-none"
                                required
                            />
                        </div>
                        {errors.cpf && <span className="text-xs text-red-500 font-medium px-1 mt-1">{errors.cpf}</span>}
                    </div>

                    <div className="bg-blue-50 border border-blue-100 rounded-2xl p-4 flex gap-4">
                        <div className="text-blue-500 shrink-0 mt-1">
                            <ShieldCheck size={20} />
                        </div>
                        <div className="flex flex-col gap-1">
                            <h4 className="text-sm font-bold text-blue-900 leading-none">Compromisso LGPD</h4>
                            <p className="text-[11px] text-blue-700 leading-relaxed uppercase tracking-tighter font-medium">
                                Seus dados são coletados exclusivamente para validação de identidade e proteção contra fraudes. Não compartilhamos suas informações com terceiros.
                            </p>
                            <label className="flex items-center gap-2 mt-2 cursor-pointer group">
                                <input
                                    type="checkbox"
                                    checked={agreed}
                                    onChange={e => setAgreed(e.target.checked)}
                                    className="w-4 h-4 rounded border-blue-200 text-blue-600 focus:ring-blue-500"
                                />
                                <span className="text-xs font-bold text-blue-900 group-hover:underline">Estou ciente e concordo</span>
                            </label>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={processing || !agreed || data.cpf.length < 11}
                        className="bg-red-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all active:scale-95 uppercase tracking-widest text-sm"
                    >
                        {processing ? 'Finalizando...' : 'Concluir Cadastro'}
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
