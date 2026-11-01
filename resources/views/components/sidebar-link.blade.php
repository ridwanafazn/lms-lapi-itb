@props(['active', 'color' => 'indigo'])

@php
$colors = [
    'blue' => $active ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50',
    'green' => $active ? 'bg-green-50 text-green-700' : 'text-gray-600 hover:bg-gray-50',
    'indigo' => $active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50',
];
@endphp

<a {{ $attributes->merge(['class' => 'group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-all duration-200 ease-in-out ' . $colors[$color]]) }}
   wire:navigate.hover>
    {{ $slot }}
</a>
