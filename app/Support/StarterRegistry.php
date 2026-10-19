<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use RuntimeException;

/** What each starter feature contributes to the shared seams: routes, MCP tools, navigation, token scopes, capabilities and error codes. */
final class StarterRegistry
{
    /** @var list<array{file: string, group: ?string}> */
    private array $routes = [];

    /** @var list<class-string> */
    private array $tools = [];

    /** @var list<array{ability: string, label: string, suffix: string}> */
    private array $navigation = [];

    /** @var array<string, string> */
    private array $scopes = [];

    /** @var list<string> */
    private array $capabilities = [];

    /** @var array<string, int> */
    private array $statuses = [];

    /** @var array<string, string> */
    private array $messages = [];

    /** A route file, loaded in registration order after the host's own routes; `web` puts it in the web middleware group. */
    public function routes(string $file, ?string $group = 'web'): self
    {
        $this->routes[] = ['file' => $file, 'group' => $group];

        return $this;
    }

    /** @param list<class-string> $tools */
    public function tools(array $tools): self
    {
        array_push($this->tools, ...$tools);

        return $this;
    }

    /** A workspace navigation link, shown when the principal holds the ability. */
    public function navigation(string $ability, string $label, string $suffix): self
    {
        $this->navigation[] = ['ability' => $ability, 'label' => $label, 'suffix' => $suffix];

        return $this;
    }

    /** @param array<string, string> $scopes */
    public function scopes(array $scopes): self
    {
        $this->scopes = [...$this->scopes, ...$scopes];

        return $this;
    }

    /** @param list<string> $capabilities */
    public function capabilities(array $capabilities): self
    {
        array_push($this->capabilities, ...$capabilities);

        return $this;
    }

    /**
     * @param  array<string, int>  $statuses  error code => HTTP status
     * @param  array<string, string>  $messages  error code => page message
     */
    public function errors(array $statuses, array $messages = []): self
    {
        foreach ($statuses as $code => $status) {
            if (isset($this->statuses[$code]) && $this->statuses[$code] !== $status) {
                throw new RuntimeException('Conflicting status for error code: '.$code);
            }
            $this->statuses[$code] = $status;
        }
        $this->messages = [...$this->messages, ...$messages];

        return $this;
    }

    public function loadRoutes(): void
    {
        foreach ($this->routes as $route) {
            $route['group'] === null ? require $route['file'] : Route::middleware($route['group'])->group($route['file']);
        }
    }

    /** @return list<class-string> */
    public function mcpTools(): array
    {
        return $this->tools;
    }

    /** @return list<array{ability: string, label: string, suffix: string}> */
    public function navigationLinks(): array
    {
        return $this->navigation;
    }

    /** @return array<string, string> */
    public function tokenScopes(): array
    {
        return $this->scopes;
    }

    /** @return list<string> */
    public function workspaceCapabilities(): array
    {
        return $this->capabilities;
    }

    public function status(string $code): ?int
    {
        return $this->statuses[$code] ?? null;
    }

    public function message(string $code): ?string
    {
        return $this->messages[$code] ?? null;
    }
}
