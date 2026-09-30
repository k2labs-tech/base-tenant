<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Tenancy\Exceptions\CrossAccountWriteException;
use Base\Tenant\Tenancy\Exceptions\MissingTenantException;
use Base\Tenant\Tenancy\Exceptions\UnscopableQueryException;
use Base\Tenant\Tenancy\TenantBelongsToMany;
use Base\Tenant\Tenancy\TenantBuilder;
use Base\Tenant\Tests\Fixtures\Tenancy\Scoped\Gadget;
use Base\Tenant\Tests\Fixtures\Tenancy\Scoped\Tag;
use Base\Tenant\Tests\Fixtures\Tenancy\Scoped\Widget;
use Base\Tenant\Tests\Fixtures\Tenancy\StrictTenancyFixtures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    StrictTenancyFixtures::createTables();

    $this->mine = $this->createAccount();
    $this->theirs = $this->createAccount();

    $this->widgetFor = fn ($account, string $code, array $extra = []): Widget => Tenant::runFor(
        $account,
        fn (): Widget => Widget::create(['code' => $code, ...$extra]),
    );
});

/*
 * With the defaults, 3.0 behaviour is untouched.
 */

test('by default the model keeps the Eloquent builder and relation', function () {
    $widget = ($this->widgetFor)($this->mine, 'a');

    expect(Widget::query())->toBeInstanceOf(Builder::class)
        ->not->toBeInstanceOf(TenantBuilder::class)
        ->and($widget->tags())->toBeInstanceOf(BelongsToMany::class)
        ->not->toBeInstanceOf(TenantBelongsToMany::class);
});

test('by default forceDelete still reaches every account, as in 3.0', function () {
    ($this->widgetFor)($this->mine, 'a')->delete();
    ($this->widgetFor)($this->theirs, 'b')->delete();

    Tenant::runFor($this->mine, fn () => Widget::onlyTrashed()->forceDelete());

    expect(DB::table('tenancy_widgets')->count())->toBe(0);
});

test('by default the trait does not guard writes', function () {
    $widget = ($this->widgetFor)($this->theirs, 'b');

    Tenant::runFor($this->mine, function () use ($widget): void {
        $widget->update(['account_id' => $this->mine->getKey()]);
    });

    expect($widget->fresh()->account_id)->toBe($this->mine->getKey());
});

test('by default a record created with no account is stamped null', function () {
    config(['base-tenant.tenancy.on_missing_tenant' => 'allow']);

    expect(Widget::create(['code' => 'x'])->account_id)->toBeNull();
});

/*
 * on_missing_tenant = throw
 */

test('throw mode fails loudly on a query with no account in context', function () {
    config(['base-tenant.tenancy.on_missing_tenant' => 'throw']);

    Widget::query()->count();
})->throws(MissingTenantException::class, Widget::class);

test('throw mode lets explicit bypasses through, unfiltered', function () {
    ($this->widgetFor)($this->mine, 'a');
    ($this->widgetFor)($this->theirs, 'b');

    config(['base-tenant.tenancy.on_missing_tenant' => 'throw']);

    expect(Tenant::runWithout(fn (): int => Widget::count()))->toBe(2)
        ->and(Widget::query()->acrossAccounts()->count())->toBe(2)
        ->and(Tenant::runFor($this->mine, fn (): int => Widget::count()))->toBe(1);
});

test('throw mode still filters by the account in context', function () {
    ($this->widgetFor)($this->mine, 'a');
    ($this->widgetFor)($this->theirs, 'b');

    config(['base-tenant.tenancy.on_missing_tenant' => 'throw']);

    $codes = Tenant::runFor($this->mine, fn () => Widget::pluck('code')->all());

    expect($codes)->toBe(['a']);
});

test('deny mode keeps returning nothing silently', function () {
    ($this->widgetFor)($this->mine, 'a');

    config(['base-tenant.tenancy.on_missing_tenant' => 'deny']);

    expect(Widget::count())->toBe(0);
});

/*
 * Strict builder
 */

