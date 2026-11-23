**This is a stand-in page. Replace it before you deploy.**

The template ships it so the Terms link in the footer leads somewhere. It is not a legal document, and it grants and limits nothing.

## Before you deploy

- Write your terms of service, or have them written, and serve them at a URL of your own, such as `/legal/terms`.
- Set `terms_url` in `config/brand.php` to that URL, and remove this page and its route in `routes/web.php`.
- `php artisan kit:preflight` refuses a production deploy while the footer still points at `/terms`.

An internal tool with no public terms sets `'private' => true` in `config/brand.php` instead, which drops the Terms and [Privacy](/privacy) links.
