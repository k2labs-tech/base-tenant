<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-surface-100 text-primary-700 font-medium text-sm rounded-lg border border-surface-300 hover:bg-surface-200 hover:border-surface-400 focus:bg-surface-200 focus:outline-hidden focus:ring-2 focus:ring-primary-500/20 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>
