<?php

/**
 * HR/Payroll Module Routes
 * Included from public/index.php
 */

use App\Controllers\HrController;
use App\Controllers\HrClientController;
use App\Controllers\HrContractLeaveController;
use App\Controllers\HrPayrollController;
use App\Controllers\HrDocumentOnboardingController;
use App\Controllers\HrDeclarationsController;
use App\Controllers\HrReportsController;
use App\Controllers\HrAttendanceController;
use App\Controllers\HrAnalyticsController;
use App\Controllers\HrPpkController;
use App\Controllers\HrBatchController;
use App\Controllers\HrComplianceController;
use App\Controllers\HrPfronController;
use App\Controllers\HrGusController;
use App\Controllers\HrMassOperationsController;

// HR multi-client dashboard
$router->get('/office/hr/dashboard',                       [HrBatchController::class, 'dashboard']);
$router->get('/office/hr/compliance',                      [HrBatchController::class, 'compliance']);

// HR global settings
$router->get('/office/hr/settings',                        [HrController::class, 'settings']);
$router->post('/office/hr/settings/enable-all',            [HrController::class, 'settingsEnableAll']);
$router->post('/office/hr/settings/disable-all',           [HrController::class, 'settingsDisableAll']);
$router->post('/office/hr/settings/toggle/{clientId}',     [HrController::class, 'settingsToggleClient']);

// HR client-specific settings
$router->get('/office/hr/{clientId}/settings',             [HrController::class, 'clientSettings']);
$router->post('/office/hr/{clientId}/settings',            [HrController::class, 'clientSettingsSave']);

// Employee registry
$router->get('/office/hr/{clientId}/employees',                         [HrController::class, 'employees']);
$router->get('/office/hr/{clientId}/employees/create',                  [HrController::class, 'employeeCreateForm']);
$router->post('/office/hr/{clientId}/employees/create',                 [HrController::class, 'employeeCreate']);
$router->get('/office/hr/{clientId}/employees/{id}',                    [HrController::class, 'employeeDetail']);
$router->get('/office/hr/{clientId}/employees/{id}/edit',               [HrController::class, 'employeeEditForm']);
$router->post('/office/hr/{clientId}/employees/{id}/edit',              [HrController::class, 'employeeEdit']);
$router->post('/office/hr/{clientId}/employees/{id}/archive',           [HrController::class, 'employeeArchive']);

// Onboarding/Offboarding
$router->get('/office/hr/{clientId}/employees/{empId}/onboarding',             [HrDocumentOnboardingController::class, 'onboarding']);
$router->post('/office/hr/{clientId}/employees/{empId}/onboarding/{taskId}',   [HrDocumentOnboardingController::class, 'onboardingToggle']);

// Archive with reason + PDF
$router->get('/office/hr/{clientId}/employees/{id}/archive-form',        [HrController::class, 'employeeArchiveForm']);
$router->post('/office/hr/{clientId}/employees/{id}/archive-confirm',    [HrController::class, 'employeeArchiveConfirm']);
$router->get('/office/hr/{clientId}/employees/{id}/swiadectwo.pdf',      [HrController::class, 'swiadectwoPdf']);
$router->get('/office/hr/{clientId}/employees/{empId}/contracts/{contractId}/pdf', [HrController::class, 'contractPdf']);

// Contracts
$router->get('/office/hr/{clientId}/employees/{empId}/contracts/create',  [HrContractLeaveController::class, 'contractCreateForm']);
$router->post('/office/hr/{clientId}/employees/{empId}/contracts/create', [HrContractLeaveController::class, 'contractCreate']);
$router->post('/office/hr/{clientId}/employees/{empId}/contracts/{contractId}/terminate', [HrContractLeaveController::class, 'contractTerminate']);

// Leave management
$router->get('/office/hr/{clientId}/leaves',                 [HrContractLeaveController::class, 'leaveRequests']);
$router->post('/office/hr/{clientId}/leaves/create',         [HrContractLeaveController::class, 'leaveCreate']);
$router->post('/office/hr/{clientId}/leaves/{id}/approve',   [HrContractLeaveController::class, 'leaveApprove']);
$router->post('/office/hr/{clientId}/leaves/{id}/reject',    [HrContractLeaveController::class, 'leaveReject']);
$router->get('/office/hr/{clientId}/leaves/calendar',        [HrContractLeaveController::class, 'leaveCalendar']);

