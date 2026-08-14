import React, { useEffect, useRef, useState } from 'react';
import MarketplaceLayout from '@/Layouts/MarketplaceLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { Package, Clock, CheckCircle2, Truck, AlertCircle, ChevronDown, ShoppingBag, RotateCcw, X, ListOrdered, KeyRound } from 'lucide-react';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

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

const timelineSteps = [
    { key: 'created_at', status: 'pending', label: 'Pedido recebido', icon: Clock },
    { key: 'confirmed_at', status: 'processing', label: 'Em separação', icon: Package },
    { key: 'shipped_at', status: 'shipped', label: 'Saiu para entrega', icon: Truck },
    { key: 'delivered_at', status: 'delivered', label: 'Entregue', icon: CheckCircle2 },
];

const statusOrder = ['pending', 'processing', 'shipped', 'delivered'];

function OrderTimeline({ order }) {
    if (order.status === 'cancelled') {
        return (
            <div className="flex items-center gap-2 text-red-500 font-bold text-sm bg-red-50 rounded-xl px-4 py-3">
                <AlertCircle size={16} />
                Pedido cancelado{order.cancelled_at ? ` em ${order.cancelled_at}` : ''}
            </div>
        );
    }

    const currentIndex = statusOrder.indexOf(order.status);

    return (
        <div className="flex flex-col gap-0">
            {timelineSteps.map((step, i) => {
                const done = i <= currentIndex;
                const Icon = step.icon;
                const timestamp = order[step.key];
                return (
                    <div key={step.key} className="flex gap-3">
                        <div className="flex flex-col items-center">
                            <div className={`w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 ${done ? 'bg-red-500 text-white' : 'bg-gray-100 text-gray-300'}`}>
                                <Icon size={14} />
                            </div>
                            {i < timelineSteps.length - 1 && (
                                <div className={`w-0.5 flex-grow min-h-[16px] ${i < currentIndex ? 'bg-red-500' : 'bg-gray-100'}`} />
                            )}
                        </div>
                        <div className="pb-4">
                            <p className={`text-sm font-bold ${done ? 'text-gray-900' : 'text-gray-300'}`}>{step.label}</p>
                            {timestamp && <p className="text-xs text-gray-400">{timestamp}</p>}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function EtaBadge({ order }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (order.status === 'delivered' || order.status === 'cancelled' || !order.estimated_ready_at) return;
        const timer = setInterval(() => setNow(Date.now()), 30000);
        return () => clearInterval(timer);
    }, [order.status, order.estimated_ready_at]);

    if (!order.estimated_ready_at || order.status === 'delivered' || order.status === 'cancelled') {
        return null;
    }

    const diffMinutes = Math.round((new Date(order.estimated_ready_at).getTime() - now) / 60000);

    return (
        <div className="flex items-center gap-1.5 text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full w-max">
            <Clock size={12} />
            {diffMinutes > 0 ? `Previsão: ~${diffMinutes} min` : 'Previsão: a qualquer momento'}
        </div>
    );
}

function QueueBadge({ order }) {
    if (order.status !== 'pending' && order.status !== 'processing') return null;
    if (order.queue_position === null || order.queue_position === undefined) return null;

    return (
        <div className="flex items-center gap-1.5 text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-full w-max">
            <ListOrdered size={12} />
            {order.queue_position === 0
                ? 'Você é o próximo!'
                : `${order.queue_position} pedido${order.queue_position > 1 ? 's' : ''} na sua frente`}
        </div>
    );
}

function DeliveryCodeReveal({ code }) {
    return (
        <div className="bg-red-50 border border-dashed border-red-100 rounded-2xl p-4 mb-3 text-center">
            <div className="flex items-center justify-center gap-2 mb-1">
                <KeyRound size={14} className="text-red-600" />
                <p className="text-[11px] font-extrabold text-red-600 uppercase tracking-wider">Código de confirmação</p>
            </div>
            <div className="flex gap-2.5 justify-center my-3">
                {code.split('').map((digit, i) => (
                    <div key={i} className="w-12 h-14 bg-white border border-red-100 rounded-xl flex items-center justify-center text-2xl font-black text-gray-900">
                        {digit}
                    </div>
                ))}
            </div>
            <p className="text-xs font-semibold text-red-600 leading-relaxed">
                Mostre esse código pro entregador quando ele chegar — é assim que a gente confirma que o pagamento foi feito com a pessoa certa.
            </p>
        </div>
    );
}

function PaidReceipt({ code, driverName, collectedAt }) {
    return (
        <div className="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-2xl px-4 py-3 mb-3">
            <div className="w-7 h-7 bg-emerald-500 text-white rounded-full flex items-center justify-center flex-shrink-0">
                <CheckCircle2 size={16} />
            </div>
            <div>
                <p className="text-xs font-extrabold text-emerald-700">Entrega confirmada com o código {code}</p>
                {(driverName || collectedAt) && (
                    <p className="text-[11px] text-emerald-600 mt-0.5">
                        Pagamento recebido{driverName ? ` por ${driverName}` : ''}{collectedAt ? ` às ${collectedAt}` : ''}
                    </p>
                )}
            </div>
        </div>
    );
}

function markerIcon(color) {
    return L.divIcon({
        className: '',
        html: `<div style="width:16px;height:16px;background:${color};border:2px solid white;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>`,
        iconAnchor: [8, 8],
    });
}

function DeliveryMap({ order }) {
    const mapRef = useRef(null);
    const mapInstance = useRef(null);
    const markersRef = useRef({});
    const [tracking, setTracking] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;

        const fetchTracking = async () => {
            try {
                const res = await axios.get(`/meus-pedidos/${order.uuid}/rastreio`);
                if (!cancelled) setTracking(res.data);
            } catch {
                // silently ignore, keep last known state
            } finally {
                if (!cancelled) setLoading(false);
            }
        };

        fetchTracking();
        const interval = setInterval(fetchTracking, 10000);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [order.uuid]);

    useEffect(() => {
        if (!tracking?.tracking_available || !mapRef.current) return;

        if (!mapInstance.current) {
            mapInstance.current = L.map(mapRef.current);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
            }).addTo(mapInstance.current);
        }

        const map = mapInstance.current;
        const { origin, destination, driver_position: driverPosition } = tracking;
        const bounds = [];

        if (origin?.latitude && origin?.longitude) {
            if (!markersRef.current.origin) {
                markersRef.current.origin = L.marker([origin.latitude, origin.longitude], { icon: markerIcon('#3b82f6') })
                    .addTo(map)
                    .bindTooltip('Loja');
            }
            bounds.push([origin.latitude, origin.longitude]);
        }

        if (destination?.latitude && destination?.longitude) {
            if (!markersRef.current.destination) {
                markersRef.current.destination = L.marker([destination.latitude, destination.longitude], { icon: markerIcon('#10b981') })
                    .addTo(map)
                    .bindTooltip('Endereço de entrega');
            }
            bounds.push([destination.latitude, destination.longitude]);
        }

        if (driverPosition) {
            if (markersRef.current.driver) {
                markersRef.current.driver.setLatLng([driverPosition.latitude, driverPosition.longitude]);
            } else {
                markersRef.current.driver = L.marker([driverPosition.latitude, driverPosition.longitude], { icon: markerIcon('#ef4444') })
                    .addTo(map)
                    .bindTooltip(tracking.driver_name, { permanent: true, direction: 'top', offset: [0, -10] });
            }
            bounds.push([driverPosition.latitude, driverPosition.longitude]);
        }

        if (bounds.length === 1) {
            map.setView(bounds[0], 14);
        } else if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [40, 40] });
        }

        setTimeout(() => map.invalidateSize(), 100);
    }, [tracking]);

    useEffect(() => () => {
        if (mapInstance.current) {
            mapInstance.current.remove();
            mapInstance.current = null;
            markersRef.current = {};
        }
    }, []);

    if (loading) {
        return <div className="h-48 rounded-xl bg-gray-100 animate-pulse mt-3" />;
    }

    if (!tracking?.tracking_available) {
        return null;
    }

    return (
        <div className="mt-3">
            <div ref={mapRef} className="h-56 w-full rounded-xl overflow-hidden border border-gray-200" />
            <p className="text-xs text-gray-400 mt-2 text-center">
                {tracking.driver_position
                    ? `${tracking.driver_name} · atualizado às ${new Date(tracking.driver_position.updated_at).toLocaleTimeString('pt-BR')}`
                    : `Aguardando ${tracking.driver_name} iniciar o compartilhamento de localização...`}
            </p>
        </div>
    );
}

