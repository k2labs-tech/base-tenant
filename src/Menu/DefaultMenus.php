<?php

declare(strict_types=1);

namespace Base\Tenant\Menu;

use Base\Tenant\Facades\Menu;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Support\Module;

/**
 * The navigation the package ships with. Host applications add their own
 * entries by calling `Menu::register()` from a service provider.
 */
class DefaultMenus
{
    public static function register(): void
    {
        Menu::register('main', function (MenuBuilder $menu): void {
            $menu->item('dashboard')
                ->label('base-tenant::app.navigation.dashboard')
                ->icon('home')
                ->route('base-tenant.dashboard')
                ->position(10);

            $menu->item('users')
                ->label('base-tenant::app.navigation.users')
                ->icon('users')
                ->route('base-tenant.users.index')
                ->permission('users.view')
                ->position(20);

            $menu->item('invitations')
                ->label('base-tenant::app.navigation.invitations')
                ->icon('envelope')
                ->route('base-tenant.invitations.index')
                ->permission('invitations.view')
                ->badge(static fn (): int => UserInvite::pending()->count())
                ->position(30);

            $menu->item('accounts')
                ->label('base-tenant::app.navigation.accounts')
                ->icon('building-office')
                ->route('base-tenant.accounts.index')
                ->permission('accounts.view')
                ->position(40);
        });

        Menu::register('settings', function (MenuBuilder $menu): void {
            $menu->item('profile')
                ->label('base-tenant::app.navigation.profile')
                ->icon('user')
                ->route('base-tenant.profile')
                ->position(10);

            $menu->item('roles')
                ->label('base-tenant::app.navigation.roles')
                ->icon('shield-check')
                ->route('base-tenant.roles.index')
                ->permission('roles.view')
                ->position(20);

            $menu->item('navigation')
                ->label('base-tenant::app.navigation.navigation')
                ->icon('bars-3')
                ->route('base-tenant.menus.index')
                ->permission('menus.update')
                ->position(30);

            $menu->item('features')
                ->label('base-tenant::app.navigation.features')
                ->icon('sparkles')
                ->route('base-tenant.features.index')
                ->permission('features.view')
                ->position(35);

            // Solo si el módulo está encendido: una entrada que lleva a una
            // ruta que no existe rompe el menú entero al construir la URL.
            if (Module::enabled(Module::METERING)) {
                $menu->item('usage')
                    ->label('base-tenant::app.navigation.usage')
                    ->icon('chart-bar')
                    ->route('base-tenant.usage')
                    ->permission('usage.view')
                    ->position(36);
            }

            if (Module::enabled(Module::FILES)) {
                $menu->item('files')
                    ->label('base-tenant::app.navigation.files')
                    ->icon('folder')
                    ->route('base-tenant.files.index')
                    ->permission('files.view')
                    ->position(37);
            }

            if (Module::enabled(Module::LANGUAGES)) {
                $menu->item('languages')
                    ->label('base-tenant::app.navigation.languages')
                    ->icon('language')
                    ->route('base-tenant.languages.index')
                    ->permission('languages.manage')
                    ->position(39);
            }

            if (Module::enabled(Module::TRANSFER)) {
                $menu->item('transfers')
                    ->label('base-tenant::app.navigation.transfers')
                    ->icon('arrows-right-left')
                    ->route('base-tenant.transfers.index')
                    ->permission('transfers.view')
                    ->position(36);
            }

            if (Module::enabled(Module::CONNECTIONS)) {
                $menu->item('connections')
                    ->label('base-tenant::app.navigation.connections')
                    ->icon('link')
                    ->route('base-tenant.connections.index')
                    ->permission('connections.manage')
                    ->position(37);
            }

            $menu->item('settings')
                ->label('base-tenant::app.navigation.settings')
                ->icon('cog')
                ->route('base-tenant.settings.index')
                ->permission('settings.view')
                ->position(38);

            $menu->item('activity')
                ->label('base-tenant::app.navigation.activity')
                ->icon('clock')
                ->route('base-tenant.activity')
                ->permission('activity.view')
                ->position(40);
        });
    }
}