// Payroll
$router->get('/office/hr/{clientId}/payroll',                              [HrPayrollController::class, 'payrollList']);
$router->post('/office/hr/{clientId}/payroll',                             [HrPayrollController::class, 'payrollCreate']);
$router->get('/office/hr/{clientId}/payroll/{runId}',                      [HrPayrollController::class, 'payrollRun']);
$router->post('/office/hr/{clientId}/payroll/{runId}',                     [HrPayrollController::class, 'payrollRun']);
$router->post('/office/hr/{clientId}/payroll/{runId}/calculate',           [HrPayrollController::class, 'payrollCalculate']);
$router->post('/office/hr/{clientId}/payroll/{runId}/approve',             [HrPayrollController::class, 'payrollApprove']);
$router->post('/office/hr/{clientId}/payroll/{runId}/lock',                [HrPayrollController::class, 'payrollLock']);
$router->post('/office/hr/{clientId}/payroll/{runId}/unlock',              [HrPayrollController::class, 'payrollUnlock']);
$router->post('/office/hr/{clientId}/payroll/{runId}/correction',          [HrPayrollController::class, 'payrollCorrectionCreate']);
$router->get('/office/hr/{clientId}/payroll/{runId}/payslip/{empId}',      [HrPayrollController::class, 'payslipPdf']);

// ZUS declarations
$router->get('/office/hr/{clientId}/zus',                                  [HrDeclarationsController::class, 'zusDeclarations']);
$router->post('/office/hr/{clientId}/zus',                                 [HrDeclarationsController::class, 'zusGenerate']);
$router->get('/office/hr/{clientId}/zus/{declarationId}/download',         [HrDeclarationsController::class, 'zusDownload']);
$router->post('/office/hr/{clientId}/zus/{declarationId}/regenerate',      [HrDeclarationsController::class, 'zusRegenerate']);

// PIT declarations
$router->get('/office/hr/{clientId}/pit',                              [HrDeclarationsController::class, 'pitDeclarations']);
$router->post('/office/hr/{clientId}/pit',                             [HrDeclarationsController::class, 'pitGenerate']);
$router->get('/office/hr/{clientId}/pit/{declarationId}/download',     [HrDeclarationsController::class, 'pitDownload']);

// HR Reports
$router->get('/office/hr/{clientId}/reports',                          [HrReportsController::class, 'hrReports']);
$router->get('/office/hr/{clientId}/reports/monthly-excel',            [HrReportsController::class, 'hrReportExportMonthly']);
$router->get('/office/hr/{clientId}/reports/annual-excel',             [HrReportsController::class, 'hrReportExportAnnual']);

// e-Teczka documents
$router->get('/office/hr/{clientId}/employees/{id}/documents',                              [HrDocumentOnboardingController::class, 'documentList']);
$router->post('/office/hr/{clientId}/employees/{id}/documents/upload',                     [HrDocumentOnboardingController::class, 'documentUpload']);
$router->get('/office/hr/{clientId}/employees/{id}/documents/{docId}/download',            [HrDocumentOnboardingController::class, 'documentDownload']);
$router->post('/office/hr/{clientId}/employees/{id}/documents/{docId}/delete',             [HrDocumentOnboardingController::class, 'documentDelete']);

// Calculator + Budget
$router->get('/office/hr/calculator',  [HrReportsController::class, 'calculator']);
$router->post('/office/hr/calculator', [HrReportsController::class, 'calculatorResult']);
$router->get('/office/hr/{clientId}/budget',              [HrReportsController::class, 'budget']);
$router->post('/office/hr/{clientId}/budget',             [HrReportsController::class, 'budgetSave']);
$router->get('/office/hr/{clientId}/budget/export-excel', [HrReportsController::class, 'budgetExportExcel']);

// PPK
$router->get('/office/hr/{clientId}/ppk',                      [HrPpkController::class, 'ppkManagement']);
$router->post('/office/hr/{clientId}/ppk/{empId}/enroll',      [HrPpkController::class, 'ppkEnroll']);
$router->post('/office/hr/{clientId}/ppk/{empId}/opt-out',     [HrPpkController::class, 'ppkOptOut']);
$router->get('/office/hr/{clientId}/ppk/export',               [HrPpkController::class, 'ppkReportExport']);

// Analytics
$router->get('/office/hr/{clientId}/analytics', [HrAnalyticsController::class, 'analytics']);

