# Multi-tenant ownership pattern

BiLLU keeps tenants separated at the application layer. Database rows
carry `office_id` (offices) or `client_id` (clients) and every read or
write of an entity by ID MUST verify the caller owns it. There is no
row-level security in MySQL — the controller is the gatekeeper.

This document is the canonical reference for new endpoints.

## Sessions

| Session key       | Set during                  | Used by                                    |
|-------------------|-----------------------------|--------------------------------------------|
| `client_id`       | client login / impersonate  | client routes                              |
| `office_id`       | office login                | office routes                              |
| `employee_id`     | office-employee login       | office routes (with assignment filter)     |
| `is_admin`        | master admin login          | admin routes                               |
| `impersonator_id` | employee→client impersonate | recorded by `AuditLog`                     |

`Auth::requireClient()`, `Auth::requireOffice()`, `Auth::requireAdmin()`
guard route access; they do NOT verify per-entity ownership.

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

## Adding a new tenant-scoped model

1. Add `findByIdForClient` / `findByIdForOffice` next to `findById`.
2. Define `FILLABLE` (and `ADMIN_FILLABLE` if any field is admin-only).
3. Add a row in the table above.
4. Add a `OwnershipHelpersTest` data-provider entry under
   `tests/Security/`.
5. In every controller method that handles a URL param, call the
   ownership accessor — never raw `findById`.
