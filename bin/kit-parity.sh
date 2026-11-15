#!/bin/sh
# Asserts the template's kit files equal what `kit:scaffold` writes for its adopted features, from the locked tey/kit.
# Usage: sh bin/kit-parity.sh, in the template's checkout with its dependencies installed. Since kit 0.18 an app owns its kit pages:
# `kit:scaffold <feature>` writes them once, and `--diff` compares each with the kit's current stub, writing nothing.
set -eu
template=$(cd "$(dirname "$0")/.." && pwd)
cd "$template"

# A file the template marks `kit: tweaked <reason>` in its first lines (`// kit: tweaked …` in a script, `<!-- kit: tweaked … -->` in a template) is a
# deliberate app tweak waiting to graduate into the kit: listed, never a failure. Any other difference, and any file the template lacks, fails.
tweaked() { head -n 5 "$1" 2>/dev/null | grep -q 'kit: tweaked'; }
reason() { head -n 5 "$1" | grep -o 'kit: tweaked.*' | head -n 1 | sed -e 's/^kit: tweaked[[:space:]]*//' -e 's/[[:space:]]*-->[[:space:]]*$//'; }
# The template's adopted features (config/kit.php `features`), not the whole catalog: an opt-in feature the template
# does not ship (notes, finalize, deliver) is left out.
features=$(php -r '$c = include $argv[1]; echo implode(" ", $c["features"]);' "$template/config/kit.php")
status=0
tweaks=0
files=0
for feature in $features; do
    report=$(php artisan kit:scaffold "$feature" --diff --no-ansi)
    while IFS= read -r line; do
        case "$line" in
            "unchanged "*)
                files=$((files + 1))
                ;;
            "--- a/"*)
                file=${line#--- a/}
                files=$((files + 1))
                if tweaked "$file"; then
                    echo "parity: tweaked, a graduation candidate: $file ($(reason "$file"))"
                    tweaks=$((tweaks + 1))
                    continue
                fi
                echo "parity: $file differs from kit:scaffold $feature (take the kit's file with --force=$file, or mark a deliberate tweak with \`// kit: tweaked <reason>\`)" >&2
                status=1
                ;;
            "missing "*)
                echo "parity: ${line#missing }" >&2
                status=1
                ;;
        esac
    done <<EOF
$report
EOF
done
[ "$status" -eq 0 ] && echo "parity: the template's $files kit files equal kit:scaffold $features ($tweaks marked tweaks listed above)"
exit "$status"
