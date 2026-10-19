#!/bin/sh
# Fails when tracked files contain local paths, internal tool links or private names.
# Private names come from the PRIVACY_PATTERN environment variable (a CI secret), so
# this public script never lists them. Lockfiles are skipped: third-party metadata only.
pattern='/Users/|/home/[a-z]+/|solo://|scratchpad|claude\.ai/code'
if [ -n "$PRIVACY_PATTERN" ]; then
  pattern="$pattern|$PRIVACY_PATTERN"
fi
if git grep -nIiE "$pattern" -- . ':!composer.lock' ':!package-lock.json' ':!bin/privacy-check.sh' >/dev/null; then
  git grep -lIiE "$pattern" -- . ':!composer.lock' ':!package-lock.json' ':!bin/privacy-check.sh' >&2
  echo "Private reference found in the files above; remove it before publishing." >&2
  exit 1
fi
echo "Privacy check passed."
