import MarketplaceLayout from '../../Layouts/MarketplaceLayout';
import { Link } from '@inertiajs/react';

export default function MarketplaceIndex({ companies, lastVisited, categories, selectedCategory }) {
    return (
        <MarketplaceLayout>
            {/* Categorias */}
            <div className="flex gap-4 overflow-x-auto pb-4 mb-8 no-scrollbar">
                {categories.map((cat) => (
                    <Link
                        key={cat}
                        href={`/?category=${cat}`}
                        className={`flex-shrink-0 px-6 py-8 rounded-2xl border transition-all hover:shadow-lg flex flex-col items-center gap-2 group ${selectedCategory === cat
                                ? 'bg-red-50 border-red-200 ring-2 ring-red-500/20'
                                : 'bg-white border-gray-100 hover:border-red-100'
                            }`}
                    >
                        <div className="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform">
                            {/* Ícones simplificados por tipo */}
                            {cat === 'Restaurantes' && '🍔'}
                            {cat === 'Mercados' && '🛒'}
                            {cat === 'Farmácias' && '💊'}
                            {cat === 'Bebidas' && '🍺'}
                        </div>
                        <span className={`text-sm font-semibold ${selectedCategory === cat ? 'text-red-600' : 'text-gray-600'}`}>
                            {cat}
                        </span>
                    </Link>
                ))}
            </div>

            {/* Banners Promocionais */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
                <div className="h-48 rounded-3xl bg-gradient-to-br from-red-600 to-orange-500 p-8 flex flex-col justify-center text-white shadow-xl shadow-red-500/10 active:scale-[0.99] transition-transform cursor-pointer">
                    <span className="text-sm font-bold uppercase tracking-widest opacity-80 decoration-white">Cupom de Boas-vindas</span>
                    <h2 className="text-3xl font-black mt-1">R$ 20 OFF</h2>
                    <p className="mt-2 text-red-100 font-medium italic">No seu primeiro pedido fiado</p>
                </div>
                <div className="h-48 rounded-3xl bg-gradient-to-br from-gray-800 to-gray-900 p-8 flex flex-col justify-center text-white shadow-xl shadow-gray-900/10 active:scale-[0.99] transition-transform cursor-pointer">
                    <span className="text-sm font-bold uppercase tracking-widest opacity-80">Parceria VIP</span>
                    <h2 className="text-3xl font-black mt-1">TAXA ZERO</h2>
                    <p className="mt-2 text-gray-300 font-medium italic">Para os parceiros de destaque da semana</p>
                </div>
            </div>

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
                                    <img src={store.logo_path ?? '/default-store-logo.png'} className="w-full h-full rounded-full object-cover" alt={store.name} />
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
                        <Link
                            key={store.uuid}
                            href={`/store/${store.uuid}`}
                            className="bg-white rounded-2xl border border-gray-100 p-4 hover:shadow-xl hover:-translate-y-1 transition-all group flex gap-4"
                        >
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
                    ))}
                </div>
            </div>
        </MarketplaceLayout>
    );
}
