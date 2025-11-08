<x-base-tenant::app-layout>
    <x-slot name="header">
        {{ __('UI Component Kit') }}
    </x-slot>

    <div class="space-y-8">
        <!-- Typography -->
        <div class="bg-white rounded-xl shadow-soft p-6">
            <h2 class="text-2xl font-semibold text-primary-900 mb-6">Typography</h2>
            <div class="space-y-4">
                <h1 class="text-5xl font-bold text-primary-900">Heading 1</h1>
                <h2 class="text-4xl font-semibold text-primary-900">Heading 2</h2>
                <h3 class="text-3xl font-semibold text-primary-900">Heading 3</h3>
                <h4 class="text-2xl font-medium text-primary-900">Heading 4</h4>
                <h5 class="text-xl font-medium text-primary-900">Heading 5</h5>
                <h6 class="text-lg font-medium text-primary-900">Heading 6</h6>
                <p class="text-base text-primary-700">Body text - Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                <p class="text-sm text-primary-600">Small text - Lorem ipsum dolor sit amet.</p>
                <p class="text-xs text-primary-500">Extra small text - Lorem ipsum dolor sit amet.</p>
            </div>
        </div>

        <!-- Colors -->
        <div class="bg-white rounded-xl shadow-soft p-6">
            <h2 class="text-2xl font-semibold text-primary-900 mb-6">Color Palette</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Primary Colors -->
                <div>
                    <h3 class="text-lg font-medium text-primary-900 mb-3">Primary</h3>
                    <div class="space-y-2">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-50 rounded-lg"></div>
                            <span class="text-sm text-primary-600">50</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-100 rounded-lg"></div>
                            <span class="text-sm text-primary-600">100</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-200 rounded-lg"></div>
                            <span class="text-sm text-primary-600">200</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-300 rounded-lg"></div>
                            <span class="text-sm text-primary-600">300</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-400 rounded-lg"></div>
                            <span class="text-sm text-primary-600">400</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-500 rounded-lg"></div>
                            <span class="text-sm text-primary-600">500</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-600 rounded-lg"></div>
                            <span class="text-sm text-primary-600">600</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-700 rounded-lg"></div>
                            <span class="text-sm text-primary-600">700</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-800 rounded-lg"></div>
                            <span class="text-sm text-primary-600">800</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-primary-900 rounded-lg"></div>
                            <span class="text-sm text-primary-600">900</span>
                        </div>
                    </div>
                </div>

                <!-- Accent Colors -->
                <div>
                    <h3 class="text-lg font-medium text-primary-900 mb-3">Accent</h3>
                    <div class="space-y-2">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-50 rounded-lg"></div>
                            <span class="text-sm text-primary-600">50</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-100 rounded-lg"></div>
                            <span class="text-sm text-primary-600">100</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-200 rounded-lg"></div>
                            <span class="text-sm text-primary-600">200</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-300 rounded-lg"></div>
                            <span class="text-sm text-primary-600">300</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-400 rounded-lg"></div>
                            <span class="text-sm text-primary-600">400</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-500 rounded-lg"></div>
                            <span class="text-sm text-primary-600">500</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-600 rounded-lg"></div>
                            <span class="text-sm text-primary-600">600</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-700 rounded-lg"></div>
                            <span class="text-sm text-primary-600">700</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-800 rounded-lg"></div>
                            <span class="text-sm text-primary-600">800</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-accent-900 rounded-lg"></div>
                            <span class="text-sm text-primary-600">900</span>
                        </div>
                    </div>
                </div>

                <!-- Surface Colors -->
                <div>
                    <h3 class="text-lg font-medium text-primary-900 mb-3">Surface</h3>
                    <div class="space-y-2">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-50 rounded-lg border border-surface-200"></div>
                            <span class="text-sm text-primary-600">50</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-100 rounded-lg border border-surface-200"></div>
                            <span class="text-sm text-primary-600">100</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-200 rounded-lg"></div>
                            <span class="text-sm text-primary-600">200</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-300 rounded-lg"></div>
                            <span class="text-sm text-primary-600">300</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-400 rounded-lg"></div>
                            <span class="text-sm text-primary-600">400</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-500 rounded-lg"></div>
                            <span class="text-sm text-primary-600">500</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-600 rounded-lg"></div>
                            <span class="text-sm text-primary-600">600</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-700 rounded-lg"></div>
                            <span class="text-sm text-primary-600">700</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-800 rounded-lg"></div>
                            <span class="text-sm text-primary-600">800</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-surface-900 rounded-lg"></div>
                            <span class="text-sm text-primary-600">900</span>
                        </div>
                    </div>
                </div>

                <!-- Status Colors -->
                <div>
                    <h3 class="text-lg font-medium text-primary-900 mb-3">Status</h3>
                    <div class="space-y-2">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-success rounded-lg"></div>
                            <span class="text-sm text-primary-600">Success</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-warning rounded-lg"></div>
                            <span class="text-sm text-primary-600">Warning</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-error rounded-lg"></div>
                            <span class="text-sm text-primary-600">Error</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-info rounded-lg"></div>
                            <span class="text-sm text-primary-600">Info</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Buttons -->
        <div class="bg-white rounded-xl shadow-soft p-6">
            <h2 class="text-2xl font-semibold text-primary-900 mb-6">Buttons</h2>
            <div class="space-y-6">
                <!-- Primary Buttons -->
                <div>
                    <h3 class="text-lg font-medium text-primary-900 mb-3">Primary Buttons</h3>
                    <div class="flex flex-wrap gap-4">
                        <x-primary-button>Default</x-primary-button>
                        <x-primary-button disabled>Disabled</x-primary-button>
                        <x-primary-button class="px-6 py-3">Large</x-primary-button>
                        <x-primary-button class="px-3 py-1.5 text-xs">Small</x-primary-button>
                    </div>
                </div>

                <!-- Secondary Buttons -->
                <div>
                    <h3 class="text-lg font-medium text-primary-900 mb-3">Secondary Buttons</h3>
                    <div class="flex flex-wrap gap-4">
                        <x-secondary-button>Default</x-secondary-button>
                        <x-secondary-button disabled>Disabled</x-secondary-button>
                        <x-secondary-button class="px-6 py-3">Large</x-secondary-button>
                        <x-secondary-button class="px-3 py-1.5 text-xs">Small</x-secondary-button>
                    </div>
                </div>

                <!-- Icon Buttons -->
                <div>
                    <h3 class="text-lg font-medium text-primary-900 mb-3">Icon Buttons</h3>
                    <div class="flex flex-wrap gap-4">
                        <button class="p-2 bg-accent-600 text-white rounded-lg hover:bg-accent-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </button>
                        <button class="p-2 bg-surface-100 text-primary-700 rounded-lg hover:bg-surface-200 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>
                        <button class="p-2 bg-error text-white rounded-lg hover:bg-red-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Elements -->
        <div class="bg-white rounded-xl shadow-soft p-6">
            <h2 class="text-2xl font-semibold text-primary-900 mb-6">Form Elements</h2>
            <div class="max-w-xl space-y-6">
                <!-- Text Input -->
                <div>
                    <x-input-label for="example-input" value="Text Input" />
                    <x-text-input id="example-input" type="text" class="mt-1" placeholder="Enter text..." />
                </div>

                <!-- Select -->
                <div>
                    <x-input-label for="example-select" value="Select" />
                    <select id="example-select" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                        <option>Option 1</option>
                        <option>Option 2</option>
                        <option>Option 3</option>
                    </select>
                </div>

                <!-- Textarea -->
                <div>
                    <x-input-label for="example-textarea" value="Textarea" />
                    <textarea id="example-textarea" rows="4" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 placeholder-primary-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200" placeholder="Enter description..."></textarea>
                </div>

                <!-- Checkbox -->
                <div class="flex items-center">
                    <input id="example-checkbox" type="checkbox" class="w-4 h-4 text-accent-600 bg-white border-surface-300 rounded-smfocus:ring-accent-500 focus:ring-2">
                    <label for="example-checkbox" class="ml-2 text-sm text-primary-700">
                        Remember me
                    </label>
                </div>

                <!-- Radio -->
                <div class="space-y-2">
                    <div class="flex items-center">
                        <input id="radio-1" name="radio-group" type="radio" class="w-4 h-4 text-accent-600 bg-white border-surface-300 focus:ring-accent-500 focus:ring-2">
                        <label for="radio-1" class="ml-2 text-sm text-primary-700">
                            Option 1
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input id="radio-2" name="radio-group" type="radio" class="w-4 h-4 text-accent-600 bg-white border-surface-300 focus:ring-accent-500 focus:ring-2">
                        <label for="radio-2" class="ml-2 text-sm text-primary-700">
                            Option 2
                        </label>
                    </div>
                </div>

                <!-- Toggle -->
                <div class="flex items-center">
                    <button type="button" x-data="{ enabled: false }" @click="enabled = !enabled" :class="enabled ? 'bg-accent-600' : 'bg-surface-300'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                        <span :class="enabled ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                    </button>
                    <span class="ml-3 text-sm text-primary-700">Enable notifications</span>
                </div>
            </div>
        </div>

        <!-- Cards -->
        <div class="bg-white rounded-xl shadow-soft p-6">
            <h2 class="text-2xl font-semibold text-primary-900 mb-6">Cards</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Basic Card -->
                <div class="bg-white rounded-lg border border-surface-200 p-6">
                    <h3 class="text-lg font-semibold text-primary-900 mb-2">Basic Card</h3>
                    <p class="text-sm text-primary-600">This is a basic card with a border.</p>
                </div>

                <!-- Shadow Card -->
                <div class="bg-white rounded-lg shadow-soft p-6">
                    <h3 class="text-lg font-semibold text-primary-900 mb-2">Shadow Card</h3>
                    <p class="text-sm text-primary-600">This card has a soft shadow effect.</p>
                </div>

                <!-- Interactive Card -->
                <div class="bg-white rounded-lg shadow-soft p-6 hover:shadow-lg transition-shadow cursor-pointer">
                    <h3 class="text-lg font-semibold text-primary-900 mb-2">Interactive Card</h3>
                    <p class="text-sm text-primary-600">This card has hover effects.</p>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <div class="bg-white rounded-xl shadow-soft p-6">
            <h2 class="text-2xl font-semibold text-primary-900 mb-6">Alerts</h2>
            <div class="space-y-4">
                <!-- Success Alert -->
                <div class="p-4 bg-success/10 border border-success/20 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-success shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-success">Success!</p>
                            <p class="text-sm text-success/80 mt-1">Your changes have been saved successfully.</p>
                        </div>
                    </div>
                </div>

                <!-- Warning Alert -->
                <div class="p-4 bg-warning/10 border border-warning/20 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-warning shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-warning">Warning!</p>
                            <p class="text-sm text-warning/80 mt-1">Please review your settings before continuing.</p>
                        </div>
                    </div>
                </div>

                <!-- Error Alert -->
                <div class="p-4 bg-error/10 border border-error/20 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-error shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-error">Error!</p>
                            <p class="text-sm text-error/80 mt-1">Something went wrong. Please try again.</p>
                        </div>
                    </div>
                </div>

                <!-- Info Alert -->
                <div class="p-4 bg-info/10 border border-info/20 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-info shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-info">Information</p>
                            <p class="text-sm text-info/80 mt-1">This feature will be available in the next update.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables -->
        <div class="bg-white rounded-xl shadow-soft">
            <div class="px-6 py-4 border-b border-surface-200">
                <h2 class="text-2xl font-semibold text-primary-900">Tables</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-surface-50 border-b border-surface-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-primary-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-200">
                        <tr class="hover:bg-surface-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-primary-900">John Doe</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-primary-600">john@example.com</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-primary-600">Admin</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 text-xs font-medium bg-success/10 text-success rounded-full">Active</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <button class="text-accent-600 hover:text-accent-700">Edit</button>
                            </td>
                        </tr>
                        <tr class="hover:bg-surface-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-primary-900">Jane Smith</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-primary-600">jane@example.com</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-primary-600">User</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 text-xs font-medium bg-primary-100 text-primary-700 rounded-full">Inactive</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <button class="text-accent-600 hover:text-accent-700">Edit</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Example -->
        <div class="bg-white rounded-xl shadow-soft p-6">
            <h2 class="text-2xl font-semibold text-primary-900 mb-6">Modal</h2>
            <x-primary-button x-data="" @click="$dispatch('open-modal', 'example-modal')">
                Open Modal
            </x-primary-button>

            <x-modal name="example-modal" :show="false">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-primary-900 mb-4">Modal Title</h3>
                    <p class="text-sm text-primary-600 mb-6">This is an example modal dialog. You can put any content here.</p>
                    <div class="flex justify-end space-x-3">
                        <x-secondary-button @click="$dispatch('close')">Cancel</x-secondary-button>
                        <x-primary-button @click="$dispatch('close')">Confirm</x-primary-button>
                    </div>
                </div>
            </x-modal>
        </div>
    </div>
</x-base-tenant::app-layout>
