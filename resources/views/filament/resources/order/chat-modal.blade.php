@php
    $record = $record ?? null;
@endphp
<div
    x-data="{
        orderUuid: @js($record->uuid),
        indexUrl: @js(route('admin.order.chat.index', $record->uuid)),
        storeUrl: @js(route('admin.order.chat.store', $record->uuid)),
        csrfToken: @js(csrf_token()),
        messages: [],
        text: '',
        loading: true,
        sending: false,
        chatOpen: true,
        poll: null,
        escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        },
        formatTime(iso) {
            return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.list;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
        async fetchMessages() {
            try {
                const res = await fetch(this.indexUrl, { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                this.messages = data.messages;
                this.chatOpen = data.chat_open;
            } catch (e) {
                // mantém o último estado conhecido
            } finally {
                this.loading = false;
                this.scrollToBottom();
            }
        },
        async send() {
            const trimmed = this.text.trim();
            if (!trimmed || this.sending) return;
            this.sending = true;
            try {
                const res = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ message: trimmed }),
                });
                if (!res.ok) throw new Error('request failed');
                const data = await res.json();
                this.messages.push(data.message);
                this.text = '';
                this.scrollToBottom();
            } catch (e) {
                // mantém o texto no campo para a loja tentar de novo
            } finally {
                this.sending = false;
            }
        },
        init() {
            this.fetchMessages();
            // O modal Alpine não dispara um hook de destruição confiável ao
            // fechar — em vez de depender de um evento, cada tick confere se
            // o próprio elemento ainda está no DOM antes de buscar de novo,
            // e para o polling sozinho quando o modal é fechado.
            this.poll = setInterval(() => {
                if (!document.body.contains(this.$el)) {
                    clearInterval(this.poll);
                    return;
                }
                this.fetchMessages();
            }, 6000);
        },
    }"
    x-init="init()"
    class="coc-root"
>
    <style>
        /* Mesma limitação dos outros componentes admin customizados: classes
           Tailwind arbitrárias não existem no CSS compilado do painel, então
           este modal usa apenas regras escopadas sob .coc-*. */
        .coc-root { display: flex; flex-direction: column; gap: 0.75rem; }
        .coc-list {
            max-height: 20rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
        }
        html.dark .coc-list { background: rgba(255,255,255,0.03); border-color: #374151; }
        .coc-empty { font-size: 0.8125rem; color: #9ca3af; text-align: center; padding: 1.5rem 0; }
        .coc-row { display: flex; }
        .coc-row--company { justify-content: flex-end; }
        .coc-row--client { justify-content: flex-start; }
        .coc-bubble { max-width: 80%; border-radius: 0.875rem; padding: 0.5rem 0.75rem; }
        .coc-bubble--company { background: #4f46e5; color: #fff; }
        .coc-bubble--client { background: #e5e7eb; color: #111827; }
        html.dark .coc-bubble--client { background: #374151; color: #f3f4f6; }
        .coc-bubble p { margin: 0; font-size: 0.8125rem; white-space: pre-wrap; word-break: break-word; }
        .coc-bubble-time { font-size: 0.625rem; margin-top: 0.25rem; opacity: 0.75; }
        .coc-form { display: flex; gap: 0.5rem; }
        .coc-input {
            flex: 1;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            background: #fff;
            color: #111827;
        }
        html.dark .coc-input { background: #1f2937; border-color: #374151; color: #f3f4f6; }
        .coc-send {
            border: none;
            border-radius: 0.75rem;
            width: 2.25rem;
            height: 2.25rem;
            flex-shrink: 0;
            background: #4f46e5;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .coc-send:disabled { opacity: 0.5; cursor: default; }
        .coc-closed { font-size: 0.75rem; color: #9ca3af; text-align: center; padding: 0.5rem 0; }
    </style>

    <div class="coc-list" x-ref="list">
        <template x-if="loading">
            <p class="coc-empty">Carregando conversa...</p>
        </template>
        <template x-if="!loading && messages.length === 0">
            <p class="coc-empty">Nenhuma mensagem ainda neste pedido.</p>
        </template>
        <template x-for="message in messages" :key="message.uuid">
            <div class="coc-row" :class="message.sender_type === 'company' ? 'coc-row--company' : 'coc-row--client'">
                <div class="coc-bubble" :class="message.sender_type === 'company' ? 'coc-bubble--company' : 'coc-bubble--client'">
                    <p x-text="message.message"></p>
                    <p class="coc-bubble-time" x-text="(message.sender_type === 'client' ? (message.author_name + ' · ') : '') + formatTime(message.created_at)"></p>
                </div>
            </div>
        </template>
    </div>

    <template x-if="chatOpen">
        <form class="coc-form" @submit.prevent="send()">
            <input
                type="text"
                class="coc-input"
                x-model="text"
                maxlength="1000"
                placeholder="Responder ao cliente..."
            />
            <button type="submit" class="coc-send" :disabled="sending || !text.trim()">
                <x-heroicon-o-paper-airplane style="width: 1rem; height: 1rem;" />
            </button>
        </form>
    </template>
    <template x-if="!chatOpen">
        <p class="coc-closed">Conversa encerrada — pedido finalizado.</p>
    </template>
</div>
