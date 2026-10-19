<?php

namespace App\Operations;

use App\Data\SettingsView;
use App\Models\Workspace;
use App\Settings\WorkspaceSettings;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Principal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

final class WorkspaceProfile
{
    use AsAction;

    public function read(Principal $principal): SettingsView
    {
        $workspace = app(Access::class)->authorize($principal, 'settings.read');
        $settings = WorkspaceSettings::current();

        return new SettingsView($workspace->id, $settings->display_name, $settings->timezone, [['label' => 'Workspace settings', 'href' => '/workspaces/'.$workspace->id.'/settings']], (int) $workspace->fresh()->settings_version);
    }

    public function update(Principal $principal, array $input): SettingsView
    {
        if (array_diff(array_keys($input), ['display_name', 'timezone', 'expected_version', 'intent_key']) !== []) {
            throw new \RuntimeException('unknown_arguments');
        }
        $data = Validator::make($input, ['display_name' => ['required', 'string', 'max:120'], 'timezone' => ['required', 'timezone'], 'expected_version' => ['required', 'integer', 'min:1'], 'intent_key' => ['required', 'string', 'max:120']])->validate();
        $fingerprint = hash('sha256', json_encode([$data['display_name'], $data['timezone'], (int) $data['expected_version']], JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($principal, $data, $fingerprint): SettingsView {
                $workspace = app(Access::class)->authorize($principal, 'settings.write');
                $locked = Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
                $previous = DB::table('settings_intents')->where('workspace_id', $workspace->id)->where('actor_id', $principal->actorId)->where('intent_key', $data['intent_key'])->first();
                if ($previous) {
                    if ($previous->fingerprint !== $fingerprint) {
                        throw new \RuntimeException('intent_conflict');
                    }

                    return SettingsView::from(json_decode($previous->result, true, flags: JSON_THROW_ON_ERROR));
                }
                if ((int) $locked->settings_version !== (int) $data['expected_version']) {
                    throw new \RuntimeException('stale_settings');
                }
                $settings = WorkspaceSettings::current();
                $settings->display_name = $data['display_name'];
                $settings->timezone = $data['timezone'];
                $settings->save();
                $locked->increment('settings_version');
                app(Audit::class)->write($principal->actorId, $workspace->id, 'workspace.settings.updated', ['fields' => ['display_name', 'timezone']]);
                $result = $this->read($principal);
                DB::table('settings_intents')->insert(['workspace_id' => $workspace->id, 'actor_id' => $principal->actorId, 'intent_key' => $data['intent_key'], 'fingerprint' => $fingerprint, 'result' => $result->toJson()]);

                return $result;
            });
        } catch (\Throwable $exception) {
            // Settings publishes cache values before the enclosing transaction commits.
            // Refresh from the rolled-back DB and republish using the package API.
            $settings = new WorkspaceSettings;
            $settings->refresh();
            app(SettingsCacheFactory::class)->build()->put($settings);
            throw $exception;
        }
    }
}
