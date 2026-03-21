<x-filament-panels::page.simple>
    @if ($this->showCompanySelector)
        <div class="fi-simple-page">
            <div class="grid gap-y-6">
                <div class="text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Você tem acesso a múltiplas lojas. Selecione em qual deseja entrar.
                    </p>
                </div>

                <div class="grid gap-y-4">
                    @foreach ($this->companyOptions as $option)
                        <button
                            type="button"
                            wire:click="$set('selectedCompanyUuid', '{{ $option['uuid'] }}')"
                            wire:loading.attr="disabled"
                            class="fi-btn fi-btn-size-md fi-btn-color-gray fi-btn-outlined relative flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold shadow-sm outline-none transition duration-75 hover:bg-gray-50 focus:ring-2 focus:ring-primary-600 dark:border-white/20 dark:hover:bg-white/5 {{ $this->selectedCompanyUuid === $option['uuid'] ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-400 dark:bg-primary-400/10 dark:text-primary-400' : 'text-gray-700 dark:text-gray-200' }}"
                        >
                            {{ $option['name'] }}
                        </button>
                    @endforeach
                </div>

                <x-filament::button
                    wire:click="selectCompany"
                    wire:loading.attr="disabled"
                    :disabled="! $this->selectedCompanyUuid"
                    size="lg"
                    class="w-full"
                >
                    Entrar na loja
                </x-filament::button>
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
