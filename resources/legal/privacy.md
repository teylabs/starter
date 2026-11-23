**This is a stand-in page. Replace it before you deploy.**

The template ships it so the Privacy link in the footer leads somewhere. It is not a privacy policy, and it says nothing about how an app built from this template handles personal data.

## Before you deploy

- Write your privacy policy, or have it written, and serve it at a URL of your own, such as `/legal/privacy`.
- Set `privacy_url` and `privacy_email` in `config/brand.php`, and remove this page and its route in `routes/web.php`.
- `php artisan kit:preflight` refuses a production deploy while the footer still points at `/privacy`.

An internal tool with no public policy sets `'private' => true` in `config/brand.php` instead, which drops the [Terms](/terms) and Privacy links.
