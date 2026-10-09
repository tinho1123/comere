import { useEffect, useState } from 'react';
import MarketplaceLayout from '../../Layouts/MarketplaceLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { Heart, MapPin, X } from 'lucide-react';
import axios from 'axios';
import { getCurrentPosition, haversineKm, reverseGeocode } from '../../utils/geo';

const LOCATION_SUGGEST_THRESHOLD_KM = 1;

function LocationSuggestionSheet() {
    const { auth, default_address } = usePage().props;
    const [suggestion, setSuggestion] = useState(null);
    const [visible, setVisible] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!auth.user || !default_address?.latitude || !default_address?.longitude) return;
        if (sessionStorage.getItem('comere_location_prompt_seen')) return;

        let cancelled = false;

        (async () => {
            try {
                const { latitude, longitude } = await getCurrentPosition();
                const distance = haversineKm(default_address.latitude, default_address.longitude, latitude, longitude);
                if (cancelled || distance < LOCATION_SUGGEST_THRESHOLD_KM) return;

                const address = await reverseGeocode(latitude, longitude);
                if (cancelled) return;

                setSuggestion({ latitude, longitude, address });
                setVisible(true);
            } catch {
                // sem permissão ou GPS indisponível: não insiste
            }
        })();

        return () => { cancelled = true; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [auth.user, default_address?.latitude, default_address?.longitude]);

    const dismiss = () => {
        sessionStorage.setItem('comere_location_prompt_seen', '1');
        setVisible(false);
    };

    const useSuggestion = async () => {
        if (!suggestion || saving) return;
        setSaving(true);
        try {
            const payload = {
                latitude: suggestion.latitude,
                longitude: suggestion.longitude,
            };
            if (suggestion.address.street) payload.street = suggestion.address.street;
            if (suggestion.address.number) payload.number = suggestion.address.number;
            if (suggestion.address.neighborhood) payload.neighborhood = suggestion.address.neighborhood;
            if (suggestion.address.city) payload.city = suggestion.address.city;
            if (suggestion.address.state?.length === 2) payload.state = suggestion.address.state;
            if (suggestion.address.zip_code) payload.zip_code = suggestion.address.zip_code;

            await axios.put(`/addresses/${default_address.uuid}`, payload);
            sessionStorage.setItem('comere_location_prompt_seen', '1');
            setVisible(false);
            router.reload({ only: ['default_address', 'companies'] });
        } catch {
            // se falhar, o cliente ainda pode atualizar manualmente em Endereços
        } finally {
            setSaving(false);
        }
    };

    if (!visible || !suggestion) return null;

    const currentLabel = default_address
        ? `${default_address.street}, ${default_address.number} — ${default_address.city}`
        : null;

    return (
        <AnimatePresence>
            <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                onClick={dismiss}
                className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50"
            />
            <motion.div
                initial={{ y: '100%' }}
                animate={{ y: 0 }}
                exit={{ y: '100%' }}
                transition={{ type: 'spring', damping: 25, stiffness: 220 }}
                className="fixed bottom-0 left-0 right-0 z-[60] bg-white rounded-t-3xl shadow-2xl p-6 sm:max-w-md sm:mx-auto sm:rounded-3xl sm:bottom-6"
            >
                <div className="flex items-start justify-between mb-4">
                    <div className="flex items-center gap-2 text-red-500 font-bold">
                        <MapPin size={18} />
                        <span>Você está por aqui?</span>
                    </div>
                    <button onClick={dismiss} className="text-gray-300 hover:text-gray-500">
                        <X size={20} />
                    </button>
                </div>

                <div className="bg-gray-50 border border-gray-100 rounded-2xl p-4 mb-2">
                    <p className="text-sm font-semibold text-gray-900">{suggestion.address.label ?? 'Localização atual'}</p>
                </div>

                <p className="text-xs text-gray-500 mb-5">
                    Detectamos que você está em um local diferente do seu endereço salvo{currentLabel ? ` ("${currentLabel}")` : ''}. Quer usar a localização atual como seu endereço de entrega?
                </p>

                <div className="flex flex-col gap-2">
                    <button
                        onClick={useSuggestion}
                        disabled={saving}
                        className="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded-xl transition-all disabled:opacity-50"
                    >
                        {saving ? 'Atualizando...' : 'Usar esta localização'}
                    </button>
                    <button
                        onClick={dismiss}
                        className="w-full text-gray-500 font-medium py-2 text-sm hover:text-gray-700"
                    >
                        Manter endereço salvo
                    </button>
                </div>
            </motion.div>
        </AnimatePresence>
    );
}

function FavoriteButton({ uuid, initialFavorited, onToggle }) {
    const { auth } = usePage().props;
    const [favorited, setFavorited] = useState(initialFavorited);
    const [loading, setLoading] = useState(false);

    if (!auth) return null;

    const toggle = async (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (loading) return;
        setLoading(true);
        try {
            const res = await axios.post(`/favorites/${uuid}`);
            setFavorited(res.data.favorited);
            onToggle?.(uuid, res.data.favorited);
        } catch {
            // silently ignore
        } finally {
            setLoading(false);
        }
    };

    return (
        <button
            onClick={toggle}
            className={`absolute top-3 right-3 z-10 w-8 h-8 rounded-full flex items-center justify-center shadow transition-all ${
                favorited ? 'bg-red-500 text-white' : 'bg-white text-gray-400 hover:text-red-400'
            }`}
            aria-label={favorited ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}
        >
            <Heart size={15} fill={favorited ? 'currentColor' : 'none'} />
        </button>
    );
}

