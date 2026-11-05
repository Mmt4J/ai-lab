<x-layouts::app :title="__('AI Lab')">
    @php
        /*
         * A single source of truth for the lab hub.
         *
         * Each entry powers one card: the route name used for the link, the
         * Flux/Heroicon name, a human title, the skill the lab teaches, and a
         * short description. Adding a seventh lab later only means adding one
         * array entry here.
         */
        $labs = [
            [
                'route' => 'labs.summarizer',
                'icon' => 'document-text',
                'title' => 'Summarizer',
                'skill' => 'The basic API call',
                'description' => 'Paste a long article, email thread, or report and get three clear bullets back.',
            ],
            [
                'route' => 'labs.tickets',
                'icon' => 'inbox',
                'title' => 'Ticket classifier',
                'skill' => 'Structured output & tool calls',
                'description' => 'Save support tickets, then let the AI assign a category, urgency, and summary.',
            ],
            [
                'route' => 'labs.product',
                'icon' => 'shopping-bag',
                'title' => 'Product writer',
                'skill' => 'Prompt design & tone',
                'description' => 'Turn a product name and a few keywords into copy in the tone you choose.',
            ],
            [
                'route' => 'labs.faq',
                'icon' => 'chat-bubble-left-right',
                'title' => 'FAQ chatbot',
                'skill' => 'History & context',
                'description' => 'Ask questions against a pasted FAQ. The bot answers only from what it is given.',
            ],
            [
                'route' => 'labs.receipt',
                'icon' => 'receipt-percent',
                'title' => 'Receipt reader',
                'skill' => 'Image input',
                'description' => 'Upload a bank receipt and pull out the vendor, date, amount, and reference fields.',
            ],
            [
                'route' => 'labs.reviews',
                'icon' => 'star',
                'title' => 'Sentiment tagger',
                'skill' => 'Bridge to analysis',
                'description' => 'Label customer reviews as positive, neutral, or negative and watch the counts move.',
            ],
        ];
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-8">
        {{-- Hub intro. The shared styling mirrors the lab header component. --}}
        <header class="space-y-3">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-700 dark:text-brand-400">
                {{ __('AI Lab') }}
            </p>

            <flux:heading size="xl" class="font-display tracking-tight">
                {{ __('Six small builds, one skill each.') }}
            </flux:heading>

            <flux:text class="max-w-2xl text-sm text-muted">
                {{ __('Each lab isolates one AI technique so you can see exactly what changes between them. Pick a card to start experimenting.') }}
            </flux:text>
        </header>

        {{-- Responsive card grid: one column on phones, two on tablets, three on desktop. --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($labs as $lab)
                {{-- The whole card is the link, which keeps the hit target large and the markup simple. --}}
                <a href="{{ route($lab['route']) }}" wire:navigate
                    class="group block h-full rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-paper">
                    <flux:card
                        class="flex h-full flex-col gap-4 border-line! bg-card! p-5 transition duration-200 group-hover:-translate-y-0.5 group-hover:border-brand-300 group-hover:shadow-lg group-hover:shadow-brand-900/5 dark:group-hover:border-brand-700">
                        <div class="flex items-start justify-between gap-3">
                            {{-- Icon tile flips to solid green while the card is hovered. --}}
                            <span
                                class="flex size-11 items-center justify-center rounded-lg bg-brand-50 text-brand-700 transition-colors group-hover:bg-brand-700 group-hover:text-white dark:bg-brand-950 dark:text-brand-300 dark:group-hover:bg-brand-500 dark:group-hover:text-brand-950">
                                <flux:icon :name="$lab['icon']" class="size-5" />
                            </span>

                            <flux:icon.arrow-up-right
                                class="size-4 text-ink/25 transition group-hover:text-brand-700 dark:text-white/25 dark:group-hover:text-brand-300" />
                        </div>

                        <div class="space-y-1.5">
                            <flux:heading size="lg" class="font-display">
                                {{ __($lab['title']) }}
                            </flux:heading>

                            {{-- Uppercase skill label gives every card a scannable second line. --}}
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand-700 dark:text-brand-400">
                                {{ __($lab['skill']) }}
                            </p>

                            <flux:text class="text-sm leading-relaxed text-muted">
                                {{ __($lab['description']) }}
                            </flux:text>
                        </div>

                        <div class="mt-auto flex items-center gap-1.5 pt-1 text-sm font-medium text-brand-700 dark:text-brand-400">
                            {{ __('Open lab') }}
                            <flux:icon.arrow-right
                                class="size-4 transition-transform group-hover:translate-x-0.5" />
                        </div>
                    </flux:card>
                </a>
            @endforeach
        </div>
    </div>
</x-layouts::app>
