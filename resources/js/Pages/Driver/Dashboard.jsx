import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { Bike, Power, Package, ArrowRight, X, Store, CheckCircle2, XCircle } from 'lucide-react';
import axios from 'axios';

function playAlertSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const now = ctx.currentTime;

        [880, 1175].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            osc.connect(gain);
            gain.connect(ctx.destination);

            const start = now + i * 0.18;
            gain.gain.setValueAtTime(0, start);
            gain.gain.linearRampToValueAtTime(0.35, start + 0.02);
            gain.gain.linearRampToValueAtTime(0, start + 0.16);

            osc.start(start);
            osc.stop(start + 0.18);
        });
    } catch {
        // navegador sem suporte a Web Audio API — falha silenciosamente
    }
}

function StatusToggle({ isOnline, onToggle, loading }) {
    return (
        <button
            onClick={onToggle}
            disabled={loading}
            className={`w-full rounded-3xl p-6 flex items-center justify-between transition-all shadow-lg disabled:opacity-70 ${
                isOnline
                    ? 'bg-emerald-500 shadow-emerald-500/30'
                    : 'bg-gray-800 shadow-gray-800/20'
            }`}
        >
            <div className="text-left">
                <p className="text-white/70 text-xs font-bold uppercase tracking-wider">Status</p>
                <p className="text-white text-2xl font-black">{isOnline ? 'Você está ONLINE' : 'Você está OFFLINE'}</p>
                <p className="text-white/70 text-xs font-medium mt-1">
                    {isOnline ? 'Recebendo pedidos das lojas vinculadas' : 'Toque para começar a receber pedidos'}
                </p>
            </div>
            <div className={`w-14 h-14 rounded-full flex items-center justify-center flex-shrink-0 ${isOnline ? 'bg-white/20' : 'bg-white/10'}`}>
                <Power size={26} className="text-white" />
            </div>
        </button>
    );
}

function NewDeliveryAlert({ delivery, onDismiss }) {
    if (!delivery) return null;

    return (
        <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.9 }}
            className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
        >
            <motion.div
                initial={{ y: 40 }}
                animate={{ y: 0 }}
                className="bg-white rounded-3xl p-6 w-full max-w-sm text-center shadow-2xl"
            >
                <div className="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                    <Package size={30} className="text-emerald-500" />
                </div>
                <p className="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Nova entrega!</p>
                <h2 className="text-xl font-black text-gray-900 mb-1">{delivery.company_name}</h2>
                <p className="text-sm text-gray-500 mb-1">Pedido #{delivery.order_short_id}</p>
                <p className="text-lg font-bold text-emerald-600 mb-6">
                    {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(delivery.driver_fee)}
                </p>
                <div className="flex gap-2">
                    <button
                        onClick={onDismiss}
                        className="flex-1 bg-gray-100 text-gray-500 font-bold py-3 rounded-xl"
                    >
                        Fechar
                    </button>
                    <a
                        href={route('delivery.tracking.show', delivery.tracking_token)}
                        className="flex-1 bg-emerald-500 text-white font-bold py-3 rounded-xl active:scale-95 transition-transform"
                    >
                        Ver entrega
                    </a>
                </div>
            </motion.div>
        </motion.div>
    );
}

