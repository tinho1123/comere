import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Wallet, Package } from 'lucide-react';

function money(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

export default function DriverHistory({ deliveries, totals }) {
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

                <div className="grid grid-cols-2 gap-3 mb-8">
                    <div className="bg-red-500 rounded-2xl p-4 shadow-lg shadow-red-500/20">
                        <p className="text-white/70 text-xs font-bold uppercase tracking-wider">Hoje</p>
                        <p className="text-white text-xl font-black">{money(totals.today)}</p>
                    </div>
                    <div className="bg-gray-900 rounded-2xl p-4">
                        <p className="text-white/60 text-xs font-bold uppercase tracking-wider">Esta semana</p>
                        <p className="text-white text-xl font-black">{money(totals.this_week)}</p>
                    </div>
                    <div className="bg-white rounded-2xl p-4 border border-gray-100 col-span-2 flex items-center justify-between">
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
                </div>

                <h2 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Histórico</h2>

                {deliveries.length === 0 ? (
                    <div className="bg-white rounded-2xl border border-gray-100 p-8 text-center">
                        <Package size={28} className="text-gray-300 mx-auto mb-3" />
                        <p className="text-sm text-gray-400">Nenhuma entrega concluída ainda.</p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {deliveries.map(delivery => (
                            <div key={delivery.id} className="bg-white rounded-xl border border-gray-100 px-4 py-3 flex items-center justify-between">
                                <div>
                                    <p className="font-bold text-gray-900 text-sm">{delivery.company_name}</p>
                                    <p className="text-xs text-gray-400">Pedido #{delivery.order_short_id} · {delivery.delivered_at}</p>
                                </div>
                                <span className="text-sm font-bold text-emerald-600">{money(delivery.driver_fee)}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
