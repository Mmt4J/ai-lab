<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
{{-- The app is light-first to match the original prototype. Flux still stores
     the user's light/dark/system choice and applies the `dark` class itself. --}}

<head>
    @include('partials.head')
</head>

{{-- Paper canvas + ink text come from the tokens in resources/css/app.css. --}}
<body class="min-h-screen bg-paper text-ink">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-line bg-card dark:border-line dark:bg-card">
        <flux:sidebar.header>
            {{-- "AI Lab" is passed explicitly so the brand reads nicely even though
                 APP_NAME in .env uses an underscore. --}}
            <x-app-logo :sidebar="true" name="AI Lab" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        {{-- Short descriptor from the prototype, kept outside the flex header
             so it always sits on its own line under the brand. --}}
        <p class="-mt-1 mb-3 px-2 text-xs text-ink/55 dark:text-white/50">
            Six small builds, one skill each.
        </p>

        <flux:sidebar.nav>
            <flux:navlist.group heading="AI Lab">
                {{-- Each link uses the shared green active state from lab-nav-item. --}}
                <x-lab-nav-item icon="squares-2x2" :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')" wire:navigate>
                    Dashboard
                </x-lab-nav-item>

                <x-lab-nav-item icon="document-text" :href="route('labs.summarizer')"
                    :current="request()->routeIs('labs.summarizer')" wire:navigate>
                    Summarizer
                </x-lab-nav-item>

                <x-lab-nav-item icon="inbox" :href="route('labs.tickets')"
                    :current="request()->routeIs('labs.tickets')" wire:navigate>
                    Ticket classifier
                </x-lab-nav-item>

                <x-lab-nav-item icon="shopping-bag" :href="route('labs.product')"
                    :current="request()->routeIs('labs.product')" wire:navigate>
                    Product writer
                </x-lab-nav-item>

                <x-lab-nav-item icon="chat-bubble-left-right" :href="route('labs.faq')"
                    :current="request()->routeIs('labs.faq')" wire:navigate>
                    FAQ chatbot
                </x-lab-nav-item>

                <x-lab-nav-item icon="receipt-percent" :href="route('labs.receipt')"
                    :current="request()->routeIs('labs.receipt')" wire:navigate>
                    Receipt reader
                </x-lab-nav-item>

                <x-lab-nav-item icon="star" :href="route('labs.reviews')"
                    :current="request()->routeIs('labs.reviews')" wire:navigate>
                    Sentiment tagger
                </x-lab-nav-item>
            </flux:navlist.group>
        </flux:sidebar.nav>

        <flux:spacer />

        {{-- Account menu stays pinned to the bottom on desktop. --}}
        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile header: same brand, plus a compact profile menu. -->
    <flux:header class="border-b border-line bg-card/90 backdrop-blur lg:hidden dark:bg-card">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <x-app-logo name="AI Lab" href="{{ route('dashboard') }}" wire:navigate />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                {{-- Appearance switch for mobile, matching the desktop menu. --}}
                <div class="px-1 pb-1 pt-2">
                    <p class="mb-2 px-1 text-xs font-medium uppercase tracking-wide text-ink/50 dark:text-white/40">
                        {{ __('Appearance') }}
                    </p>

                    <flux:radio.group x-data variant="segmented" size="sm" x-model="$flux.appearance">
                        <flux:radio value="light" icon="sun" />
                        <flux:radio value="dark" icon="moon" />
                        <flux:radio value="system" icon="computer-desktop" />
                    </flux:radio.group>
                </div>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    {{-- Toast host for Livewire events dispatched from the lab components. --}}
    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    {{-- Flash error messages (from exceptions). --}}
    @if (session('error'))
        <flux:toast.group>
            <flux:toast variant="danger" :dismissible="true" :persist="true">
                {{ session('error') }}
            </flux:toast>
        </flux:toast.group>
    @endif

    {{-- Flash success messages. --}}
    @if (session('success'))
        <flux:toast.group>
            <flux:toast variant="success" :dismissible="true">
                {{ session('success') }}
            </flux:toast>
        </flux:toast.group>
    @endif

    @fluxScripts
</body>

</html>
