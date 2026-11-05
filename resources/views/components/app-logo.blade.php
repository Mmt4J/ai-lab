@props([
    'sidebar' => false,
    // Allow each shell to pass a human-friendly product name while the
    // fallback still keeps the starter kit usable out of the box.
    'name' => null,
])

@php
    $name ??= config('app.name', 'AI Lab');
@endphp

@if($sidebar)
    {{-- Sidebar version: Flux renders the logo, name, and collapse controls. --}}
    <flux:sidebar.brand :name="$name" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            {{-- The starter kit mark inherits the green accent set in app.css. --}}
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-brand-950" />
        </x-slot>
    </flux:sidebar.brand>
@else
    {{-- Top-bar version used by the alternate header layout. --}}
    <flux:brand :name="$name" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-brand-950" />
        </x-slot>
    </flux:brand>
@endif
