# Starter

- Run `php artisan standards:check` before pushing; it must exit 0. Find the pattern for new work with `php artisan standards:pattern "<intent>"` (offline `bakery.find`).
- Kit-owned files under `resources/js/components/Kit` and the kit's server classes are not edited here; fix them in the kit.
- Pages render server-provided descriptors and capability flags; they never check roles or permissions themselves.
- Tests run with `--tia` locally (`--no-tia` for browser specs); see `tests/Pest.php`.
- No pixel-pinned tests: a browser test asserts what the user gets and how elements relate (visible, in order, not overlapping, one below another, equal widths, a class or data state). Never compare a `getComputedStyle` size, colour or font, or a measured width or height, with a fixed value. Design tokens change without behaviour changing. tey/standards' `no-pixel-pins` enforces it.
