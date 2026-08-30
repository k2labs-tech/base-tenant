<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Models\WaitlistSignup;
use Base\Tenant\Services\InvitationService;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Open the doors: turn the waiting list into accounts and invitations.
 *
 * Each person gets an empty account and an invitation into it, which is the
 * mechanism the product already has for "here is your way in". Inviting
 * without an account would produce an invitation into nothing.
 *
 * The flag itself lives in the environment, so this command reports what has
 * to change rather than editing it: a command that rewrites `.env` on a
 * deployed machine makes a change no deploy would reproduce.
 */
class PresaleOpenCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:presale-open
                            {--batch=50 : How many to invite in this run}
                            {--role=customer-admin : Role each invitee receives in their account}
                            {--dry-run : List who would be invited}';

    protected $description = 'Convert the waiting list into accounts and invitations';

    public function handle(): int
    {
        if (! Module::enabled(Module::PRESALE)) {
            $this->components->warn('Pre-sale is not on; there is nothing to open.');

            return self::SUCCESS;
        }

        $inviter = $this->inviter();

        if (! $inviter) {
            $this->components->error('No platform administrator to send the invitations from.');

            return self::FAILURE;
        }

        $role = Role::query()->where('key', (string) $this->option('role'))->first();

        if (! $role) {
            $this->components->error("There is no `{$this->option('role')}` role. Run k2labs-base:sync-roles first.");

            return self::FAILURE;
        }

        $waiting = WaitlistSignup::query()
            ->waiting()
            ->orderBy('created_at')
            ->limit(max(1, (int) $this->option('batch')))
            ->get();

        if ($waiting->isEmpty()) {
            $this->components->info('Nobody is waiting.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $waiting->each(fn (WaitlistSignup $signup) => $this->line("  <fg=gray>would invite</> {$signup->email}"));

            $this->newLine();
            $this->components->info("Would invite {$waiting->count()} people.");

            return self::SUCCESS;
        }

        $invited = 0;
        $failed = 0;

        foreach ($waiting as $signup) {
            try {
                DB::transaction(function () use ($signup, $role, $inviter): void {
                    $account = $this->accountFor($signup);

                    InvitationService::send($signup->email, $account->getKey(), $role->getKey(), $inviter);

                    // Stamped inside the transaction and only once the
                    // invitation exists, so a run that dies half way
                    // re-invites nobody and drops nobody.
                    $signup->forceFill(['invited_at' => now()])->save();
                });

                $invited++;
            } catch (Throwable $exception) {
                $failed++;

                $this->components->error("{$signup->email}: {$exception->getMessage()}");
            }
        }

        $this->components->info("Invited {$invited} people.".($failed > 0 ? " {$failed} failed and are still waiting." : ''));

        if ($remaining = WaitlistSignup::query()->waiting()->count()) {
            $this->components->warn("{$remaining} still waiting. Run it again for the next batch.");
        }

        $this->newLine();
        $this->line('  <fg=cyan>Set BASE_TENANT_PRESALE=false and deploy to reopen standard registration.</>');

        return self::SUCCESS;
    }

    /**
     * The empty account the invitation points at.
     */
    protected function accountFor(WaitlistSignup $signup): object
    {
        $model = config('base-tenant.models.account');

        return $model::create([
            'name' => $signup->name ?: Str::before($signup->email, '@'),
            'email' => $signup->email,
        ]);
    }

    /**
     * Invitations are sent by a person, and the mail says who. A platform
     * administrator is the only one who is not somebody else's customer.
     */
    protected function inviter(): ?User
    {
        $model = config('base-tenant.models.user', User::class);

        return $model::query()->where('is_admin', true)->orderBy('created_at')->first();
    }
}