export default function DriverDashboard({ driver, pendingInvites: initialInvites, acceptedCompanies, activeDeliveries: initialDeliveries }) {
    const { flash } = usePage().props;
    const [isOnline, setIsOnline] = useState(driver.is_online);
    const [toggling, setToggling] = useState(false);
    const [activeDeliveries, setActiveDeliveries] = useState(initialDeliveries);
    const [newDelivery, setNewDelivery] = useState(null);
    const knownIds = useRef(new Set(initialDeliveries.map(d => d.id)));

    const handleToggle = async () => {
        if (toggling) return;
        setToggling(true);
        const next = !isOnline;
        try {
            const res = await axios.post(route('motoboy.status.toggle'), { is_online: next });
            setIsOnline(res.data.is_online);
        } catch {
            // mantém o estado anterior em caso de falha
        } finally {
            setToggling(false);
        }
    };

    useEffect(() => {
        const interval = setInterval(async () => {
            try {
                const res = await axios.get(route('motoboy.poll'));
                const incoming = res.data.active_deliveries;

                const fresh = incoming.find(d => !knownIds.current.has(d.id));
                if (fresh) {
                    playAlertSound();
                    setNewDelivery(fresh);
                }

                incoming.forEach(d => knownIds.current.add(d.id));
                setActiveDeliveries(incoming);
                setIsOnline(res.data.is_online);
            } catch {
                // tenta de novo no próximo ciclo
            }
        }, 6000);

        return () => clearInterval(interval);
    }, []);

    const respondToInvite = (pivotId, action) => {
        const routeName = action === 'accept' ? 'motoboy.invite.accept' : 'motoboy.invite.reject';
        router.post(route(routeName, pivotId));
    };

    return (
        <div className="min-h-screen bg-gray-50 pb-16">
            <Head title="Minhas entregas - Motoboy Comere" />

            <div className="max-w-md mx-auto px-4 pt-8">
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-3">
                        <div className="w-11 h-11 bg-red-500 rounded-2xl flex items-center justify-center shadow-md shadow-red-500/20">
                            <Bike size={20} className="text-white" />
                        </div>
                        <div>
                            <p className="text-xs text-gray-400 font-semibold uppercase tracking-wider">Olá,</p>
                            <h1 className="text-lg font-black text-gray-900 leading-tight">{driver.name}</h1>
                        </div>
                    </div>
                    <button
                        onClick={() => router.post(route('motoboy.logout'))}
                        className="text-sm font-bold text-gray-400 hover:text-red-500"
                    >
                        Sair
                    </button>
                </div>

                {flash?.success && (
                    <div className="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-bold rounded-xl px-4 py-3 mb-4">
                        {flash.success}
                    </div>
                )}

                <div className="mb-8">
                    <StatusToggle isOnline={isOnline} onToggle={handleToggle} loading={toggling} />
                </div>

                {activeDeliveries.length > 0 && (
                    <div className="mb-8">
                        <h2 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Entregas em andamento</h2>
                        <div className="flex flex-col gap-3">
                            {activeDeliveries.map(delivery => (
                                <a
                                    key={delivery.id}
                                    href={route('delivery.tracking.show', delivery.tracking_token)}
                                    className="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between hover:border-red-300 transition-colors"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                                            <Package size={18} className="text-red-500" />
                                        </div>
                                        <div>
                                            <p className="font-bold text-gray-900 text-sm">{delivery.company_name}</p>
                                            <p className="text-xs text-gray-400">Pedido #{delivery.order_short_id}</p>
                                        </div>
                                    </div>
                                    <ArrowRight size={16} className="text-gray-300" />
                                </a>
                            ))}
                        </div>
                    </div>
                )}

                {initialInvites.length > 0 && (
                    <div className="mb-8">
                        <h2 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Convites de lojas</h2>
                        <div className="flex flex-col gap-3">
                            {initialInvites.map(invite => (
                                <div key={invite.pivot_id} className="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                                    <div className="flex items-center gap-3 mb-3">
                                        <div className="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center">
                                            <Store size={18} className="text-gray-400" />
                                        </div>
                                        <div>
                                            <p className="font-bold text-gray-900 text-sm">{invite.name}</p>
                                            <p className="text-xs text-gray-500">
                                                Taxa: <span className="font-bold text-gray-700">
                                                    {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(invite.delivery_fee)}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            onClick={() => respondToInvite(invite.pivot_id, 'accept')}
                                            className="flex-1 flex items-center justify-center gap-1.5 bg-red-500 hover:bg-red-600 text-white text-sm font-bold py-2.5 rounded-xl transition-colors"
                                        >
                                            <CheckCircle2 size={15} /> Aceitar
                                        </button>
                                        <button
                                            onClick={() => respondToInvite(invite.pivot_id, 'reject')}
                                            className="flex-1 flex items-center justify-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-bold py-2.5 rounded-xl transition-colors"
                                        >
                                            <XCircle size={15} /> Recusar
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div>
                    <h2 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Lojas vinculadas</h2>
                    {acceptedCompanies.length === 0 ? (
                        <p className="text-sm text-gray-400">Nenhuma loja vinculada ainda. Peça para a loja te convidar pelo seu telefone cadastrado.</p>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {acceptedCompanies.map((company, i) => (
                                <div key={i} className="bg-white rounded-xl border border-gray-100 px-4 py-3 flex items-center justify-between">
                                    <span className="font-medium text-gray-800 text-sm">{company.name}</span>
                                    <span className="text-xs font-bold text-gray-400">
                                        {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(company.delivery_fee)}/entrega
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <AnimatePresence>
                {newDelivery && (
                    <NewDeliveryAlert delivery={newDelivery} onDismiss={() => setNewDelivery(null)} />
                )}
            </AnimatePresence>
        </div>
    );
}
