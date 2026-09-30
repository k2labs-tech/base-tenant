<?php

declare(strict_types=1);

use Base\Tenant\Console\Commands\TenancyAuditCommand;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Tenancy\Events\TenancyBypassed;
use Base\Tenant\Tests\Fixtures\Tenancy\Scoped\Widget;
use Base\Tenant\Tests\Fixtures\Tenancy\StrictTenancyFixtures;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    StrictTenancyFixtures::createTables();

    // Registered by the service provider; added here while it is not, so the
    // command is tested either way.
    if (! array_key_exists('k2labs-base:tenancy-audit', Artisan::all())) {
        Artisan::registerCommand($this->app->make(TenancyAuditCommand::class));
    }
});

test('runWithout announces the bypass with its reason and caller', function () {
    Event::fake([TenancyBypassed::class]);

    $account = $this->createAccount();

    Tenant::runFor($account, fn () => Tenant::runWithout(fn () => null, 'nightly purge'));

    Event::assertDispatched(TenancyBypassed::class, function (TenancyBypassed $event) use ($account): bool {
        return $event->source === TenancyBypassed::SOURCE_RUN_WITHOUT
            && $event->reason === 'nightly purge'
            && $event->accountId === $account->getKey()
            && str_contains((string) $event->callSite, 'TenancyAuditTest.php');
    });
});

test('nested runWithout frames are announced once', function () {
    Event::fake([TenancyBypassed::class]);

    Tenant::runWithout(fn () => Tenant::runWithout(fn () => null));

    Event::assertDispatchedTimes(TenancyBypassed::class, 1);
});

test('runWithout marks the context as bypassed only while it runs', function () {
    expect(Tenant::isBypassed())->toBeFalse()
        ->and(Tenant::runWithout(fn (): bool => Tenant::isBypassed()))->toBeTrue()
        ->and(Tenant::isBypassed())->toBeFalse();
});

test('runFor inside runWithout is scoped again', function () {
    $account = $this->createAccount();

    $inner = Tenant::runWithout(fn (): bool => Tenant::runFor($account, fn (): bool => Tenant::isBypassed()));

    expect($inner)->toBeFalse();
});

test('acrossAccounts announces the bypass with the model queried', function () {
    Event::fake([TenancyBypassed::class]);

    UserInvite::query()->acrossAccounts()->count();

    Event::assertDispatched(
        TenancyBypassed::class,
        fn (TenancyBypassed $event): bool => $event->source === TenancyBypassed::SOURCE_ACROSS_ACCOUNTS
            && $event->subject === UserInvite::class
            && str_contains((string) $event->callSite, 'TenancyAuditTest.php'),
    );
});

test('forAccount announces reading another account, not its own', function () {
    Event::fake([TenancyBypassed::class]);

    $mine = $this->createAccount();
    $theirs = $this->createAccount();

    Tenant::runFor($mine, fn () => Widget::query()->forAccount($mine)->count());
    Event::assertNotDispatched(TenancyBypassed::class);

    Tenant::runFor($mine, fn () => Widget::query()->forAccount($theirs)->count());
    Event::assertDispatched(
        TenancyBypassed::class,
        fn (TenancyBypassed $event): bool => $event->targetAccountId === $theirs->getKey(),
    );
});

test('the audit fails when a model with account_id lacks the trait', function () {
    $this->artisan('k2labs-base:tenancy-audit', ['--path' => [dirname(__DIR__).'/Fixtures/Tenancy/Unscoped']])
        ->expectsOutputToContain('LooseWidget')
        ->assertExitCode(1);
});

test('the audit passes when every such model uses the trait', function () {
    $this->artisan('k2labs-base:tenancy-audit', ['--path' => [dirname(__DIR__).'/Fixtures/Tenancy/Scoped']])
        ->assertExitCode(0);
});

test('the audit reads its directories from configuration', function () {
    config(['base-tenant.tenancy.audit.paths' => [dirname(__DIR__).'/Fixtures/Tenancy']]);

    $this->artisan('k2labs-base:tenancy-audit')->assertExitCode(1);

    config(['base-tenant.tenancy.audit.exempt' => ['Base\\Tenant\\Tests\\Fixtures\\Tenancy\\Unscoped\\']]);

    $this->artisan('k2labs-base:tenancy-audit')->assertExitCode(0);
});

test('the audit flags the trait on a table without the account column', function () {
    Schema::create('tenancy_orphans', function ($table): void {
        $table->id();
    });

    $directory = sys_get_temp_dir().'/tenancy-audit-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/Orphan.php', <<<'PHP'
        <?php

        namespace Base\Tenant\Tests\Generated;

        class Orphan extends \Illuminate\Database\Eloquent\Model
        {
            use \Base\Tenant\Traits\BelongsToAccount;

            protected $table = 'tenancy_orphans';
        }
        PHP);

    require_once $directory.'/Orphan.php';

    try {
        $this->artisan('k2labs-base:tenancy-audit', ['--path' => [$directory]])
            ->expectsOutputToContain('Orphan')
            ->assertExitCode(1);
    } finally {
        unlink($directory.'/Orphan.php');
        rmdir($directory);
    }
});
