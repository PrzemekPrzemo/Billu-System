# Multi-tenant ownership pattern

BiLLU keeps tenants separated at the application layer. Database rows
carry `office_id` (offices) or `client_id` (clients) and every read or
write of an entity by ID MUST verify the caller owns it. There is no
row-level security in MySQL — the controller is the gatekeeper.

This document is the canonical reference for new endpoints.

## Sessions

| Session key                  | Set during                  | Used by                                |
|------------------------------|-----------------------------|----------------------------------------|
| `client_id`                  | client login / impersonate  | client routes                          |
| `office_id`                  | office login                | office routes                          |
| `employee_id`                | office-employee login       | office routes (with assignment filter) |
| `client_employee_id`         | client-employee login       | `/employee/*` self-service routes      |
| `client_employee_client_id`  | client-employee login       | tenant scope for the employee's panel  |
| `is_admin`                   | master admin login          | admin routes                           |
| `impersonator_id`            | employee→client impersonate | recorded by `AuditLog`                 |

`Auth::requireClient()`, `Auth::requireOffice()`, `Auth::requireAdmin()`,
`Auth::requireClientEmployee()` guard route access; they do NOT verify
per-entity ownership.

## Roles cheat-sheet

| Role                  | Login URL          | Tenant column                 | Sidebar source                |
|-----------------------|--------------------|-------------------------------|-------------------------------|
| Master admin          | `/masterLogin`     | none (cross-tenant)           | `templates/layout.php` admin  |
| Office (firm)         | `/login`           | `office_id`                   | layout, office block          |
| Office employee       | `/login`           | `office_id` + assigned client | layout, office block (filtered) |
| Client (company)      | `/login`           | `client_id` (own only)        | layout, client block          |
| **Client employee**   | `/employee/login`  | `client_employee_id` (self)   | layout, client_employee block |

## Pattern: ownership-checked accessor

Every model that exposes "find by primary key" defines two helpers:

```php
Model::findByIdForClient(int $id, int $clientId): ?array
Model::findByIdForOffice(int $id, int $officeId): ?array
```

They run a single SQL query that filters by both the PK and the tenant
column. Returning `null` is the only signal — there is no "found but
forbidden" branch to misuse.

### Where they live

| Model         | Tenant column                             | Notes                              |
|---------------|-------------------------------------------|------------------------------------|
| `Invoice`     | `client_id` directly; `c.office_id` via join | Both helpers present              |
| `InvoiceBatch`| `client_id` directly; `c.office_id` via join | Both helpers present              |
| `Contractor`  | `client_id`; office via join              | Both helpers present              |
| `Message`     | `client_id`; office via join              | Both helpers present              |
| `ClientFile`  | `client_id`; office via join              | Both helpers present              |
| `Client`      | `office_id` directly                      | Use `Client::findByOffice` for lists |
| `IssuedInvoice` | `client_id` (no office shortcut)        | Verify via `Client::findById($invoice['client_id'])` if office route |
| `ClientEmployee`| `client_id`; office via join + login_email lookup | Both helpers + `findByLoginEmail` for `Auth::loginClientEmployee` |

## Controller recipe

### Client route

```php
public function invoiceShow(int $id): void
{
    Auth::requireClient();
    $clientId = Session::get('client_id');

    $invoice = Invoice::findByIdForClient($id, $clientId);
    if ($invoice === null) {
        $this->redirect('/client/invoices');   // 302, no information leak
        return;
    }
    $this->render('client/invoice', ['invoice' => $invoice]);
}
```

### Office route

```php
public function batchDetail(int $id): void
{
    Auth::requireOffice();
    $officeId = (int) Session::get('office_id');

    $batch = InvoiceBatch::findByIdForOffice($id, $officeId);
    if ($batch === null) {
        $this->redirect('/office/batches');
        return;
    }

    // Employee further-narrows: only assigned clients
    $clientFilter = $this->getEmployeeClientFilter();
    if ($clientFilter !== null && !in_array($batch['client_id'], $clientFilter, true)) {
        $this->redirect('/office/batches');
        return;
    }

    // ... render
}
```

### What NOT to do

```php
// BAD — leaks existence and is one refactor away from a tenant break
$invoice = Invoice::findById($id);
if ((int) $invoice['client_id'] !== $clientId) { abort(403); }

// BAD — hands $_POST['client_id'] straight to the DB
Invoice::update($id, $_POST);
```

## Mass-assignment recipe

`Model::update($id, $data)` filters `$data` through `Model::FILLABLE`
(allowlist) before writing. Privilege fields require the second
argument:

```php
// Office form: filtered by Client::FILLABLE
Client::update($clientId, $_POST);

// Admin form: explicit, includes office_id / password_hash / is_demo
Client::update($clientId, $_POST, Client::adminAllowedFields());
```

`IssuedInvoice` uses a denylist (PROTECTED_FIELDS) by default because
internal services (`InvoicePdfService`, `KsefInvoiceSendService`)
update many distinct columns; user-input flows MUST pass
`IssuedInvoice::FILLABLE` explicitly:

```php
// User flow (ClientController::issuedInvoiceUpdate, API)
IssuedInvoice::update($id, $data, IssuedInvoice::FILLABLE);

// Service flow
IssuedInvoice::update($id, ['ksef_status' => 'sent']);   // PROTECTED still blocks id/client_id
```

### Client-employee route (self-service panel)

```php
public function payslipPdf(int $entryId): void
{
    Auth::requireClientEmployee();
    $employeeId = (int) Session::get('client_employee_id');

    // Hard ownership check — entry's employee_id MUST equal session's.
    $entry = PayrollEntry::findById($entryId);
    if (!$entry || (int) ($entry['employee_id'] ?? 0) !== $employeeId) {
        $this->redirect('/employee/payslips');
        return;
    }
    // ... serve PDF
}
```

A client-employee NEVER sees data of another employee, even at the
same client. The session's `client_employee_id` is the only tenant
key — `client_employee_client_id` is informational (used in UI for
"Konto pracownicze · {company}").

## Adding a new tenant-scoped model

1. Add `findByIdForClient` / `findByIdForOffice` next to `findById`.
2. Define `FILLABLE` (and `ADMIN_FILLABLE` if any field is admin-only).
3. Add a row in the table above.
4. Add a `OwnershipHelpersTest` data-provider entry under
   `tests/Security/`.
5. In every controller method that handles a URL param, call the
   ownership accessor — never raw `findById`.