describe('strict builder', function () {
    beforeEach(function () {
        config(['base-tenant.tenancy.strict' => true]);
    });

    test('is handed out by the trait', function () {
        expect(Widget::query())->toBeInstanceOf(TenantBuilder::class);
    });

    test('forceDelete only purges the account in context', function () {
        ($this->widgetFor)($this->mine, 'a')->delete();
        ($this->widgetFor)($this->theirs, 'b')->delete();

        $deleted = Tenant::runFor($this->mine, fn () => Widget::onlyTrashed()->forceDelete());

        expect($deleted)->toBe(1)
            ->and(DB::table('tenancy_widgets')->pluck('code')->all())->toBe(['b']);
    });

    test('forceDelete still removes trashed rows, not only live ones', function () {
        ($this->widgetFor)($this->mine, 'a')->delete();
        ($this->widgetFor)($this->mine, 'b');

        Tenant::runFor($this->mine, fn () => Widget::query()->forceDelete());

        expect(DB::table('tenancy_widgets')->count())->toBe(0);
    });

    test('forceDelete with no account throws in throw mode', function () {
        config(['base-tenant.tenancy.on_missing_tenant' => 'throw']);

        Widget::query()->forceDelete();
    })->throws(MissingTenantException::class);

    test('forceDelete across accounts is allowed when asked for in writing', function () {
        ($this->widgetFor)($this->mine, 'a');
        ($this->widgetFor)($this->theirs, 'b');

        Tenant::runFor($this->mine, fn () => Widget::query()->acrossAccounts()->forceDelete());

        expect(DB::table('tenancy_widgets')->count())->toBe(0);
    });

    test('truncate is refused while the scope is active', function () {
        Tenant::runFor($this->mine, fn () => Widget::query()->truncate());
    })->throws(UnscopableQueryException::class, 'truncate');

    test('truncate is allowed inside runWithout', function () {
        ($this->widgetFor)($this->theirs, 'b');

        Tenant::runWithout(fn () => Widget::query()->truncate());

        expect(DB::table('tenancy_widgets')->count())->toBe(0);
    });

    test('updateOrInsert is refused while the scope is active', function () {
        ($this->widgetFor)($this->theirs, 'b');

        try {
            Tenant::runFor($this->mine, fn () => Widget::query()->updateOrInsert(['code' => 'b'], ['name' => 'hijacked']));
            $this->fail('updateOrInsert was not refused.');
        } catch (UnscopableQueryException) {
            // expected
        }

        expect(DB::table('tenancy_widgets')->value('name'))->toBeNull();
    });

    test('upsert requires the account column in the conflict target', function () {
        Tenant::runFor($this->mine, fn () => Widget::query()->upsert([['code' => 'a', 'name' => 'x']], ['code'], ['name']));
    })->throws(UnscopableQueryException::class, 'account_id');

    test('upsert stamps the account in context on rows that lack it', function () {
        ($this->widgetFor)($this->theirs, 'a', ['name' => 'theirs']);

        Tenant::runFor($this->mine, fn () => Widget::query()->upsert(
            [['code' => 'a', 'name' => 'mine']],
            ['account_id', 'code'],
            ['name'],
        ));

        expect(DB::table('tenancy_widgets')->where('account_id', $this->theirs->getKey())->value('name'))->toBe('theirs')
            ->and(DB::table('tenancy_widgets')->where('account_id', $this->mine->getKey())->value('name'))->toBe('mine');
    });

    test('upsert refuses a row that names another account', function () {
        Tenant::runFor($this->mine, fn () => Widget::query()->upsert(
            [['code' => 'a', 'name' => 'x', 'account_id' => $this->theirs->getKey()]],
            ['account_id', 'code'],
            ['name'],
        ));
    })->throws(CrossAccountWriteException::class);

    test('upsert with no account throws where unscoped access is not allowed', function () {
        config(['base-tenant.tenancy.on_missing_tenant' => 'deny']);

        Widget::query()->upsert([['code' => 'a']], ['account_id', 'code'], ['code']);
    })->throws(MissingTenantException::class);

    test('an inner join carries the account in its ON clause', function () {
        $mine = ($this->widgetFor)($this->mine, 'a');
        $theirs = ($this->widgetFor)($this->theirs, 'b');

        // A child of their account pointing at my widget: a forged row.
        Tenant::runFor($this->theirs, fn () => Gadget::create(['widget_id' => $mine->id, 'name' => 'forged']));
        Tenant::runFor($this->mine, fn () => Gadget::create(['widget_id' => $mine->id, 'name' => 'real']));

        $names = Tenant::runFor($this->mine, fn () => Widget::query()
            ->join('tenancy_gadgets', 'tenancy_gadgets.widget_id', '=', 'tenancy_widgets.id')
            ->pluck('tenancy_gadgets.name')
            ->all());

        expect($names)->toBe(['real']);
    });

    test('a left join stays a left join and does not leak foreign children', function () {
        $mine = ($this->widgetFor)($this->mine, 'a');

        Tenant::runFor($this->theirs, fn () => Gadget::create(['widget_id' => $mine->id, 'name' => 'forged']));

        $rows = Tenant::runFor($this->mine, fn () => Widget::query()
            ->leftJoin('tenancy_gadgets as g', 'g.widget_id', '=', 'tenancy_widgets.id')
            ->where('tenancy_widgets.code', 'a')
            ->get(['tenancy_widgets.code', 'g.name'])
            ->map(fn ($row): array => [$row->code, $row->name])
            ->all());

        expect($rows)->toBe([['a', null]]);
    });

    test('join bindings stay in order when the join has its own bindings', function () {
        $mine = ($this->widgetFor)($this->mine, 'a');

        Tenant::runFor($this->mine, fn () => Gadget::create(['widget_id' => $mine->id, 'name' => 'real']));
        Tenant::runFor($this->theirs, fn () => Gadget::create(['widget_id' => $mine->id, 'name' => 'forged']));

        $names = Tenant::runFor($this->mine, fn () => Widget::query()
            ->join('tenancy_gadgets', function ($join): void {
                $join->on('tenancy_gadgets.widget_id', '=', 'tenancy_widgets.id')
                    ->where('tenancy_gadgets.name', '!=', 'nothing');
            })
            ->where('tenancy_widgets.code', 'a')
            ->pluck('tenancy_gadgets.name')
            ->all());

        expect($names)->toBe(['real']);
    });

    test('a join inside acrossAccounts is not constrained', function () {
        $mine = ($this->widgetFor)($this->mine, 'a');

        Tenant::runFor($this->theirs, fn () => Gadget::create(['widget_id' => $mine->id, 'name' => 'forged']));

        $names = Tenant::runFor($this->mine, fn () => Widget::query()
            ->acrossAccounts()
            ->join('tenancy_gadgets', 'tenancy_gadgets.widget_id', '=', 'tenancy_widgets.id')
            ->pluck('tenancy_gadgets.name')
            ->all());

        expect($names)->toBe(['forged']);
    });

    test('a join against an exempt table is left alone', function () {
        $user = $this->createUser($this->theirs);
        ($this->widgetFor)($this->mine, 'a', ['name' => $user->email]);

        $emails = Tenant::runFor($this->mine, fn () => Widget::query()
            ->join('users', 'users.email', '=', 'tenancy_widgets.name')
            ->pluck('users.email')
            ->all());

        expect($emails)->toBe([$user->email]);
    });
});

