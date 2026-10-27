#!/bin/sh
# Fails when tracked files contain local paths, internal tool links or private names.
# Private names come from the PRIVACY_PATTERN environment variable (a CI secret), so
# this public script never lists them. Lockfiles are skipped: third-party metadata only.
# The fixed patterns are case-sensitive so app routes like "/users/create" don't match "/Users/"; only the private names ignore case.
fixed='/Users/|/home/[a-z]+/|solo://|scratchpad|claude\.ai/code'
found=$(git grep -lIE "$fixed" -- . ':!composer.lock' ':!package-lock.json' ':!bin/privacy-check.sh')
if [ -n "$PRIVACY_PATTERN" ]; then
  found=$(printf '%s\n%s' "$found" "$(git grep -lIiE "$PRIVACY_PATTERN" -- . ':!composer.lock' ':!package-lock.json' ':!bin/privacy-check.sh')" | sed '/^$/d' | sort -u)
fi
if [ -n "$found" ]; then
  echo "$found" >&2
  echo "Private reference found in the files above; remove it before publishing." >&2
  exit 1
fi
echo "Privacy check passed."
