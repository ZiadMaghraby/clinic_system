# Clinic System

A Laravel 12 application with doctor listings, patient appointments, medical history, invoices, and administrative screens.

## Requirements

- PHP 8.2 or newer with the extensions required by Composer.
- Composer, MySQL, and Node.js/npm compatible with the Vite dependency.

## Local setup

1. Run `composer install` and `npm ci` in the repository root.
2. Copy `.env.example` to `.env` and run `php artisan key:generate`.
3. Create a local MySQL database. Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`.
4. Run `php artisan migrate` against that development database.
5. Run `composer run dev` and open the URL printed by the application server.

Use `MAIL_MAILER=log` for local email testing. Inspect `storage/logs/laravel.log` rather than sending real email. Optional seed data is available through `php artisan db:seed`; review the seeders before loading their sample accounts.

## Development commands

```sh
php artisan route:list
composer run test
npm run build
```

The test command runs the repository's existing tests; it does not establish complete coverage of the clinic workflows.

## Source map

| Location | Responsibility |
| --- | --- |
| `routes/web.php` | Public pages, patient routes, administrative routes, profile routes. |
| `app/Http/Controllers/` | Request handling and application flows. |
| `app/Models/` | Eloquent models. |
| `database/migrations/` | Database schema. |
| `database/seeders/` | Sample users and doctors. |
| `resources/views/` | Blade screens. |

Use synthetic patient records for development. Keep `.env` and real medical data outside version control. Review role and record-level authorization before deployment; authenticated routes alone do not establish access control for every record.

This is ZiadMaghraby's fork of [the collaborative clinic project](https://github.com/ABDELRAHMAN-MAHM0UD/clinic_system). Laravel framework licensing is documented in its upstream repository.
