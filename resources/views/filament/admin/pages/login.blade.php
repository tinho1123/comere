<x-filament-panels::page.simple>
    @if ($this->showCompanySelector)
        {{--
            Formulário HTML puro (sem Livewire) para evitar o 419 causado pelo
            session()->regenerate() que ocorre dentro de parent::authenticate().
            O @csrf aqui já usa o token da sessão regenerada.
        --}}
        <form
            method="POST"
            action="{{ route('admin.select-company.store') }}"
            class="grid gap-y-4"
        >
            @csrf

            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                Você tem acesso a múltiplas lojas. Selecione em qual deseja entrar.
            </p>

            <div class="grid gap-y-2">
                @foreach ($this->companyOptions as $i => $option)
                    <label class="flex items-center gap-4 rounded-xl border border-gray-200 dark:border-white/10 px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-500/10 transition">
                        <input
                            type="radio"
                            name="company_uuid"
                            value="{{ $option['uuid'] }}"
                            class="accent-primary-600 shrink-0"
                            @if ($i === 0) checked @endif
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
                    </label>
                @endforeach
            </div>

            @error('company_uuid')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror

            <x-filament::button type="submit" size="lg" class="w-full">
                Entrar na loja
            </x-filament::button>
        </form>
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