/*
 * Pivots
 */

describe('strict pivots', function () {
    beforeEach(function () {
        config(['base-tenant.tenancy.strict' => true]);
    });

    test('use the tenant-aware relation', function () {
        $widget = ($this->widgetFor)($this->mine, 'a');

        expect($widget->tags())->toBeInstanceOf(TenantBelongsToMany::class);
    });

    test('attach writes the parent account, over a forged one', function () {
        $widget = ($this->widgetFor)($this->mine, 'a');
        $tag = Tenant::runFor($this->mine, fn (): Tag => Tag::create(['name' => 't']));

        Tenant::runFor($this->mine, fn () => $widget->tags()->attach([
            $tag->id => ['account_id' => $this->theirs->getKey()],
        ]));

        expect(DB::table('tenancy_tag_widget')->value('account_id'))->toBe($this->mine->getKey());
    });

    test('attach on another account\'s parent is refused', function () {
        $widget = ($this->widgetFor)($this->theirs, 'b');

        Tenant::runFor($this->mine, fn () => $widget->tags()->attach(1));
    })->throws(CrossAccountWriteException::class);

    test('attach with no account is refused where unscoped access is not allowed', function () {
        $widget = ($this->widgetFor)($this->mine, 'a');

        config(['base-tenant.tenancy.on_missing_tenant' => 'deny']);

        $widget->tags()->attach(1);
    })->throws(MissingTenantException::class);

    test('detach and reads only see pivot rows of the parent\'s account', function () {
        $widget = ($this->widgetFor)($this->mine, 'a');
        $tag = Tenant::runFor($this->mine, fn (): Tag => Tag::create(['name' => 'mine']));

        Tenant::runFor($this->mine, fn () => $widget->tags()->attach($tag->id));

        // A row forged straight into the pivot, naming the other account.
        DB::table('tenancy_tag_widget')->insert([
            'account_id' => $this->theirs->getKey(),
            'widget_id' => $widget->id,
            'tag_id' => $tag->id,
        ]);

        Tenant::runFor($this->mine, function () use ($widget): void {
            expect($widget->tags()->count())->toBe(1)
                ->and(Widget::with('tags')->find($widget->id)->tags)->toHaveCount(1);

            $widget->tags()->detach();
        });

        expect(DB::table('tenancy_tag_widget')->pluck('account_id')->all())->toBe([$this->theirs->getKey()]);
    });

    test('sync keeps the pivot account', function () {
        $widget = ($this->widgetFor)($this->mine, 'a');
        [$first, $second] = Tenant::runFor($this->mine, fn (): array => [
            Tag::create(['name' => 'one']),
            Tag::create(['name' => 'two']),
        ]);

        Tenant::runFor($this->mine, fn () => $widget->tags()->sync([$first->id, $second->id]));

        expect(DB::table('tenancy_tag_widget')->where('account_id', $this->mine->getKey())->count())->toBe(2);
    });
});

