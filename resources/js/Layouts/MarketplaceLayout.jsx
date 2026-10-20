import { useState, useEffect, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { User, LogIn, X, Mail, LogOut, ShoppingCart, Search, Package, MapPin, ChevronDown, ChevronRight, Plus, Check, Trash2, Home, Heart, Lock, ChevronLeft } from 'lucide-react';
import axios from 'axios';
import { useClerk, useUser } from '@clerk/react';

const clerkEnabled = !!import.meta.env.VITE_CLERK_PUBLISHABLE_KEY;

function ClerkSSOButton({ onAuthenticated }) {
    const { openSignIn } = useClerk();
    const { isSignedIn, user } = useUser();
    const syncedRef = useRef(false);

    useEffect(() => {
        if (isSignedIn && user && !syncedRef.current) {
            syncedRef.current = true;
            axios.post('/sso-callback', {
                clerk_id: user.id,
                email: user.primaryEmailAddress?.emailAddress,
                name: user.fullName,
            }).then((res) => {
                if (res.data?.redirect_url) {
                    onAuthenticated?.();
                    router.visit(res.data.redirect_url);
                }
            });
        }
    }, [isSignedIn, user]);

    return (
        <button
            type="button"
            onClick={() => openSignIn()}
            className="w-full bg-white text-gray-700 border border-gray-200 font-bold py-3.5 rounded-xl text-sm flex items-center justify-center gap-2 hover:bg-gray-50 transition-all"
        >
            <Lock size={15} /> Entrar com SSO (Clerk)
        </button>
    );
}

function initials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}

