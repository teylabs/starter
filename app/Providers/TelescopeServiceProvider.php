<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Tey\Kit\Features\Workspaces\Operators;

/**
 * Telescope, on from day one (cookbook rule 72). Only the app's operators (the kit's operators table, seeded with kit:operators:seed) open it: it shows
 * every workspace's requests, so workspace owners do not. Off production it records everything; in production only exceptions, failed requests and jobs,
 * scheduled tasks, slow queries (100 ms), warnings and up, and anything a monitored tag or TELESCOPE_LOG_EVERYTHING asks for. The cache's and Telescope's
 * own SQL is never recorded. telescope:prune keeps 48 hours (routes/console.php).
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();
        Telescope::filter(fn (IncomingEntry $entry): bool => self::keeps($entry, ! $this->app->isProduction() || (bool) config('telescope.log_everything')));
    }

    /** Whether Telescope stores an entry; everything is true where every entry is recorded, except the cache's and Telescope's own SQL. */
    public static function keeps(IncomingEntry $entry, bool $everything): bool
    {
        if ($entry->type === EntryType::QUERY && preg_match('/\b(?:from|into|update)\s+"?(?:cache|cache_locks|telescope_\w+)"?(?=[\s(;]|$)/i', (string) ($entry->content['sql'] ?? '')) === 1) {
            return false;
        }

        return $everything
            || $entry->isReportableException()
            || $entry->isFailedRequest()
            || ($entry->type === EntryType::REQUEST && ($entry->content['response_status'] ?? 200) >= 400)
            || $entry->isFailedJob()
            || $entry->isScheduledTask()
            || $entry->isSlowQuery()
            || $entry->type === EntryType::LOG
            || $entry->hasMonitoredTag();
    }

    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'current_password', 'token', 'code', 'recovery_code']);
        Telescope::hideRequestHeaders(['authorization', 'cookie', 'set-cookie', 'proxy-authorization', 'x-csrf-token', 'x-xsrf-token']);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', fn ($user = null): bool => Operators::allows($user));
    }
}
