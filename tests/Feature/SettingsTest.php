<?php

declare(strict_types=1);

use Base\Tenant\Exceptions\NoAccountException;
use Base\Tenant\Facades\Settings;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Settings\SettingsSchema;

class BrandSettings extends SettingsSchema
{
    public static function group(): string
    {
        return 'brand';
    }

    public string $tone = 'neutral';

    public bool $signOffWithName = true;

    public int $maxSubjectLength = 60;

    /** @var array<int, string> */
    public array $bannedWords = [];
}

test('a schema returns its declared defaults when nothing is stored', function () {
    $account = $this->createAccount();

    $brand = Settings::for($account)->get(BrandSettings::class);

    expect($brand->tone)->toBe('neutral')
        ->and($brand->signOffWithName)->toBeTrue()
        ->and($brand->maxSubjectLength)->toBe(60)
        ->and($brand->bannedWords)->toBe([]);
});

test('saved values come back with their declared types', function () {
    $account = $this->createAccount();

    $brand = Settings::for($account)->get(BrandSettings::class);
    $brand->tone = 'playful';
    $brand->signOffWithName = false;
    $brand->maxSubjectLength = 80;
    $brand->bannedWords = ['synergy', 'leverage'];
    $brand->save();

    $reloaded = Settings::for($account)->get(BrandSettings::class);

    expect($reloaded->tone)->toBe('playful')
        ->and($reloaded->signOffWithName)->toBeFalse()
        ->and($reloaded->maxSubjectLength)->toBe(80)
        ->and($reloaded->bannedWords)->toBe(['synergy', 'leverage']);
});

test('settings belong to one owner only', function () {
    $first = $this->createAccount();
    $second = $this->createAccount();

    Settings::for($first)->put(BrandSettings::class, ['tone' => 'formal']);

    expect(Settings::for($first)->get(BrandSettings::class)->tone)->toBe('formal')
        ->and(Settings::for($second)->get(BrandSettings::class)->tone)->toBe('neutral');
});

test('current() reads the account in context', function () {
    $account = $this->createAccount();

    Tenant::runFor($account, function (): void {
        Settings::current()->put(BrandSettings::class, ['tone' => 'direct']);

        expect(Settings::current()->get(BrandSettings::class)->tone)->toBe('direct');
    });
});

test('current() refuses to guess when no account is in context', function () {
    Tenant::forget();

    Settings::current();
})->throws(NoAccountException::class);

test('user settings still resolve over account settings', function () {
    $account = $this->createAccount();
    $user = $this->createUser($account);

    $account->setSetting('theme', 'light');
    $user->setSetting('theme', 'dark');

    Tenant::runFor($account, function () use ($user): void {
        $this->actingAs($user);

        expect(tenant_setting('theme'))->toBe('dark');
    });
});
