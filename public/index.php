<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Cache;
use App\Core\Session;
use App\Core\Router;
use App\Core\Language;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\ClientController;
use App\Controllers\OfficeController;

// Config
$appConfig = require __DIR__ . '/../config/app.php';
date_default_timezone_set($appConfig['timezone']);

// Cache (Redis with no-op fallback)
Cache::init(require __DIR__ . '/../config/cache.php');

// Security headers (PHP fallback when mod_headers unavailable)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── REST API Intercept ──────────────────────────────────
// Must run before session start and web routes.
// All /api/* requests are handled by ApiKernel (JSON, JWT auth).
if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    $apiKernel = new \App\Api\ApiKernel();
    $apiKernel->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    exit; // ApiKernel always exits
}
// ───────────────────────────────────────────────────────

// Session
Session::start();

// Language
$locale = Session::get('client_language', Session::get('office_language', $appConfig['locale']));
Language::setLocale($locale);

// Router
$router = new Router();

// ── Public Auth Routes ─────────────────────────────
$router->get('/', [AuthController::class, 'loginForm']);
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/masterLogin', [AuthController::class, 'masterLoginForm']);
$router->post('/masterLogin', [AuthController::class, 'masterLogin']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/account-blocked', [AuthController::class, 'accountBlocked']);
$router->get('/change-password', [AuthController::class, 'changePasswordForm']);
$router->post('/change-password', [AuthController::class, 'changePassword']);
$router->get('/forgot-password', [AuthController::class, 'forgotPasswordForm']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password', [AuthController::class, 'resetPasswordForm']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);
$router->get('/accept-privacy', [AuthController::class, 'acceptPrivacyForm']);
$router->post('/accept-privacy', [AuthController::class, 'acceptPrivacy']);
$router->get('/stop-impersonation', [AuthController::class, 'stopImpersonation']);
$router->get('/privacy-policy', [AuthController::class, 'privacyPolicy']);
$router->get('/terms', [AuthController::class, 'terms']);
$router->get('/two-factor-verify', [AuthController::class, 'twoFactorVerifyForm']);
$router->post('/two-factor-verify', [AuthController::class, 'twoFactorVerify']);
$router->get('/two-factor-setup', [AuthController::class, 'twoFactorSetupForm']);
$router->post('/two-factor-setup', [AuthController::class, 'twoFactorEnable']);
$router->get('/two-factor-recovery', [AuthController::class, 'twoFactorRecovery']);

// Trusted devices (self-service for any logged-in user)
$router->get('/trusted-devices', [\App\Controllers\TrustedDeviceController::class, 'index']);
$router->post('/trusted-devices/{id}/revoke', [\App\Controllers\TrustedDeviceController::class, 'revoke']);
$router->post('/trusted-devices/revoke-all', [\App\Controllers\TrustedDeviceController::class, 'revokeAll']);

// Client-employee authentication & activation
$router->get('/employee/login', [AuthController::class, 'clientEmployeeLoginForm']);
$router->post('/employee/login', [AuthController::class, 'clientEmployeeLogin']);
$router->get('/employee/logout', [AuthController::class, 'clientEmployeeLogout']);
$router->get('/employee/activate', [AuthController::class, 'employeeActivateForm']);
$router->post('/employee/activate', [AuthController::class, 'employeeActivate']);

// Client-employee self-service panel
$router->get('/employee', [\App\Controllers\EmployeeController::class, 'dashboard']);
$router->get('/employee/profile', [\App\Controllers\EmployeeController::class, 'profile']);
$router->get('/employee/change-password', [\App\Controllers\EmployeeController::class, 'changePasswordForm']);
$router->post('/employee/change-password', [\App\Controllers\EmployeeController::class, 'changePassword']);
$router->get('/employee/payslips', [\App\Controllers\EmployeeController::class, 'payslips']);
$router->get('/employee/payslips/{id}/pdf', [\App\Controllers\EmployeeController::class, 'payslipPdf']);
$router->get('/employee/leaves', [\App\Controllers\EmployeeController::class, 'leaves']);
$router->get('/employee/leaves/request', [\App\Controllers\EmployeeController::class, 'leaveRequestForm']);
$router->post('/employee/leaves/request', [\App\Controllers\EmployeeController::class, 'leaveRequest']);
$router->post('/two-factor-disable', [AuthController::class, 'twoFactorDisable']);

// ── Admin Routes (/admin) ──────────────────────────
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/analytics', [AdminController::class, 'analytics']);
$router->get('/admin/clients', [AdminController::class, 'clients']);
$router->get('/admin/clients/create', [AdminController::class, 'clientCreateForm']);
$router->post('/admin/clients/create', [AdminController::class, 'clientCreate']);
$router->get('/admin/clients/{id}/edit', [AdminController::class, 'clientEditForm']);
$router->post('/admin/clients/{id}/update', [AdminController::class, 'clientUpdate']);
$router->post('/admin/clients/{id}/delete', [AdminController::class, 'clientDelete']);
$router->post('/admin/clients/{id}/toggle-active', [AdminController::class, 'clientToggleActive']);
$router->post('/admin/clients/{id}/reset-password', [AdminController::class, 'clientResetPassword']);
$router->get('/admin/clients/{id}/cost-centers', [AdminController::class, 'clientCostCenters']);
$router->post('/admin/clients/{id}/cost-centers', [AdminController::class, 'clientCostCentersUpdate']);
$router->get('/admin/clients/{id}/modules', [AdminController::class, 'clientModules']);
$router->post('/admin/clients/{id}/modules', [AdminController::class, 'clientModulesUpdate']);
$router->get('/admin/clients/bulk-import', [AdminController::class, 'bulkImportForm']);
$router->post('/admin/clients/bulk-import', [AdminController::class, 'bulkImport']);
$router->get('/admin/module-bundles', [AdminController::class, 'moduleBundles']);
$router->post('/admin/module-bundles/assign', [AdminController::class, 'moduleBundleAssign']);
$router->get('/admin/offices', [AdminController::class, 'offices']);
$router->get('/admin/offices/create', [AdminController::class, 'officeCreateForm']);
$router->post('/admin/offices/create', [AdminController::class, 'officeCreate']);
$router->get('/admin/offices/{id}/edit', [AdminController::class, 'officeEditForm']);
$router->get('/admin/offices/{id}/modules', [AdminController::class, 'officeModules']);
$router->post('/admin/offices/{id}/modules', [AdminController::class, 'officeModulesUpdate']);
$router->post('/admin/offices/{id}/update', [AdminController::class, 'officeUpdate']);
$router->post('/admin/offices/{id}/toggle-active', [AdminController::class, 'officeToggleActive']);
$router->post('/admin/offices/{id}/reset-password', [AdminController::class, 'officeResetPassword']);
$router->get('/admin/import', [AdminController::class, 'importForm']);
$router->get('/admin/import/template', [AdminController::class, 'importTemplate']);
$router->post('/admin/import', [AdminController::class, 'import']);
$router->post('/admin/import/template-save', [AdminController::class, 'importTemplateSave']);
$router->post('/admin/import/template-delete/{id}', [AdminController::class, 'importTemplateDelete']);
$router->post('/admin/import/ksef', [AdminController::class, 'ksefImport']);
$router->get('/admin/import/ksef-status', [AdminController::class, 'ksefImportStatus']);
$router->get('/admin/batches', [AdminController::class, 'batches']);
$router->get('/admin/batches/{id}', [AdminController::class, 'batchDetail']);
$router->post('/admin/batches/{id}/finalize', [AdminController::class, 'finalizeBatch']);
$router->get('/admin/reports/{id}/download', [AdminController::class, 'downloadReport']);
$router->get('/admin/settings', [AdminController::class, 'settings']);
$router->post('/admin/settings', [AdminController::class, 'settingsUpdate']);
$router->get('/admin/api-settings', [AdminController::class, 'apiSettings']);
$router->post('/admin/api-settings', [AdminController::class, 'apiSettingsUpdate']);
$router->get('/admin/activity-log', [AdminController::class, 'activityLog']);
// Mobile API management
$router->get('/admin/api/sessions', [AdminController::class, 'apiSessions']);
$router->post('/admin/api/sessions/revoke-all', [AdminController::class, 'apiRevokeAllSessions']);
$router->post('/admin/api/sessions/{id}/revoke', [AdminController::class, 'apiRevokeSession']);
$router->post('/admin/clients/{id}/toggle-mobile', [AdminController::class, 'clientToggleMobile']);
$router->post('/admin/offices/{id}/toggle-mobile-clients', [AdminController::class, 'officeBulkToggleMobile']);
$router->get('/admin/audit-log', [AdminController::class, 'auditLog']);
$router->get('/admin/ksef-logs', [AdminController::class, 'ksefLogs']);
$router->get('/admin/ksef-test', [AdminController::class, 'ksefTest']);
$router->get('/admin/ksef-operations', [AdminController::class, 'ksefOperations']);
$router->get('/admin/gus-lookup', [AdminController::class, 'gusLookup']);
$router->get('/admin/gus-diagnostic', [AdminController::class, 'gusDiagnostic']);
$router->get('/admin/ceidg-diagnostic', [AdminController::class, 'ceidgDiagnostic']);
$router->get('/admin/vies-check', [AdminController::class, 'viesCheck']);
$router->get('/admin/impersonate/{type}/{id}', [AdminController::class, 'impersonate']);
$router->get('/admin/notifications', [AdminController::class, 'notifications']);
$router->post('/admin/notifications/read', [AdminController::class, 'notificationsMarkRead']);
$router->get('/admin/audit-log/export', [AdminController::class, 'auditLogExport']);
$router->get('/admin/reports/aggregate', [AdminController::class, 'aggregateReport']);
$router->post('/admin/reports/aggregate', [AdminController::class, 'aggregateReportGenerate']);
$router->get('/admin/reports/comparison', [AdminController::class, 'periodComparison']);
$router->get('/admin/reports/suppliers', [AdminController::class, 'supplierAnalysis']);
$router->get('/admin/scheduled-exports', [AdminController::class, 'scheduledExports']);
$router->post('/admin/scheduled-exports/create', [AdminController::class, 'scheduledExportCreate']);
$router->post('/admin/scheduled-exports/{id}/delete', [AdminController::class, 'scheduledExportDelete']);
$router->post('/admin/scheduled-exports/{id}/toggle', [AdminController::class, 'scheduledExportToggle']);
$router->post('/admin/scheduled-exports/{id}/run', [AdminController::class, 'scheduledExportRunNow']);
$router->get('/admin/webhooks', [AdminController::class, 'webhooks']);
$router->post('/admin/webhooks/create', [AdminController::class, 'webhookCreate']);
$router->post('/admin/webhooks/{id}/delete', [AdminController::class, 'webhookDelete']);
$router->post('/admin/webhooks/{id}/toggle', [AdminController::class, 'webhookToggle']);
$router->get('/admin/invoices/{id}/comments', [AdminController::class, 'invoiceComments']);
$router->post('/admin/invoices/comment', [AdminController::class, 'addComment']);
$router->get('/admin/erp-export', [AdminController::class, 'erpExportForm']);
$router->post('/admin/erp-export', [AdminController::class, 'erpExport']);
$router->get('/admin/security', [AdminController::class, 'security']);
$router->get('/admin/security-scan', [AdminController::class, 'securityScan']);
$router->post('/admin/security-scan', [AdminController::class, 'securityScan']);
$router->post('/admin/security-scan/ignore', [AdminController::class, 'securityScanIgnore']);

// Admin: Duplicates report (F2)
$router->get('/admin/duplicates', [AdminController::class, 'duplicatesReport']);
$router->post('/admin/duplicates/scan', [AdminController::class, 'duplicatesScan']);
$router->post('/admin/duplicates/{id}/review', [AdminController::class, 'duplicateReview']);

// Admin: SMTP test
$router->post('/admin/settings/test-smtp', [AdminController::class, 'testSmtp']);
$router->post('/admin/offices/{id}/test-smtp', [AdminController::class, 'testOfficeSmtp']);

// Admin: Demo management
$router->get('/admin/demo', [AdminController::class, 'demoManagement']);
$router->post('/admin/demo/reset', [AdminController::class, 'demoReset']);
$router->post('/admin/demo/passwords', [AdminController::class, 'demoPasswordReset']);

// Admin - Client SMTP & Invoice Sending
$router->post('/admin/clients/{id}/test-smtp', [AdminController::class, 'testClientSmtp']);
$router->post('/admin/offices/{id}/enable-invoice-sending', [AdminController::class, 'enableInvoiceSendingForOffice']);
$router->post('/admin/offices/{id}/disable-invoice-sending', [AdminController::class, 'disableInvoiceSendingForOffice']);

// Admin - Email Templates
$router->get('/admin/email-templates', [AdminController::class, 'emailTemplates']);
$router->get('/admin/email-templates/{key}', [AdminController::class, 'emailTemplateEdit']);
$router->post('/admin/email-templates/{key}', [AdminController::class, 'emailTemplateUpdate']);

// ── Office Routes (/office) ────────────────────────
$router->get('/office', [OfficeController::class, 'dashboard']);
$router->get('/office/clients', [OfficeController::class, 'clients']);
$router->get('/office/clients/{id}/edit', [OfficeController::class, 'clientEdit']);
$router->post('/office/clients/{id}/edit', [OfficeController::class, 'clientEditSave']);
$router->post('/office/clients/{id}/delete-data', [OfficeController::class, 'clientDeleteData']);
$router->get('/office/clients/{id}/cost-centers', [OfficeController::class, 'clientCostCenters']);
$router->post('/office/clients/{id}/cost-centers', [OfficeController::class, 'clientCostCentersUpdate']);
$router->get('/office/batches', [OfficeController::class, 'batches']);
$router->get('/office/batches/{id}', [OfficeController::class, 'batchDetail']);
$router->post('/office/invoices/{id}/whitelist-override', [OfficeController::class, 'invoiceWhitelistOverride']);
$router->get('/office/import', [OfficeController::class, 'importForm']);
$router->get('/office/import/template', [OfficeController::class, 'importTemplate']);
$router->post('/office/import', [OfficeController::class, 'import']);
$router->post('/office/import/template-save', [OfficeController::class, 'importTemplateSave']);
$router->post('/office/import/template-delete/{id}', [OfficeController::class, 'importTemplateDelete']);
$router->post('/office/import/ksef', [OfficeController::class, 'importKsef']);
$router->get('/office/import/ksef-status', [OfficeController::class, 'ksefImportStatus']);
$router->get('/office/reports', [OfficeController::class, 'reports']);
$router->get('/office/reports/{id}/download', [OfficeController::class, 'downloadReport']);
$router->get('/office/analytics', [OfficeController::class, 'analytics']);
$router->get('/office/language', [OfficeController::class, 'switchLanguage']);
$router->get('/office/notifications', [OfficeController::class, 'notifications']);
$router->post('/office/notifications/read', [OfficeController::class, 'notificationsMarkRead']);
$router->get('/office/invoices/{id}/comments', [OfficeController::class, 'invoiceComments']);
$router->get('/office/erp-export', [OfficeController::class, 'erpExportForm']);
$router->post('/office/erp-export', [OfficeController::class, 'erpExport']);
$router->get('/office/security', [OfficeController::class, 'security']);
$router->get('/office/employees', [OfficeController::class, 'employees']);
$router->get('/office/employees/create', [OfficeController::class, 'employeeCreateForm']);
$router->post('/office/employees/create', [OfficeController::class, 'employeeCreate']);
$router->get('/office/employees/{id}/edit', [OfficeController::class, 'employeeEditForm']);
$router->post('/office/employees/{id}/update', [OfficeController::class, 'employeeUpdate']);
$router->post('/office/employees/{id}/delete', [OfficeController::class, 'employeeDelete']);
$router->post('/office/impersonate-client', [OfficeController::class, 'impersonateClient']);
$router->get('/office/settings', [OfficeController::class, 'settingsForm']);
$router->post('/office/settings', [OfficeController::class, 'settingsUpdate']);
$router->get('/office/sftp', [OfficeController::class, 'sftpForm']);
$router->post('/office/sftp', [OfficeController::class, 'sftpUpdate']);
$router->post('/office/sftp/test', [OfficeController::class, 'sftpTest']);
$router->get('/office/email-settings', [OfficeController::class, 'emailSettings']);
$router->post('/office/email-settings', [OfficeController::class, 'emailSettingsUpdate']);

// Office: Messages & Tasks
$router->get('/office/messages', [OfficeController::class, 'messages']);
$router->get('/office/messages/preferences', [OfficeController::class, 'messageNotificationPrefs']);
$router->post('/office/messages/preferences', [OfficeController::class, 'messageNotificationPrefs']);
$router->get('/office/messages/attachment/{id}', [OfficeController::class, 'messageAttachment']);
$router->get('/office/messages/{id}', [OfficeController::class, 'messageThread']);
$router->post('/office/messages/create', [OfficeController::class, 'messageCreate']);
$router->post('/office/messages/{id}/reply', [OfficeController::class, 'messageReply']);
$router->get('/office/tasks', [OfficeController::class, 'tasks']);
$router->get('/office/tasks/attachment/{id}', [OfficeController::class, 'taskAttachment']);
$router->post('/office/tasks/create', [OfficeController::class, 'taskCreate']);
$router->post('/office/tasks/{id}/update', [OfficeController::class, 'taskUpdate']);
$router->post('/office/tasks/{id}/delete', [OfficeController::class, 'taskDelete']);
$router->get('/office/tasks/billing', [OfficeController::class, 'tasksBilling']);
$router->post('/office/tasks/{id}/billing', [OfficeController::class, 'taskBillingUpdate']);

// Tax payments
$router->get('/office/tax-payments', [OfficeController::class, 'taxPayments']);
$router->post('/office/tax-payments/save', [OfficeController::class, 'taxPaymentsSave']);

// Office: Tax Calendar (F1)
$router->get('/office/tax-calendar', [OfficeController::class, 'taxCalendar']);
$router->get('/office/tax-calendar/config/{id}', [OfficeController::class, 'taxCalendarConfig']);
$router->post('/office/tax-calendar/config/{id}', [OfficeController::class, 'taxCalendarConfigSave']);
$router->post('/office/tax-calendar/event', [OfficeController::class, 'taxCalendarAddEvent']);
$router->post('/office/tax-calendar/event/{id}/delete', [OfficeController::class, 'taxCalendarDeleteEvent']);

// Office: Client Workflow Status (F6)
$router->post('/office/clients/{id}/status', [OfficeController::class, 'clientStatusUpdate']);

// Office: Client Notes (F4)
$router->get('/office/clients/{id}/notes', [OfficeController::class, 'clientNotes']);
$router->post('/office/clients/{id}/notes', [OfficeController::class, 'clientNotesSave']);
$router->post('/office/clients/{id}/notes/toggle-pin', [OfficeController::class, 'clientNoteTogglePin']);
// External register notes (GUS/KRS/CEIDG/CRBR) — office-only.
$router->get('/office/clients/{id}/registers', [OfficeController::class, 'clientRegisters']);
$router->post('/office/clients/{id}/registers/refresh', [OfficeController::class, 'clientRegistersRefresh']);
$router->post('/office/clients/{id}/crbr/refresh', [OfficeController::class, 'clientCrbrRefresh']);
$router->get('/office/clients/{id}/contractors/{contractorId}/registers', [OfficeController::class, 'contractorRegisters']);
$router->post('/office/clients/{id}/contractors/{contractorId}/registers/refresh', [OfficeController::class, 'contractorRegistersRefresh']);
$router->get('/office/clients/{id}/vat-settlement', [OfficeController::class, 'clientVatSettlement']);

// Office: Client File sharing
$router->get('/office/clients/{id}/files', [OfficeController::class, 'clientFiles']);
$router->post('/office/clients/{id}/files/upload', [OfficeController::class, 'clientFileUpload']);
$router->get('/office/files/{id}/download', [OfficeController::class, 'clientFileDownload']);
$router->post('/office/files/{id}/delete', [OfficeController::class, 'clientFileDelete']);
$router->post('/office/clients/{id}/file-storage-path', [OfficeController::class, 'clientFileStoragePath']);

// Office: Duplicates Report (F2)
$router->get('/office/tax-calculator', [OfficeController::class, 'taxCalculator']);
$router->get('/office/tax-calculator/pdf', [OfficeController::class, 'taxCalculatorPdf']);
$router->post('/office/tax-calculator/save', [OfficeController::class, 'taxCalculatorSave']);
$router->post('/office/tax-calculator/simulation/{id}/delete', [OfficeController::class, 'taxCalculatorDeleteSimulation']);
$router->get('/office/duplicates', [OfficeController::class, 'duplicatesReport']);
$router->post('/office/duplicates/scan', [OfficeController::class, 'duplicatesScan']);
$router->post('/office/duplicates/{id}/review', [OfficeController::class, 'duplicateReview']);

// Office: HR / Kadry i Płace
$router->get('/office/hr', [OfficeController::class, 'hrDashboard']);
$router->get('/office/hr/calculator', [OfficeController::class, 'hrPayrollCalculator']);
$router->get('/office/hr/{clientId}/employees', [OfficeController::class, 'hrEmployees']);
$router->get('/office/hr/{clientId}/employees/create', [OfficeController::class, 'hrEmployeeCreate']);
$router->post('/office/hr/{clientId}/employees/create', [OfficeController::class, 'hrEmployeeStore']);
$router->get('/office/hr/{clientId}/employees/{employeeId}/edit', [OfficeController::class, 'hrEmployeeEdit']);
$router->post('/office/hr/{clientId}/employees/{employeeId}/update', [OfficeController::class, 'hrEmployeeUpdate']);
$router->get('/office/hr/{clientId}/contracts', [OfficeController::class, 'hrContracts']);
$router->get('/office/hr/{clientId}/contracts/create', [OfficeController::class, 'hrContractCreate']);
$router->post('/office/hr/{clientId}/contracts/create', [OfficeController::class, 'hrContractStore']);
$router->get('/office/hr/contracts/{id}/edit', [OfficeController::class, 'hrContractEdit']);
$router->post('/office/hr/contracts/{id}/update', [OfficeController::class, 'hrContractUpdate']);
$router->get('/office/hr/{clientId}/payroll', [OfficeController::class, 'hrPayrollList']);
$router->post('/office/hr/{clientId}/payroll/generate', [OfficeController::class, 'hrPayrollGenerate']);
$router->get('/office/hr/payroll/{id}', [OfficeController::class, 'hrPayrollDetail']);
$router->post('/office/hr/payroll/{id}/approve', [OfficeController::class, 'hrPayrollApprove']);
$router->get('/office/hr/payroll/{id}/pdf', [OfficeController::class, 'hrPayrollPdf']);
$router->get('/office/hr/payroll/entry/{id}/pdf', [OfficeController::class, 'hrPayslipPdf']);
$router->get('/office/hr/{clientId}/leaves', [OfficeController::class, 'hrLeaves']);
$router->get('/office/hr/{clientId}/leaves/create', [OfficeController::class, 'hrLeaveCreate']);
$router->post('/office/hr/{clientId}/leaves/create', [OfficeController::class, 'hrLeaveStore']);
$router->post('/office/hr/leaves/{id}/approve', [OfficeController::class, 'hrLeaveApprove']);
$router->post('/office/hr/leaves/{id}/reject', [OfficeController::class, 'hrLeaveReject']);
$router->get('/office/hr/{clientId}/declarations', [OfficeController::class, 'hrDeclarations']);
$router->post('/office/hr/{clientId}/declarations/generate', [OfficeController::class, 'hrDeclarationGenerate']);
$router->get('/office/hr/declarations/{id}/download', [OfficeController::class, 'hrDeclarationDownload']);

// ── Client Routes (/client) ────────────────────────
$router->get('/client', [ClientController::class, 'dashboard']);
$router->get('/client/invoices/detail', [ClientController::class, 'getInvoiceDetail']);
$router->get('/client/invoices/visualization', [ClientController::class, 'getInvoiceVisualization']);
$router->get('/client/invoices/pdf', [ClientController::class, 'purchaseInvoicePdf']);
$router->post('/client/invoices/bulk-pdf', [ClientController::class, 'purchaseInvoicesBulkPdf']);
$router->get('/client/bank-export/download/{filename}', [ClientController::class, 'bankExportDownload']);
$router->get('/client/bank-export/{batchId}', [ClientController::class, 'bankExport']);
$router->post('/client/bank-export/generate', [ClientController::class, 'bankExportGenerate']);
$router->get('/client/invoices/{batchId}', [ClientController::class, 'invoices']);
$router->post('/client/invoices/verify', [ClientController::class, 'verifyInvoice']);
$router->post('/client/invoices/bulk', [ClientController::class, 'bulkVerify']);
$router->get('/client/reports', [ClientController::class, 'reports']);
$router->get('/client/reports/{id}/download', [ClientController::class, 'downloadReport']);
$router->post('/client/import-ksef', [ClientController::class, 'importKsef']);
$router->get('/client/import-ksef-status', [ClientController::class, 'ksefImportStatus']);
$router->get('/client/ksef', [ClientController::class, 'ksefConfig']);
$router->post('/client/ksef/upload-cert', [ClientController::class, 'ksefUploadCert']);
$router->post('/client/ksef/upload-pem', [ClientController::class, 'ksefUploadPem']);
$router->post('/client/ksef/delete-cert', [ClientController::class, 'ksefDeleteCert']);
$router->post('/client/ksef/save-token', [ClientController::class, 'ksefSaveToken']);
$router->post('/client/ksef/environment', [ClientController::class, 'ksefSaveEnvironment']);
$router->post('/client/ksef/upo-toggle', [ClientController::class, 'ksefToggleUpo']);
$router->get('/client/ksef/test', [ClientController::class, 'ksefTestConnection']);
$router->get('/client/ksef/diagnostic', [ClientController::class, 'ksefDiagnostic']);
// Certificate enrollment disabled for clients - they must use certificates generated in KSeF system
// $router->post('/client/ksef/enroll-cert', [ClientController::class, 'ksefEnrollCert']);
// $router->get('/client/ksef/check-enrollment', [ClientController::class, 'ksefCheckEnrollment']);
$router->post('/client/ksef/delete-ksef-cert', [ClientController::class, 'ksefDeleteKsefCert']);
$router->get('/client/ksef/certificates', [ClientController::class, 'ksefCertificates']);
$router->get('/client/reports/rejected/{batchId}', [ClientController::class, 'downloadRejected']);
$router->get('/client/rodo-export', [ClientController::class, 'rodoExport']);
$router->get('/client/language', [ClientController::class, 'switchLanguage']);
$router->get('/client/notifications', [ClientController::class, 'notifications']);
$router->post('/client/notifications/read', [ClientController::class, 'notificationsMarkRead']);
$router->post('/client/invoices/bulk-mpk', [ClientController::class, 'bulkAssignCostCenter']);
$router->post('/client/invoices/comment', [ClientController::class, 'addComment']);
$router->get('/client/invoices/comments', [ClientController::class, 'getComments']);
$router->post('/client/invoices/toggle-paid', [ClientController::class, 'toggleInvoicePaid']);
$router->get('/client/security', [ClientController::class, 'security']);
$router->post('/client/account/delete', [ClientController::class, 'accountDelete']);

// Client: Messages & Tasks
$router->get('/client/messages', [ClientController::class, 'messages']);
$router->get('/client/messages/preferences', [ClientController::class, 'messageNotificationPrefs']);
$router->post('/client/messages/preferences', [ClientController::class, 'messageNotificationPrefs']);
$router->get('/client/messages/attachment/{id}', [ClientController::class, 'messageAttachment']);
$router->get('/client/messages/{id}', [ClientController::class, 'messageThread']);
$router->post('/client/messages/create', [ClientController::class, 'messageCreate']);
$router->post('/client/messages/{id}/reply', [ClientController::class, 'messageReply']);
$router->get('/client/tasks', [ClientController::class, 'tasks']);
$router->get('/client/tasks/attachment/{id}', [ClientController::class, 'taskAttachment']);
$router->post('/client/tasks/{id}/status', [ClientController::class, 'taskUpdateStatus']);

// Client: File sharing
$router->get('/client/files', [ClientController::class, 'files']);
$router->post('/client/files/upload', [ClientController::class, 'fileUpload']);
$router->get('/client/files/{id}/download', [ClientController::class, 'fileDownload']);
$router->post('/client/files/{id}/delete', [ClientController::class, 'fileDelete']);

// Tax payments
$router->get('/client/tax-payments', [ClientController::class, 'taxPayments']);

// Client: Tax Calendar (F1)
$router->get('/client/tax-calendar', [ClientController::class, 'taxCalendar']);
$router->get('/client/calculators', [ClientController::class, 'calculators']);

// Client: HR / Kadry i Płace (read-only + leave requests)
$router->get('/client/hr/employees', [ClientController::class, 'hrEmployees']);
$router->get('/client/hr/employees/create', [ClientController::class, 'hrEmployeeCreateForm']);
$router->post('/client/hr/employees/create', [ClientController::class, 'hrEmployeeStore']);
$router->get('/client/hr/employees/{id}/edit', [ClientController::class, 'hrEmployeeEditForm']);
$router->post('/client/hr/employees/{id}/update', [ClientController::class, 'hrEmployeeUpdate']);
$router->post('/client/hr/employees/{id}/delete', [ClientController::class, 'hrEmployeeDelete']);
$router->post('/client/hr/employees/{id}/resend-invitation', [ClientController::class, 'hrEmployeeResendInvitation']);
$router->get('/client/hr/payroll', [ClientController::class, 'hrPayrollLists']);
$router->get('/client/hr/payroll/{id}', [ClientController::class, 'hrPayrollDetail']);
$router->get('/client/hr/payroll/{id}/pdf', [ClientController::class, 'hrPayrollPdf']);
$router->get('/client/hr/leaves', [ClientController::class, 'hrLeaves']);
$router->post('/client/hr/leaves/request', [ClientController::class, 'hrLeaveRequest']);
$router->get('/client/hr/declarations', [ClientController::class, 'hrDeclarations']);
$router->get('/client/hr/declarations/{id}/download', [ClientController::class, 'hrDeclarationDownload']);

// Client: Duplicate check AJAX (F2)
$router->post('/client/sales/check-duplicate', [ClientController::class, 'checkSalesInvoiceDuplicate']);

// Client: Bank account utilities (F4, F5)
$router->post('/client/company/bank-account/identify-bank', [ClientController::class, 'bankAccountIdentifyBank']);
$router->post('/client/company/bank-account/whitelist-check', [ClientController::class, 'bankAccountWhitelistCheck']);

// Company profile
$router->get('/client/company', [ClientController::class, 'companyProfile']);
$router->get('/client/company/gus-lookup', [ClientController::class, 'companyGusLookup']);
$router->post('/client/company', [ClientController::class, 'companyProfileUpdate']);
$router->post('/client/company/logo', [ClientController::class, 'companyLogoUpload']);
$router->post('/client/company/logo/delete', [ClientController::class, 'companyLogoDelete']);
$router->post('/client/company/bank-account', [ClientController::class, 'bankAccountCreate']);
$router->post('/client/company/bank-account/{id}/delete', [ClientController::class, 'bankAccountDelete']);
$router->post('/client/company/bank-account/{id}/set-default', [ClientController::class, 'bankAccountSetDefault']);

// Services catalog
$router->get('/client/services', [ClientController::class, 'services']);
$router->post('/client/services/create', [ClientController::class, 'serviceCreate']);
$router->post('/client/services/{id}/update', [ClientController::class, 'serviceUpdate']);
$router->post('/client/services/{id}/delete', [ClientController::class, 'serviceDelete']);

// Contractors
$router->get('/client/contractors', [ClientController::class, 'contractors']);
$router->get('/client/contractors/create', [ClientController::class, 'contractorCreateForm']);
$router->post('/client/contractors/create', [ClientController::class, 'contractorCreate']);
$router->get('/client/contractors/import', [ClientController::class, 'contractorImportForm']);
$router->post('/client/contractors/import', [ClientController::class, 'contractorImport']);
$router->get('/client/contractors/import/template', [ClientController::class, 'contractorImportTemplate']);
$router->get('/client/contractors/{id}/edit', [ClientController::class, 'contractorEditForm']);
$router->post('/client/contractors/{id}/update', [ClientController::class, 'contractorUpdate']);
$router->post('/client/contractors/{id}/delete', [ClientController::class, 'contractorDelete']);
$router->post('/client/contractors/{id}/logo', [ClientController::class, 'contractorLogoUpload']);
$router->post('/client/contractors/{id}/logo/delete', [ClientController::class, 'contractorLogoDelete']);
$router->get('/client/contractors/search', [ClientController::class, 'contractorSearch']);
$router->get('/client/contractors/gus-lookup', [ClientController::class, 'contractorGusLookup']);

// Issued invoices (sales)
$router->get('/client/sales', [ClientController::class, 'issuedInvoices']);
$router->get('/client/sales/dashboard', [ClientController::class, 'salesDashboard']);
$router->get('/client/sales/create', [ClientController::class, 'issuedInvoiceCreate']);
$router->post('/client/sales/create', [ClientController::class, 'issuedInvoiceStore']);
$router->get('/client/sales/jpk', [ClientController::class, 'salesJpk']);
$router->get('/client/sales/report', [ClientController::class, 'salesReport']);
$router->get('/client/sales/email-settings', [ClientController::class, 'invoiceEmailSettings']);
$router->post('/client/sales/email-settings', [ClientController::class, 'invoiceEmailSettingsUpdate']);
$router->post('/client/sales/bulk-send-email', [ClientController::class, 'invoiceBulkSendEmail']);
$router->get('/client/sales/advance-invoices', [ClientController::class, 'getAdvanceInvoices']);
$router->get('/client/sales/{id}/send-email', [ClientController::class, 'invoiceEmailForm']);
$router->post('/client/sales/{id}/send-email', [ClientController::class, 'invoiceEmailSend']);
$router->get('/client/sales/{id}', [ClientController::class, 'issuedInvoiceView']);
$router->get('/client/sales/{id}/edit', [ClientController::class, 'issuedInvoiceEdit']);
$router->post('/client/sales/{id}/update', [ClientController::class, 'issuedInvoiceUpdate']);
$router->post('/client/sales/{id}/delete', [ClientController::class, 'issuedInvoiceDelete']);
$router->post('/client/sales/{id}/issue', [ClientController::class, 'issuedInvoiceIssue']);
$router->post('/client/sales/{id}/send-ksef', [ClientController::class, 'issuedInvoiceSendKsef']);
$router->post('/client/sales/bulk-pdf', [ClientController::class, 'salesBulkPdf']);
$router->post('/client/sales/bulk-send-ksef', [ClientController::class, 'bulkSendKsef']);
$router->post('/client/sales/ksef-backfill', [ClientController::class, 'ksefBackfill']);
$router->post('/client/invoices/ksef-backfill', [ClientController::class, 'ksefBackfillPurchase']);
$router->post('/client/invoices/whitelist-recheck', [ClientController::class, 'whitelistRecheck']);
$router->get('/client/ksef-send-status', [ClientController::class, 'ksefSendStatus']);
$router->get('/client/sales/{id}/pdf', [ClientController::class, 'issuedInvoicePdf']);
$router->get('/client/sales/{id}/upo', [ClientController::class, 'issuedInvoiceUpo']);
$router->post('/client/sales/{id}/duplicate', [ClientController::class, 'issuedInvoiceDuplicate']);
$router->get('/client/sales/{id}/correction', [ClientController::class, 'issuedInvoiceCorrection']);

// ── Contracts module ─────────────────────────────────────
// Office side
$router->get ('/office/contracts',                                [\App\Controllers\ContractController::class, 'dashboard']);
$router->get ('/office/contracts/templates',                      [\App\Controllers\ContractController::class, 'templatesIndex']);
$router->get ('/office/contracts/templates/upload',               [\App\Controllers\ContractController::class, 'templateUploadForm']);
$router->post('/office/contracts/templates/upload',               [\App\Controllers\ContractController::class, 'templateUpload']);
$router->get ('/office/contracts/templates/{id}/edit',            [\App\Controllers\ContractController::class, 'templateEdit']);
$router->post('/office/contracts/templates/{id}/update',          [\App\Controllers\ContractController::class, 'templateUpdate']);
$router->post('/office/contracts/templates/{id}/delete',          [\App\Controllers\ContractController::class, 'templateDelete']);
$router->get ('/office/contracts/templates/{id}/preview',         [\App\Controllers\ContractController::class, 'templatePreview']);
$router->get ('/office/contracts/templates/{id}/issue',           [\App\Controllers\ContractController::class, 'formCreateForm']);
$router->post('/office/contracts/templates/{id}/issue',           [\App\Controllers\ContractController::class, 'formStore']);
$router->get ('/office/contracts/forms',                          [\App\Controllers\ContractController::class, 'formsIndex']);
$router->get ('/office/contracts/forms/{id}',                     [\App\Controllers\ContractController::class, 'formDetail']);
$router->post('/office/contracts/forms/{id}/cancel',              [\App\Controllers\ContractController::class, 'formCancel']);
$router->get ('/office/contracts/forms/{id}/filled.pdf',          [\App\Controllers\ContractController::class, 'downloadFilledPdf']);
$router->get ('/office/contracts/forms/{id}/signed.pdf',          [\App\Controllers\ContractController::class, 'downloadSignedPdf']);
// Public token-based form
$router->get ('/contracts/form/{token}',                          [\App\Controllers\PublicContractFormController::class, 'formView']);
$router->post('/contracts/form/submit',                           [\App\Controllers\PublicContractFormController::class, 'formSubmit']);
// Client panel — list of own forms
$router->get ('/client/contracts',                                [ClientController::class, 'contractsIndex']);
// SIGNIUS webhook (HMAC-verified inside controller)
$router->post('/webhooks/signius',                                [\App\Controllers\SigniusWebhookController::class, 'handle']);

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
