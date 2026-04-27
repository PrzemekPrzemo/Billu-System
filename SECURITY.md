# Security policy

BiLLU is a multi-tenant invoicing platform. This document describes the
threat model, the isolation boundaries the codebase enforces, and how to
report vulnerabilities.

## Reporting

Email **security@billu.pl** with a description, reproduction steps, and
the affected version / deployment. Encrypt sensitive findings against
the PGP key published on the same page. We aim to acknowledge within
two business days and to ship a fix within 30 days for high-severity
issues.

## Threat model

| Actor                  | Trust level | Boundary                                                              |
|------------------------|-------------|-----------------------------------------------------------------------|
| Anonymous              | None        | Login page, password reset, public assets only                        |
| Client (tenant user)   | Limited     | Strict per-`client_id` data scope                                     |
| Office employee        | Limited     | All clients of the assigned office; explicit assignments for employees |
| Office admin           | Elevated    | All clients of own office; SMTP / branding / KSeF config              |
| Master admin           | Privileged  | Cross-office; system settings; full audit log                         |
| External services      | Untrusted   | KSeF, GUS, CEIDG, VIES, NBP, banks — treated as adversarial input     |

We assume the database, Redis, and SMTP are deployed on trusted
infrastructure. Process-to-process communication should run over TLS in
production (`DB_SSL_CA`).

## Isolation invariants

The codebase enforces these invariants. Tests under `tests/Security/`
guard them.

1. **Tenant boundary on every controller route.** Any handler that
   loads an entity by ID from the URL or POST body MUST verify
   `client_id` (for client routes) or `office_id` (for office routes)
   before reading or writing. The canonical accessors are
   `Model::findByIdForClient($id, $clientId)` and
   `Model::findByIdForOffice($id, $officeId)` — see
   `docs/multitenant.md`.
2. **Mass assignment is allow-listed.** `Client::FILLABLE`,
   `Office::FILLABLE`, `IssuedInvoice::FILLABLE` define which fields a
   form submission may set. Privilege fields (`office_id`,
   `password_hash`, `is_demo`, `client_id`, …) require an explicit
   `$allowed` argument to `Model::update()` and are only used in
   `AdminController` paths.
3. **Authentication state is server-side.** Sessions live in MySQL,
   not in cookies; CSRF tokens rotate per session; failed-login
   throttling lives in `Auth::isRateLimited` (per IP, 15 min lockout
   after 5 attempts).
4. **Two-factor enforcement is configurable.** Master admin toggles
   `2fa_required_admin / _client / _office` (see `/admin/settings` →
   "2FA"). Users without TOTP get redirected to `/two-factor-setup` on
   next login.
5. **Sensitive values are redacted in audit log.** `AuditLog::log`
   replaces `password_hash`, `totp_secret`, `recovery_codes`,
   `ksef_token`, `api_key`, etc. with `[REDACTED]` before persisting.
6. **Recipient addresses are masked in error logs.** `MailService`
   logs `a***@example.com` instead of full addresses on send failures.
7. **Database-at-rest encryption.** TDE is configured at the MariaDB
   level (`docs/db-tde.md`). The application does not double-encrypt
   PII at the column level; rely on TDE plus disk-level controls.
8. **Database-in-transit encryption.** Set `DB_SSL_CA` (and
   `DB_SSL_VERIFY=true`) in `.env` to force TLS to MySQL.

## Hardening defaults checklist (production)

- [ ] `APP_DEBUG=false`
- [ ] `APP_SECRET_KEY` set to 64 random hex chars
- [ ] `DB_SSL_CA` populated, `DB_SSL_VERIFY=true`
- [ ] MariaDB `file_key_management` plugin loaded (TDE)
- [ ] `2fa_enabled=1` and at least `2fa_required_admin=1` in `settings`
- [ ] `CACHE_DRIVER=redis` so `PasswordResetService` throttle is active
- [ ] HTTPS termination in front of the app (HSTS, secure cookies)
- [ ] Session cookie `Secure` + `HttpOnly` + `SameSite=Lax`
- [ ] Reverse-proxy rate-limit on `/login` and `/forgot-password`
- [ ] Backups encrypted at rest, separate keyring from TDE

## Out of scope (currently)

- WAF / DDoS layer — assumed to be provided by the hosting layer.
- Per-column PII encryption beyond what TDE covers.
- External penetration test.
