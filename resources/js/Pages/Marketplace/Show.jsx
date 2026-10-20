import { useState, useEffect } from 'react';
import MarketplaceLayout from '../../Layouts/MarketplaceLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { ShoppingCart, X, Plus, Minus, Trash2, LogIn, MapPin, Truck, Heart, Star, ChevronDown, ChevronLeft, Tag, Check, Banknote, CreditCard, QrCode, AlertTriangle } from 'lucide-react';
import axios from 'axios';
import { getCurrentPosition, haversineKm } from '../../utils/geo';

const CHECKOUT_DISTANCE_WARNING_KM = 3;

const PAYMENT_METHODS = [
    { value: 'cash', label: 'Dinheiro', icon: Banknote },
    { value: 'credit', label: 'Cartão (maquininha)', icon: CreditCard },
    { value: 'pix', label: 'Pix', icon: QrCode },
];

function StarRating({ company }) {
    const { auth } = usePage().props;
    const [hovered, setHovered] = useState(0);
    const [selected, setSelected] = useState(company.user_rating?.rating ?? 0);
    const [average, setAverage] = useState(Number(company.rating) || 0);
    const [count, setCount] = useState(company.ratings_count);
    const [loading, setLoading] = useState(false);

    const rate = async (star) => {
        if (loading) return;
        setSelected(star);
        setLoading(true);
        try {
            const res = await axios.post(`/store/${company.uuid}/rate`, { rating: star });
            setAverage(Number(res.data.average) || 0);
            setCount(res.data.count);
        } catch {
            setSelected(company.user_rating?.rating ?? 0);
        } finally {
            setLoading(false);
        }
    };

    const display = hovered || selected || Math.round(average);

    return (
        <div className="flex items-center gap-3 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-full border border-gray-100 shadow-sm w-max">
            <div
                className="flex items-center gap-0.5"
                onMouseLeave={() => setHovered(0)}
                title={auth ? 'Clique para avaliar' : 'Entre para avaliar'}
            >
                {[1, 2, 3, 4, 5].map((star) => (
                    <button
                        key={star}
                        disabled={!auth || loading}
                        onMouseEnter={() => auth && setHovered(star)}
                        onClick={() => auth && rate(star)}
                        className={`transition-transform ${auth ? 'hover:scale-125 cursor-pointer active:scale-95' : 'cursor-default'}`}
                    >
                        <Star
                            size={16}
                            className={star <= display ? 'text-yellow-400' : 'text-gray-300'}
                            fill={star <= display ? 'currentColor' : 'none'}
                        />
                    </button>
                ))}
            </div>
            <span className="text-xs font-bold text-gray-700">
                {average ? average.toFixed(1) : '—'}
            </span>
            {count > 0 && (
                <span className="text-xs text-gray-400">({count})</span>
            )}
        </div>
    );
}

/**
 * Conteúdo do carrinho/checkout, compartilhado entre o drawer mobile e a
 * barra lateral fixa do desktop (ver mockup VitrineDesktop/Carrinho).
 */
