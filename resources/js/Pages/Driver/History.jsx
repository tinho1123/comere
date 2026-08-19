import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Wallet, Banknote, Package, Clock, Check } from 'lucide-react';

function money(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

const FILTERS = {
    all: 'Todas',
    pending: 'A receber',
    paid: 'Pagas',
};

export default function DriverHistory({ deliveries, totals }) {
    const [filter, setFilter] = useState('all');

    const pendingCount = deliveries.filter(d => ! d.is_paid).length;

    const filteredDeliveries = deliveries.filter(delivery => {
        if (filter === 'pending') return ! delivery.is_paid;
        if (filter === 'paid') return delivery.is_paid;
        return true;
    });

    return (
        <div className="min-h-screen bg-gray-50 pb-16">
            <Head title="Meus ganhos - Motoboy Comere" />

            <div className="max-w-md mx-auto px-4 pt-8">
                <div className="flex items-center gap-3 mb-6">
                    <Link href="/drivers" className="text-gray-400 hover:text-red-500">
                        <ArrowLeft size={20} />
                    </Link>
                    <h1 className="text-lg font-black text-gray-900">Meus ganhos</h1>
                </div>

                <div className="grid grid-cols-2 gap-3 mb-3">
                    <div className="bg-red-500 rounded-2xl p-4 shadow-lg shadow-red-500/20">
                        <p className="text-white/70 text-xs font-bold uppercase tracking-wider">Hoje</p>
                        <p className="text-white text-xl font-black">{money(totals.today)}</p>
                    </div>
                    <div className="bg-gray-900 rounded-2xl p-4">
                        <p className="text-white/60 text-xs font-bold uppercase tracking-wider">Esta semana</p>
                        <p className="text-white text-xl font-black">{money(totals.this_week)}</p>
                    </div>
                </div>

                {totals.to_receive > 0 && (
                    <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-3 flex items-center gap-3">
                        <div className="w-11 h-11 bg-amber-500 rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-amber-500/30">
                            <Banknote size={20} className="text-white" />
                        </div>
                        <div>
                            <p className="text-[10.5px] font-extrabold text-amber-700 uppercase tracking-wider">A receber</p>
                            <p className="text-xl font-black text-gray-900">{money(totals.to_receive)}</p>
                            <p className="text-[11px] text-amber-700 font-semibold">
                                {pendingCount} {pendingCount === 1 ? 'entrega que a loja' : 'entregas que a loja'} ainda não confirmou o repasse
                            </p>
                        </div>
                    </div>
                )}

                <div className="bg-white rounded-2xl p-4 border border-gray-100 mb-8 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="w-9 h-9 bg-emerald-50 rounded-xl flex items-center justify-center">
                            <Wallet size={16} className="text-emerald-500" />
                        </div>
                        <div>
                            <p className="text-xs text-gray-400 font-semibold uppercase tracking-wider">Total ganho</p>
                            <p className="text-lg font-black text-gray-900">{money(totals.lifetime)}</p>
                        </div>
                    </div>
                    <p className="text-xs text-gray-400 font-medium">{totals.deliveries_count} entregas</p>
                </div>

                <h2 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Histórico</h2>

                {deliveries.length === 0 ? (
                    <div className="bg-white rounded-2xl border border-gray-100 p-8 text-center">
                        <Package size={28} className="text-gray-300 mx-auto mb-3" />
                        <p className="text-sm text-gray-400">Nenhuma entrega concluída ainda.</p>
                    </div>
                ) : (
                    <>
                        <div className="flex gap-1.5 mb-3">
                            {Object.entries(FILTERS).map(([key, label]) => (
                                <button
                                    key={key}
                                    onClick={() => setFilter(key)}
                                    className={`text-xs font-extrabold px-3.5 py-1.5 rounded-full transition-colors ${
                                        filter === key
                                            ? key === 'pending'
                                                ? 'bg-amber-500 text-white'
                                                : 'bg-gray-900 text-white'
                                            : 'bg-gray-100 text-gray-500'
                                    }`}
                                >
                                    {label}{key === 'pending' ? ` · ${pendingCount}` : ''}
                                </button>
                            ))}
                        </div>

                        {filteredDeliveries.length === 0 ? (
                            <div className="bg-white rounded-2xl border border-gray-100 p-8 text-center">
                                <p className="text-sm text-gray-400">Nenhuma entrega nesse filtro.</p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {filteredDeliveries.map(delivery => (
                                    <div key={delivery.id} className="bg-white rounded-xl border border-gray-100 px-4 py-3 flex items-center justify-between">
                                        <div>
                                            <p className="font-bold text-gray-900 text-sm">{delivery.company_name}</p>
                                            <p className="text-xs text-gray-400">Pedido #{delivery.order_short_id} · {delivery.delivered_at}</p>
                                        </div>
                                        <div className="flex flex-col items-end gap-1">
                                            <span className={`text-sm font-bold ${delivery.is_paid ? 'text-emerald-600' : 'text-gray-900'}`}>
                                                {money(delivery.driver_fee)}
                                            </span>
                                            <span className={`inline-flex items-center gap-1 text-[9.5px] font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wide ${
                                                delivery.is_paid ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-700'
                                            }`}>
                                                {delivery.is_paid ? <Check size={9} /> : <Clock size={9} />}
                                                {delivery.is_paid ? 'Paga' : 'A receber'}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