function OrderCard({ order, index }) {
    const [expanded, setExpanded] = useState(false);
    const [reordering, setReordering] = useState(false);
    const config = statusConfig[order.status] || statusConfig.pending;
    const StatusIcon = config.icon;

    const handleReorder = () => {
        if (reordering) return;
        setReordering(true);
        router.post(`/store/${order.company.uuid}/orders/${order.uuid}/reorder`, {}, {
            onFinish: () => setReordering(false),
        });
    };

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
                    <div className="flex flex-col items-end gap-1.5">
                        <div className={`flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-sm ${config.bg} ${config.color}`}>
                            <StatusIcon size={18} />
                            {config.label}
                        </div>
                        <QueueBadge order={order} />
                        <EtaBadge order={order} />
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
                                {order.status === 'shipped' && order.payment_method !== 'credit' && order.delivery?.payment_confirmation_code && (
                                    <DeliveryCodeReveal code={order.delivery.payment_confirmation_code} />
                                )}
                                {order.status === 'delivered' && order.delivery?.payment_collected && order.delivery?.payment_confirmation_code && (
                                    <PaidReceipt
                                        code={order.delivery.payment_confirmation_code}
                                        driverName={order.delivery.driver_name}
                                        collectedAt={order.delivery.payment_collected_at}
                                    />
                                )}
                                <OrderTimeline order={order} />
                                {order.status === 'shipped' && <DeliveryMap order={order} />}
                                <div className="space-y-2 mb-3 border-t border-gray-200 pt-3">
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

                                {order.can_reorder && (
                                    <button
                                        onClick={handleReorder}
                                        disabled={reordering}
                                        className="mt-4 w-full flex items-center justify-center gap-2 bg-white border border-gray-200 text-gray-700 font-bold py-3 rounded-xl hover:border-red-400 hover:text-red-500 transition-colors disabled:opacity-50"
                                    >
                                        <RotateCcw size={16} className={reordering ? 'animate-spin' : ''} />
                                        {reordering ? 'Recriando pedido...' : 'Pedir novamente'}
                                    </button>
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

function FlashToast() {
    const { flash } = usePage().props;
    const [dismissed, setDismissed] = useState(false);
    const message = flash?.success || flash?.error;

    useEffect(() => setDismissed(false), [flash?.success, flash?.error]);

    if (!message || dismissed) return null;

    const isError = Boolean(flash?.error);

    return (
        <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className={`mb-6 flex items-center justify-between gap-3 rounded-2xl px-5 py-3.5 font-bold text-sm ${isError ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700'}`}
        >
            {message}
            <button onClick={() => setDismissed(true)} className="opacity-60 hover:opacity-100">
                <X size={16} />
            </button>
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

                <FlashToast />

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
