import { useState } from 'react';
import MarketplaceLayout from '../../Layouts/MarketplaceLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { ShoppingCart, X, Plus, Minus, Trash2, LogIn, MapPin, Truck, Heart, Star } from 'lucide-react';
import axios from 'axios';

function RatingSection({ company }) {
    const { auth } = usePage().props;
    const [hovered, setHovered] = useState(0);
    const [selected, setSelected] = useState(company.user_rating?.rating ?? 0);
    const [comment, setComment] = useState(company.user_rating?.comment ?? '');
    const [average, setAverage] = useState(company.rating);
    const [count, setCount] = useState(company.ratings_count);
    const [loading, setLoading] = useState(false);
    const [submitted, setSubmitted] = useState(!!company.user_rating);

    const submit = async () => {
        if (!selected || loading) return;
        setLoading(true);
        try {
            const res = await axios.post(`/store/${company.uuid}/rate`, { rating: selected, comment });
            setAverage(res.data.average);
            setCount(res.data.count);
            setSubmitted(true);
        } catch {
            // silently ignore
        } finally {
            setLoading(false);
        }
    };

    const displayStars = hovered || selected;

    return (
        <div className="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 mt-6">
            <div className="flex items-center justify-between mb-6">
                <h3 className="text-xl font-bold text-gray-900">Avaliações</h3>
                {average && (
                    <div className="flex items-center gap-2">
                        <Star size={18} className="text-yellow-400" fill="currentColor" />
                        <span className="text-lg font-black text-gray-900">{Number(average).toFixed(1)}</span>
                        <span className="text-sm text-gray-400">({count} {count === 1 ? 'avaliação' : 'avaliações'})</span>
                    </div>
                )}
            </div>

            {!auth ? (
                <p className="text-gray-500 text-sm">
                    <button
                        onClick={() => window.dispatchEvent(new Event('open-auth-modal'))}
                        className="text-red-500 font-bold hover:underline"
                    >
                        Entre
                    </button>{' '}
                    para avaliar esta loja.
                </p>
            ) : (
                <div>
                    {submitted && (
                        <p className="text-sm text-emerald-600 font-semibold mb-4">
                            ✓ Sua avaliação foi salva!{' '}
                            <button onClick={() => setSubmitted(false)} className="text-gray-400 font-normal hover:underline text-xs">Editar</button>
                        </p>
                    )}

                    {!submitted && (
                        <div className="space-y-4">
                            <div>
                                <p className="text-sm font-semibold text-gray-700 mb-2">
                                    {selected ? `Sua nota: ${selected} estrela${selected > 1 ? 's' : ''}` : 'Selecione uma nota'}
                                </p>
                                <div className="flex gap-1">
                                    {[1, 2, 3, 4, 5].map((star) => (
                                        <button
                                            key={star}
                                            onMouseEnter={() => setHovered(star)}
                                            onMouseLeave={() => setHovered(0)}
                                            onClick={() => setSelected(star)}
                                            className="transition-transform hover:scale-110 active:scale-95"
                                        >
                                            <Star
                                                size={32}
                                                className={star <= displayStars ? 'text-yellow-400' : 'text-gray-200'}
                                                fill={star <= displayStars ? 'currentColor' : 'none'}
                                            />
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <textarea
                                value={comment}
                                onChange={(e) => setComment(e.target.value)}
                                placeholder="Deixe um comentário (opcional)..."
                                maxLength={500}
                                rows={3}
                                className="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-700 resize-none focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent"
                            />

                            <button
                                onClick={submit}
                                disabled={!selected || loading}
                                className="bg-red-500 hover:bg-red-600 disabled:opacity-40 text-white font-bold px-6 py-2.5 rounded-xl transition-all active:scale-95 text-sm"
                            >
                                {loading ? 'Enviando...' : 'Enviar avaliação'}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function MarketplaceShow({ company, productsByCategory }) {
    const { auth } = usePage().props;
    const [activeCategory, setActiveCategory] = useState(Object.keys(productsByCategory)[0]);
    const [cart, setCart] = useState({});
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [isCheckingOut, setIsCheckingOut] = useState(false);
    const [favorited, setFavorited] = useState(company.is_favorited);
    const [favLoading, setFavLoading] = useState(false);

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

                {auth && (
                    <button
                        onClick={toggleFavorite}
                        disabled={favLoading}
                        className={`absolute top-4 right-4 z-10 w-10 h-10 rounded-full flex items-center justify-center shadow-lg transition-all ${
                            favorited ? 'bg-red-500 text-white' : 'bg-white text-gray-400 hover:text-red-400'
                        }`}
                        aria-label={favorited ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}
                    >
                        <Heart size={18} fill={favorited ? 'currentColor' : 'none'} />
                    </button>
                )}

                <div className="absolute -bottom-16 left-8 flex items-end gap-6">
                    <div className="w-32 h-32 rounded-2xl border-4 border-white shadow-xl overflow-hidden bg-white">
                        <img src={company.logo} className="w-full h-full object-cover" alt="Logo" />
                    </div>
                    <div className="mb-4">
                        <h1 className="text-3xl font-black text-white drop-shadow-md select-none">{company.name}</h1>
                        <div className="flex items-center gap-3 mt-1 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full border border-gray-100 shadow-sm w-max">
                            <span className="text-yellow-500 font-bold">★ {company.rating ?? '—'}{company.ratings_count > 0 && <span className="text-gray-400 font-normal text-xs ml-1">({company.ratings_count})</span>}</span>
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

            <RatingSection company={company} />

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
                                {/* Resumo financeiro */}
                                <div className="flex justify-between items-center">
                                    <span className="font-bold text-gray-700">Total</span>
                                    <span className="text-2xl font-black text-gray-900">
                                        R$ {cartTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                    </span>
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