function StoreCard({ store, isFavorited, onToggleFavorite }) {
    const isClosed = store.is_open === false;

    return (
        <Link
            href={`/store/${store.uuid}`}
            className={`relative bg-white rounded-2xl border p-4 hover:shadow-xl hover:-translate-y-1 transition-all group flex gap-4 ${isClosed ? 'border-gray-200 opacity-60' : 'border-gray-100'}`}
        >
            <FavoriteButton uuid={store.uuid} initialFavorited={isFavorited} onToggle={onToggleFavorite} />

            <div className="w-20 h-20 rounded-xl overflow-hidden shadow-inner border border-gray-50 bg-gray-50 flex-shrink-0">
                <img
                    src={store.logo}
                    className={`w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 ${isClosed ? 'grayscale' : ''}`}
                    alt={store.name}
                />
            </div>

            <div className="flex-grow min-w-0 py-1 flex flex-col justify-between">
                <div>
                    <div className="flex items-center gap-2 flex-wrap">
                        <h4 className="font-bold text-gray-900 truncate group-hover:text-red-500 transition-colors">{store.name}</h4>
                        {isClosed && (
                            <span className="text-[10px] font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full flex-shrink-0">Fechado</span>
                        )}
                    </div>
                    <div className="flex items-center gap-2 text-xs text-gray-500 font-medium mt-1">
                        <span className="text-yellow-500">★ {store.rating}</span>
                        <span>•</span>
                        <span className="truncate">{store.type}</span>
                    </div>
                    {store.distance_km !== null && (
                        <div className="flex items-center gap-2 mt-1.5">
                            <span className="text-xs text-gray-500">📍 {store.distance_km} km</span>
                            {store.delivery_fee !== null ? (
                                <span className="text-xs font-bold text-green-600">
                                    Entrega R$ {Number(store.delivery_fee).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                </span>
                            ) : (
                                <span className="text-xs text-red-500 font-medium">Fora da área</span>
                            )}
                        </div>
                    )}
                </div>
                {store.is_promoted && !isClosed && (
                    <span className="text-[10px] font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded italic w-max">PROMOÇÃO</span>
                )}
            </div>
        </Link>
    );
}

export default function MarketplaceIndex({ companies, lastVisited, categories, selectedCategory, favoriteUuids }) {
    const [favorites, setFavorites] = useState(new Set(favoriteUuids));

    const handleToggleFavorite = (uuid, isFavorited) => {
        setFavorites((prev) => {
            const next = new Set(prev);
            isFavorited ? next.add(uuid) : next.delete(uuid);
            return next;
        });
    };

    const favoriteCompanies = companies.filter((s) => favorites.has(s.uuid));

    return (
        <MarketplaceLayout>
            <LocationSuggestionSheet />

            {/* Categorias */}
            {categories.length > 0 && (
                <div className="flex gap-3 overflow-x-auto pb-4 mb-8 no-scrollbar">
                    {categories.map((cat) => (
                        <Link
                            key={cat.uuid}
                            href={`/?category=${cat.uuid}`}
                            className={`flex-shrink-0 flex items-center gap-2 px-5 py-2.5 rounded-full border font-bold text-sm transition-all ${selectedCategory === cat.uuid
                                    ? 'bg-red-500 border-red-500 text-white shadow-md shadow-red-500/20'
                                    : 'bg-white border-gray-100 text-gray-600 hover:border-red-200'
                                }`}
                        >
                            <span className="text-base">{cat.icon}</span>
                            {cat.name}
                        </Link>
                    ))}
                </div>
            )}

            {/* Favoritos */}
            {favoriteCompanies.length > 0 && (
                <div id="favoritos" className="mb-12 scroll-mt-20">
                    <h3 className="text-xl font-bold mb-6 flex items-center gap-2">
                        <Heart size={20} className="text-red-500" fill="currentColor" /> Favoritos
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        {favoriteCompanies.map((store) => (
                            <StoreCard
                                key={store.uuid}
                                store={store}
                                isFavorited={true}
                                onToggleFavorite={handleToggleFavorite}
                            />
                        ))}
                    </div>
                </div>
            )}

            {/* Histórico: Lojas Frequentadas */}
            {lastVisited.length > 0 && (
                <div className="mb-12">
                    <h3 className="text-xl font-bold mb-6 flex items-center gap-2">
                        <span className="text-red-500">🕒</span> Últimas lojas visitadas
                    </h3>
                    <div className="flex gap-4 overflow-x-auto pb-2 no-scrollbar">
                        {lastVisited.map((store) => (
                            <Link
                                key={store.uuid}
                                href={`/store/${store.uuid}`}
                                className="flex-shrink-0 w-24 flex flex-col items-center gap-2 group"
                            >
                                <div className="w-16 h-16 rounded-full border-2 border-gray-100 p-1 bg-white group-hover:border-red-500 transition-colors shadow-sm">
                                    <img src={store.logo} className="w-full h-full rounded-full object-cover" alt={store.name} />
                                </div>
                                <span className="text-xs font-semibold text-center truncate w-full px-1">{store.name}</span>
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            {/* Lista de Lojas */}
            <div>
                <h3 className="text-xl font-bold mb-6 flex items-center justify-between">
                    <span>Lojas em destaque</span>
                    {selectedCategory && (
                        <Link href="/" className="text-sm font-medium text-red-500 hover:underline">Ver tudo</Link>
                    )}
                </h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {companies.map((store) => (
                        <StoreCard
                            key={store.uuid}
                            store={store}
                            isFavorited={favorites.has(store.uuid)}
                            onToggleFavorite={handleToggleFavorite}
                        />
                    ))}
                </div>
            </div>
        </MarketplaceLayout>
    );
}
