import { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { User, LogIn, X, Mail, LogOut, ShoppingCart, Search, Package } from 'lucide-react';

export default function MarketplaceLayout({ children }) {
    const { auth, orders_count } = usePage().props;
    const [isAuthOpen, setIsAuthOpen] = useState(false);

    useEffect(() => {
        const handler = () => setIsAuthOpen(true);
        window.addEventListener('open-auth-modal', handler);
        return () => window.removeEventListener('open-auth-modal', handler);
    }, []);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    const isAuthenticated = !!auth.user;
    const displayUser = auth.user;

    const handleManualLogin = (e) => {
        e.preventDefault();
        setIsLoading(true);
        router.post('/marketplace/login', {
            email,
            password
        }, {
            onFinish: () => setIsLoading(false),
            onSuccess: () => {
                setIsAuthOpen(false);
                setEmail('');
                setPassword('');
            }
        });
    };

    const handleLogout = () => {
        router.post('/marketplace/logout');
    };

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
                                <span className="text-2xl font-bold bg-gradient-to-r from-red-600 to-red-500 bg-clip-text text-transparent italic">
                                    Comere
                                </span>
                            </Link>

                            <div className="hidden md:block w-96">
                                <div className="relative">
                                    <input
                                        type="text"
                                        placeholder="Busque por produtos ou lojas"
                                        className="w-full bg-gray-100 border-none rounded-lg py-2 pl-4 pr-10 focus:ring-2 focus:ring-red-500/20 focus:bg-white transition-all text-sm"
                                    />
                                    <div className="absolute right-3 top-2.5 text-red-500">
                                        <Search size={18} />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-4">
                            {isAuthenticated ? (
                                <div className="flex items-center gap-6">
                                    <div className="flex items-center gap-2">
                                        <Link
                                            href="/meus-pedidos"
                                            className={`relative flex items-center gap-2 text-sm font-semibold transition-colors py-2 px-1 rounded-lg ${window.location.pathname === '/meus-pedidos' ? 'text-red-500' : 'text-gray-600 hover:text-red-500'}`}
                                        >
                                            <Package size={18} />
                                            <span className="hidden lg:inline">Meus Pedidos</span>
                                            {orders_count?.unfinished > 0 && (
                                                <span className="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold h-4 w-4 rounded-full flex items-center justify-center animate-pulse">
                                                    {orders_count.unfinished}
                                                </span>
                                            )}
                                        </Link>
                                    </div>

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
                                        <button
                                            onClick={handleLogout}
                                            title="Sair"
                                            className="text-gray-400 hover:text-red-500 transition-colors p-1"
                                        >
                                            <LogOut size={18} />
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <button
                                    onClick={() => setIsAuthOpen(true)}
                                    className="flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-red-500 transition-colors py-2 px-1 rounded-lg"
                                >
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

            {/* Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 min-h-[calc(100vh-12rem)]">
                {children}
            </main>

            {/* Auth Drawer */}
            <AnimatePresence>
                {isAuthOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setIsAuthOpen(false)}
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
                                <h2 className="text-xl font-bold text-gray-900">Acesse sua conta</h2>
                                <button
                                    onClick={() => setIsAuthOpen(false)}
                                    className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500"
                                >
                                    <X size={24} />
                                </button>
                            </div>

                            <div className="flex-grow overflow-y-auto p-8 flex flex-col gap-8">
                                <div className="text-center">
                                    <div className="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                        <User size={32} />
                                    </div>
                                    <p className="text-gray-500 text-sm italic">
                                        Faça login para gerenciar seus pedidos e seu saldo fiado com os parceiros.
                                    </p>
                                </div>

                                <form onSubmit={handleManualLogin} className="flex flex-col gap-4">
                                    <div className="flex flex-col gap-1.5">
                                        <label className="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">E-mail</label>
                                        <div className="relative">
                                            <input
                                                type="email"
                                                value={email}
                                                onChange={(e) => setEmail(e.target.value)}
                                                required
                                                placeholder="seu@email.com"
                                                className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 pl-10 pr-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none"
                                            />
                                            <Mail className="absolute left-3 top-3.5 text-gray-300" size={18} />
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <div className="flex justify-between items-center px-1">
                                            <label className="text-xs font-bold text-gray-400 uppercase tracking-wider">Senha</label>
                                            <button type="button" className="text-[10px] font-bold text-red-500 uppercase hover:underline">Esqueci a senha</button>
                                        </div>
                                        <input
                                            type="password"
                                            value={password}
                                            onChange={(e) => setPassword(e.target.value)}
                                            required
                                            placeholder="••••••••"
                                            className="w-full bg-gray-50 border-gray-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-sm outline-none"
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={isLoading}
                                        className="mt-4 bg-red-500 text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/20 hover:bg-red-600 transition-all active:scale-95 uppercase tracking-widest text-sm disabled:opacity-50"
                                    >
                                        {isLoading ? 'Entrando...' : 'Login'}
                                    </button>
                                </form>

                                <div className="mt-4 text-center">
                                    <p className="text-sm text-gray-500">
                                        Ainda não tem conta? <button className="text-red-500 font-bold hover:underline">Cadastre-se</button>
                                    </p>
                                </div>
                            </div>

                            <div className="p-6 bg-gray-50 border-t border-gray-100 text-center">
                                <p className="text-[10px] text-gray-400 uppercase tracking-widest leading-relaxed">
                                    Ao continuar, você concorda com nossos <br />
                                    <span className="font-bold underline cursor-pointer">Termos de Serviço</span> e <span className="font-bold underline cursor-pointer">Privacidade</span>.
                                </p>
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            {/* Footer */}
            <footer className="bg-white border-t border-gray-100 mt-12 py-8">
                <div className="max-w-7xl mx-auto px-4 text-center">
                    <p className="text-sm text-gray-500 italic">
                        © 2026 Comere - O seu marketplace de confiança
                    </p>
                </div>
            </footer>
        </div>
    );
}
