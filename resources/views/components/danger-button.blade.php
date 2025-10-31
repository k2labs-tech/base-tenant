<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-error-600 text-white font-medium text-sm rounded-lg hover:bg-error-700 focus:bg-error-700 active:bg-error-800 focus:outline-hidden focus:ring-2 focus:ring-error-500/20 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>
