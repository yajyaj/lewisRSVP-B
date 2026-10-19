<?php

namespace App\Console\Commands;

use App\Models\AdminAuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapSuperAdmin extends Command
{
    protected $signature = 'rsvp:bootstrap-super-admin {identifier : Exact existing username or email}';

    protected $description = 'Promote one active existing organizer only when no active Super Admin exists';

    public function handle(): int
    {
        return DB::transaction(function (): int {
            $users = User::query()->orderBy('id')->lockForUpdate()->get();
            $matches = $users->filter(fn (User $user): bool => $user->email === $this->argument('identifier') || $user->username === $this->argument('identifier'));
            if ($matches->count() !== 1) {
                $this->error('Choose an unambiguous existing organizer. No account changed.');

                return self::FAILURE;
            }
            $user = $matches->first();
            if ($user->isSuperAdmin()) {
                $this->info('This account is already the active Super Admin. No changes made.');

                return self::SUCCESS;
            }
            if (! $user->canAccessAdmin() || $users->contains(fn (User $account): bool => $account->isSuperAdmin())) {
                $this->error('Bootstrap requires an active organizer and no existing active Super Admin.');

                return self::FAILURE;
            }
            $user->role = 'super_admin';
            $user->save();
            AdminAuditEvent::create(['actor_id' => $user->id, 'subject_id' => $user->id, 'action' => 'user.bootstrap_super_admin']);
            $this->info('Existing organizer promoted. Passwords and tokens were preserved.');

            return self::SUCCESS;
        });
    }
}
