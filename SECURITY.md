# Security reporting

Report security concerns privately to the repository owner. Do not include actual patient records, passwords, tokens or database dumps in public issues.

## Boundaries

- Patient: own appointments and invoices only.
- Doctor: assigned visits and clinical notes only; no staff management or billing access.
- Receptionist: patient contact details, scheduling and recorded payments; no clinical notes.
- Administrator: clinic setup, team access, clinical records and audit history.

New registrations cannot choose a staff role. Staff access requires verified email; disabling a staff account invalidates access on subsequent requests. Notes and visit reasons use Laravel encryption. The application key must be kept secret and backed up.

Audit logs record identifiers and actions, never medical text. They are not tamper-proof against database administrators; production operators should provide an external restricted audit sink if required.

Use supported, patched runtime versions, HTTPS, production configuration and the deployment checklist. Automated tests and dependency audits are part of release validation, not a guarantee that the application is free of vulnerabilities.
