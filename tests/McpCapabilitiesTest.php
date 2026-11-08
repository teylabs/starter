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
/** How a `test_*` name and a Pest description are compared: lowercased, punctuation stripped, runs of whitespace and underscores collapsed to one space. */
function mcpCapabilitiesTestName(string $name): string
{
    return trim((string) preg_replace('/[\s_]+/', ' ', (string) preg_replace('/[^\p{L}\p{N}\s_]+/u', '', mb_strtolower($name))));
}

/** Whether the source has a Pest `test()` or `it()` whose description, normalised, is the `test_*` name's (`test_a_b` is `it('a, b')`). */
function mcpCapabilitiesPestDeclares(string $source, string $symbol): bool
{
    if (! str_starts_with($symbol, 'test_')) {
        return false;
    }
    $wanted = mcpCapabilitiesTestName(substr($symbol, 5));
    preg_match_all('/\b(?:test|it)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', $source, $matches);
    foreach ($matches[2] as $index => $description) {
        // A single-quoted PHP string keeps `\n` as a backslash and an `n` (the backslash is then stripped as punctuation); a double-quoted one decodes escapes.
        if (mcpCapabilitiesTestName($matches[1][$index] === '"' ? stripcslashes($description) : $description) === $wanted) {
            return true;
        }
    }

    return false;
}

/** The file exists and declares the symbol: a function, class or const, or for a `test_*` name, the Pest test of that name (see mcpCapabilitiesPestDeclares). */
function mcpCapabilitiesAssertDeclares(string $path, string $symbol): void
{
    expect(base_path($path))->toBeFile();
    $source = file_get_contents(base_path($path));
    $declared = mcpCapabilitiesPestDeclares($source, $symbol) || preg_match('/\b(function|class|const)\s+'.preg_quote($symbol, '/').'\b/', $source) === 1;
    expect($declared)->toBeTrue($path.' declares no '.$symbol.'. A `test_*` name matches a Pest test()/it() whose description, lowercased with punctuation stripped and whitespace and underscores collapsed, is the name after `test_` (test_a_b matches it(\'a, b\')); any other name must be a function, class or const.');
}

it('matches a test_* name to a Pest description the same way on both sides', function (string $source, string $symbol, bool $declared) {
    expect(mcpCapabilitiesPestDeclares($source, $symbol))->toBe($declared);
})->with([
    'plain' => ["it('a b', fn () => null);", 'test_a_b', true],
    'a comma' => ["it('a, b', fn () => null);", 'test_a_b', true],
    'punctuation and case' => ["test(\"Lets an owner's save: go!\", fn () => null);", 'test_lets_an_owners_save_go', true],
    'extra spaces' => ["it('a   b', fn () => null);", 'test_a__b', true],
    'escaped quote' => ["it('an owner\\'s save', fn () => null);", 'test_an_owners_save', true],
    'a single-quoted backslash n' => ["it('a\\nb', fn () => null);", 'test_anb', true],
    'a double-quoted newline' => ['it("a\\nb", fn () => null);', 'test_a_b', true],
    'a different name' => ["it('a, c', fn () => null);", 'test_a_b', false],
    'a prefix only' => ["it('a b c', fn () => null);", 'test_a_b', false],
    'not a test name' => ["it('a b', fn () => null);", 'a_b', false],
]);
