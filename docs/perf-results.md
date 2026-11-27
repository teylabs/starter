# Performance results

One row per tuning round, before and after, so a change in speed has its numbers next to it.

| Date | Page | Queries | Prod time | Fix | Commit/PR | How measured |
| ---- | ---- | ------- | --------- | --- | --------- | ------------ |
| 2026-11-27 | `/login` (mobile) | n/a (frontend) | not measured (local only) | Run kit's `kitImports` rewrite in builds, so kit components split per file instead of one shared chunk every page loads. Before: Lighthouse performance 66, total blocking time 1,770 ms, 1,654 KiB, 740 KiB unused JS, 71 static files (1,529 KiB raw) including the editor and table. After: 93 and 94 (two runs), 240–260 ms, 1,039 KiB, 292 KiB unused JS, 55 files (919 KiB raw), no editor or table. LCP 1.6 s to 1.5 s. | this PR | Lighthouse 13.6 mobile preset, headless Chrome 157, against `php artisan serve` on a fresh `composer setup` clone (uncompressed, so byte counts are raw); static file counts from the Vite manifest. Desktop was already 100 before. |