// Attendance
$router->get('/office/hr/{clientId}/attendance',                   [HrAttendanceController::class, 'attendance']);
$router->post('/office/hr/{clientId}/attendance',                  [HrAttendanceController::class, 'attendanceSave']);
$router->get('/office/hr/{clientId}/attendance/pdf',               [HrAttendanceController::class, 'attendanceExportPdf']);
$router->post('/office/hr/{clientId}/attendance/inject-overtime',  [HrAttendanceController::class, 'attendanceInjectOvertime']);

// BHP + Medical
$router->get('/office/hr/{clientId}/bhp',                    [HrComplianceController::class, 'bhpTraining']);
$router->post('/office/hr/{clientId}/bhp/create',            [HrComplianceController::class, 'bhpTrainingCreate']);
$router->get('/office/hr/{clientId}/medical',                [HrComplianceController::class, 'medicalExams']);
$router->post('/office/hr/{clientId}/medical/create',        [HrComplianceController::class, 'medicalExamCreate']);

// PFRON
$router->get('/office/hr/{clientId}/pfron-decl',             [HrPfronController::class, 'pfron']);
$router->post('/office/hr/{clientId}/pfron-decl/calculate',  [HrPfronController::class, 'pfronCalculate']);

// GUS + Company Documents
$router->get('/office/hr/{clientId}/gus',                             [HrGusController::class, 'gusReports']);
$router->get('/office/hr/{clientId}/company-docs',                    [HrGusController::class, 'companyDocuments']);
$router->post('/office/hr/{clientId}/company-docs/upload',            [HrGusController::class, 'companyDocumentUpload']);
$router->get('/office/hr/{clientId}/company-docs/{docId}/download',   [HrGusController::class, 'companyDocumentDownload']);
$router->post('/office/hr/{clientId}/company-docs/{docId}/delete',    [HrGusController::class, 'companyDocumentDelete']);

// Mass Operations
$router->get('/office/hr/{clientId}/mass-salary',            [HrMassOperationsController::class, 'massSalaryUpdate']);
$router->post('/office/hr/{clientId}/mass-salary/apply',     [HrMassOperationsController::class, 'massSalaryApply']);
$router->get('/office/hr/{clientId}/mass-export',            [HrMassOperationsController::class, 'massExport']);

// Client HR routes
$router->get('/client/hr',                                [HrClientController::class, 'dashboard']);
$router->get('/client/hr/employees',                      [HrClientController::class, 'employees']);
$router->get('/client/hr/employees/{id}',                 [HrClientController::class, 'employeeDetail']);
$router->get('/client/hr/leaves',                         [HrClientController::class, 'leaveRequests']);
$router->post('/client/hr/leaves/create',                 [HrClientController::class, 'leaveCreate']);
$router->post('/client/hr/leaves/{id}/approve',           [HrClientController::class, 'leaveApprove']);
$router->post('/client/hr/leaves/{id}/reject',            [HrClientController::class, 'leaveReject']);
$router->post('/client/hr/leaves/{id}/cancel',            [HrClientController::class, 'leaveCancel']);
$router->get('/client/hr/leaves/calendar',                [HrClientController::class, 'leaveCalendar']);
$router->get('/client/hr/attendance',                     [HrClientController::class, 'attendance']);
$router->get('/client/hr/contracts',                      [HrClientController::class, 'contracts']);
$router->get('/client/hr/contracts/{contractId}/pdf',     [HrClientController::class, 'contractPdf']);
$router->get('/client/hr/costs',                          [HrClientController::class, 'costs']);
$router->get('/client/hr/employees/{empId}/documents',    [HrClientController::class, 'documents']);
$router->get('/client/hr/employees/{empId}/documents/{docId}/download', [HrClientController::class, 'documentDownload']);
$router->post('/client/hr/employees/{empId}/documents/upload', [HrClientController::class, 'documentUpload']);
$router->get('/client/hr/employees/{empId}/onboarding',   [HrClientController::class, 'onboarding']);
$router->get('/client/hr/analytics',                      [HrClientController::class, 'analytics']);
$router->get('/client/hr/messages',                       [HrClientController::class, 'messages']);
$router->post('/client/hr/messages/create',               [HrClientController::class, 'messageCreate']);
$router->get('/client/hr/payslips',                       [HrClientController::class, 'payslips']);
$router->get('/client/hr/payslips/{runId}/{empId}/pdf',   [HrClientController::class, 'payslipPdf']);