/*
 * Integrity of the trait
 */

describe('strict integrity', function () {
    beforeEach(function () {
        config(['base-tenant.tenancy.strict' => true]);
    });

    test('a record cannot move to another account', function () {
        $widget = ($this->widgetFor)($this->mine, 'a');

        Tenant::runFor($this->mine, fn () => $widget->update(['account_id' => $this->theirs->getKey()]));
    })->throws(CrossAccountWriteException::class, 'cannot change');

    test('a record of another account cannot be updated', function () {
        $widget = ($this->widgetFor)($this->theirs, 'b');

        Tenant::runFor($this->mine, fn () => $widget->update(['name' => 'hijacked']));
    })->throws(CrossAccountWriteException::class, 'update');

    test('a record of another account cannot be deleted', function () {
        $widget = ($this->widgetFor)($this->theirs, 'b');

        Tenant::runFor($this->mine, fn () => $widget->delete());
    })->throws(CrossAccountWriteException::class, 'delete');

    test('a record of another account cannot be restored', function () {
        $widget = ($this->widgetFor)($this->theirs, 'b');
        Tenant::runFor($this->theirs, fn () => $widget->delete());

        Tenant::runFor($this->mine, fn () => $widget->restore());
    })->throws(CrossAccountWriteException::class, 'restore');

    test('the account in context can update, delete and restore its own records', function () {
        $widget = ($this->widgetFor)($this->mine, 'a');

        Tenant::runFor($this->mine, function () use ($widget): void {
            $widget->update(['name' => 'renamed']);
            $widget->delete();
            $widget->restore();
        });

        expect($widget->fresh()->name)->toBe('renamed')
            ->and($widget->fresh()->trashed())->toBeFalse();
    });

    test('runWithout lets a deliberate cross-account write through', function () {
        $widget = ($this->widgetFor)($this->theirs, 'b');

        Tenant::runFor($this->mine, fn () => Tenant::runWithout(fn () => $widget->update(['name' => 'staff'])));

        expect($widget->fresh()->name)->toBe('staff');
    });

    test('with no account, writes follow the read rule of on_missing_tenant', function () {
        $widget = ($this->widgetFor)($this->theirs, 'b');

        // auto + console: reads are unfiltered, so writes are allowed.
        $widget->update(['name' => 'console']);

        config(['base-tenant.tenancy.on_missing_tenant' => 'throw']);

        expect(fn () => $widget->update(['name' => 'nope']))->toThrow(MissingTenantException::class)
            ->and($widget->fresh()->name)->toBe('console');
    });

    test('creating with no account throws instead of stamping null', function () {
        Widget::create(['code' => 'orphan']);
    })->throws(MissingTenantException::class, Widget::class);

    test('creating with an explicit account needs no context', function () {
        $widget = Widget::create(['code' => 'x', 'account_id' => $this->mine->getKey()]);

        expect($widget->account_id)->toBe($this->mine->getKey());
    });

    test('a model can allow account-less records', function () {
        $model = new class extends Widget
        {
            public function allowsAccountlessRecords(): bool
            {
                return true;
            }
        };

        $model->fill(['code' => 'platform'])->save();

        expect($model->account_id)->toBeNull();
    });
});
