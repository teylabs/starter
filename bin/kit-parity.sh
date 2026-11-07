#!/bin/sh
# Asserts the template's feature files equal `kit:add` of its adopted features on a clean Laravel app.
# Usage: sh bin/kit-parity.sh <clean-app>, where <clean-app> is a fresh `laravel/laravel` checkout with its
# dependencies installed. It adds the template's locked tey/kit and the packages the features need, runs
# kit:install and kit:add, then compares every path a feature owns in both directions.
set -eu
template=$(cd "$(dirname "$0")/.." && pwd)
app=$(cd "$1" && pwd)
locked() { php -r '$l = json_decode(file_get_contents($argv[1]), true); foreach ($l["packages"] as $p) { if ($p["name"] === $argv[2]) { $v = ltrim($p["version"], "v"); echo str_starts_with($v, "dev-") && isset($p["source"]["reference"]) ? $v."#".$p["source"]["reference"] : $v; exit; } } exit(1);' "$template/composer.lock" "$1"; }

cd "$app"
packages=""
for package in tey/kit tey/concierge inertiajs/inertia-laravel laravel/fortify laravel/mcp laravel/passport laravel/pennant lorisleiva/laravel-actions spatie/laravel-data spatie/laravel-multitenancy spatie/laravel-settings; do
    packages="$packages $package:$(locked "$package")"
done
# shellcheck disable=SC2086
composer require $packages --no-interaction --no-progress
php artisan kit:install
# The workspaces feature's host step (kit 0.1.3+): the user model implements Concierge's Member through the kit's trait,
# as the template's does. kit:add refuses until it is met, and never writes the model itself.
php -r '$f = "app/Models/User.php"; $s = file_get_contents($f); $n = preg_replace("/^class User extends Authenticatable\n\{\n/m", "class User extends Authenticatable implements \\Tey\\Concierge\\Contracts\\Member\n{\n    use \\Tey\\Kit\\Features\\Workspaces\\Concerns\\HasWorkspaces;\n", $s, 1, $count); if ($count !== 1) { fwrite(STDERR, "parity: could not apply the user model host step\n"); exit(1); } file_put_contents($f, $n);'
# core's host_ui step (kit 0.7+): the kit's components import the host's shadcn-vue primitives. They are the host's own
# files, never a feature's, so the template's copies stand in for `npx shadcn-vue add`.
mkdir -p resources/js/components
rm -rf resources/js/components/ui
cp -R "$template/resources/js/components/ui" resources/js/components/ui
touch database/database.sqlite
php artisan migrate --force --path=database/migrations
# The template's adopted features (config/kit.php `features`), not the whole catalog: an opt-in feature the template
# does not ship (finalize, deliver) is left out.
features=$(php -r '$c = include $argv[1]; echo implode(" ", $c["features"]);' "$template/config/kit.php")
# shellcheck disable=SC2086
plan=$(php artisan kit:add $features --json | php -r 'echo json_decode(stream_get_contents(STDIN), true)["plan"];')
# shellcheck disable=SC2086
php artisan kit:add $features --apply --expected-plan="$plan"

# A file the template marks `kit: tweaked <reason>` in its first lines (`// kit: tweaked …` in a script, `<!-- kit: tweaked … -->` in a template) is a
# deliberate app tweak waiting to graduate into the kit: listed, never a failure. Any other difference, and any file only on one side, fails.
tweaked() { head -n 5 "$1" 2>/dev/null | grep -q 'kit: tweaked'; }
reason() { head -n 5 "$1" | grep -o 'kit: tweaked.*' | head -n 1 | sed -e 's/^kit: tweaked[[:space:]]*//' -e 's/[[:space:]]*-->[[:space:]]*$//'; }
status=0
tweaks=0
for owned in $(php artisan kit:list --json | php -r '$want = explode(" ", $argv[1]); foreach (json_decode(stream_get_contents(STDIN), true) as $f) { if (in_array($f["id"], $want, true)) { echo implode("\n", $f["owns"]), "\n"; } }' "$features"); do
    differences=$(diff -rq "$app/$owned" "$template/$owned" || true)
    [ -z "$differences" ] && continue
    while IFS= read -r line; do
        case "$line" in
            "Files "*" differ")
                file=${line#* and }
                file=${file% differ}
                if tweaked "$file"; then
                    echo "parity: tweaked, a graduation candidate: ${file#"$template"/} ($(reason "$file"))"
                    tweaks=$((tweaks + 1))
                    continue
                fi
                echo "parity: ${file#"$template"/} differs from kit:add (mark a deliberate tweak with \`// kit: tweaked <reason>\`)" >&2
                ;;
            *)
                echo "parity: $line" >&2
                ;;
        esac
        status=1
    done <<EOF
$differences
EOF
done
[ "$status" -eq 0 ] && echo "parity: the template's feature files equal kit:add $features ($tweaks marked tweaks listed above)"
exit "$status"
