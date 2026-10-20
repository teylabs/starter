<?php

namespace App\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\InviteTenant;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Local only: the first owner and their workspace, through Concierge's tenant invitation and the kit's acceptance,
 * so the app is provisioned exactly as in production. Refuses outside the local environment and once any user
 * exists. There is no default password: it is prompted for, or generated and shown once without --interaction.
 */
final class FirstOwnerCommand extends Command
{
    protected $signature = 'starter:first-owner {--name= : The owner\'s name} {--email= : The owner\'s email} {--workspace= : The workspace\'s name}';

    protected $description = 'Create the first local owner and workspace (local environment only)';

    public function handle(): int
    {
        if (! $this->laravel->environment('local')) {
            $this->error('starter:first-owner runs in the local environment only; nothing was created.');

            return self::FAILURE;
        }
        if (User::query()->exists()) {
            $this->info('Users already exist; nothing was created.');

            return self::SUCCESS;
        }
        $interactive = $this->input->isInteractive();
        $name = $this->option('name') ?: ($interactive ? text('Owner name', default: 'Local owner', required: true) : 'Local owner');
        $email = $this->option('email') ?: ($interactive ? text('Owner email', default: 'owner@example.com', required: true) : 'owner@example.com');
        $workspaceName = $this->option('workspace') ?: ($interactive ? text('Workspace name', default: 'Local workspace', required: true) : 'Local workspace');
        $generated = $interactive ? null : Str::password(24);
        $secret = $generated ?? password('Owner password', required: true, validate: fn (string $value): ?string => strlen($value) < 12 ? 'Use at least 12 characters.' : null);

        if ($this->call('concierge:sync-roles', ['--no-interaction' => true]) !== self::SUCCESS || $this->call('kit:permissions:sync', ['--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        $workspace = DB::transaction(function () use ($name, $email, $secret, $workspaceName): int {
            $owner = User::create(['name' => $name, 'email' => $email, 'email_verified_at' => now(), 'password' => Hash::make($secret)]);
            // The local owner operates their own install; tenant invitations need an operator.
            DB::table('operators')->insert(['actor_id' => $owner->id]);
            $invitation = InviteTenant::run($owner, $owner->email);

            $workspace = app(AcceptWorkspaceInvitation::class)->handle(new Principal((int) $owner->id), $invitation->token)['workspace_id'];
            // Provisioning names the workspace after its owner; give it its own name so it never reads as the person.
            Workspace::query()->whereKey($workspace)->update(['name' => $workspaceName]);

            return $workspace;
        });

        $this->info('Created '.$email.' as the owner of '.$workspaceName.' (workspace '.$workspace.').');
        if ($generated !== null) {
            $this->line('Sign in with this password; it is shown once and not stored: '.$generated);
        }

        return self::SUCCESS;
    }
}
