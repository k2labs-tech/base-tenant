<?php

declare(strict_types=1);

use Base\Tenant\Facades\Feature;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Services\FeatureService;

beforeEach(function () {
    config([
        'base-tenant.plans.free.features' => [
            'max_users' => 3,
            'export' => false,
            'api_access' => false,
        ],
    ]);

    FeatureService::flush();
});

test('an account without overrides falls back to its plan', function () {
    $account = $this->createAccount();

    expect(Feature::for($account)->active('export'))->toBeFalse()
        ->and(Feature::for($account)->limit('max_users'))->toBe(3);
});

test('an override wins over the plan', function () {
    $account = $this->createAccount();

    Feature::for($account)->set('export', true);

    expect(Feature::for($account)->active('export'))->toBeTrue();
});

test('an override applies to one account only', function () {
    $first = $this->createAccount();
    $second = $this->createAccount();

    Feature::for($first)->set('export', true);

    expect(Feature::for($first)->active('export'))->toBeTrue()
        ->and(Feature::for($second)->active('export'))->toBeFalse();
});

test('a numeric override raises the limit', function () {
    $account = $this->createAccount();

    Feature::for($account)->set('max_users', 25);

    expect(Feature::for($account)->limit('max_users'))->toBe(25)
        ->and(Feature::for($account)->withinLimit('max_users', 10))->toBeTrue()
        ->and(Feature::for($account)->withinLimit('max_users', 25))->toBeFalse();
});

test('an unlimited override never runs out', function () {
    $account = $this->createAccount();

    Feature::for($account)->set('max_users', -1);

    expect(Feature::for($account)->withinLimit('max_users', 10_000))->toBeTrue();
});

test('an expired override stops applying', function () {
    $account = $this->createAccount();

    Feature::for($account)->set('export', true, now()->subDay());

    FeatureService::flush();

    expect(Feature::for($account)->active('export'))->toBeFalse();
});

test('forgetting an override returns the account to its plan', function () {
    $account = $this->createAccount();

    Feature::for($account)->set('export', true);
    Feature::for($account)->forget('export');

    expect(Feature::for($account)->active('export'))->toBeFalse();
});

test('the facade reads the account in context', function () {
    $account = $this->createAccount();

    Feature::for($account)->set('export', true);

    expect(Tenant::runFor($account, fn (): bool => Feature::active('export')))->toBeTrue()
        ->and(Feature::active('export'))->toBeFalse();
});

test('all() merges overrides over the plan', function () {
    $account = $this->createAccount();

    Feature::for($account)->set('export', true);

    expect(Feature::for($account)->all())
        ->toMatchArray(['max_users' => 3, 'export' => true, 'api_access' => false]);
});

test('the legacy service API still answers', function () {
    $account = $this->createAccount();

    Feature::for($account)->set('api_access', true);

    expect(FeatureService::accountCan($account, 'api_access'))->toBeTrue()
        ->and(FeatureService::getLimit($account, 'max_users'))->toBe(3)
        ->and(FeatureService::isWithinLimit($account, 'max_users', 1))->toBeTrue();
});
