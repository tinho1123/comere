import { useState } from 'react';
import MarketplaceLayout from '../../Layouts/MarketplaceLayout';
import { Link, usePage } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import axios from 'axios';

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
    return (
        <Link
            href={`/store/${store.uuid}`}
            className="relative bg-white rounded-2xl border border-gray-100 p-4 hover:shadow-xl hover:-translate-y-1 transition-all group flex gap-4"
        >
            <FavoriteButton uuid={store.uuid} initialFavorited={isFavorited} onToggle={onToggleFavorite} />
            <div className="w-20 h-20 rounded-xl overflow-hidden shadow-inner border border-gray-50 bg-gray-50 flex-shrink-0">
                <img src={store.logo} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt={store.name} />
            </div>
            <div className="flex-grow min-w-0 py-1 flex flex-col justify-between">
                <div>
                    <h4 className="font-bold text-gray-900 truncate group-hover:text-red-500 transition-colors">{store.name}</h4>
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
                {store.is_promoted && (
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
            {/* Categorias */}
            {categories.length > 0 && (
                <div className="flex gap-4 overflow-x-auto pb-4 mb-8 no-scrollbar">
                    {categories.map((cat) => (
                        <Link
                            key={cat.uuid}
                            href={`/?category=${cat.uuid}`}
                            className={`flex-shrink-0 px-6 py-8 rounded-2xl border transition-all hover:shadow-lg flex flex-col items-center gap-2 group ${selectedCategory === cat.uuid
                                    ? 'bg-red-50 border-red-200 ring-2 ring-red-500/20'
                                    : 'bg-white border-gray-100 hover:border-red-100'
                                }`}
                        >
                            <div className="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform text-2xl">
                                {cat.icon}
                            </div>
                            <span className={`text-sm font-semibold ${selectedCategory === cat.uuid ? 'text-red-600' : 'text-gray-600'}`}>
                                {cat.name}
                            </span>
                        </Link>
                    ))}
                </div>
            )}

            {/* Favoritos */}
            {favoriteCompanies.length > 0 && (
                <div className="mb-12">
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
