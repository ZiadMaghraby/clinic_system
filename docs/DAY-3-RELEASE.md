# Day 3 release — 2026-10-10

## Changes

- Published the previous patient-profile/reporting work to main.
- Added administrator-managed doctor absences, including partial-day blocks and multi-day leave, reversible cancellation, audit records and transaction locks shared with bookings.
- Blocks that overlap existing pending/confirmed appointments are rejected; appointments and invoices are preserved.
- Added /ready, a stateless database health probe with generic 200/503 responses, plus stronger clinic:preflight checks and --json output for deployment scripts.
- Added 19 regression cases, bringing the suite to 92 tests and 382 assertions.

## Verified

- Full PHP test suite, project-wide Pint, Vite production build, Blade and route caches.
- New migration ran on a fresh isolated SQLite database and the existing local checkout.
- Browser: demo login, absence creation, Arabic layout and mobile width 390px. Document width 375px including the scrollbar allocation; no page overflow.
- HTTP /ready returned 200 and ready; simulated database outage test returns 503 without connection details.
- GitHub Actions passed for code commit dd817c4 on PHP 8.2/8.3 with SQLite/MySQL 8. The workflow also runs Composer and npm audits.

## Current limitation

The owner confirmed no hosting or domain has been purchased. This is a tested staging candidate, not a live production clinic. HTTPS hosting, production database, SMTP delivery verification, clinic identity settings, backup/restore validation and operator procedures remain required. No purchase or public application deployment was performed.

## GitHub work

Three implementation commits were pushed to the existing main branch using its configured author identity. This documentation is a separate release handoff commit. No empty commits, rewritten dates or changed author identities were used. Publishing code is separate from deploying a running website.
