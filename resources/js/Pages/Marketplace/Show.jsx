import React, { useState } from 'react';
import MarketplaceLayout from '../../Layouts/MarketplaceLayout';
import { Head, Link } from '@inertiajs/react';

export default function MarketplaceShow({ company, productsByCategory }) {
    const [activeCategory, setActiveCategory] = useState(Object.keys(productsByCategory)[0]);

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
                <p className="text-gray-600 font-medium mb-10 max-w-3xl leading-relaxed">
                    {company.description ?? 'Bem-vindo à nossa loja! Oferecemos os melhores produtos com a qualidade que você já conhece. Peça agora e aproveite nosso sistema de fiado exclusivo.'}
                </p>

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
                                        <img src={product.image ?? '/demo-product.png'} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt={product.name} />
                                        <button className="absolute bottom-1 right-1 bg-white text-red-500 w-8 h-8 rounded-full shadow-lg flex items-center justify-center font-bold text-xl hover:scale-110 active:scale-90 transition-all border border-gray-100">
                                            +
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </MarketplaceLayout>
    );
}
