import { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { User, LogIn, X, Mail, LogOut, ShoppingCart, Search, Package, MapPin, ChevronDown, Plus, Check, Trash2 } from 'lucide-react';
import axios from 'axios';

export default function MarketplaceLayout({ children }) {
    const { auth, orders_count, default_address } = usePage().props;
    const [isAuthOpen, setIsAuthOpen] = useState(false);
    const [isAddressOpen, setIsAddressOpen] = useState(false);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    // Address state
    const [addresses, setAddresses] = useState([]);
    const [isAddingAddress, setIsAddingAddress] = useState(false);
    const [addressErrors, setAddressErrors] = useState({});
    const [addressForm, setAddressForm] = useState({
        label: 'Casa', zip_code: '', street: '', number: '',
        complement: '', neighborhood: '', city: '', state: '', is_default: false,
    });

    const isAuthenticated = !!auth.user;
    const displayUser = auth.user;

    useEffect(() => {
        const handler = () => setIsAuthOpen(true);
        window.addEventListener('open-auth-modal', handler);
        return () => window.removeEventListener('open-auth-modal', handler);
    }, []);

    const loadAddresses = async () => {
        const res = await axios.get('/addresses');
        setAddresses(res.data.data);
    };

    const openAddressModal = () => {
        setIsAddressOpen(true);
        loadAddresses();
    };

    const handleManualLogin = (e) => {
        e.preventDefault();
        setIsLoading(true);
        router.post('/marketplace/login', { email, password }, {
            onFinish: () => setIsLoading(false),
            onSuccess: () => { setIsAuthOpen(false); setEmail(''); setPassword(''); },
        });
    };

    const handleLogout = () => {
        router.post('/marketplace/logout');
    };

    const handleSetDefault = async (uuid) => {
        await axios.patch(`/addresses/${uuid}/default`);
        router.reload({ only: ['default_address'] });
        loadAddresses();
    };

    const handleDeleteAddress = async (uuid) => {
        await axios.delete(`/addresses/${uuid}`);
        router.reload({ only: ['default_address'] });
        loadAddresses();
    };

    const handleAddAddress = async (e) => {
        e.preventDefault();
        setIsLoading(true);
        setAddressErrors({});
        try {
            await axios.post('/addresses', addressForm);
            router.reload({ only: ['default_address'] });
            loadAddresses();
            setIsAddingAddress(false);
            setAddressForm({ label: 'Casa', zip_code: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '', is_default: false });
        } catch (err) {
            if (err.response?.status === 422) {
                setAddressErrors(err.response.data.errors ?? {});
            }
        } finally {
            setIsLoading(false);
        }
    };

    const fetchAddressByCep = async (cep) => {
        const clean = cep.replace(/\D/g, '');
        if (clean.length !== 8) return;
        try {
            const res = await fetch(`https://viacep.com.br/ws/${clean}/json/`);
            const data = await res.json();
            if (!data.erro) {
                setAddressForm(prev => ({
                    ...prev,
                    street: data.logradouro || prev.street,
                    neighborhood: data.bairro || prev.neighborhood,
                    city: data.localidade || prev.city,
                    state: data.uf || prev.state,
                }));
            }
        } catch {}
    };

    const defaultAddressLabel = default_address
        ? `${default_address.street}, ${default_address.number} — ${default_address.city}`
        : 'Adicionar endereço';

    return (
        <div className="min-h-screen bg-gray-50 font-sans text-gray-900 overflow-x-hidden">
            <Head>
                <title>Comere Marketplace</title>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
            </Head>

            {/* Navbar */}
            <nav className="sticky top-0 z-40 bg-white shadow-sm border-b border-gray-100">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between items-center h-16">
                        <div className="flex items-center gap-8">
                            <Link href="/" className="flex-shrink-0 flex items-center gap-2">
                                <span className="text-2xl font-bold bg-gradient-to-r from-red-600 to-red-500 bg-clip-text text-transparent italic">Comere</span>
                            </Link>

                            <div className="hidden md:block w-96">
                                <div className="relative">
                                    <input type="text" placeholder="Busque por produtos ou lojas" className="w-full bg-gray-100 border-none rounded-lg py-2 pl-4 pr-10 focus:ring-2 focus:ring-red-500/20 focus:bg-white transition-all text-sm" />
                                    <div className="absolute right-3 top-2.5 text-red-500"><Search size={18} /></div>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-4">
                            {isAuthenticated ? (
                                <div className="flex items-center gap-4">
                                    {/* Endereço */}
                                    <button
                                        onClick={openAddressModal}
                                        className="hidden md:flex items-center gap-1.5 text-sm text-gray-600 hover:text-red-500 transition-colors max-w-[200px]"
                                    >
                                        <MapPin size={15} className="text-red-500 flex-shrink-0" />
                                        <span className="truncate text-xs font-medium">{defaultAddressLabel}</span>
                                        <ChevronDown size={13} className="flex-shrink-0" />
                                    </button>

                                    <Link href="/meus-pedidos" className={`relative flex items-center gap-2 text-sm font-semibold transition-colors py-2 px-1 rounded-lg ${window.location.pathname === '/meus-pedidos' ? 'text-red-500' : 'text-gray-600 hover:text-red-500'}`}>
                                        <Package size={18} />
                                        <span className="hidden lg:inline">Meus Pedidos</span>
                                        {orders_count?.unfinished > 0 && (
                                            <span className="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold h-4 w-4 rounded-full flex items-center justify-center animate-pulse">{orders_count.unfinished}</span>
                                        )}
                                    </Link>

                                    <div className="flex items-center gap-3">
                                        <div className="text-right hidden sm:block">
                                            <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-none mb-1">Bem-vindo</p>
                                            <p className="text-sm font-bold text-gray-900 leading-none">{displayUser?.name || 'Usuário'}</p>
                                        </div>
                                        <div className="h-9 w-9 rounded-full bg-gradient-to-tr from-red-500 to-red-400 p-0.5 shadow-sm">
                                            <div className="h-full w-full rounded-full bg-white flex items-center justify-center">
                                                <User size={18} className="text-red-500" />
                                            </div>
                                        </div>
                                        <button onClick={handleLogout} title="Sair" className="text-gray-400 hover:text-red-500 transition-colors p-1">
                                            <LogOut size={18} />
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <button onClick={() => setIsAuthOpen(true)} className="flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-red-500 transition-colors py-2 px-1 rounded-lg">
                                    <LogIn size={18} />
                                    <span>Entrar</span>
                                </button>
                            )}

                            <button className="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-md shadow-red-500/20 active:scale-95 flex items-center gap-2">
                                <ShoppingCart size={18} />
                                <span className="hidden sm:inline">Carrinho</span>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 min-h-[calc(100vh-12rem)]">
                {children}
            </main>

            {/* Auth Drawer */}
            <AnimatePresence>
                {isAuthOpen && (
                    <>
                        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={() => setIsAuthOpen(false)} className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50" />
                        <motion.div initial={{ x: '100%' }} animate={{ x: 0 }} exit={{ x: '100%' }} transition={{ type: 'spring', damping: 25, stiffness: 200 }} className="fixed top-0 right-0 h-full w-full sm:w-[420px] bg-white z-[60] shadow-2xl flex flex-col">
                            <div className="flex items-center justify-between p-6 border-b border-gray-100">
                                <h2 className="text-xl font-bold text-gray-900">Acesse sua conta</h2>
                                <button onClick={() => setIsAuthOpen(false)} className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500"><X size={24} /></button>
                            </div>
                            <div className="flex-grow overflow-y-auto p-8 flex flex-col gap-8">
                                <div className="text-center">
                                    <div className="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-4"><User size={32} /></div>
                                    <p className="text-gray-500 text-sm italic">Faça login para gerenciar seus pedidos e seu saldo fiado com os parceiros.</p>
                                </div>
                                <form onSubmit={handleManualLogin} className="flex flex-col gap-4">
                                    <div className="flex flex-col gap-1.5">
                                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">E-mail</label>
                                        <div className="relative">
                                            <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required placeholder="seu@email.com" className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 pl-10 pr-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none" />
                                            <Mail className="absolute left-3 top-3.5 text-gray-300" size={18} />
                                        </div>
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Senha</label>
                                        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required placeholder="••••••••" className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none" />
                                    </div>
                                    <button type="submit" disabled={isLoading} className="mt-4 bg-red-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 hover:bg-red-600 transition-all active:scale-95 uppercase tracking-widest text-sm disabled:opacity-50">
                                        {isLoading ? 'Entrando...' : 'Login'}
                                    </button>
                                </form>
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            {/* Address Drawer */}
            <AnimatePresence>
                {isAddressOpen && (
                    <>
                        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={() => { setIsAddressOpen(false); setIsAddingAddress(false); }} className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50" />
                        <motion.div initial={{ x: '100%' }} animate={{ x: 0 }} exit={{ x: '100%' }} transition={{ type: 'spring', damping: 25, stiffness: 200 }} className="fixed top-0 right-0 h-full w-full sm:w-[440px] bg-white z-[60] shadow-2xl flex flex-col">
                            <div className="flex items-center justify-between p-6 border-b border-gray-100">
                                <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                                    <MapPin size={20} className="text-red-500" />
                                    {isAddingAddress ? 'Novo endereço' : 'Meus endereços'}
                                </h2>
                                <button onClick={() => { setIsAddressOpen(false); setIsAddingAddress(false); }} className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500"><X size={24} /></button>
                            </div>

                            <div className="flex-grow overflow-y-auto p-6">
                                {isAddingAddress ? (
                                    <form onSubmit={handleAddAddress} className="flex flex-col gap-4">
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="flex flex-col gap-1 col-span-2">
                                                <label className="text-xs font-bold text-gray-400 uppercase">Label</label>
                                                <select value={addressForm.label} onChange={e => setAddressForm(p => ({ ...p, label: e.target.value }))} className="border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none focus:border-red-500">
                                                    <option>Casa</option><option>Trabalho</option><option>Outro</option>
                                                </select>
                                            </div>
                                            <div className="flex flex-col gap-1 col-span-2">
                                                <label className="text-xs font-bold text-gray-400 uppercase">CEP</label>
                                                <input value={addressForm.zip_code} onChange={e => { setAddressForm(p => ({ ...p, zip_code: e.target.value })); fetchAddressByCep(e.target.value); }} placeholder="00000-000" className="border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none focus:border-red-500" />
                                            </div>
                                            <div className="flex flex-col gap-1 col-span-2">
                                                <label className="text-xs font-bold text-gray-400 uppercase">Rua</label>
                                                <input value={addressForm.street} onChange={e => setAddressForm(p => ({ ...p, street: e.target.value }))} required className="border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none focus:border-red-500" />
                                            </div>
                                            <div className="flex flex-col gap-1">
                                                <label className="text-xs font-bold text-gray-400 uppercase">Número</label>
                                                <input value={addressForm.number} onChange={e => setAddressForm(p => ({ ...p, number: e.target.value }))} required className="border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none focus:border-red-500" />
                                            </div>
                                            <div className="flex flex-col gap-1">
                                                <label className="text-xs font-bold text-gray-400 uppercase">Complemento</label>
                                                <input value={addressForm.complement} onChange={e => setAddressForm(p => ({ ...p, complement: e.target.value }))} className="border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none focus:border-red-500" />
                                            </div>
                                            <div className="flex flex-col gap-1 col-span-2">
                                                <label className="text-xs font-bold text-gray-400 uppercase">Bairro</label>
                                                <input value={addressForm.neighborhood} onChange={e => setAddressForm(p => ({ ...p, neighborhood: e.target.value }))} required className="border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none focus:border-red-500" />
                                            </div>
                                            <div className="flex flex-col gap-1">
                                                <label className="text-xs font-bold text-gray-400 uppercase">Cidade</label>
                                                <input value={addressForm.city} onChange={e => setAddressForm(p => ({ ...p, city: e.target.value }))} required className="border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none focus:border-red-500" />
                                            </div>
                                            <div className="flex flex-col gap-1">
                                                <label className="text-xs font-bold text-gray-400 uppercase">UF</label>
                                                <input value={addressForm.state} onChange={e => setAddressForm(p => ({ ...p, state: e.target.value.toUpperCase() }))} maxLength={2} required className="border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none focus:border-red-500 uppercase" />
                                            </div>
                                            <label className="col-span-2 flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" checked={addressForm.is_default} onChange={e => setAddressForm(p => ({ ...p, is_default: e.target.checked }))} className="accent-red-500" />
                                                <span className="text-sm text-gray-600">Definir como principal</span>
                                            </label>
                                            {Object.values(addressErrors).flat().length > 0 && (
                                                <div className="col-span-2 bg-red-50 border border-red-200 rounded-xl p-3">
                                                    {Object.values(addressErrors).flat().map((err, i) => (
                                                        <p key={i} className="text-xs text-red-600">{err}</p>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex gap-3 mt-2">
                                            <button type="button" onClick={() => setIsAddingAddress(false)} className="flex-1 border border-gray-200 text-gray-600 font-bold py-3 rounded-xl text-sm hover:bg-gray-50 transition-all">Cancelar</button>
                                            <button type="submit" disabled={isLoading} className="flex-1 bg-red-500 text-white font-bold py-3 rounded-xl text-sm hover:bg-red-600 transition-all disabled:opacity-50">
                                                {isLoading ? 'Salvando...' : 'Salvar'}
                                            </button>
                                        </div>
                                    </form>
                                ) : (
                                    <div className="flex flex-col gap-3">
                                        {addresses.map(addr => (
                                            <div key={addr.uuid} className={`p-4 rounded-2xl border-2 transition-all ${addr.is_default ? 'border-red-500 bg-red-50' : 'border-gray-100 bg-white'}`}>
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="flex-grow min-w-0">
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <span className="text-xs font-bold uppercase tracking-wider text-gray-500">{addr.label}</span>
                                                            {addr.is_default && <span className="text-[10px] bg-red-500 text-white px-2 py-0.5 rounded-full font-bold">Principal</span>}
                                                        </div>
                                                        <p className="text-sm text-gray-800 font-medium">{addr.street}, {addr.number}</p>
                                                        <p className="text-xs text-gray-500">{addr.neighborhood} — {addr.city}/{addr.state}</p>
                                                    </div>
                                                    <div className="flex items-center gap-1 flex-shrink-0">
                                                        {!addr.is_default && (
                                                            <button onClick={() => handleSetDefault(addr.uuid)} title="Definir como principal" className="p-1.5 text-gray-400 hover:text-red-500 transition-colors">
                                                                <Check size={16} />
                                                            </button>
                                                        )}
                                                        <button onClick={() => handleDeleteAddress(addr.uuid)} title="Remover" className="p-1.5 text-gray-400 hover:text-red-500 transition-colors">
                                                            <Trash2 size={16} />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}

                                        {addresses.length === 0 && (
                                            <div className="text-center py-12 text-gray-400">
                                                <MapPin size={40} className="mx-auto mb-3 opacity-30" />
                                                <p className="text-sm">Nenhum endereço cadastrado</p>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            {!isAddingAddress && (
                                <div className="p-6 border-t border-gray-100">
                                    <button onClick={() => setIsAddingAddress(true)} className="w-full flex items-center justify-center gap-2 bg-red-500 text-white font-bold py-3.5 rounded-xl hover:bg-red-600 transition-all active:scale-95 text-sm">
                                        <Plus size={18} />
                                        Adicionar endereço
                                    </button>
                                </div>
                            )}
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            <footer className="bg-white border-t border-gray-100 mt-12 py-8">
                <div className="max-w-7xl mx-auto px-4 text-center">
                    <p className="text-sm text-gray-500 italic">© 2026 Comere - O seu marketplace de confiança</p>
                </div>
            </footer>
        </div>
    );
}
