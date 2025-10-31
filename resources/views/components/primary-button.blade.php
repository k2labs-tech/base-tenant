<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-accent-600 text-white font-medium text-sm rounded-lg hover:bg-accent-700 focus:bg-accent-700 active:bg-accent-800 focus:outline-hidden focus:ring-2 focus:ring-accent-500/20 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>