function CartPanel({
    company, cartItems, cartCount, cartTotal, finalTotal,
    updateQuantity, removeFromCart, onEditItem,
    couponCode, setCouponCode, couponStatus, setCouponStatus, couponDiscount, couponMessage, applyCoupon, clearCoupon,
    paymentMethod, setPaymentMethod,
    isCheckingOut, checkingLocation, handleCheckout,
    onAddMoreItems, onOpenLogin,
    maxVisibleItems = Infinity,
}) {
    const { auth } = usePage().props;

    if (cartCount === 0) {
        return (
            <div className="flex flex-col items-center justify-center text-center py-10 px-4">
                <ShoppingCart size={32} className="text-gray-300 mb-3" />
                <p className="text-sm font-semibold text-gray-500">Seu carrinho está vazio</p>
                <p className="text-xs text-gray-400 mt-1">Adicione itens do cardápio para continuar</p>
            </div>
        );
    }

    const isScrollable = cartItems.length > maxVisibleItems;

    return (
        <div className="flex flex-col gap-4">
            <div
                className={`flex flex-col gap-3 ${isScrollable ? 'overflow-y-auto pr-1' : ''}`}
                style={isScrollable ? { maxHeight: `${maxVisibleItems * 88 + (maxVisibleItems - 1) * 12}px` } : undefined}
            >
                {cartItems.map(({ product, quantity, notes }) => (
                    <div key={product.id} className="bg-white border border-gray-100 rounded-2xl p-3 flex gap-3 items-center">
                        <button
                            type="button"
                            onClick={() => onEditItem?.(product)}
                            className="w-14 h-14 rounded-xl overflow-hidden flex-shrink-0 border border-gray-50"
                        >
                            <img src={product.image ?? '/icons/icon-192x192.png'} className="w-full h-full object-cover" alt={product.name} />
                        </button>
                        <button type="button" onClick={() => onEditItem?.(product)} className="flex-grow min-w-0 text-left">
                            <p className="font-bold text-sm text-gray-900 truncate">{product.name}</p>
                            <p className="text-xs font-bold text-red-500">R$ {Number(product.amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</p>
                            {notes && <p className="text-[11px] text-gray-400 italic truncate mt-0.5">Obs: {notes}</p>}
                        </button>
                        <div className="flex items-center gap-2 flex-shrink-0 border border-gray-200 rounded-lg px-2 py-1.5">
                            <button onClick={() => updateQuantity(product.id, quantity - 1)} className="text-red-500 hover:scale-110 transition-transform">
                                <Minus size={13} />
                            </button>
                            <span className="text-xs font-black w-4 text-center">{quantity}</span>
                            <button onClick={() => updateQuantity(product.id, quantity + 1)} className="text-red-500 hover:scale-110 transition-transform">
                                <Plus size={13} />
                            </button>
                        </div>
                        <button onClick={() => removeFromCart(product.id)} className="text-gray-300 hover:text-red-500 transition-colors flex-shrink-0">
                            <Trash2 size={15} />
                        </button>
                    </div>
                ))}
            </div>

            {onAddMoreItems && (
                <button
                    onClick={onAddMoreItems}
                    className="border border-dashed border-gray-200 rounded-xl px-4 py-3 flex items-center gap-2 text-sm font-semibold text-gray-600 hover:border-red-300 hover:text-red-500 transition-colors"
                >
                    <Plus size={14} className="text-red-500" />
                    Adicionar mais itens
                </button>
            )}

            {/* Cupom de desconto */}
            {auth.user && (
                <div>
                    {couponStatus === 'valid' ? (
                        <div className="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5">
                            <div className="flex items-center gap-2 text-emerald-700 text-sm font-bold">
                                <Check size={16} />
                                Cupom {couponCode.trim().toUpperCase()} aplicado
                            </div>
                            <button onClick={clearCoupon} className="text-emerald-600 hover:text-emerald-800">
                                <X size={16} />
                            </button>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-1.5">
                            <div className="flex items-center gap-2">
                                <div className="flex-grow relative">
                                    <Tag size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                                    <input
                                        type="text"
                                        value={couponCode}
                                        onChange={(e) => { setCouponCode(e.target.value); setCouponStatus('idle'); }}
                                        onKeyDown={(e) => e.key === 'Enter' && applyCoupon()}
                                        placeholder="Cupom de desconto"
                                        className="w-full pl-9 pr-3 py-2.5 text-sm font-medium rounded-xl border border-gray-200 focus:border-red-400 focus:outline-none uppercase"
                                    />
                                </div>
                                <button
                                    onClick={applyCoupon}
                                    disabled={!couponCode.trim() || couponStatus === 'checking'}
                                    className="px-4 py-2.5 text-sm font-bold rounded-xl bg-gray-900 text-white hover:bg-gray-800 transition-colors disabled:opacity-40"
                                >
                                    {couponStatus === 'checking' ? '...' : 'Aplicar'}
                                </button>
                            </div>
                            {couponStatus === 'invalid' && (
                                <span className="text-xs font-medium text-red-500">{couponMessage}</span>
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* Resumo financeiro */}
            <div className="flex flex-col gap-1.5">
                <div className="flex justify-between items-center text-sm">
                    <span className="text-gray-500 font-medium">Subtotal</span>
                    <span className="text-gray-700 font-medium">
                        R$ {cartTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                    </span>
                </div>
                {company.delivery_fee !== null && company.delivery_fee !== undefined && (
                    <div className="flex justify-between items-center text-sm">
                        <span className="text-gray-500 font-medium">Taxa de entrega</span>
                        <span className="text-gray-700 font-medium">
                            R$ {Number(company.delivery_fee).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                        </span>
                    </div>
                )}
                {couponDiscount > 0 && (
                    <div className="flex justify-between items-center text-sm">
                        <span className="text-emerald-600 font-medium">Desconto</span>
                        <span className="text-emerald-600 font-bold">
                            - R$ {couponDiscount.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                        </span>
                    </div>
                )}
                <div className="flex justify-between items-center pt-1">
                    <span className="font-bold text-gray-900">Total</span>
                    <span className="text-xl font-black text-red-500">
                        R$ {finalTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                    </span>
                </div>
            </div>

            {auth.user ? (
                <>
                    <div>
                        <p className="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Pagamento na entrega</p>
                        <div className="grid grid-cols-3 gap-2">
                            {PAYMENT_METHODS.map(({ value, label, icon: Icon }) => (
                                <button
                                    key={value}
                                    onClick={() => setPaymentMethod(value)}
                                    className={`flex flex-col items-center gap-1 py-3 rounded-xl border text-xs font-semibold transition-all ${
                                        paymentMethod === value
                                            ? 'border-red-500 bg-red-50 text-red-600'
                                            : 'border-gray-200 text-gray-500 hover:border-gray-300'
                                    }`}
                                >
                                    <Icon size={18} />
                                    {label}
                                </button>
                            ))}
                        </div>
                        <div className="bg-gray-50 rounded-lg px-3 py-2 mt-2 text-[11px] text-gray-500 leading-relaxed">
                            💳 O pagamento é feito direto com a loja/entregador — o Comere não processa cobranças.
                        </div>
                    </div>

                    <button
                        onClick={handleCheckout}
                        disabled={isCheckingOut || checkingLocation || !paymentMethod}
                        className="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 transition-all active:scale-95 disabled:opacity-50 uppercase tracking-widest text-sm"
                    >
                        {isCheckingOut
                            ? 'Enviando...'
                            : checkingLocation
                                ? 'Verificando localização...'
                                : `Ir para o checkout · R$ ${finalTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`}
                    </button>
                </>
            ) : (
                <div className="text-center bg-gray-50 rounded-xl p-4">
                    <LogIn size={24} className="mx-auto text-gray-400 mb-2" />
                    <p className="text-sm text-gray-500 mb-3">Faça login para finalizar o pedido</p>
                    <button onClick={onOpenLogin} className="text-sm font-bold text-red-500 hover:underline">
                        Entrar na conta
                    </button>
                </div>
            )}
        </div>
    );
}

function cartStorageKey(companyUuid) {
    return `marketplace_cart_${companyUuid}`;
}

function restoreCart(companyUuid, productsByCategory) {
    try {
        const raw = sessionStorage.getItem(cartStorageKey(companyUuid));
        if (!raw) return {};

        const saved = JSON.parse(raw);
        const allProducts = Object.values(productsByCategory).flat();
        const restored = {};

        for (const { product_id, quantity, notes } of saved) {
            const product = allProducts.find(p => p.id === product_id);
            if (product && quantity > 0) {
                restored[product_id] = { product, quantity, notes: notes ?? null };
            }
        }

        return restored;
    } catch {
        return {};
    }
}

export default function MarketplaceShow({ company, productsByCategory }) {
    const { auth, default_address } = usePage().props;
    const [activeCategory, setActiveCategory] = useState(Object.keys(productsByCategory)[0]);
    const [cart, setCart] = useState(() => restoreCart(company.uuid, productsByCategory));
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [isCheckingOut, setIsCheckingOut] = useState(false);
    const [favorited, setFavorited] = useState(company.is_favorited);
    const [favLoading, setFavLoading] = useState(false);
    const [couponCode, setCouponCode] = useState('');
    const [couponStatus, setCouponStatus] = useState('idle'); // idle | checking | valid | invalid
    const [couponDiscount, setCouponDiscount] = useState(0);
    const [couponMessage, setCouponMessage] = useState('');
    const [paymentMethod, setPaymentMethod] = useState(null);
    const [checkingLocation, setCheckingLocation] = useState(false);
    const [rangeWarningDistance, setRangeWarningDistance] = useState(null);

    // Tela de detalhe do produto
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [detailQuantity, setDetailQuantity] = useState(1);
    const [detailNotes, setDetailNotes] = useState('');

    const openProductDetail = (product) => {
        const existing = cart[product.id];
        setSelectedProduct(product);
        setDetailQuantity(existing?.quantity ?? 1);
        setDetailNotes(existing?.notes ?? '');
    };

    const closeProductDetail = () => setSelectedProduct(null);

    const toggleFavorite = async () => {
        if (!auth || favLoading) return;
        setFavLoading(true);
        try {
            const res = await axios.post(`/favorites/${company.uuid}`);
            setFavorited(res.data.favorited);
        } catch {
            // silently ignore
        } finally {
            setFavLoading(false);
        }
    };

    const cartItems = Object.values(cart);
    const cartCount = cartItems.reduce((sum, item) => sum + item.quantity, 0);
    const cartTotal = cartItems.reduce((sum, item) => sum + Number(item.product.amount) * item.quantity, 0);
    const deliveryFee = company.delivery_fee !== null && company.delivery_fee !== undefined ? Number(company.delivery_fee) : 0;
    const finalTotal = Math.max(cartTotal + deliveryFee - couponDiscount, 0);

    useEffect(() => {
        try {
            if (cartItems.length > 0) {
                const toSave = cartItems.map(({ product, quantity, notes }) => ({ product_id: product.id, quantity, notes: notes ?? null }));
                sessionStorage.setItem(cartStorageKey(company.uuid), JSON.stringify(toSave));
            } else {
                sessionStorage.removeItem(cartStorageKey(company.uuid));
            }
        } catch {
            // sessionStorage indisponível (modo privado, etc.) — carrinho só dura a sessão da página
        }
    }, [cart]);

    const applyCoupon = async () => {
        if (!couponCode.trim() || couponStatus === 'checking') return;
        setCouponStatus('checking');
        try {
            const res = await axios.post(`/store/${company.uuid}/coupons/validate`, {
                code: couponCode.trim(),
                subtotal: cartTotal,
            });
            if (res.data.valid) {
                setCouponDiscount(Number(res.data.discount_amount));
                setCouponStatus('valid');
                setCouponMessage(res.data.message);
            } else {
                setCouponDiscount(0);
                setCouponStatus('invalid');
                setCouponMessage(res.data.message);
            }
        } catch {
            setCouponDiscount(0);
            setCouponStatus('invalid');
            setCouponMessage('Não foi possível validar o cupom.');
        }
    };

    const clearCoupon = () => {
        setCouponCode('');
        setCouponStatus('idle');
        setCouponDiscount(0);
        setCouponMessage('');
    };

    const addToCart = (product) => {
        setCart(prev => ({
            ...prev,
            [product.id]: {
                product,
                quantity: (prev[product.id]?.quantity ?? 0) + 1,
                notes: prev[product.id]?.notes ?? null,
            },
        }));
    };

    const confirmProductDetail = () => {
        setCart(prev => ({
            ...prev,
            [selectedProduct.id]: {
                product: selectedProduct,
                quantity: detailQuantity,
                notes: detailNotes.trim() || null,
            },
        }));
        closeProductDetail();
    };

    const updateQuantity = (productId, quantity) => {
        if (quantity <= 0) {
            removeFromCart(productId);
            return;
        }
        setCart(prev => ({
            ...prev,
            [productId]: { ...prev[productId], quantity },
        }));
    };

    const removeFromCart = (productId) => {
        setCart(prev => {
            const next = { ...prev };
            delete next[productId];
            return next;
        });
    };

    const submitOrder = () => {
        setIsCheckingOut(true);
        router.post(`/store/${company.uuid}/orders`, {
            items: cartItems.map(item => ({
                product_id: item.product.id,
                quantity: item.quantity,
                notes: item.notes || undefined,
            })),
            coupon_code: couponStatus === 'valid' ? couponCode.trim() : undefined,
            payment_method: paymentMethod,
        }, {
            onSuccess: () => {
                setCart({});
                try {
                    sessionStorage.removeItem(cartStorageKey(company.uuid));
                } catch {
                    // sessionStorage indisponível
                }
                setIsCartOpen(false);
                clearCoupon();
                setPaymentMethod(null);
            },
            onFinish: () => setIsCheckingOut(false),
        });
    };

    const handleCheckout = async () => {
        if (!paymentMethod) return;

        if (default_address?.latitude && default_address?.longitude) {
            setCheckingLocation(true);
            try {
                const { latitude, longitude } = await getCurrentPosition();
                const distance = haversineKm(default_address.latitude, default_address.longitude, latitude, longitude);
                setCheckingLocation(false);

                if (distance > CHECKOUT_DISTANCE_WARNING_KM) {
                    setRangeWarningDistance(distance);
                    return;
                }
            } catch {
                setCheckingLocation(false);
                // sem GPS disponível ou permissão negada: não bloqueia o pedido
            }
        }

        submitOrder();
    };

    const confirmCheckoutAnyway = () => {
        setRangeWarningDistance(null);
        submitOrder();
    };

    const cartPanelProps = {
        company, cartItems, cartCount, cartTotal, finalTotal,
        updateQuantity, removeFromCart, onEditItem: openProductDetail,
        couponCode, setCouponCode, couponStatus, setCouponStatus, couponDiscount, couponMessage, applyCoupon, clearCoupon,
        paymentMethod, setPaymentMethod,
        isCheckingOut, checkingLocation, handleCheckout,
        onOpenLogin: () => {
            setIsCartOpen(false);
            window.dispatchEvent(new CustomEvent('open-auth-modal'));
        },
    };

    return (
        <MarketplaceLayout>
            <Head title={`${company.name} - Marketplace`} />

            {/* Cabeçalho da Loja */}
            <div className="relative mb-6">
                <div className="h-44 md:h-56 rounded-3xl overflow-hidden shadow-lg border border-gray-100">
                    <img src={company.banner} className="w-full h-full object-cover" alt="Banner" />
                </div>

                <button
                    onClick={() => router.visit('/')}
                    className="absolute top-4 left-4 z-10 w-9 h-9 rounded-full bg-white/90 backdrop-blur-sm text-gray-900 flex items-center justify-center shadow-md hover:bg-white transition-colors"
                    aria-label="Voltar"
                >
                    <ChevronLeft size={18} />
                </button>

                {auth && (
                    <button
                        onClick={toggleFavorite}
                        disabled={favLoading}
                        className={`absolute top-4 right-4 z-10 w-9 h-9 rounded-full flex items-center justify-center shadow-md transition-all ${
                            favorited ? 'bg-red-500 text-white' : 'bg-white/90 backdrop-blur-sm text-gray-400 hover:text-red-400'
                        }`}
                        aria-label={favorited ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}
                    >
                        <Heart size={16} fill={favorited ? 'currentColor' : 'none'} />
                    </button>
                )}
            </div>

            <div className="flex flex-col gap-1 mb-6">
                <h1 className="text-2xl font-black text-gray-900">{company.name}</h1>
                <div className="flex items-center gap-2 flex-wrap mt-1">
                    <StarRating company={company} />
                    <div className="flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-full border border-gray-100">
                        <span className="text-xs font-semibold text-gray-600 uppercase tracking-tighter">{company.type}</span>
                        <span className="text-gray-300">|</span>
                        <span className="text-xs font-medium text-gray-500">{company.delivery_time}</span>
                    </div>
                    {company.is_open !== null && (
                        <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold ${company.is_open ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200'}`}>
                            <span className={`w-1.5 h-1.5 rounded-full ${company.is_open ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400'}`} />
                            {company.is_open ? 'Aberto agora' : 'Fechado no momento'}
                        </div>
                    )}
                </div>

                <p className="text-gray-600 text-sm mt-3 max-w-3xl leading-relaxed">
                    {company.description ?? 'Bem-vindo à nossa loja! Oferecemos os melhores produtos com a qualidade que você já conhece.'}
                </p>

                {/* Distância e entrega */}
                <div className="flex flex-wrap gap-3 mt-4">
                    {company.distance_km !== null && (
                        <div className="flex items-center gap-1.5 bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl text-sm">
                            <MapPin size={14} className="text-red-500" />
                            <span className="font-medium text-gray-700">{company.distance_km} km de você</span>
                        </div>
                    )}
                    {company.delivery_fee !== null && company.delivery_fee !== undefined && (
                        <div className="flex items-center gap-1.5 bg-green-50 border border-green-100 px-3 py-2 rounded-xl text-sm">
                            <Truck size={14} className="text-green-600" />
                            <span className="font-medium text-green-700">
                                Entrega R$ {Number(company.delivery_fee).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                            </span>
                        </div>
                    )}
                    {company.distance_km !== null && company.delivery_fee === null && (
                        <div className="flex items-center gap-1.5 bg-red-50 border border-red-100 px-3 py-2 rounded-xl text-sm">
                            <Truck size={14} className="text-red-500" />
                            <span className="font-medium text-red-600">Fora da área de entrega</span>
                        </div>
                    )}
                    {company.fee_ranges?.length > 0 && (
                        <div className="flex items-center gap-2 flex-wrap">
                            {company.fee_ranges.filter(r => r.is_active).map(r => (
                                <span key={r.max_km} className={`text-[11px] font-bold px-2.5 py-1 rounded-full border ${company.distance_km !== null && company.distance_km <= r.max_km && (company.fee_ranges.filter(x => x.is_active && x.max_km < r.max_km).every(x => company.distance_km > x.max_km)) ? 'bg-red-500 text-white border-red-500' : 'bg-gray-50 text-gray-500 border-gray-200'}`}>
                                    até {r.max_km}km · R$ {Number(r.fee).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {/* Horário de Funcionamento */}
                {company.hours?.length > 0 && (
                    <details className="mt-4 group">
                        <summary className="cursor-pointer list-none flex items-center gap-2 text-sm font-bold text-gray-700 hover:text-red-500 transition-colors select-none w-max">
                            <span>🕐 Horário de funcionamento</span>
                            <ChevronDown size={15} className="group-open:rotate-180 transition-transform" />
                        </summary>
                        <div className="mt-3 rounded-2xl border border-gray-100 overflow-hidden">
                            {company.hours.map((h) => {
                                const isToday = new Date().getDay() === h.day_of_week;
                                return (
                                    <div
                                        key={h.day_of_week}
                                        className={`flex items-center justify-between px-4 py-2.5 text-sm border-b border-gray-50 last:border-0 ${isToday ? 'bg-red-50' : ''}`}
                                    >
                                        <span className={`font-semibold ${isToday ? 'text-red-600' : 'text-gray-700'}`}>
                                            {h.day_name}{isToday && <span className="ml-2 text-[10px] font-bold bg-red-500 text-white px-1.5 py-0.5 rounded-full">hoje</span>}
                                        </span>
                                        {h.is_closed ? (
                                            <span className="text-gray-400 font-medium">Fechado</span>
                                        ) : (
                                            <span className={isToday ? 'text-red-600 font-bold' : 'text-gray-600'}>
                                                {h.opens_at} – {h.closes_at}
                                            </span>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </details>
                )}
            </div>

            {/* Categorias — abas horizontais, iguais em mobile e desktop */}
            <nav className="flex gap-6 border-b border-gray-100 overflow-x-auto no-scrollbar mb-2">
                {Object.keys(productsByCategory).map((cat) => (
                    <button
                        key={cat}
                        onClick={() => setActiveCategory(cat)}
                        className={`text-sm font-bold whitespace-nowrap pb-3 transition-colors border-b-2 ${
                            activeCategory === cat
                                ? 'text-red-500 border-red-500'
                                : 'text-gray-400 border-transparent hover:text-gray-600'
                        }`}
                    >
                        {cat}
                    </button>
                ))}
            </nav>

            <div className="flex flex-col lg:flex-row gap-10 pt-6">
                {/* Lista de Produtos */}
                <div className="flex-grow">
                    {productsByCategory[activeCategory]?.map((product) => (
                        <div key={product.uuid} className="flex gap-4 py-4 border-b border-gray-50 last:border-0">
                            <button
                                type="button"
                                onClick={() => openProductDetail(product)}
                                className="flex-grow flex flex-col justify-center min-w-0 text-left"
                            >
                                <h4 className="font-bold text-gray-900 truncate">{product.name}</h4>
                                <p className="text-xs text-gray-400 mt-1 line-clamp-2 leading-relaxed">
                                    {product.description ?? 'Sem descrição disponível para este produto.'}
                                </p>
                                <div className="mt-2 flex items-center gap-3">
                                    <span className="text-sm font-black text-red-500">
                                        R$ {Number(product.amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                    </span>
                                    {product.isCool && (
                                        <span className="bg-blue-50 text-blue-600 text-[10px] font-bold px-2 py-0.5 rounded-full italic">❄️ GELADA</span>
                                    )}
                                </div>
                            </button>
                            <div className="w-20 h-20 rounded-2xl overflow-hidden flex-shrink-0 border border-gray-100 relative">
                                <img src={product.image ?? '/icons/icon-192x192.png'} className="w-full h-full object-cover" alt={product.name} />
                                {cart[product.id] ? (
                                    <div className="absolute bottom-1 right-1 flex items-center gap-1 bg-white rounded-full shadow-lg border border-gray-100 px-1">
                                        <button
                                            onClick={() => updateQuantity(product.id, cart[product.id].quantity - 1)}
                                            className="text-red-500 w-6 h-6 flex items-center justify-center font-bold hover:scale-110 transition-all"
                                        >
                                            <Minus size={12} />
                                        </button>
                                        <span className="text-xs font-black text-gray-900 min-w-[16px] text-center">{cart[product.id].quantity}</span>
                                        <button
                                            onClick={() => updateQuantity(product.id, cart[product.id].quantity + 1)}
                                            className="text-red-500 w-6 h-6 flex items-center justify-center font-bold hover:scale-110 transition-all"
                                        >
                                            <Plus size={12} />
                                        </button>
                                    </div>
                                ) : (
                                    <button
                                        onClick={() => addToCart(product)}
                                        className="absolute bottom-1 right-1 bg-white text-red-500 w-7 h-7 rounded-full shadow-lg flex items-center justify-center font-bold text-lg hover:scale-110 active:scale-90 transition-all border border-gray-100"
                                    >
                                        +
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Carrinho fixo — só no desktop (no mobile vira a barra flutuante + drawer abaixo) */}
                <div className="hidden lg:block w-[360px] flex-shrink-0">
                    <div className="sticky top-24 bg-white border border-gray-100 rounded-3xl shadow-sm p-5">
                        <h3 className="text-base font-bold text-gray-900 mb-1 flex items-center gap-2">
                            <ShoppingCart size={18} className="text-red-500" />
                            Seu carrinho {cartCount > 0 && `(${cartCount})`}
                        </h3>
                        <CartPanel {...cartPanelProps} maxVisibleItems={3} />
                    </div>
                </div>
            </div>

            {/* Barra flutuante do carrinho — só no mobile */}
            <AnimatePresence>
                {cartCount > 0 && !isCartOpen && (
                    <motion.button
                        initial={{ y: 100, opacity: 0 }}
                        animate={{ y: 0, opacity: 1 }}
                        exit={{ y: 100, opacity: 0 }}
                        onClick={() => setIsCartOpen(true)}
                        className="lg:hidden fixed bottom-20 left-4 right-4 z-40 bg-red-500 text-white px-6 py-4 rounded-2xl shadow-2xl shadow-red-500/40 flex items-center justify-between gap-4 font-bold hover:bg-red-600 transition-all active:scale-95"
                    >
                        <div className="flex items-center gap-3">
                            <div className="relative">
                                <ShoppingCart size={20} />
                                <span className="absolute -top-2 -right-2 bg-white text-red-500 text-[10px] font-black h-4 w-4 rounded-full flex items-center justify-center">{cartCount}</span>
                            </div>
                            <span className="text-sm">{cartCount} {cartCount === 1 ? 'item' : 'itens'} · R$ {cartTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
                        </div>
                        <span className="text-sm">Ver carrinho →</span>
                    </motion.button>
                )}
            </AnimatePresence>

            {/* Drawer do Carrinho — só no mobile */}
            <AnimatePresence>
                {isCartOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setIsCartOpen(false)}
                            className="lg:hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50"
                        />

                        <motion.div
                            initial={{ x: '100%' }}
                            animate={{ x: 0 }}
                            exit={{ x: '100%' }}
                            transition={{ type: 'spring', damping: 25, stiffness: 200 }}
                            className="lg:hidden fixed top-0 right-0 h-full w-full sm:w-[420px] bg-white z-[60] shadow-2xl flex flex-col"
                        >
                            <div className="flex items-center justify-between p-6 border-b border-gray-100">
                                <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                                    <ShoppingCart size={20} className="text-red-500" />
                                    Seu carrinho {cartCount > 0 && `(${cartCount})`}
                                </h2>
                                <button onClick={() => setIsCartOpen(false)} className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500">
                                    <X size={24} />
                                </button>
                            </div>

                            <div className="flex-grow overflow-y-auto p-6">
                                <span className="text-xs font-bold text-gray-400 uppercase tracking-wide block mb-4">{company.name}</span>
                                <CartPanel {...cartPanelProps} onAddMoreItems={() => setIsCartOpen(false)} />
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            {/* Aviso: cliente longe do endereço de entrega escolhido */}
            <AnimatePresence>
                {rangeWarningDistance !== null && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[70]"
                        />
                        <motion.div
                            initial={{ opacity: 0, scale: 0.95 }}
                            animate={{ opacity: 1, scale: 1 }}
                            exit={{ opacity: 0, scale: 0.95 }}
                            className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[80] bg-white rounded-2xl shadow-2xl p-6 w-[90%] max-w-sm"
                        >
                            <div className="flex items-center gap-2 text-amber-600 font-bold mb-3">
                                <AlertTriangle size={20} />
                                <span>Longe do endereço de entrega</span>
                            </div>
                            <p className="text-sm text-gray-600 mb-5">
                                Sua localização atual está a <strong>{rangeWarningDistance.toFixed(1)} km</strong> de {default_address ? `"${default_address.street}, ${default_address.number} — ${default_address.city}"` : 'seu endereço de entrega'}, o endereço escolhido para esse pedido. Confirme se é mesmo esse o endereço antes de continuar.
                            </p>
                            <div className="flex flex-col gap-2">
                                <button
                                    onClick={() => setRangeWarningDistance(null)}
                                    className="w-full border border-gray-200 text-gray-700 font-bold py-3 rounded-xl hover:bg-gray-50 transition-all"
                                >
                                    Cancelar pedido
                                </button>
                                <button
                                    onClick={confirmCheckoutAnyway}
                                    className="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded-xl transition-all"
                                >
                                    Pedir mesmo assim
                                </button>
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            {/* Detalhe do produto */}
            <AnimatePresence>
                {selectedProduct && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={closeProductDetail}
                            className="hidden sm:block fixed inset-0 bg-black/40 backdrop-blur-sm z-[75]"
                        />
                        <motion.div
                            initial={{ opacity: 0, y: 24 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: 24 }}
                            transition={{ type: 'spring', damping: 28, stiffness: 220 }}
                            className="fixed inset-0 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 z-[80] bg-white sm:rounded-3xl sm:shadow-2xl sm:w-[440px] sm:max-h-[85vh] flex flex-col overflow-hidden"
                        >
                            <div className="relative flex-shrink-0 bg-gray-100">
                                <img
                                    src={selectedProduct.image ?? '/icons/icon-192x192.png'}
                                    className="w-full h-56 sm:h-64 object-cover"
                                    alt={selectedProduct.name}
                                />
                                <button
                                    onClick={closeProductDetail}
                                    className="absolute top-4 left-4 w-9 h-9 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center shadow-md text-gray-700 hover:bg-white transition-colors"
                                >
                                    <ChevronLeft size={20} />
                                </button>
                            </div>

                            <div className="flex-grow overflow-y-auto px-5 sm:px-6 py-5 pb-32 sm:pb-5 flex flex-col gap-4">
                                <div>
                                    <h2 className="text-xl font-bold text-gray-900">{selectedProduct.name}</h2>
                                    <p className="text-sm text-gray-500 mt-2 leading-relaxed">
                                        {selectedProduct.description ?? 'Sem descrição disponível para este produto.'}
                                    </p>
                                </div>

                                <span className="text-xl font-black text-red-500">
                                    R$ {Number(selectedProduct.amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                </span>

                                <div className="h-px bg-gray-100" />

                                <div className="flex flex-col gap-2">
                                    <label className="text-sm font-bold text-gray-900">Observações</label>
                                    <textarea
                                        value={detailNotes}
                                        onChange={(e) => setDetailNotes(e.target.value.slice(0, 300))}
                                        placeholder="Ex: sem cebola, ponto da carne, etc."
                                        rows={3}
                                        className="w-full bg-gray-50 border border-gray-100 rounded-xl p-3 text-sm outline-none focus:border-red-400 focus:ring-2 focus:ring-red-500/10 transition-all resize-none"
                                    />
                                </div>
                            </div>

                            <div className="fixed sm:static bottom-0 left-0 right-0 bg-white border-t border-gray-100 px-5 sm:px-6 py-3.5 flex items-center gap-3.5 flex-shrink-0">
                                <div className="flex items-center gap-2.5 border border-gray-200 rounded-xl px-3 py-2.5 flex-shrink-0">
                                    <button onClick={() => setDetailQuantity((q) => Math.max(1, q - 1))} className="text-red-500 hover:scale-110 transition-transform">
                                        <Minus size={15} />
                                    </button>
                                    <span className="text-sm font-black w-5 text-center">{detailQuantity}</span>
                                    <button onClick={() => setDetailQuantity((q) => q + 1)} className="text-red-500 hover:scale-110 transition-transform">
                                        <Plus size={15} />
                                    </button>
                                </div>
                                <button
                                    onClick={confirmProductDetail}
                                    className="flex-1 bg-red-500 hover:bg-red-600 text-white font-bold py-3.5 rounded-xl transition-all active:scale-95"
                                >
                                    Adicionar · R$ {(Number(selectedProduct.amount) * detailQuantity).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                </button>
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>
        </MarketplaceLayout>
    );
}