function formatCurrency(value) {
    return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function MarketplaceLayout({ children }) {
    const { props: { auth, orders_count, default_address, favored_balance, errors }, url } = usePage();
    const [isAuthOpen, setIsAuthOpen] = useState(false);
    const [isAddressOpen, setIsAddressOpen] = useState(false);
    const [isProfileSheetOpen, setIsProfileSheetOpen] = useState(false);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [rememberMe, setRememberMe] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    // Auth drawer: login / register / forgot-password
    const [authMode, setAuthMode] = useState('login');
    const [registerForm, setRegisterForm] = useState({ name: '', email: '', password: '', password_confirmation: '', document_number: '' });
    const [forgotEmail, setForgotEmail] = useState('');
    const [forgotSent, setForgotSent] = useState(false);

    // Address state
    const [addresses, setAddresses] = useState([]);
    const [isAddingAddress, setIsAddingAddress] = useState(false);
    const [addressErrors, setAddressErrors] = useState({});
    const [isGeocodingAddress, setIsGeocodingAddress] = useState(false);
    const [addressForm, setAddressForm] = useState({
        label: 'Casa', zip_code: '', street: '', number: '',
        complement: '', neighborhood: '', city: '', state: '',
        latitude: '', longitude: '', is_default: false,
    });

    const isAuthenticated = !!auth.user;
    const displayUser = auth.user;
    const [showOnboarding, setShowOnboarding] = useState(false);
    const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
    const userMenuRef = useRef(null);
    const searchRef = useRef(null);
    const searchTimerRef = useRef(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState(null);
    const [isSearching, setIsSearching] = useState(false);

    useEffect(() => {
        const handler = (e) => {
            if (userMenuRef.current && !userMenuRef.current.contains(e.target)) {
                setIsUserMenuOpen(false);
            }
            if (searchRef.current && !searchRef.current.contains(e.target)) {
                setSearchResults(null);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const handleSearchChange = (e) => {
        const q = e.target.value;
        setSearchQuery(q);
        clearTimeout(searchTimerRef.current);
        if (q.length < 2) { setSearchResults(null); return; }
        setIsSearching(true);
        searchTimerRef.current = setTimeout(async () => {
            try {
                const res = await axios.get(`/search?q=${encodeURIComponent(q)}`);
                setSearchResults(res.data);
            } catch {}
            finally { setIsSearching(false); }
        }, 300);
    };

    const clearSearch = () => { setSearchQuery(''); setSearchResults(null); };

    useEffect(() => {
        const handler = () => setIsAuthOpen(true);
        window.addEventListener('open-auth-modal', handler);
        return () => window.removeEventListener('open-auth-modal', handler);
    }, []);

    useEffect(() => {
        if (!isAuthenticated && !localStorage.getItem('comere_onboarding_seen')) {
            setShowOnboarding(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const dismissOnboarding = () => {
        localStorage.setItem('comere_onboarding_seen', '1');
        setShowOnboarding(false);
    };

    const onboardingLogin = () => {
        dismissOnboarding();
        setAuthMode('login');
        setIsAuthOpen(true);
    };

    const loadAddresses = async () => {
        const res = await axios.get('/addresses');
        setAddresses(res.data.data);
    };

    const openAddressModal = () => {
        setIsAddressOpen(true);
        loadAddresses();
    };

    const resetAuthState = () => {
        setAuthMode('login');
        setEmail('');
        setPassword('');
        setRememberMe(false);
        setRegisterForm({ name: '', email: '', password: '', password_confirmation: '', document_number: '' });
        setForgotEmail('');
        setForgotSent(false);
    };

    const closeAuthModal = () => {
        setIsAuthOpen(false);
        resetAuthState();
    };

    const handleManualLogin = (e) => {
        e.preventDefault();
        setIsLoading(true);
        router.post('/marketplace/login', { email, password, remember: rememberMe }, {
            onFinish: () => setIsLoading(false),
            onSuccess: () => { setIsAuthOpen(false); setEmail(''); setPassword(''); setRememberMe(false); },
        });
    };

    const handleRegister = (e) => {
        e.preventDefault();
        setIsLoading(true);
        router.post('/cadastro', registerForm, {
            onFinish: () => setIsLoading(false),
            onSuccess: () => { setIsAuthOpen(false); resetAuthState(); },
        });
    };

    const handleForgotPassword = (e) => {
        e.preventDefault();
        setIsLoading(true);
        router.post('/esqueci-senha', { email: forgotEmail }, {
            onFinish: () => setIsLoading(false),
            onSuccess: () => setForgotSent(true),
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
            setAddressForm({ label: 'Casa', zip_code: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '', latitude: '', longitude: '', is_default: false });
        } catch (err) {
            if (err.response?.status === 422) {
                setAddressErrors(err.response.data.errors ?? {});
            }
        } finally {
            setIsLoading(false);
        }
    };

    const geocodeAddressData = async (form) => {
        const { street, number, city, state } = form;
        if (!street || !city || !state) return;
        setIsGeocodingAddress(true);
        try {
            const q = [number, street, city, state, 'Brasil'].filter(Boolean).join(', ');
            const res = await fetch(
                `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=json&limit=1`,
                { headers: { 'Accept-Language': 'pt-BR' } }
            );
            const data = await res.json();
            if (data.length > 0) {
                setAddressForm(prev => ({
                    ...prev,
                    latitude: parseFloat(data[0].lat).toFixed(6),
                    longitude: parseFloat(data[0].lon).toFixed(6),
                }));
            }
        } catch {} finally {
            setIsGeocodingAddress(false);
        }
    };

    const fetchAddressByCep = async (cep) => {
        const clean = cep.replace(/\D/g, '');
        if (clean.length !== 8) return;
        try {
            const res = await fetch(`https://viacep.com.br/ws/${clean}/json/`);
            const data = await res.json();
            if (!data.erro) {
                setAddressForm(prev => {
                    const updated = {
                        ...prev,
                        street: data.logradouro || prev.street,
                        neighborhood: data.bairro || prev.neighborhood,
                        city: data.localidade || prev.city,
                        state: data.uf || prev.state,
                    };
                    geocodeAddressData(updated);
                    return updated;
                });
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

            <AnimatePresence>
                {showOnboarding && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 z-[100] flex items-center justify-center sm:p-6 bg-gradient-to-b from-red-500 via-red-600 to-gray-900"
                    >
                        <div className="w-full h-full sm:h-auto sm:max-w-sm sm:rounded-3xl sm:overflow-hidden sm:shadow-2xl flex flex-col text-white sm:max-h-[90vh]">
                            <div className="flex-grow flex flex-col items-center justify-center gap-4 px-9 py-10 text-center sm:min-h-[460px]">
                                <span className="text-4xl font-bold italic">Comere</span>
                                <p className="text-sm opacity-90 leading-relaxed max-w-[280px]">
                                    Peça dos seus restaurantes favoritos, acompanhe a entrega e gerencie seus pedidos em um só lugar.
                                </p>
                                <div className="flex gap-1.5 mt-2">
                                    <span className="w-[22px] h-1 rounded-full bg-white" />
                                    <span className="w-2 h-1 rounded-full bg-white/40" />
                                    <span className="w-2 h-1 rounded-full bg-white/40" />
                                </div>
                            </div>

                            <div className="px-8 pt-4 pb-9 flex flex-col gap-3 flex-shrink-0">
                                <button
                                    onClick={dismissOnboarding}
                                    className="w-full bg-white text-red-500 font-bold py-4 rounded-2xl text-sm hover:bg-gray-50 transition-all active:scale-95"
                                >
                                    Começar
                                </button>
                                <button
                                    onClick={onboardingLogin}
                                    className="text-center text-xs font-bold opacity-90 hover:opacity-100 transition-opacity py-1"
                                >
                                    Já tenho conta — Entrar
                                </button>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            {/* Navbar */}
            <nav className="sticky top-0 z-40 bg-white shadow-sm border-b border-gray-100">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between items-center h-16">
                        <div className="flex items-center gap-8">
                            <Link href="/" className="flex-shrink-0 flex items-center gap-2">
                                <span className="text-2xl font-bold bg-gradient-to-r from-red-600 to-red-500 bg-clip-text text-transparent italic">Comere</span>
                            </Link>

                            <div className="hidden md:block w-96 relative" ref={searchRef}>
                                <div className="relative">
                                    <input
                                        type="text"
                                        value={searchQuery}
                                        onChange={handleSearchChange}
                                        placeholder="Busque por produtos ou lojas"
                                        className="w-full bg-gray-100 border-none rounded-lg py-2 pl-4 pr-10 focus:ring-2 focus:ring-red-500/20 focus:bg-white transition-all text-sm outline-none"
                                    />
                                    <div className="absolute right-3 top-2.5 text-red-400">
                                        {isSearching ? (
                                            <div className="w-4 h-4 border-2 border-red-400 border-t-transparent rounded-full animate-spin" />
                                        ) : (
                                            <Search size={16} />
                                        )}
                                    </div>
                                </div>

                                <AnimatePresence>
                                    {searchResults && (searchResults.companies.length > 0 || searchResults.products.length > 0) && (
                                        <motion.div
                                            initial={{ opacity: 0, y: -6 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: -6 }}
                                            transition={{ duration: 0.15 }}
                                            className="absolute top-full mt-2 left-0 right-0 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden z-50"
                                        >
                                            {searchResults.companies.length > 0 && (
                                                <div>
                                                    <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-4 pt-3 pb-1">Lojas</p>
                                                    {searchResults.companies.map((c) => (
                                                        <Link
                                                            key={c.uuid}
                                                            href={`/store/${c.uuid}`}
                                                            onClick={clearSearch}
                                                            className="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors"
                                                        >
                                                            <img src={c.logo} alt={c.name} className="w-8 h-8 rounded-lg object-cover border border-gray-100 flex-shrink-0" />
                                                            <div className="min-w-0">
                                                                <p className="text-sm font-semibold text-gray-900 truncate">{c.name}</p>
                                                                <p className="text-xs text-gray-400">{c.type}</p>
                                                            </div>
                                                        </Link>
                                                    ))}
                                                </div>
                                            )}

                                            {searchResults.products.length > 0 && (
                                                <div className={searchResults.companies.length > 0 ? 'border-t border-gray-100' : ''}>
                                                    <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-4 pt-3 pb-1">Produtos</p>
                                                    {searchResults.products.map((p, i) => (
                                                        <Link
                                                            key={i}
                                                            href={`/store/${p.company_uuid}`}
                                                            onClick={clearSearch}
                                                            className="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors"
                                                        >
                                                            <img src={p.logo} alt={p.company_name} className="w-8 h-8 rounded-lg object-cover border border-gray-100 flex-shrink-0" />
                                                            <div className="flex-grow min-w-0">
                                                                <p className="text-sm font-semibold text-gray-900 truncate">{p.name}</p>
                                                                <p className="text-xs text-gray-400 truncate">{p.company_name}</p>
                                                            </div>
                                                            <span className="text-sm font-bold text-gray-700 flex-shrink-0">
                                                                R$ {Number(p.amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                                            </span>
                                                        </Link>
                                                    ))}
                                                </div>
                                            )}
                                        </motion.div>
                                    )}

                                    {searchResults && searchResults.companies.length === 0 && searchResults.products.length === 0 && (
                                        <motion.div
                                            initial={{ opacity: 0, y: -6 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: -6 }}
                                            transition={{ duration: 0.15 }}
                                            className="absolute top-full mt-2 left-0 right-0 bg-white rounded-2xl shadow-xl border border-gray-100 px-4 py-6 flex flex-col items-center text-center gap-1.5 z-50"
                                        >
                                            <Search size={28} className="text-gray-300 mb-1" />
                                            <p className="text-sm font-bold text-gray-900">Nenhum resultado para "{searchQuery}"</p>
                                            <p className="text-xs text-gray-400">Tente buscar por outro termo ou categoria.</p>
                                        </motion.div>
                                    )}
                                </AnimatePresence>
                            </div>
                        </div>

                        <div className="flex items-center gap-4">
                            {isAuthenticated ? (
                                <div className="flex items-center gap-3">
                                    {/* Endereço */}
                                    <button
                                        onClick={openAddressModal}
                                        className="hidden md:flex items-center gap-1.5 text-sm text-gray-600 hover:text-red-500 transition-colors max-w-[200px]"
                                    >
                                        <MapPin size={15} className="text-red-500 flex-shrink-0" />
                                        <span className="truncate text-xs font-medium">{defaultAddressLabel}</span>
                                        <ChevronDown size={13} className="flex-shrink-0" />
                                    </button>

                                    {/* User menu */}
                                    <div className="relative" ref={userMenuRef}>
                                        <button
                                            onClick={() => setIsUserMenuOpen((v) => !v)}
                                            className="flex items-center gap-2 rounded-full hover:bg-gray-100 pr-2 transition-colors"
                                        >
                                            <div className="h-9 w-9 rounded-full bg-gradient-to-tr from-red-500 to-red-400 flex items-center justify-center text-white text-xs font-bold shadow-sm flex-shrink-0">
                                                {initials(displayUser?.name)}
                                            </div>
                                            <span className="hidden sm:block text-sm font-semibold text-gray-700 max-w-[100px] truncate">
                                                {displayUser?.name?.split(' ')[0] || 'Usuário'}
                                            </span>
                                            <ChevronDown size={14} className={`text-gray-400 transition-transform ${isUserMenuOpen ? 'rotate-180' : ''}`} />
                                        </button>

                                        <AnimatePresence>
                                            {isUserMenuOpen && (
                                                <motion.div
                                                    initial={{ opacity: 0, y: -6, scale: 0.97 }}
                                                    animate={{ opacity: 1, y: 0, scale: 1 }}
                                                    exit={{ opacity: 0, y: -6, scale: 0.97 }}
                                                    transition={{ duration: 0.15 }}
                                                    className="absolute right-0 mt-2 w-64 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden z-50"
                                                >
                                                    <div className="flex items-center gap-3 px-4 py-4 border-b border-gray-100">
                                                        <div className="h-11 w-11 rounded-full bg-gradient-to-tr from-red-500 to-red-400 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                                                            {initials(displayUser?.name)}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <p className="text-sm font-bold text-gray-900 truncate">{displayUser?.name || 'Usuário'}</p>
                                                            <p className="text-xs text-gray-400 truncate">{displayUser?.email}</p>
                                                        </div>
                                                    </div>

                                                    <div className="grid grid-cols-2 gap-2 px-4 py-3 border-b border-gray-100">
                                                        <div className="bg-gray-50 rounded-xl py-2.5 text-center">
                                                            <p className="text-sm font-bold text-gray-900">{formatCurrency(favored_balance)}</p>
                                                            <p className="text-[10px] text-gray-500 font-medium">Saldo fiado</p>
                                                        </div>
                                                        <div className="bg-gray-50 rounded-xl py-2.5 text-center">
                                                            <p className="text-sm font-bold text-gray-900">{orders_count?.total ?? 0}</p>
                                                            <p className="text-[10px] text-gray-500 font-medium">Pedidos</p>
                                                        </div>
                                                    </div>

                                                    <button
                                                        onClick={() => { setIsUserMenuOpen(false); openAddressModal(); }}
                                                        className="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                                                    >
                                                        <MapPin size={16} className="text-gray-400" />
                                                        Endereços
                                                    </button>

                                                    <Link
                                                        href="/meus-pedidos"
                                                        onClick={() => setIsUserMenuOpen(false)}
                                                        className="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors relative"
                                                    >
                                                        <Package size={16} className="text-gray-400" />
                                                        Meus Pedidos
                                                        {orders_count?.unfinished > 0 && (
                                                            <span className="ml-auto bg-red-500 text-white text-[10px] font-bold h-4 px-1.5 rounded-full flex items-center justify-center animate-pulse">
                                                                {orders_count.unfinished}
                                                            </span>
                                                        )}
                                                    </Link>

                                                    <button
                                                        onClick={() => { setIsUserMenuOpen(false); handleLogout(); }}
                                                        className="w-full flex items-center gap-3 px-4 py-3 text-sm text-red-500 hover:bg-red-50 transition-colors border-t border-gray-100"
                                                    >
                                                        <LogOut size={16} />
                                                        Sair
                                                    </button>
                                                </motion.div>
                                            )}
                                        </AnimatePresence>
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

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-24 md:pb-8 min-h-[calc(100vh-12rem)]">
                {children}
            </main>

            {/* Abas fixas — só no mobile; no desktop a navegação fica no topo */}
            <nav className="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-100 flex items-stretch justify-around pb-[env(safe-area-inset-bottom)]">
                <Link
                    href="/"
                    className={`flex flex-col items-center justify-center gap-1 py-2.5 flex-1 ${url === '/' ? 'text-red-500' : 'text-gray-400'}`}
                >
                    <Home size={20} />
                    <span className={`text-[11px] ${url === '/' ? 'font-bold' : 'font-medium'}`}>Início</span>
                </Link>
                <Link
                    href="/meus-pedidos"
                    className={`flex flex-col items-center justify-center gap-1 py-2.5 flex-1 ${url.startsWith('/meus-pedidos') ? 'text-red-500' : 'text-gray-400'}`}
                >
                    <Package size={20} />
                    <span className={`text-[11px] ${url.startsWith('/meus-pedidos') ? 'font-bold' : 'font-medium'}`}>Pedidos</span>
                </Link>
                <Link
                    href="/#favoritos"
                    className="flex flex-col items-center justify-center gap-1 py-2.5 flex-1 text-gray-400"
                >
                    <Heart size={20} />
                    <span className="text-[11px] font-medium">Favoritos</span>
                </Link>
                <button
                    onClick={() => (isAuthenticated ? setIsProfileSheetOpen(true) : setIsAuthOpen(true))}
                    className="flex flex-col items-center justify-center gap-1 py-2.5 flex-1 text-gray-400"
                >
                    <User size={20} />
                    <span className="text-[11px] font-medium">Perfil</span>
                </button>
            </nav>

            {/* Perfil — bottom sheet mobile */}
            <AnimatePresence>
                {isProfileSheetOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setIsProfileSheetOpen(false)}
                            className="md:hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50"
                        />
                        <motion.div
                            initial={{ y: '100%' }}
                            animate={{ y: 0 }}
                            exit={{ y: '100%' }}
                            transition={{ type: 'spring', damping: 25, stiffness: 220 }}
                            className="md:hidden fixed bottom-0 left-0 right-0 z-[60] bg-white rounded-t-3xl shadow-2xl p-6"
                        >
                            <div className="flex items-center gap-3 mb-5">
                                <div className="h-14 w-14 rounded-full bg-gradient-to-tr from-red-500 to-red-400 flex items-center justify-center text-white text-lg font-bold flex-shrink-0">
                                    {initials(displayUser?.name)}
                                </div>
                                <div className="min-w-0">
                                    <p className="text-sm font-bold text-gray-900 truncate">{displayUser?.name || 'Usuário'}</p>
                                    <p className="text-xs text-gray-400 truncate">{displayUser?.email}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-2.5 mb-2">
                                <div className="bg-gray-50 rounded-2xl py-3 text-center">
                                    <p className="text-base font-bold text-gray-900">{formatCurrency(favored_balance)}</p>
                                    <p className="text-[11px] text-gray-500 font-medium">Saldo fiado</p>
                                </div>
                                <div className="bg-gray-50 rounded-2xl py-3 text-center">
                                    <p className="text-base font-bold text-gray-900">{orders_count?.total ?? 0}</p>
                                    <p className="text-[11px] text-gray-500 font-medium">Pedidos</p>
                                </div>
                            </div>

                            <button
                                onClick={() => { setIsProfileSheetOpen(false); openAddressModal(); }}
                                className="w-full flex items-center gap-3 px-2 py-3.5 text-sm font-semibold text-gray-700 border-t border-gray-100"
                            >
                                <MapPin size={18} className="text-gray-400" />
                                <span className="flex-1 text-left truncate">{defaultAddressLabel}</span>
                                <ChevronRight size={16} className="text-gray-300" />
                            </button>

                            <Link
                                href="/meus-pedidos"
                                onClick={() => setIsProfileSheetOpen(false)}
                                className="w-full flex items-center gap-3 px-2 py-3.5 text-sm font-semibold text-gray-700 border-t border-gray-100"
                            >
                                <Package size={18} className="text-gray-400" />
                                <span className="flex-1 text-left">Meus Pedidos</span>
                                {orders_count?.unfinished > 0 && (
                                    <span className="bg-red-500 text-white text-[10px] font-bold h-4 px-1.5 rounded-full flex items-center justify-center">
                                        {orders_count.unfinished}
                                    </span>
                                )}
                                <ChevronRight size={16} className="text-gray-300" />
                            </Link>

                            <button
                                onClick={() => { setIsProfileSheetOpen(false); handleLogout(); }}
                                className="w-full flex items-center gap-3 px-2 py-3.5 text-sm font-semibold text-red-500 border-t border-gray-100"
                            >
                                <LogOut size={18} />
                                Sair
                            </button>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            {/* Auth Drawer */}
            <AnimatePresence>
                {isAuthOpen && (
                    <>
                        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={closeAuthModal} className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50" />
                        <motion.div initial={{ x: '100%' }} animate={{ x: 0 }} exit={{ x: '100%' }} transition={{ type: 'spring', damping: 25, stiffness: 200 }} className="fixed top-0 right-0 h-full w-full sm:w-[420px] bg-white z-[60] shadow-2xl flex flex-col">
                            <div className="flex items-center justify-between p-6 border-b border-gray-100">
                                <div className="flex items-center gap-2">
                                    {authMode !== 'login' && (
                                        <button onClick={() => setAuthMode('login')} className="p-1 -ml-1 text-gray-400 hover:text-gray-700 transition-colors"><ChevronLeft size={22} /></button>
                                    )}
                                    <h2 className="text-xl font-bold text-gray-900">
                                        {authMode === 'register' ? 'Criar conta' : authMode === 'forgot' ? 'Recuperar senha' : 'Acesse sua conta'}
                                    </h2>
                                </div>
                                <button onClick={closeAuthModal} className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500"><X size={24} /></button>
                            </div>
                            <div className="flex-grow overflow-y-auto p-8 flex flex-col gap-8">
                                {authMode === 'login' && (
                                    <>
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
                                            {errors?.email && <span className="text-xs text-red-500 font-medium px-1 -mt-2">{errors.email}</span>}
                                            <div className="flex items-center justify-between">
                                                <label className="flex items-center gap-2 cursor-pointer">
                                                    <input type="checkbox" checked={rememberMe} onChange={(e) => setRememberMe(e.target.checked)} className="accent-red-500" />
                                                    <span className="text-xs text-gray-500">Lembrar-me</span>
                                                </label>
                                                <button type="button" onClick={() => setAuthMode('forgot')} className="text-xs font-bold text-red-500">Esqueci minha senha</button>
                                            </div>
                                            <button type="submit" disabled={isLoading} className="mt-2 bg-red-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 hover:bg-red-600 transition-all active:scale-95 uppercase tracking-widest text-sm disabled:opacity-50">
                                                {isLoading ? 'Entrando...' : 'Login'}
                                            </button>

                                            {clerkEnabled && (
                                                <>
                                                    <div className="flex items-center gap-2.5 my-1">
                                                        <div className="flex-1 h-px bg-gray-200" />
                                                        <span className="text-[11px] text-gray-400">ou</span>
                                                        <div className="flex-1 h-px bg-gray-200" />
                                                    </div>
                                                    <ClerkSSOButton onAuthenticated={closeAuthModal} />
                                                </>
                                            )}

                                            <p className="text-xs text-gray-500 text-center mt-2">
                                                Ainda não tem conta?{' '}
                                                <button type="button" onClick={() => setAuthMode('register')} className="text-red-500 font-bold">Cadastre-se</button>
                                            </p>
                                        </form>
                                    </>
                                )}

                                {authMode === 'register' && (
                                    <form onSubmit={handleRegister} className="flex flex-col gap-4">
                                        <div className="flex flex-col gap-1.5">
                                            <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Nome</label>
                                            <input type="text" value={registerForm.name} onChange={(e) => setRegisterForm((p) => ({ ...p, name: e.target.value }))} required placeholder="Seu nome completo" className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none" />
                                            {errors?.name && <span className="text-xs text-red-500 font-medium px-1">{errors.name}</span>}
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">E-mail</label>
                                            <input type="email" value={registerForm.email} onChange={(e) => setRegisterForm((p) => ({ ...p, email: e.target.value }))} required placeholder="seu@email.com" className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none" />
                                            {errors?.email && <span className="text-xs text-red-500 font-medium px-1">{errors.email}</span>}
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">CPF</label>
                                            <input type="text" value={registerForm.document_number} onChange={(e) => setRegisterForm((p) => ({ ...p, document_number: e.target.value.replace(/\D/g, '').slice(0, 11) }))} required placeholder="000.000.000-00" className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none" />
                                            {errors?.document_number && <span className="text-xs text-red-500 font-medium px-1">{errors.document_number}</span>}
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Senha</label>
                                            <input type="password" value={registerForm.password} onChange={(e) => setRegisterForm((p) => ({ ...p, password: e.target.value }))} required placeholder="••••••••" className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none" />
                                            {errors?.password && <span className="text-xs text-red-500 font-medium px-1">{errors.password}</span>}
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Confirmar senha</label>
                                            <input type="password" value={registerForm.password_confirmation} onChange={(e) => setRegisterForm((p) => ({ ...p, password_confirmation: e.target.value }))} required placeholder="••••••••" className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none" />
                                        </div>
                                        <button type="submit" disabled={isLoading} className="mt-2 bg-red-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 hover:bg-red-600 transition-all active:scale-95 uppercase tracking-widest text-sm disabled:opacity-50">
                                            {isLoading ? 'Criando conta...' : 'Criar conta'}
                                        </button>
                                        <p className="text-xs text-gray-500 text-center mt-1">
                                            Já tem conta?{' '}
                                            <button type="button" onClick={() => setAuthMode('login')} className="text-red-500 font-bold">Fazer login</button>
                                        </p>
                                    </form>
                                )}

                                {authMode === 'forgot' && (
                                    forgotSent ? (
                                        <div className="text-center flex flex-col gap-4">
                                            <div className="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mx-auto"><Mail size={32} /></div>
                                            <p className="text-gray-600 text-sm">Se o e-mail <strong>{forgotEmail}</strong> estiver cadastrado, enviamos um link para redefinir sua senha.</p>
                                            <button type="button" onClick={() => setAuthMode('login')} className="text-sm font-bold text-red-500">Voltar para o login</button>
                                        </div>
                                    ) : (
                                        <form onSubmit={handleForgotPassword} className="flex flex-col gap-4">
                                            <p className="text-gray-500 text-sm italic">Informe seu e-mail e enviaremos um link para você redefinir sua senha.</p>
                                            <div className="flex flex-col gap-1.5">
                                                <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">E-mail</label>
                                                <input type="email" value={forgotEmail} onChange={(e) => setForgotEmail(e.target.value)} required placeholder="seu@email.com" className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none" />
                                                {errors?.email && <span className="text-xs text-red-500 font-medium px-1">{errors.email}</span>}
                                            </div>
                                            <button type="submit" disabled={isLoading} className="mt-2 bg-red-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 hover:bg-red-600 transition-all active:scale-95 uppercase tracking-widest text-sm disabled:opacity-50">
                                                {isLoading ? 'Enviando...' : 'Enviar link'}
                                            </button>
                                        </form>
                                    )
                                )}
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
                                                <input
                                                    value={addressForm.number}
                                                    onChange={e => setAddressForm(p => ({ ...p, number: e.target.value }))}
                                                    onBlur={e => geocodeAddressData({ ...addressForm, number: e.target.value })}
                                                    required
                                                    className="border border-gray-200 rounded-xl py-2.5 px-3 text-sm outline-none focus:border-red-500"
                                                />
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
                                            {/* Localização */}
                                            <div className="col-span-2 flex flex-col gap-2">
                                                <div className="flex items-center justify-between">
                                                    <label className="text-xs font-bold text-gray-400 uppercase">Localização no mapa</label>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (!navigator.geolocation) return;
                                                            navigator.geolocation.getCurrentPosition(pos => {
                                                                setAddressForm(p => ({
                                                                    ...p,
                                                                    latitude: pos.coords.latitude.toFixed(6),
                                                                    longitude: pos.coords.longitude.toFixed(6),
                                                                }));
                                                            });
                                                        }}
                                                        className="flex items-center gap-1 text-xs font-semibold text-red-500 hover:text-red-600 transition-colors"
                                                    >
                                                        <MapPin size={13} /> Usar GPS
                                                    </button>
                                                </div>

                                                {isGeocodingAddress && (
                                                    <div className="h-[160px] rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center">
                                                        <p className="text-xs text-gray-400 animate-pulse">Buscando localização...</p>
                                                    </div>
                                                )}

                                                {!isGeocodingAddress && addressForm.latitude && addressForm.longitude && (
                                                    <div className="rounded-xl overflow-hidden border border-gray-200 relative" style={{ height: '160px' }}>
                                                        <iframe
                                                            src={`https://www.openstreetmap.org/export/embed.html?bbox=${parseFloat(addressForm.longitude) - 0.004},${parseFloat(addressForm.latitude) - 0.004},${parseFloat(addressForm.longitude) + 0.004},${parseFloat(addressForm.latitude) + 0.004}&layer=mapnik&marker=${addressForm.latitude},${addressForm.longitude}`}
                                                            width="100%"
                                                            height="160"
                                                            style={{ border: 0, pointerEvents: 'none' }}
                                                            title="Localização"
                                                        />
                                                        <p className="absolute bottom-1 right-2 text-[10px] text-gray-500 bg-white/80 px-1 rounded">
                                                            {addressForm.latitude}, {addressForm.longitude}
                                                        </p>
                                                    </div>
                                                )}

                                                {!isGeocodingAddress && !addressForm.latitude && (
                                                    <div className="h-[160px] rounded-xl border border-dashed border-gray-200 bg-gray-50 flex flex-col items-center justify-center gap-2">
                                                        <MapPin size={20} className="text-gray-300" />
                                                        <p className="text-xs text-gray-400">Preencha o CEP e o número para identificar a localização</p>
                                                    </div>
                                                )}
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
