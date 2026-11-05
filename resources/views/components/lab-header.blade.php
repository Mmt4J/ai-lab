{{--
    Shared page intro for every AI Lab screen.

    Keeping the eyebrow, display heading, and supporting copy in one component
    means all six labs line up exactly, and a future copy change only has to
    happen here instead of in six Blade files.

    Props:
    - title:       the page name, shown in the display face
    - description: one or two sentences explaining the lab
    - eyebrow:     optional label above the title (defaults to "AI Lab")
--}}
@props([
    'title',
    'description' => null,
    'eyebrow' => 'AI Lab',
])

<header {{ $attributes->class('space-y-2') }}>
    {{-- Small uppercase label that gives each page the same prototype rhythm. --}}
    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-700 dark:text-brand-400">
        {{ $eyebrow }}
    </p>

    {{-- Page title. font-display is the Bricolage Grotesque token in app.css. --}}
    <flux:heading size="xl" class="font-display tracking-tight">
        {{ $title }}
    </flux:heading>

    @if ($description)
        <flux:text class="max-w-2xl text-sm text-muted">
            {{ $description }}
        </flux:text>
    @endif
</header>
