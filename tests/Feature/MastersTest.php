<?php

declare(strict_types=1);

use Base\Tenant\Facades\Feature;
use Base\Tenant\Facades\Menu;
use Base\Tenant\Facades\Settings;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\AccountSettings;
use Base\Tenant\Livewire\FeatureManager;
use Base\Tenant\Models\Feature as FeatureModel;
use Base\Tenant\Settings\SettingsSchema;
use Livewire\Livewire;

class NotificationSettings extends SettingsSchema
{
    public static function group(): string
    {
        return 'notifications';
    }

    public string $fromName = 'Support';

    public bool $digest = true;

    public int $retentionDays = 30;

    /** @var array<int, string> */
    public array $blockedDomains = [];
}

beforeEach(function () {
    $this->syncPermissions();

    config([
        'base-tenant.plans.free.features' => [
            'max_users' => 3,
            'export' => false,
        ],
    ]);

    $this->account = $this->createAccount();

    // A tenant administrator may see what they are entitled to.
    $this->admin = $this->createUser($this->account, 'customer-admin');

    // Changing an entitlement is a commercial decision, so it belongs to staff.
    $this->staff = $this->createUser($this->account, null, ['is_admin' => true]);
});

test('the feature master lists what the plan grants', function () {
    $this->actingAsTenant($this->admin, $this->account);

    Livewire::test(FeatureManager::class)
        ->assertOk()
        ->assertSee('max_users')
        ->assertSee('export');
});

test('toggling a boolean feature writes an override for that account only', function () {
    $other = $this->createAccount();

    $this->actingAsTenant($this->staff, $this->account);

    Livewire::test(FeatureManager::class)->call('toggle', 'export');

    expect(Feature::for($this->account)->active('export'))->toBeTrue()
        ->and(Feature::for($other)->active('export'))->toBeFalse();
});

test('a numeric allowance can be raised and given an expiry', function () {
    $this->actingAsTenant($this->staff, $this->account);

    Livewire::test(FeatureManager::class)
        ->call('edit', 'max_users')
        ->set('value', '25')
        ->set('expiresAt', now()->addMonth()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    expect(Feature::for($this->account)->limit('max_users'))->toBe(25);

    $override = FeatureModel::where('account_id', $this->account->getKey())
        ->where('key', 'max_users')
        ->firstOrFail();

    expect($override->type)->toBe('integer')
        ->and($override->expires_at)->not->toBeNull();
});

test('an expiry in the past is refused', function () {
    $this->actingAsTenant($this->staff, $this->account);

    Livewire::test(FeatureManager::class)
        ->call('edit', 'max_users')
        ->set('value', '25')
        ->set('expiresAt', now()->subDay()->format('Y-m-d'))
        ->call('save')
        ->assertHasErrors('expiresAt');
});

test('resetting a feature returns the account to its plan', function () {
    $this->actingAsTenant($this->staff, $this->account);

    Livewire::test(FeatureManager::class)
        ->call('toggle', 'export')
        ->call('resetToPlan', 'export');

    expect(Feature::for($this->account)->active('export'))->toBeFalse()
        ->and(FeatureModel::where('account_id', $this->account->getKey())->count())->toBe(0);
});

test('a user without the features permission cannot reach the master', function () {
    $viewer = $this->createUser($this->account, 'customer-viewer');

    $this->actingAsTenant($viewer, $this->account);

    Livewire::test(FeatureManager::class)->assertForbidden();
});

test('a tenant administrator can see their entitlements but not grant themselves more', function () {
    $this->actingAsTenant($this->admin, $this->account);

    Livewire::test(FeatureManager::class)
        ->assertOk()
        ->assertSee('export')
        ->call('toggle', 'export')
        ->assertForbidden();

    expect(Feature::for($this->account)->active('export'))->toBeFalse();
});

test('a role granted features.update may change them', function () {
    $this->createRole($this->account, 'ops', ['features.view', 'features.update']);
    $ops = $this->createUser($this->account, 'ops');

    $this->actingAsTenant($ops, $this->account);

    Livewire::test(FeatureManager::class)->call('toggle', 'export');

    expect(Feature::for($this->account)->active('export'))->toBeTrue();
});

test('only a super admin can switch the account being edited', function () {
    $other = $this->createAccount();

    $this->actingAsTenant($this->admin, $this->account);

    Livewire::test(FeatureManager::class)->call('selectAccount', $other->getKey())->assertForbidden();
});

test('the settings master renders a form from the registered schema', function () {
    Settings::register(NotificationSettings::class);

    $this->actingAsTenant($this->admin, $this->account);

    Livewire::test(AccountSettings::class)
        ->assertOk()
        ->assertSet('group', 'notifications')
        ->assertSee('notifications.fromName')
        ->assertSee('notifications.retentionDays');
});

test('saved settings keep their declared types', function () {
    Settings::register(NotificationSettings::class);

    $this->actingAsTenant($this->admin, $this->account);

    Livewire::test(AccountSettings::class)
        ->set('values.fromName', 'Billing')
        ->set('values.digest', false)
        ->set('values.retentionDays', '90')
        ->set('values.blockedDomains', '["spam.test","junk.test"]')
        ->call('save');

    $saved = Settings::for($this->account)->get(NotificationSettings::class);

    expect($saved->fromName)->toBe('Billing')
        ->and($saved->digest)->toBeFalse()
        ->and($saved->retentionDays)->toBe(90)
        ->and($saved->blockedDomains)->toBe(['spam.test', 'junk.test']);
});

test('invalid JSON is stored as an empty list rather than blowing up', function () {
    Settings::register(NotificationSettings::class);

    $this->actingAsTenant($this->admin, $this->account);

    Livewire::test(AccountSettings::class)
        ->set('values.blockedDomains', 'not json at all')
        ->call('save');

    expect(Settings::for($this->account)->get(NotificationSettings::class)->blockedDomains)->toBe([]);
});

test('settings belong to one account', function () {
    Settings::register(NotificationSettings::class);

    $other = $this->createAccount();

    $this->actingAsTenant($this->admin, $this->account);

    Livewire::test(AccountSettings::class)->set('values.fromName', 'Billing')->call('save');

    expect(Settings::for($other)->get(NotificationSettings::class)->fromName)->toBe('Support');
});

test('a user without the settings permission cannot reach the master', function () {
    $viewer = $this->createUser($this->account, 'customer-viewer');

    $this->actingAsTenant($viewer, $this->account);

    Livewire::test(AccountSettings::class)->assertForbidden();
});

test('schemas can also be declared in configuration', function () {
    config(['base-tenant.settings.schemas' => [NotificationSettings::class]]);

    $this->actingAsTenant($this->admin, $this->account);

    Livewire::test(AccountSettings::class)->assertOk()->assertSet('group', 'notifications');
});

test('both masters appear in the navigation for someone who may see them', function () {
    Menu::sync();

    $this->actingAsTenant($this->admin, $this->account);

    expect(Menu::tree('settings', $this->admin)->pluck('key')->all())
        ->toContain('features', 'settings');
});

test('the navigation hides them from someone who may not', function () {
    Menu::sync();

    $viewer = $this->createUser($this->account, 'customer-viewer');

    $this->actingAsTenant($viewer, $this->account);

    $keys = Menu::tree('settings', $viewer)->pluck('key')->all();

    expect($keys)->not->toContain('features')->and($keys)->not->toContain('settings');
});
