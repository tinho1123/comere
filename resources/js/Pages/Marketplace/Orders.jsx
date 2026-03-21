import React, { useState } from 'react';
import MarketplaceLayout from '@/Layouts/MarketplaceLayout';
import { Head, Link } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { Package, Clock, CheckCircle2, Truck, AlertCircle, ChevronDown, ShoppingBag } from 'lucide-react';

const statusConfig = {
    pending: { label: 'Aguardando Aprovação', color: 'text-amber-500', bg: 'bg-amber-50', icon: Clock },
    processing: { label: 'Em separação', color: 'text-blue-500', bg: 'bg-blue-50', icon: Package },
    shipped: { label: 'Saiu para entrega', color: 'text-indigo-500', bg: 'bg-indigo-50', icon: Truck },
    delivered: { label: 'Entregue', color: 'text-emerald-500', bg: 'bg-emerald-50', icon: CheckCircle2 },
    cancelled: { label: 'Cancelado', color: 'text-red-500', bg: 'bg-red-50', icon: AlertCircle },
};

const paymentLabels = {
    cash: 'Dinheiro',
    debit: 'Débito',
    credit: 'Crédito',
    pix: 'Pix',
};

function OrderCard({ order, index }) {
    const [expanded, setExpanded] = useState(false);
    const config = statusConfig[order.status] || statusConfig.pending;
    const StatusIcon = config.icon;

    return (
        <motion.div
            key={order.uuid}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            className="bg-white rounded-3xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-md transition-shadow"
        >
            <div className="p-6">
                <div className="flex flex-wrap justify-between items-start gap-4 mb-4">
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center p-2 overflow-hidden">
                            <img src={order.company.logo} alt={order.company.name} className="w-full h-full object-contain" />
                        </div>
                        <div>
                            <h3 className="font-bold text-lg text-gray-900">{order.company.name}</h3>
                            <p className="text-xs text-gray-400 font-bold uppercase tracking-wider">Pedido #{order.uuid.substring(0, 8)}</p>
                        </div>
                    </div>
                    <div className={`flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-sm ${config.bg} ${config.color}`}>
                        <StatusIcon size={18} />
                        {config.label}
                    </div>
                </div>

                <div className="flex justify-between items-center">
                    <div>
                        <span className="text-xs text-gray-400 font-medium block">Realizado em {order.created_at}</span>
                        <span className="text-lg font-extrabold text-red-500">
                            {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(order.total_amount)}
                        </span>
                    </div>
                    <button
                        onClick={() => setExpanded(!expanded)}
                        className="flex items-center gap-1 text-sm font-bold text-red-500 hover:text-red-600 transition-colors"
                    >
                        Detalhes do Pedido
                        <motion.span animate={{ rotate: expanded ? 180 : 0 }} transition={{ duration: 0.2 }}>
                            <ChevronDown size={16} />
                        </motion.span>
                    </button>
                </div>

                <AnimatePresence>
                    {expanded && (
                        <motion.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            transition={{ duration: 0.2 }}
                            className="overflow-hidden"
                        >
                            <div className="bg-gray-50 rounded-2xl p-4 mt-4">
                                <div className="space-y-2 mb-3">
                                    {order.items.map((item, i) => (
                                        <div key={i} className="flex justify-between items-center text-sm">
                                            <span className="text-gray-600">
                                                <span className="font-bold text-gray-900">{item.quantity}x</span> {item.product_name}
                                            </span>
                                            <span className="font-medium text-gray-900">
                                                {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(item.total_amount)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                                <div className="border-t border-gray-200 pt-3 flex justify-between items-center">
                                    <span className="text-xs text-gray-500 font-medium uppercase tracking-widest">Total</span>
                                    <span className="text-lg font-extrabold text-red-500">
                                        {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(order.total_amount)}
                                    </span>
                                </div>
                                {order.payment_method && (
                                    <div className="border-t border-gray-200 mt-3 pt-3 flex justify-between items-center text-sm">
                                        <span className="text-gray-500 font-medium">Forma de pagamento</span>
                                        <span className="font-bold text-gray-800">
                                            {paymentLabels[order.payment_method] ?? order.payment_method}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
            <div className="bg-gradient-to-r from-red-500 to-red-600 h-1.5 w-full opacity-10" />
        </motion.div>
    );
}

export default function Orders({ orders }) {
    return (
        <MarketplaceLayout>
            <Head title="Meus Pedidos - Comere" />

            <div className="max-w-4xl mx-auto">
                <header className="mb-8">
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Meus Pedidos</h1>
                    <p className="text-gray-500 mt-2 font-medium">Acompanhe suas compras e o status da entrega em tempo real.</p>
                </header>

                {orders.length === 0 ? (
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        className="bg-white rounded-3xl p-12 text-center shadow-sm border border-gray-100"
                    >
                        <div className="w-20 h-20 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-6">
                            <ShoppingBag size={40} />
                        </div>
                        <h3 className="text-xl font-bold text-gray-900 mb-2">Você ainda não fez nenhum pedido</h3>
                        <p className="text-gray-500 mb-8 max-w-xs mx-auto">Explore as melhores lojas da sua região e faça sua primeira compra agora!</p>
                        <Link
                            href="/"
                            className="inline-flex items-center justify-center px-8 py-3 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 transition-all shadow-lg shadow-red-500/20 active:scale-95"
                        >
                            Ver Lojas Disponíveis
                        </Link>
                    </motion.div>
                ) : (
                    <div className="space-y-6">
                        {orders.map((order, index) => (
                            <OrderCard key={order.uuid} order={order} index={index} />
                        ))}
                    </div>
                )}
            </div>
        </MarketplaceLayout>
    );
}
