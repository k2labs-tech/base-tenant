@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 placeholder-primary-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200 disabled:bg-surface-100 disabled:text-primary-500 disabled:cursor-not-allowed']) !!}>
