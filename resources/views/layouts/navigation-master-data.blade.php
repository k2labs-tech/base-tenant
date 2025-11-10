<nav class="flex-1 space-y-1 pl-2 py-4 uppercase"
     x-data="{
     masterDataMenu: {{ request()->routeIs('master-data.*') ? 'true' : 'false' }},
      }"
     @click.outside="masterDataMenu = false">
    <!-- Current: "bg-gray-900 text-white", Default: "text-gray-300 hover:bg-gray-700 hover:text-white" -->
    <button type="button"
            @click="masterDataMenu = !masterDataMenu"
            class="hover:bg-gray-50 text-gray-950 group flex items-center px-2 py-2 text-sm font-medium rounded-l-md hover:border-r-4 hover:border-gray-700 w-full uppercase">
        <x-heroicons::outline.cog-8-tooth class="mr-3 shrink-0 h-6 w-6" />
        {{ __('base-tenant::app.navigation.master_data') }}
    </button>

    <div
        x-show="masterDataMenu"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="uppercase"
    >
        <div
            x-data="{
                isOpen: false,
                timeout: null,
            }"
            x-on:mouseenter="clearTimeout(timeout); isOpen = true"
            x-on:mouseleave="timeout = setTimeout(() => { isOpen = false }, 300)"
           class="group flex flex-col items-left px-2 text-sm font-medium rounded-l-md  hover:text-gray-950"
            >
            <a href="#" class="flex hover:bg-gray-50 py-2">
                <x-heroicons::outline.user-group class="mr-3 shrink-0 h-6 w-6" />
                {{ __('base-tenant::app.navigation.users') }}
            </a>
            <ul x-show="isOpen" class="hover:bg-gray-50/2 px-2">
                <li>
                    <x-navigation-menu-item route="dashboard"
                                            icon="outline.user"
                                            label="{{ __('base-tenant::app.navigation.users') }}" />
                </li>
                <li>
                    <x-navigation-menu-item route="dashboard"
                                            icon="outline.user-circle"
                                            label="{{ __('base-tenant::app.navigation.roles') }}" />
                </li>
                <li>
                    <x-navigation-menu-item route="dashboard"
                                            icon="outline.academic-cap"
                                            label="{{ __('base-tenant::app.navigation.positions') }}" />
                </li>
                <li>
                    <x-navigation-menu-item route="dashboard"
                                            icon="outline.users"
                                            label="{{ __('base-tenant::app.navigation.groups') }}" />
                </li>
            </ul>
        </div>




        <x-navigation-menu-item route="dashboard"
                                icon="outline.cog-8-tooth"
                                label="{{ __('base-tenant::app.navigation.programs') }}" />
        <x-navigation-menu-item route="dashboard"
                                icon="outline.cog-8-tooth"
                                label="{{ __('base-tenant::app.navigation.services') }}" />
        <x-navigation-menu-item route="dashboard"
                                icon="outline.cog-8-tooth"
                                label="{{ __('base-tenant::app.navigation.assistance_types') }}" />
        <x-navigation-menu-item route="dashboard"
                                icon="outline.cog-8-tooth"
                                label="{{ __('base-tenant::app.navigation.request_channels') }}" />
        <x-navigation-menu-item route="dashboard"
                                icon="outline.document-text"
                                label="{{ __('base-tenant::app.navigation.redactions') }}" />
        <x-navigation-menu-item route="dashboard"
                                icon="outline.cog-8-tooth"
                                label="{{ __('base-tenant::app.navigation.tender_types') }}" />
        <x-navigation-menu-item route="dashboard"
                                icon="outline.cog-8-tooth"
                                label="{{ __('base-tenant::app.navigation.project_types') }}" />

        <x-navigation-menu-item route="dashboard"
                                icon="outline.cog-8-tooth"
                                label="{{ __('base-tenant::app.navigation.priorities') }}" />
        <x-navigation-menu-item route="dashboard"
                                icon="outline.arrow-path-rounded-square"
                                label="{{ __('base-tenant::app.navigation.procedures') }}" />

    </div>

</nav>
