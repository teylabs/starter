<?php

use App\Support\McpCapabilities;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Tey\Kit\Features\Core\Registry;

test('the committed declaration matches the served tools', function () {
    expect(file_get_contents(base_path(McpCapabilities::PATH)))->toBe(McpCapabilities::json(), 'Run php bin/mcp-capabilities.php');
});
test('the declaration validates against the vendored schema', function () {
    expect(mcpCapabilitiesSchemaErrors(json_decode(file_get_contents(base_path(McpCapabilities::PATH)))))->toBe([]);

    // The validator refuses what the schema refuses.
    $this->assertNotSame([], mcpCapabilitiesSchemaErrors(json_decode('{"version":1,"inventory":[{"path":"/workspaces","capabilities":[],"variants":["inertia"]}],"capabilities":[]}')));
});
test('every path symbol and capability exists', function () {
    $document = json_decode(file_get_contents(base_path(McpCapabilities::PATH)), true, flags: JSON_THROW_ON_ERROR);
    $ids = array_column($document['capabilities'], 'id');
    expect(array_unique($ids))->toBe($ids);
    foreach ($document['inventory'] as $entry) {
        expect(base_path($entry['path']))->toBeFile();
        expect(array_diff($entry['capabilities'], $ids))->toBe([], $entry['path']);
    }
    $served = array_map(fn (string $class): string => app($class)->name(), app(Registry::class)->mcpTools());
    foreach ($document['capabilities'] as $capability) {
        foreach ([...$capability['ui'], $capability['domain'], $capability['mcp']['entrypoint']] as $reference) {
            mcpCapabilitiesAssertDeclares($reference['path'], $reference['symbol']);
        }
        expect($served)->toContain($capability['mcp']['name']);
        foreach ($capability['mcp']['scopes'] as $scope) {
            expect($document['server']['scopes'])->toHaveKey($scope);
        }
        foreach ([...$capability['tests'], ...array_merge(...array_column($capability['variants'], 'tests'))] as $test) {
            mcpCapabilitiesAssertDeclares($test['path'], $test['name']);
        }
    }
    expect(array_column(array_column($document['capabilities'], 'mcp'), 'name'))->toEqualCanonicalizing($served);
    expect(array_diff($ids, array_merge(...array_column($document['inventory'], 'capabilities'))))->toBe([], 'Every capability is reachable from a UI file');
    mcpCapabilitiesAssertDeclares($document['server']['entrypoint']['path'], $document['server']['entrypoint']['symbol']);
});
test('the declaration is semantically consistent', function () {
    $document = json_decode(file_get_contents(base_path(McpCapabilities::PATH)), true, flags: JSON_THROW_ON_ERROR);
    $variants = [];
    foreach ($document['capabilities'] as $capability) {
        $variants[$capability['id']] = array_column($capability['variants'], 'id');
        foreach ($capability['variants'] as $variant) {
            // The UI and MCP take the same canonical inputs, with the same defaults and effects.
            foreach (['options', 'defaults', 'effects'] as $key) {
                expect(mcpCapabilitiesNormalize($variant['mcp'][$key]))->toBe(mcpCapabilitiesNormalize($variant['ui'][$key]), $capability['id'].' '.$variant['id'].' '.$key);
            }
            $covers = array_merge(...array_column($variant['tests'], 'covers'));
            foreach (['success', 'error', 'recovery'] as $required) {
                expect($covers)->toContain($required);
            }
        }
    }

    // Every UI reference is an inventory entry that lists the capability it serves.
    $inventory = array_column($document['inventory'], 'capabilities', 'path');
    foreach ($document['capabilities'] as $capability) {
        foreach ($capability['ui'] as $reference) {
            expect($inventory)->toHaveKey($reference['path']);
            expect($inventory[$reference['path']])->toContain($capability['id']);
        }
    }
    foreach ($document['inventory'] as $entry) {
        // Variants map each capability of the entry to that capability's variant ids.
        expect(array_keys($entry['variants']))->toEqualCanonicalizing($entry['capabilities'], $entry['path']);
        foreach ($entry['variants'] as $id => $ids) {
            expect(array_diff($ids, $variants[$id] ?? []))->toBe([], $entry['path'].' '.$id);
        }
    }
});
function mcpCapabilitiesNormalize(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    if (! array_is_list($value)) {
        ksort($value);
    }

    return array_map(fn (mixed $item): mixed => mcpCapabilitiesNormalize($item), $value);
}
test('the vendored schema accepts a minimal uiOnly capability and refuses one without a reason or routes', function () {
    $with = function (array $entry): mixed {
        // Decoded as objects, so the declaration's empty {} maps stay objects.
        $document = json_decode(file_get_contents(base_path(McpCapabilities::PATH)), flags: JSON_THROW_ON_ERROR);
        $document->capabilities[] = (object) $entry;

        return $document;
    };

    expect(mcpCapabilitiesSchemaErrors($with(['id' => 'webhooks.inbound', 'uiOnly' => 'a provider callback, not a user operation', 'routes' => ['POST /webhooks/inbound']])))->toBe([]);
    $this->assertNotSame([], mcpCapabilitiesSchemaErrors($with(['id' => 'webhooks.inbound', 'uiOnly' => ' ', 'routes' => ['POST /webhooks/inbound']])));
    $this->assertNotSame([], mcpCapabilitiesSchemaErrors($with(['id' => 'webhooks.inbound', 'uiOnly' => 'a provider callback', 'routes' => []])));
    $this->assertNotSame([], mcpCapabilitiesSchemaErrors($with(['id' => 'webhooks.inbound', 'kind' => 'write', 'routes' => ['POST /webhooks/inbound']])));
});
/** @return array<string, mixed> */
function mcpCapabilitiesSchemaErrors(mixed $data): array
{
    $validator = new Validator;
    $validator->setMaxErrors(20);
    $result = $validator->validate($data, file_get_contents(base_path(McpCapabilities::SCHEMA)));

    return $result->isValid() ? [] : (new ErrorFormatter)->format($result->error());
}
/** The file exists and declares the symbol: a function, class or const, or for a `test_*` name, the Pest test of that name (`test_a_b` is `test('a b')`). */
function mcpCapabilitiesAssertDeclares(string $path, string $symbol): void
{
    expect(base_path($path))->toBeFile();
    $source = file_get_contents(base_path($path));
    $pest = str_starts_with($symbol, 'test_') && preg_match('/\b(test|it)\(\s*[\'"]'.preg_quote(str_replace('_', ' ', substr($symbol, 5)), '/').'[\'"]/', $source) === 1;
    expect($pest || preg_match('/\b(function|class|const)\s+'.preg_quote($symbol, '/').'\b/', $source) === 1)->toBeTrue($path.' declares '.$symbol);
}
