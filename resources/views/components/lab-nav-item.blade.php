{{--
    One AI Lab link in the sidebar.

    Flux's navlist item already handles the route/navigation behaviour, keyboard
    focus, and mobile layout. We only add the green active state so the current
    lab is obvious at a glance. `data-current:` is the variant Flux applies to
    the active item, so these classes only paint the selected link.
--}}
@props([
    'icon',
    'href',
    'current' => false,
])

<flux:navlist.item
    :icon="$icon"
    :href="$href"
    :current="$current"
    {{ $attributes->class([
        'data-current:bg-brand-700!',
        'data-current:text-white!',
        'data-current:border-brand-700!',
        'hover:data-current:text-white!',
        'dark:data-current:bg-brand-600!',
        'dark:data-current:border-brand-600!',
    ]) }}
>
    {{ $slot }}
</flux:navlist.item>
