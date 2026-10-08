# Deployment & operations

This release is a **staging candidate**, not a certification for medical use. Software tests do not establish healthcare or privacy compliance. Run the checklist with the clinic owner before handling actual patient information.

## Runtime

- PHP 8.2+ with PDO MySQL, OpenSSL, Mbstring, Ctype, Fileinfo, Tokenizer, XML, DOM and cURL. Use a currently patched PHP build; do not deploy an old XAMPP installation.
- Composer 2, Node 22 for the build, MySQL 8/InnoDB for the production database, Nginx + PHP-FPM.
- A dedicated database and database user, TLS domain, transactional SMTP provider, encrypted backups, and monitoring.
- Web root **must** be `public/`. Never expose the repository root, `.env`, `storage`, SQL exports or Composer configuration.

## First deployment

1. Provision a fresh database. Copy `.env.example` to `.env`. Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, MySQL credentials, `CLINIC_NAME`, `CLINIC_PHONE`, `CLINIC_ADDRESS`, `CLINIC_TIMEZONE`, and `CLINIC_CURRENCY`.
2. Configure real SMTP delivery (not the log driver). Set `MAIL_MAILER=smtp`, provider host/port/credentials and a verified sender. Configure SPF/DKIM/DMARC with the provider. Do not disable certificate verification.
3. Install/build in a new release directory:

   ```sh
   composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
   npm ci
   npm run build
   php artisan key:generate
   php artisan migrate --force
   php artisan clinic:admin admin@your-clinic.example --name="Clinic Administrator"
   php artisan optimize
   php artisan clinic:preflight
   ```

   `clinic:admin` prompts for a password without echoing it. There is no production default admin account. Never run `DemoSeeder` on a deployed system.
4. Give only the web-service user write access to `storage/` and `bootstrap/cache/`. Keep source and `.env` unreadable to other users. Route requests through `public/index.php` using the supplied Nginx starting configuration. Enable HTTPS at the reverse proxy/server and restrict trusted proxy configuration to your own infrastructure.
5. Verify `/up`, registration, email verification, password reset, login/logout, all four roles, booking/cancellation, rescheduling, notes, invoice printing and payment recording in **staging**. SMTP verification/reset delivery is synchronous: test provider failures too.
6. Configure an uptime monitor for `/up`, central error monitoring with patient data redaction, log retention and encrypted backups. `/up` checks application boot only; use separate database and SMTP monitoring.

## Upgrading the old project

- Take a full encrypted DB backup and preserve the existing `APP_KEY` **before migration**. Test a restore and upgrade on a copy first. Never run `migrate:fresh` on an existing installation.
- Preflight the legacy data for duplicate active `(doctor_id, appointment_date, appointment_time)` rows and multiple invoices for a single appointment. The operations migration checks these **before** schema changes; reconcile with staff rather than deleting records automatically. MySQL DDL is not fully transactional, so retain the backup even after preflight.
- Existing `is_admin` accounts become role `admin`; remaining accounts become `patient`. Link existing doctors to new staff accounts in Staff access. Existing appointments receive a 30-minute duration snapshot; review this if the old clinic used different visit lengths.
- Unchanged passwords from the publicly shipped old seeders are invalidated and those accounts disabled. Create a new admin with `clinic:admin`, review the affected identities, and reset/reactivate legitimate accounts through a controlled administrator process.
- Legacy controller URLs are replaced by the new workspace. `/admin/admindashboard` and `/patient/dashboard` redirect to the new dashboard; update other bookmarks.
- Cancelled appointments release reservations. No appointment-delete endpoint is exposed. Accounts with clinical records cannot self-delete; retention/anonymization requests require an operator-reviewed process.
- Keep currency and timezone stable once bookings exist; changing them does not convert historical money or appointment times.

## Backups & rollback

- Back up MySQL, the encryption key and required app storage to encrypted, access-restricted storage. Do not back up the key and DB under the same publicly accessible location.
- Establish retention, a recovery-point target, and a recovery-time target with the operator. Perform a restore drill before launch and periodically after.
- Keep the previous release directory. For code-only rollback switch the release symlink back. For schema rollback restore the tested matching DB backup; do not blindly execute `migrate:rollback` with patient data.
- Losing `APP_KEY` loses access to encrypted clinical notes and visit reasons. Never regenerate it on an existing deployment. Key rotation needs a tested `APP_PREVIOUS_KEYS` migration plan.

## Deliberate boundaries

- Payment actions record payments already received; this is **not** a payment gateway. No automatic charges, refunds, tax filing or e-invoicing integration are implemented. Paid appointments cannot be cancelled until an operator handles the refund/accounting policy.
- Clinical notes are clinician-entered text, not diagnosis advice or a certified electronic health record. Only assigned doctors and administrators may access them; reception cannot.
- No SMS/WhatsApp reminders, insurance claims, lab integrations, prescription dispensing or patient document uploads are enabled.
- Appointment changes are visible in the portal; automated appointment reminders are not implemented. Account verification/password reset use email.
- Clinic-specific privacy notices, patient consent, retention, access-review procedures, emergency guidance and jurisdiction-specific obligations need the operator's review. No compliance badge is claimed.

## References

- [Laravel deployment](https://laravel.com/docs/12.x/deployment)
- [Laravel email verification](https://laravel.com/docs/12.x/verification)

