<x-filament-panels::page.simple>
    @if ($this->showCompanySelector)
        {{--
            Links GET simples (sem CSRF) para evitar o 419 causado pelo
            session()->regenerate() que ocorre dentro de parent::authenticate().
        --}}
        <div class="grid gap-y-4">
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                Você tem acesso a múltiplas lojas. Selecione em qual deseja entrar.
            </p>

            <div class="grid gap-y-2">
                @foreach ($this->companyOptions as $option)
                    <a
                        href="{{ route('filament.admin.pages.dashboard', ['tenant' => $option['uuid']]) }}"
                        class="flex items-center gap-4 rounded-xl border border-gray-200 dark:border-white/10 px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/5 hover:border-primary-500 transition no-underline"
                    >
                        @if ($option['logo_path'])
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::url($option['logo_path']) }}"
                                alt="{{ $option['name'] }}"
                                class="h-10 w-10 rounded-lg object-cover shrink-0 bg-gray-100 dark:bg-white/10"
                            >
                        @else
                            <div class="h-10 w-10 rounded-lg bg-primary-600/20 dark:bg-primary-500/20 flex items-center justify-center shrink-0">
                                <span class="text-primary-700 dark:text-primary-400 font-bold text-sm">
                                    {{ mb_strtoupper(mb_substr($option['name'], 0, 2)) }}
                                </span>
                            </div>
                        @endif

                        <span class="text-sm font-medium text-gray-900 dark:text-white leading-tight">
                            {{ $option['name'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        @if (filament()->hasRegistration())
            <x-slot name="subheading">
                {{ __('filament-panels::pages/auth/login.actions.register.before') }}
                {{ $this->registerAction }}
            </x-slot>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

        <x-filament-panels::form id="form" wire:submit="authenticate">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
    @endif
</x-filament-panels::page.simple>
