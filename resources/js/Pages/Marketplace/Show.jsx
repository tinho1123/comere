import { useState } from 'react';
import MarketplaceLayout from '../../Layouts/MarketplaceLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { ShoppingCart, X, Plus, Minus, Trash2, LogIn, MapPin, Truck } from 'lucide-react';

export default function MarketplaceShow({ company, productsByCategory }) {
    const { auth } = usePage().props;
    const [activeCategory, setActiveCategory] = useState(Object.keys(productsByCategory)[0]);
    const [cart, setCart] = useState({});
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [isCheckingOut, setIsCheckingOut] = useState(false);
    const [selectedPayment, setSelectedPayment] = useState('');

    const acceptedMethods = company.accepted_payment_methods ?? [];
    const allPaymentOptions = company.payment_options ?? {};
    const paymentOptions = Object.fromEntries(
        Object.entries(allPaymentOptions).filter(([key]) => acceptedMethods.includes(key))
    );

    const cartItems = Object.values(cart);
    const cartCount = cartItems.reduce((sum, item) => sum + item.quantity, 0);
    const cartSubtotal = cartItems.reduce((sum, item) => sum + Number(item.product.amount) * item.quantity, 0);

    const cartFee = (() => {
        if (!selectedPayment) return 0;
        return cartItems.reduce((sum, { product, quantity }) => {
            const s = product.payment_surcharges?.[selectedPayment];
            if (!s || !s.amount || s.amount <= 0) return sum;
            const itemTotal = Number(product.amount) * quantity;
            return sum + (s.type === 'percent' ? itemTotal * (s.amount / 100) : s.amount * quantity);
        }, 0);
    })();

    const cartTotal = cartSubtotal + cartFee;

    const addToCart = (product) => {
        setCart(prev => ({
            ...prev,
            [product.id]: {
                product,
                quantity: (prev[product.id]?.quantity ?? 0) + 1,
            },
        }));
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

    const handleCheckout = () => {
        setIsCheckingOut(true);
        router.post(`/store/${company.uuid}/orders`, {
            items: cartItems.map(item => ({
                product_id: item.product.id,
                quantity: item.quantity,
            })),
            payment_method: selectedPayment || null,
        }, {
            onSuccess: () => {
                setCart({});
                setIsCartOpen(false);
            },
            onFinish: () => setIsCheckingOut(false),
        });
    };

    return (
        <MarketplaceLayout>
            <Head title={`${company.name} - Marketplace`} />

            {/* Cabeçalho da Loja */}
            <div className="relative mb-20">
                <div className="h-48 md:h-64 rounded-3xl overflow-hidden shadow-lg border border-gray-100">
                    <img src={company.banner} className="w-full h-full object-cover" alt="Banner" />
                </div>

                <div className="absolute -bottom-16 left-8 flex items-end gap-6">
                    <div className="w-32 h-32 rounded-2xl border-4 border-white shadow-xl overflow-hidden bg-white">
                        <img src={company.logo} className="w-full h-full object-cover" alt="Logo" />
                    </div>
                    <div className="mb-4">
                        <h1 className="text-3xl font-black text-white drop-shadow-md select-none">{company.name}</h1>
                        <div className="flex items-center gap-3 mt-1 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full border border-gray-100 shadow-sm w-max">
                            <span className="text-yellow-500 font-bold">★ {company.rating}</span>
                            <span className="text-gray-300">|</span>
                            <span className="text-xs font-semibold text-gray-600 uppercase tracking-tighter">{company.type}</span>
                            <span className="text-gray-300">|</span>
                            <span className="text-xs font-medium text-gray-500">{company.delivery_time}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
                <p className="text-gray-600 font-medium mb-6 max-w-3xl leading-relaxed">
                    {company.description ?? 'Bem-vindo à nossa loja! Oferecemos os melhores produtos com a qualidade que você já conhece. Peça agora e aproveite nosso sistema de fiado exclusivo.'}
                </p>

                {/* Distância e entrega */}
                <div className="flex flex-wrap gap-3 mb-8">
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

                <div className="flex flex-col md:flex-row gap-12">
                    {/* Menu Lateral de Categorias */}
                    <div className="w-full md:w-64 flex-shrink-0">
                        <h3 className="text-lg font-bold mb-6 italic uppercase tracking-widest text-gray-400">Categorias</h3>
                        <nav className="flex md:flex-col gap-2 overflow-x-auto no-scrollbar">
                            {Object.keys(productsByCategory).map((cat) => (
                                <button
                                    key={cat}
                                    onClick={() => setActiveCategory(cat)}
                                    className={`text-left px-4 py-3 rounded-xl font-bold transition-all whitespace-nowrap ${activeCategory === cat
                                        ? 'bg-red-500 text-white shadow-lg shadow-red-500/20 translate-x-1'
                                        : 'text-gray-500 hover:bg-gray-50 border border-transparent'
                                    }`}
                                >
                                    {cat}
                                </button>
                            ))}
                        </nav>
                    </div>

                    {/* Lista de Produtos */}
                    <div className="flex-grow">
                        <h2 className="text-2xl font-black mb-8 border-b-4 border-red-500 inline-block pb-1">{activeCategory}</h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-12">
                            {productsByCategory[activeCategory]?.map((product) => (
                                <div key={product.uuid} className="flex gap-6 group cursor-pointer">
                                    <div className="flex-grow flex flex-col justify-between py-1">
                                        <div>
                                            <h4 className="font-bold text-gray-900 group-hover:text-red-500 transition-colors uppercase tracking-tight">{product.name}</h4>
                                            <p className="text-xs text-gray-500 mt-2 line-clamp-2 leading-relaxed">
                                                {product.description ?? 'Sem descrição disponível para este produto.'}
                                            </p>
                                        </div>
                                        <div className="mt-4 flex items-center gap-4">
                                            <span className="text-lg font-black text-gray-900">
                                                R$ {Number(product.amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                            </span>
                                            {product.isCool && (
                                                <span className="bg-blue-50 text-blue-600 text-[10px] font-bold px-2 py-0.5 rounded-full italic">❄️ GELADA</span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="w-28 h-28 rounded-2xl overflow-hidden flex-shrink-0 shadow-inner border border-gray-100 relative">
                                        <img src={product.image ?? '/icons/icon-192x192.png'} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt={product.name} />
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
                                                className="absolute bottom-1 right-1 bg-white text-red-500 w-8 h-8 rounded-full shadow-lg flex items-center justify-center font-bold text-xl hover:scale-110 active:scale-90 transition-all border border-gray-100"
                                            >
                                                +
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {/* Barra flutuante do carrinho */}
            <AnimatePresence>
                {cartCount > 0 && !isCartOpen && (
                    <motion.button
                        initial={{ y: 100, opacity: 0 }}
                        animate={{ y: 0, opacity: 1 }}
                        exit={{ y: 100, opacity: 0 }}
                        onClick={() => setIsCartOpen(true)}
                        className="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-red-500 text-white px-8 py-4 rounded-2xl shadow-2xl shadow-red-500/40 flex items-center gap-4 font-bold hover:bg-red-600 transition-all active:scale-95"
                    >
                        <div className="relative">
                            <ShoppingCart size={22} />
                            <span className="absolute -top-2 -right-2 bg-white text-red-500 text-[10px] font-black h-4 w-4 rounded-full flex items-center justify-center">{cartCount}</span>
                        </div>
                        <span>Ver carrinho</span>
                        <span className="ml-2 bg-white/20 px-2 py-0.5 rounded-lg text-sm">
                            R$ {cartTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                        </span>
                    </motion.button>
                )}
            </AnimatePresence>

            {/* Drawer do Carrinho */}
            <AnimatePresence>
                {isCartOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setIsCartOpen(false)}
                            className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50"
                        />

                        <motion.div
                            initial={{ x: '100%' }}
                            animate={{ x: 0 }}
                            exit={{ x: '100%' }}
                            transition={{ type: 'spring', damping: 25, stiffness: 200 }}
                            className="fixed top-0 right-0 h-full w-full sm:w-[420px] bg-white z-[60] shadow-2xl flex flex-col"
                        >
                            <div className="flex items-center justify-between p-6 border-b border-gray-100">
                                <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                                    <ShoppingCart size={20} className="text-red-500" />
                                    Carrinho ({cartCount})
                                </h2>
                                <button onClick={() => setIsCartOpen(false)} className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500">
                                    <X size={24} />
                                </button>
                            </div>

                            <div className="flex-grow overflow-y-auto p-6 flex flex-col gap-4">
                                {cartItems.map(({ product, quantity }) => (
                                    <div key={product.id} className="flex gap-4 items-center">
                                        <div className="w-16 h-16 rounded-xl overflow-hidden flex-shrink-0 border border-gray-100">
                                            <img src={product.image ?? '/icons/icon-192x192.png'} className="w-full h-full object-cover" alt={product.name} />
                                        </div>
                                        <div className="flex-grow min-w-0">
                                            <p className="font-bold text-sm text-gray-900 truncate">{product.name}</p>
                                            <p className="text-xs text-gray-500">R$ {Number(product.amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} cada</p>
                                        </div>
                                        <div className="flex items-center gap-2 flex-shrink-0">
                                            <button onClick={() => updateQuantity(product.id, quantity - 1)} className="w-7 h-7 rounded-full border border-gray-200 flex items-center justify-center hover:border-red-500 hover:text-red-500 transition-colors">
                                                <Minus size={12} />
                                            </button>
                                            <span className="text-sm font-black w-5 text-center">{quantity}</span>
                                            <button onClick={() => updateQuantity(product.id, quantity + 1)} className="w-7 h-7 rounded-full border border-gray-200 flex items-center justify-center hover:border-red-500 hover:text-red-500 transition-colors">
                                                <Plus size={12} />
                                            </button>
                                            <button onClick={() => removeFromCart(product.id)} className="ml-1 text-gray-300 hover:text-red-500 transition-colors">
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="p-6 border-t border-gray-100 flex flex-col gap-4">
                                {/* Forma de pagamento */}
                                {Object.keys(paymentOptions).length > 0 && (
                                    <div>
                                        <p className="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Forma de pagamento</p>
                                        <div className="grid grid-cols-2 gap-2">
                                            {Object.entries(paymentOptions).map(([key, label]) => {
                                                const s = surcharges[key];
                                                return (
                                                    <button
                                                        key={key}
                                                        onClick={() => setSelectedPayment(selectedPayment === key ? '' : key)}
                                                        className={`text-left px-3 py-2 rounded-xl border text-sm font-semibold transition-all ${selectedPayment === key ? 'bg-red-500 text-white border-red-500' : 'bg-gray-50 text-gray-700 border-gray-200 hover:border-red-300'}`}
                                                    >
                                                        <span className="block">{label}</span>
                                                        {s && <span className={`text-[10px] font-bold ${selectedPayment === key ? 'text-red-100' : 'text-orange-500'}`}>{s.label}</span>}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                {/* Resumo financeiro */}
                                <div className="flex flex-col gap-1">
                                    <div className="flex justify-between text-sm text-gray-500">
                                        <span>Subtotal</span>
                                        <span>R$ {cartSubtotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
                                    </div>
                                    {cartFee > 0 && (
                                        <div className="flex justify-between text-sm text-orange-500 font-semibold">
                                            <span>Acréscimo</span>
                                            <span>+ R$ {cartFee.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between items-center pt-1 border-t border-gray-100 mt-1">
                                        <span className="font-bold text-gray-700">Total</span>
                                        <span className="text-2xl font-black text-gray-900">
                                            R$ {cartTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                        </span>
                                    </div>
                                </div>

                                {auth.user ? (
                                    <button
                                        onClick={handleCheckout}
                                        disabled={isCheckingOut}
                                        className="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 transition-all active:scale-95 disabled:opacity-50 uppercase tracking-widest text-sm"
                                    >
                                        {isCheckingOut ? 'Enviando...' : 'Finalizar pedido'}
                                    </button>
                                ) : (
                                    <div className="text-center bg-gray-50 rounded-xl p-4">
                                        <LogIn size={24} className="mx-auto text-gray-400 mb-2" />
                                        <p className="text-sm text-gray-500 mb-3">Faça login para finalizar o pedido</p>
                                        <button
                                            onClick={() => {
                                                setIsCartOpen(false);
                                                window.dispatchEvent(new CustomEvent('open-auth-modal'));
                                            }}
                                            className="text-sm font-bold text-red-500 hover:underline"
                                        >
                                            Entrar na conta
                                        </button>
                                    </div>
                                )}
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>
        </MarketplaceLayout>
    );
}
