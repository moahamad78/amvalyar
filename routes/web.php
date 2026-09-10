<?php

declare(strict_types=1);

use App\Http\Controllers\AssetAttributeController;
use App\Http\Controllers\AssetCategoryApprovalRouteController;
use App\Http\Controllers\AssetCodeFormulaSettingsController;
use App\Http\Controllers\AssetCodeMasterDataController;
use App\Http\Controllers\AssetCodeSettingsController;
use App\Http\Controllers\AssetCompletenessQueueController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetManagerCompletionController;
use App\Http\Controllers\AssetManagerRequestController;
use App\Http\Controllers\AssetMovementRequestController;
use App\Http\Controllers\AssetPlatePrintController;
use App\Http\Controllers\AssetPlateTemplateController;
use App\Http\Controllers\AssetReferenceController;
use App\Http\Controllers\AssetRepairReportController;
use App\Http\Controllers\AssetRepairRequestController;
use App\Http\Controllers\AssetScannerController;
use App\Http\Controllers\AssetTransactionController;
use App\Http\Controllers\AssetTypeSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BulkImportController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyStorageProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryDisputeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinalWarehouseDeliveryController;
use App\Http\Controllers\FinalWarehousePlatePrintController;
use App\Http\Controllers\InventoryRequestController;
use App\Http\Controllers\InitialAssetSetupController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MyApprovalController;
use App\Http\Controllers\OperationalAlertController;
use App\Http\Controllers\OrganizationalAssetController;
use App\Http\Controllers\PublicSupportTicketController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SpecialistApprovalController;
use App\Http\Controllers\StocktakeController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaskCenterController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseRecoveryController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowStepController;
use App\Http\Middleware\EnsureActiveLoginSession;
use App\Http\Middleware\EnsureAssetManagerAssetContext;
use App\Http\Middleware\EnsureAssetManagerReadyForApproval;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\FinalizeRequesterReceiptOnApproval;
use App\Http\Middleware\FinalizeWarehouseDeliveryOnApproval;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route section
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('/support/requests', [PublicSupportTicketController::class, 'store'])
    ->middleware('throttle:4,10')
    ->name('support-tickets.store');

Route::middleware([
    EnsureActiveLoginSession::class,
    EnsureSuperAdmin::class,
])->group(function (): void {
    Route::get('/admin/support-requests', [SupportTicketController::class, 'index'])
        ->name('support-tickets.index');
    Route::patch('/admin/support-requests/{supportTicket}', [SupportTicketController::class, 'update'])
        ->name('support-tickets.update');
});

/*
|--------------------------------------------------------------------------
| Route section
|--------------------------------------------------------------------------
*/

Route::get('/login', [
    LoginController::class,
    'show',
])
    ->name('login');

Route::post('/login', [
    LoginController::class,
    'login',
])
    ->middleware('throttle:5,1')
    ->name('login.authenticate');

Route::post('/logout', [
    LogoutController::class,
    'logout',
])
    ->middleware(EnsureActiveLoginSession::class)
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Route section
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [
    DashboardController::class,
    'index',
])
    ->middleware(EnsureActiveLoginSession::class)
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Route section
|--------------------------------------------------------------------------
*/

Route::get('/users', [
    UserController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:users.view',
    ])
    ->name('users.index');

Route::get('/users/create', [
    UserController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:users.create',
    ])
    ->name('users.create');

Route::get('/users-export', [UserController::class, 'export'])
    ->middleware([EnsureActiveLoginSession::class, 'permission:users.view', 'throttle:10,1'])->name('users.export');

Route::middleware(EnsureActiveLoginSession::class)->group(function () {
    Route::get('/workspace/preferences', [\App\Http\Controllers\WorkspaceController::class, 'preferences'])->name('workspace.preferences');
    Route::post('/workspace/preferences', [\App\Http\Controllers\WorkspaceController::class, 'saveTheme'])->name('workspace.theme');
    Route::get('/workspace/history', [\App\Http\Controllers\WorkspaceController::class, 'history'])->name('workspace.history');
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/workspace/charts', [\App\Http\Controllers\WorkspaceController::class, 'charts'])->name('workspace.charts');
        Route::post('/workspace/charts', [\App\Http\Controllers\WorkspaceController::class, 'saveChart'])->name('workspace.charts.store');
        Route::delete('/workspace/charts/{id}', [\App\Http\Controllers\WorkspaceController::class, 'deleteChart'])->name('workspace.charts.destroy');
    });
});

Route::post('/users', [
    UserController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:users.create',
    ])
    ->name('users.store');

Route::get('/users/{user}/edit', [
    UserController::class,
    'edit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:users.edit',
    ])
    ->name('users.edit');

Route::put('/users/{user}', [
    UserController::class,
    'update',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:users.edit',
    ])
    ->name('users.update');

Route::delete('/users/{user}', [
    UserController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:users.delete',
    ])
    ->name('users.destroy');

/*
|--------------------------------------------------------------------------
| Route section
|--------------------------------------------------------------------------
*/

Route::get('/roles', [
    RoleController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:roles.view',
    ])
    ->name('roles.index');

Route::get('/roles/create', [
    RoleController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:roles.create',
    ])
    ->name('roles.create');

Route::post('/roles', [
    RoleController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:roles.create',
    ])
    ->name('roles.store');

Route::get('/roles/{role}/edit', [
    RoleController::class,
    'edit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:roles.edit',
    ])
    ->name('roles.edit');

Route::put('/roles/{role}', [
    RoleController::class,
    'update',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:roles.edit',
    ])
    ->name('roles.update');

Route::delete('/roles/{role}', [
    RoleController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:roles.delete',
    ])
    ->name('roles.destroy');

Route::resource(
    'companies',
    CompanyController::class
)
    ->middleware([
        EnsureActiveLoginSession::class,
        EnsureSuperAdmin::class,
    ]);

/*
|--------------------------------------------------------------------------
| Assets - Permission Protected
|--------------------------------------------------------------------------
*/

Route::get('/inventory-assets', [
    AssetController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.view',
    ])
    ->name('assets.index');

Route::get('/asset-scanner', [AssetScannerController::class, 'index'])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.view',
    ])
    ->name('asset-scanner.index');

Route::post('/asset-scanner/lookup', [AssetScannerController::class, 'lookup'])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.view',
        'throttle:30,1',
    ])
    ->name('asset-scanner.lookup');

Route::get('/inventory-assets/create', [
    AssetController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.create',
    ])
    ->name('assets.create');

Route::post('/inventory-assets', [
    AssetController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.create',
    ])
    ->name('assets.store');

Route::get('/inventory-assets/{asset}', [
    AssetController::class,
    'show',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.view',
    ])
    ->name('assets.show');

Route::get('/inventory-assets/{asset}/edit', [
    AssetController::class,
    'edit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.edit',
    ])
    ->name('assets.edit');

Route::match(
    ['put', 'patch'],
    '/inventory-assets/{asset}',
    [
        AssetController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.edit',
    ])
    ->name('assets.update');

Route::delete('/inventory-assets/{asset}', [
    AssetController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.delete',
    ])
    ->name('assets.destroy');

/*
|--------------------------------------------------------------------------
| Asset Transactions - Permission Protected
|--------------------------------------------------------------------------
*/

Route::get('/asset-transactions', [
    AssetTransactionController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.view',
    ])
    ->name('asset-transactions.index');

/*
 * Legacy direct-write endpoints are intentionally retained only as
 * compatibility guards.
 *
 * All new Transfer / Return / Disposal operations must be submitted through
 * AssetMovementRequest and its approval workflow. Initial delivery is handled
 * by the inventory-request final-delivery workflow.
 *
 * Keeping the old route names avoids silently breaking old bookmarks/forms,
 * while making the historical direct mutation controller unreachable.
 */
Route::get('/asset-transactions/create', function () {
    return redirect()
        ->route('asset-movement-requests.create');
})
    ->middleware([
        EnsureActiveLoginSession::class,
    ])
    ->name('asset-transactions.create');

Route::post('/asset-transactions', function () {
    abort(
        410,
        'ثبت مستقیم گردش اموال غیرفعال شده است. عملیات را از مسیر درخواست جابه‌جایی اموال و گردش تأیید انجام دهید.'
    );
})
    ->middleware([
        EnsureActiveLoginSession::class,
    ])
    ->name('asset-transactions.store');

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
*/

Route::get('/reports', [
    ReportController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:reports.view',
    ])
    ->name('reports.index');

Route::get('/reports/export', ReportExportController::class)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:reports.export',
    ])
    ->name('reports.export');

Route::get('/reports/repairs', AssetRepairReportController::class)
    ->middleware([EnsureActiveLoginSession::class, 'permission:reports.view'])
    ->name('reports.repairs');

/*
|--------------------------------------------------------------------------
| Sites - Organization Structure
|--------------------------------------------------------------------------
*/

Route::get('/sites', [
    SiteController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:sites.view',
    ])
    ->name('sites.index');

Route::get('/sites/create', [
    SiteController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:sites.create',
    ])
    ->name('sites.create');

Route::post('/sites', [
    SiteController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:sites.create',
    ])
    ->name('sites.store');

Route::get('/sites/{site}/edit', [
    SiteController::class,
    'edit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:sites.edit',
    ])
    ->name('sites.edit');

Route::match(
    ['put', 'patch'],
    '/sites/{site}',
    [
        SiteController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:sites.edit',
    ])
    ->name('sites.update');

Route::delete('/sites/{site}', [
    SiteController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:sites.delete',
    ])
    ->name('sites.destroy');

/*
|--------------------------------------------------------------------------
| Departments - Organization Structure
|--------------------------------------------------------------------------
*/

Route::get('/departments', [
    DepartmentController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:departments.view',
    ])
    ->name('departments.index');

Route::get('/departments/create', [
    DepartmentController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:departments.create',
    ])
    ->name('departments.create');

Route::post('/departments', [
    DepartmentController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:departments.create',
    ])
    ->name('departments.store');

Route::get('/departments/{department}/edit', [
    DepartmentController::class,
    'edit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:departments.edit',
    ])
    ->name('departments.edit');

Route::match(
    ['put', 'patch'],
    '/departments/{department}',
    [
        DepartmentController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:departments.edit',
    ])
    ->name('departments.update');

Route::delete('/departments/{department}', [
    DepartmentController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:departments.delete',
    ])
    ->name('departments.destroy');

/*
|--------------------------------------------------------------------------
| Locations - Organization Structure
|--------------------------------------------------------------------------
*/

Route::get('/locations', [
    LocationController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:locations.view',
    ])
    ->name('locations.index');

Route::get('/locations/create', [
    LocationController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:locations.create',
    ])
    ->name('locations.create');

Route::post('/locations', [
    LocationController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:locations.create',
    ])
    ->name('locations.store');

Route::get('/locations/{location}/edit', [
    LocationController::class,
    'edit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:locations.edit',
    ])
    ->name('locations.edit');

Route::match(
    ['put', 'patch'],
    '/locations/{location}',
    [
        LocationController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:locations.edit',
    ])
    ->name('locations.update');

Route::delete('/locations/{location}', [
    LocationController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:locations.delete',
    ])
    ->name('locations.destroy');

/*
|--------------------------------------------------------------------------
| Employees - Organization Structure
|--------------------------------------------------------------------------
*/

Route::get('/employees', [
    EmployeeController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:employees.view',
    ])
    ->name('employees.index');

Route::get('/employees/create', [
    EmployeeController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:employees.create',
    ])
    ->name('employees.create');

Route::post('/employees', [
    EmployeeController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:employees.create',
    ])
    ->name('employees.store');

Route::get('/employees/{employee}/edit', [
    EmployeeController::class,
    'edit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:employees.edit',
    ])
    ->name('employees.edit');

Route::match(
    ['put', 'patch'],
    '/employees/{employee}',
    [
        EmployeeController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:employees.edit',
    ])
    ->name('employees.update');

Route::delete('/employees/{employee}', [
    EmployeeController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:employees.delete',
    ])
    ->name('employees.destroy');

/*
|--------------------------------------------------------------------------
| Workflow Designer
|--------------------------------------------------------------------------
*/

Route::get('/workflows', [
    WorkflowController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.view',
    ])
    ->name('workflows.index');

Route::get('/workflows/create', [
    WorkflowController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.create',
    ])
    ->name('workflows.create');

Route::post('/workflows', [
    WorkflowController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.create',
    ])
    ->name('workflows.store');

Route::get('/workflows/{workflow}/edit', [
    WorkflowController::class,
    'edit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.edit',
    ])
    ->name('workflows.edit');

Route::match(
    ['put', 'patch'],
    '/workflows/{workflow}',
    [
        WorkflowController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.edit',
    ])
    ->name('workflows.update');

Route::delete('/workflows/{workflow}', [
    WorkflowController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.delete',
    ])
    ->name('workflows.destroy');

Route::post('/workflows/{workflow}/steps', [
    WorkflowStepController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.edit',
    ])
    ->name('workflows.steps.store');

Route::put('/workflows/{workflow}/steps/{step}', [
    WorkflowStepController::class,
    'update',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.edit',
    ])
    ->name('workflows.steps.update');

Route::delete('/workflows/{workflow}/steps/{step}', [
    WorkflowStepController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.edit',
    ])
    ->name('workflows.steps.destroy');

Route::post('/workflows/{workflow}/steps/reorder', [
    WorkflowStepController::class,
    'reorder',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.edit',
    ])
    ->name('workflows.steps.reorder');

/*
|--------------------------------------------------------------------------
| My Approvals
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Specialist Approval Route Settings
|--------------------------------------------------------------------------
*/

Route::get('/asset-settings/specialist-approval-routes', [
    AssetCategoryApprovalRouteController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.view',
    ])
    ->name('asset-settings.specialist-approval-routes.index');

Route::post('/asset-settings/specialist-approval-routes', [
    AssetCategoryApprovalRouteController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.edit',
    ])
    ->name('asset-settings.specialist-approval-routes.store');

Route::put('/asset-settings/specialist-approval-routes/{approvalRoute}', [
    AssetCategoryApprovalRouteController::class,
    'update',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.edit',
    ])
    ->name('asset-settings.specialist-approval-routes.update');

Route::delete('/asset-settings/specialist-approval-routes/{approvalRoute}', [
    AssetCategoryApprovalRouteController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:workflows.edit',
    ])
    ->name('asset-settings.specialist-approval-routes.destroy');

/*
|--------------------------------------------------------------------------
| Delivery Disputes
|--------------------------------------------------------------------------
*/

Route::get('/delivery-disputes', [
    DeliveryDisputeController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name('delivery-disputes.index');

Route::get('/delivery-disputes/{deliveryDispute}', [
    DeliveryDisputeController::class,
    'show',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name('delivery-disputes.show');

Route::post('/approvals/{step}/delivery-dispute', [
    DeliveryDisputeController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name('delivery-disputes.store');
Route::post('/delivery-disputes/{deliveryDispute}/warehouse-receive', [
    DeliveryDisputeController::class,
    'warehouseReceive',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name('delivery-disputes.warehouse-receive');
Route::post('/delivery-disputes/{deliveryDispute}/allocate-replacements', [
    DeliveryDisputeController::class,
    'allocateReplacements',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name('delivery-disputes.allocate-replacements');
Route::post('/delivery-disputes/{deliveryDispute}/redeliver-replacement', [
    DeliveryDisputeController::class,
    'redeliverReplacement',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name('delivery-disputes.redeliver-replacement');

Route::get('/approvals', [
    MyApprovalController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.view',
    ])
    ->name('approvals.index');

Route::get('/approvals/{step}', [
    MyApprovalController::class,
    'show',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.view',
    ])
    ->name('approvals.show');

Route::post('/approvals/{step}/act', [
    MyApprovalController::class,
    'act',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
        EnsureAssetManagerReadyForApproval::class,
        FinalizeWarehouseDeliveryOnApproval::class,
        FinalizeRequesterReceiptOnApproval::class,
    ])
    ->name('approvals.act');

/*
|--------------------------------------------------------------------------
| Inventory Requests
|--------------------------------------------------------------------------
*/

Route::get('/inventory-requests', [
    InventoryRequestController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:inventory_requests.view',
    ])
    ->name('inventory-requests.index');

Route::get('/inventory-requests/create', [
    InventoryRequestController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:inventory_requests.create',
    ])
    ->name('inventory-requests.create');

Route::post('/inventory-requests', [
    InventoryRequestController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:inventory_requests.create',
    ])
    ->name('inventory-requests.store');

Route::get('/inventory-requests/{inventoryRequest}/edit', [
    InventoryRequestController::class,
    'edit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:inventory_requests.edit',
    ])
    ->name('inventory-requests.edit');

Route::match(
    ['put', 'patch'],
    '/inventory-requests/{inventoryRequest}',
    [
        InventoryRequestController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:inventory_requests.edit',
    ])
    ->name('inventory-requests.update');

Route::delete('/inventory-requests/{inventoryRequest}', [
    InventoryRequestController::class,
    'destroy',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:inventory_requests.delete',
    ])
    ->name('inventory-requests.destroy');

Route::post('/inventory-requests/{inventoryRequest}/submit', [
    InventoryRequestController::class,
    'submit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:inventory_requests.submit',
    ])
    ->name('inventory-requests.submit');

Route::post(
    '/approvals/{step}/warehouse-allocations',
    [
        MyApprovalController::class,
        'saveWarehouseAllocations',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name(
        'approvals.warehouse-allocations.store'
    );

/*
|--------------------------------------------------------------------------
| Asset Identity Settings
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Asset Code Policy Settings
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Asset Code Master Data
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Asset Code Formula Designer
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Unified Asset Code Settings
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Asset Plate Template Designer
|--------------------------------------------------------------------------
*/

Route::get(
    '/asset-settings/plate-templates',
    [
        AssetPlateTemplateController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.plate-templates.index'
    );

Route::get(
    '/asset-settings/plate-templates/create',
    [
        AssetPlateTemplateController::class,
        'create',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.plate-templates.create'
    );

Route::post(
    '/asset-settings/plate-templates',
    [
        AssetPlateTemplateController::class,
        'store',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.plate-templates.store'
    );

Route::get(
    '/asset-settings/plate-templates/{plateTemplate}/edit',
    [
        AssetPlateTemplateController::class,
        'edit',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.plate-templates.edit'
    );

Route::match(
    ['put', 'patch'],
    '/asset-settings/plate-templates/{plateTemplate}',
    [
        AssetPlateTemplateController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.plate-templates.update'
    );

Route::delete(
    '/asset-settings/plate-templates/{plateTemplate}',
    [
        AssetPlateTemplateController::class,
        'destroy',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.plate-templates.destroy'
    );

Route::get(
    '/asset-settings/code',
    [
        AssetCodeSettingsController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.code.index'
    );

Route::get(
    '/asset-settings/code-formula',
    [
        AssetCodeFormulaSettingsController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.code-formula.index'
    );

Route::match(
    ['put', 'patch'],
    '/asset-settings/code-formula',
    [
        AssetCodeFormulaSettingsController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.code-formula.update'
    );

Route::get(
    '/asset-settings/code-master-data',
    [
        AssetCodeMasterDataController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.code-master-data.index'
    );

Route::match(
    ['put', 'patch'],
    '/asset-settings/code-master-data',
    [
        AssetCodeMasterDataController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.code-master-data.update'
    );

Route::get(
    '/asset-settings/code-policy',
    fn () => redirect()->route(
        'asset-settings.code.index'
    )
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.code-policy.index'
    );

Route::get(
    '/asset-settings/types',
    [
        AssetTypeSettingsController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.types.index'
    );

Route::get(
    '/asset-settings/types/{assetType}/attributes',
    [
        AssetAttributeController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_attributes.manage',
    ])
    ->name(
        'asset-settings.attributes.index'
    );

Route::get(
    '/asset-settings/types/{assetType}/attributes/create',
    [
        AssetAttributeController::class,
        'create',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_attributes.manage',
    ])
    ->name(
        'asset-settings.attributes.create'
    );

Route::post(
    '/asset-settings/types/{assetType}/attributes',
    [
        AssetAttributeController::class,
        'store',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_attributes.manage',
    ])
    ->name(
        'asset-settings.attributes.store'
    );

Route::get(
    '/asset-settings/types/{assetType}/attributes/{attribute}/edit',
    [
        AssetAttributeController::class,
        'edit',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_attributes.manage',
    ])
    ->name(
        'asset-settings.attributes.edit'
    );

Route::match(
    ['put', 'patch'],
    '/asset-settings/types/{assetType}/attributes/{attribute}',
    [
        AssetAttributeController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_attributes.manage',
    ])
    ->name(
        'asset-settings.attributes.update'
    );

Route::delete(
    '/asset-settings/types/{assetType}/attributes/{attribute}',
    [
        AssetAttributeController::class,
        'destroy',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_attributes.manage',
    ])
    ->name(
        'asset-settings.attributes.destroy'
    );

/*
|--------------------------------------------------------------------------
| Asset Type Management
|--------------------------------------------------------------------------
*/

Route::get(
    '/asset-settings/types/create',
    [
        AssetTypeSettingsController::class,
        'create',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.types.create'
    );

Route::post(
    '/asset-settings/types',
    [
        AssetTypeSettingsController::class,
        'store',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.types.store'
    );

Route::get(
    '/asset-settings/types/{assetType}/edit',
    [
        AssetTypeSettingsController::class,
        'edit',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.types.edit'
    );

Route::match(
    ['put', 'patch'],
    '/asset-settings/types/{assetType}',
    [
        AssetTypeSettingsController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.types.update'
    );

Route::delete(
    '/asset-settings/types/{assetType}',
    [
        AssetTypeSettingsController::class,
        'destroy',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_types.manage',
    ])
    ->name(
        'asset-settings.types.destroy'
    );

/*
|--------------------------------------------------------------------------
| Asset Completeness Queue
|--------------------------------------------------------------------------
*/

Route::get(
    '/asset-completeness',
    [
        AssetCompletenessQueueController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_completeness.view',
    ])
    ->name(
        'asset-completeness.index'
    );

/*
|--------------------------------------------------------------------------
| Asset Manager Completion
|--------------------------------------------------------------------------
*/

Route::get(
    '/asset-completeness/{asset}/edit',
    [
        AssetManagerCompletionController::class,
        'edit',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_completeness.view',
        EnsureAssetManagerAssetContext::class,
    ])
    ->name(
        'asset-completeness.edit'
    );

Route::match(
    ['put', 'patch'],
    '/asset-completeness/{asset}',
    [
        AssetManagerCompletionController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.edit',
        EnsureAssetManagerAssetContext::class,
    ])
    ->name(
        'asset-completeness.update'
    );

Route::post(
    '/asset-completeness/{asset}/issue-code',
    [
        AssetManagerCompletionController::class,
        'issueAssetCode',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.edit',
        EnsureAssetManagerAssetContext::class,
    ])
    ->name(
        'asset-completeness.issue-code'
    );

Route::post(
    '/approvals/{step}/warehouse-finalize',
    [
        MyApprovalController::class,
        'finalizeWarehouseSelection',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name(
        'approvals.warehouse-finalize'
    );

/*
|--------------------------------------------------------------------------
| Specialist Parallel Approvals
|--------------------------------------------------------------------------
*/

Route::get(
    '/specialist-approvals',
    [
        SpecialistApprovalController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.view',
    ])
    ->name(
        'specialist-approvals.index'
    );

Route::get(
    '/specialist-approvals/{branch}',
    [
        SpecialistApprovalController::class,
        'show',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.view',
    ])
    ->name(
        'specialist-approvals.show'
    );

Route::post(
    '/specialist-approvals/{branch}/act',
    [
        SpecialistApprovalController::class,
        'act',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name(
        'specialist-approvals.act'
    );

/*
|--------------------------------------------------------------------------
| Specialist Rejection Warehouse Recovery
|--------------------------------------------------------------------------
*/

Route::get(
    '/warehouse-recoveries',
    [
        WarehouseRecoveryController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.view',
    ])
    ->name(
        'warehouse-recoveries.index'
    );

Route::get(
    '/warehouse-recoveries/{branch}',
    [
        WarehouseRecoveryController::class,
        'show',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.view',
    ])
    ->name(
        'warehouse-recoveries.show'
    );

Route::put(
    '/warehouse-recoveries/{branch}',
    [
        WarehouseRecoveryController::class,
        'update',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.act',
    ])
    ->name(
        'warehouse-recoveries.update'
    );

/*
|--------------------------------------------------------------------------
| Asset Manager Request Workspace
|--------------------------------------------------------------------------
*/

Route::get(
    '/asset-manager-requests',
    [
        AssetManagerRequestController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_completeness.view',
    ])
    ->name(
        'asset-manager-requests.index'
    );

Route::get(
    '/asset-manager-requests/{step}',
    [
        AssetManagerRequestController::class,
        'show',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_completeness.view',
    ])
    ->name(
        'asset-manager-requests.show'
    );

/*
|--------------------------------------------------------------------------
| Final Warehouse Delivery Workspace
|--------------------------------------------------------------------------
*/

Route::get(
    '/final-warehouse-deliveries',
    [
        FinalWarehouseDeliveryController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.view',
    ])
    ->name(
        'final-warehouse-deliveries.index'
    );

Route::get(
    '/final-warehouse-deliveries/{step}',
    [
        FinalWarehouseDeliveryController::class,
        'show',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.view',
    ])
    ->name(
        'final-warehouse-deliveries.show'
    );

Route::middleware([
    'web',
    EnsureActiveLoginSession::class,
])->group(function () {

    Route::get(
        '/asset-movement-requests',
        [AssetMovementRequestController::class, 'index']
    )
        ->middleware('permission:asset_movement_requests.view')
        ->name('asset-movement-requests.index');

    Route::get(
        '/asset-movement-requests/create',
        [AssetMovementRequestController::class, 'create']
    )
        ->middleware('permission:asset_movement_requests.create')
        ->name('asset-movement-requests.create');

    Route::post(
        '/asset-movement-requests',
        [AssetMovementRequestController::class, 'store']
    )
        ->middleware('permission:asset_movement_requests.create')
        ->name('asset-movement-requests.store');

    Route::get(
        '/asset-movement-requests/{assetMovementRequest}',
        [AssetMovementRequestController::class, 'show']
    )
        ->middleware('permission:asset_movement_requests.view')
        ->name('asset-movement-requests.show');

});

Route::middleware([
    'web',
    EnsureActiveLoginSession::class,
])->group(function () {

    Route::get('/reports/print', [ReportController::class, 'printReport'])
        ->middleware('permission:reports.view')
        ->name('reports.print');

    Route::get(
        '/task-center',
        [TaskCenterController::class, 'index']
    )->name('task-center.index');

});

Route::middleware([
    'web',
    EnsureActiveLoginSession::class,
])->group(function () {

    Route::get(
        '/operational-alerts',
        [OperationalAlertController::class, 'index']
    )->name('operational-alerts.index');

});

/*
|--------------------------------------------------------------------------
| Organizational Assets
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {
    Route::get(
        '/organizational-assets',
        [OrganizationalAssetController::class, 'index']
    )->name('organizational-assets.index');

    Route::get(
        '/organizational-assets/create',
        [OrganizationalAssetController::class, 'create']
    )->name('organizational-assets.create');

    Route::post(
        '/organizational-assets',
        [OrganizationalAssetController::class, 'store']
    )->name('organizational-assets.store');
});

/*
|--------------------------------------------------------------------------
| Bulk Import
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {
    Route::get(
        '/initial-setup',
        [InitialAssetSetupController::class, 'index']
    )->name('initial-setup.index');

    Route::get(
        '/initial-setup/template',
        [InitialAssetSetupController::class, 'template']
    )->name('initial-setup.template');

    Route::post(
        '/initial-setup/preview',
        [InitialAssetSetupController::class, 'preview']
    )->name('initial-setup.preview');

    Route::post(
        '/initial-setup/commit',
        [InitialAssetSetupController::class, 'commit']
    )->name('initial-setup.commit');

    Route::get(
        '/bulk-import',
        [BulkImportController::class, 'index']
    )->name('bulk-import.index');

    Route::get(
        '/bulk-import/employees/template',
        [BulkImportController::class, 'employeeTemplate']
    )->name('bulk-import.employees.template');

    Route::post(
        '/bulk-import/employees/preview',
        [BulkImportController::class, 'employeePreview']
    )->name('bulk-import.employees.preview');
});

Route::post(
    '/bulk-import/employees/commit',
    [BulkImportController::class, 'employeeCommit']
)->middleware('auth')
    ->name('bulk-import.employees.commit');

Route::get(
    '/bulk-import/assets',
    [BulkImportController::class, 'assetIndex']
)->middleware('auth')
    ->name('bulk-import.assets.index');

Route::get(
    '/bulk-import/assets/template',
    [BulkImportController::class, 'assetTemplate']
)->middleware('auth')
    ->name('bulk-import.assets.template');

Route::post(
    '/bulk-import/assets/preview',
    [BulkImportController::class, 'assetPreview']
)->middleware('auth')
    ->name('bulk-import.assets.preview');

Route::post(
    '/bulk-import/assets/commit',
    [BulkImportController::class, 'assetCommit']
)->middleware('auth')
    ->name('bulk-import.assets.commit');

Route::get(
    '/bulk-import/reference-structure',
    [BulkImportController::class, 'referenceStructureIndex']
)->middleware('auth')
    ->name('bulk-import.reference-structure.index');

Route::get(
    '/bulk-import/reference-structure/template',
    [BulkImportController::class, 'referenceStructureTemplate']
)->middleware('auth')
    ->name('bulk-import.reference-structure.template');

Route::post(
    '/bulk-import/reference-structure/preview',
    [BulkImportController::class, 'referenceStructurePreview']
)->middleware('auth')
    ->name('bulk-import.reference-structure.preview');

Route::post(
    '/bulk-import/reference-structure/commit',
    [BulkImportController::class, 'referenceStructureCommit']
)->middleware('auth')
    ->name('bulk-import.reference-structure.commit');

Route::middleware('auth')
    ->prefix('asset-reference')
    ->name('asset-reference.')
    ->group(function () {

        Route::get(
            '/',
            [AssetReferenceController::class, 'index']
        )->name('index');

        Route::get(
            '/categories/create',
            [AssetReferenceController::class, 'createCategory']
        )->name('categories.create');

        Route::post(
            '/categories',
            [AssetReferenceController::class, 'storeCategory']
        )->name('categories.store');

        Route::get(
            '/categories/{category}/edit',
            [AssetReferenceController::class, 'editCategory']
        )->name('categories.edit');

        Route::put(
            '/categories/{category}',
            [AssetReferenceController::class, 'updateCategory']
        )->name('categories.update');

        Route::delete(
            '/categories/{category}',
            [AssetReferenceController::class, 'destroyCategory']
        )->name('categories.destroy');

        Route::get(
            '/types/create',
            [AssetReferenceController::class, 'createType']
        )->name('types.create');

        Route::post(
            '/types',
            [AssetReferenceController::class, 'storeType']
        )->name('types.store');

        Route::get(
            '/types/{type}/edit',
            [AssetReferenceController::class, 'editType']
        )->name('types.edit');

        Route::put(
            '/types/{type}',
            [AssetReferenceController::class, 'updateType']
        )->name('types.update');

        Route::delete(
            '/types/{type}',
            [AssetReferenceController::class, 'destroyType']
        )->name('types.destroy');
    });

/*
|--------------------------------------------------------------------------
| Asset Plate Print Workspace
|--------------------------------------------------------------------------
*/

Route::get(
    '/asset-plates',
    [
        AssetPlatePrintController::class,
        'index',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.view',
    ])
    ->name('asset-plates.index');

Route::post(
    '/asset-plates/preview',
    [
        AssetPlatePrintController::class,
        'preview',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.view',
    ])
    ->name('asset-plates.preview');

Route::post(
    '/final-warehouse-deliveries/{step}/plates/preview',
    [
        FinalWarehousePlatePrintController::class,
        'preview',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:approvals.view',
    ])
    ->name('final-warehouse-deliveries.plates.preview');

Route::get(
    '/asset-plates/{asset}/print',
    [
        AssetPlatePrintController::class,
        'single',
    ]
)
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:assets.view',
    ])
    ->name('asset-plates.single');

/*
|--------------------------------------------------------------------------
| Physical Stocktake
|--------------------------------------------------------------------------
*/
Route::middleware([
    'web',
    EnsureActiveLoginSession::class,
])->group(function () {
    Route::get('/stocktakes', [StocktakeController::class, 'index'])
        ->middleware('permission:stocktakes.view')->name('stocktakes.index');
    Route::get('/stocktakes/create', [StocktakeController::class, 'create'])
        ->middleware('permission:stocktakes.create')->name('stocktakes.create');
    Route::post('/stocktakes', [StocktakeController::class, 'store'])
        ->middleware('permission:stocktakes.create')->name('stocktakes.store');
    Route::get('/stocktakes/{stocktake}', [StocktakeController::class, 'show'])
        ->middleware('permission:stocktakes.view')->name('stocktakes.show');
    Route::post('/stocktakes/{stocktake}/start', [StocktakeController::class, 'start'])
        ->middleware('permission:stocktakes.start')->name('stocktakes.start');
    Route::post('/stocktakes/{stocktake}/observe', [StocktakeController::class, 'observe'])
        ->middleware('permission:stocktakes.count')->name('stocktakes.observe');
    Route::post('/stocktakes/{stocktake}/items/{item}/missing', [StocktakeController::class, 'missing'])
        ->middleware('permission:stocktakes.count')->name('stocktakes.missing');
    Route::post('/stocktakes/{stocktake}/recount', [StocktakeController::class, 'recount'])
        ->middleware('permission:stocktakes.finalize')->name('stocktakes.recount');
    Route::post('/stocktakes/{stocktake}/complete', [StocktakeController::class, 'complete'])
        ->middleware('permission:stocktakes.finalize')->name('stocktakes.complete');
    Route::get('/stocktakes/{stocktake}/reconciliation', [StocktakeController::class, 'reconciliation'])
        ->middleware('permission:stocktakes.reconcile')->name('stocktakes.reconciliation');
    Route::post('/stocktakes/{stocktake}/items/{item}/reconciliation/apply', [StocktakeController::class, 'applyReconciliation'])
        ->middleware('permission:stocktakes.reconcile')->name('stocktakes.reconciliation.apply');
    Route::post('/stocktakes/{stocktake}/items/{item}/reconciliation/resolve', [StocktakeController::class, 'resolveReconciliation'])
        ->middleware('permission:stocktakes.reconcile')->name('stocktakes.reconciliation.resolve');
});
Route::middleware(['auth'])->group(function () {
    Route::get('/company-storage-profiles', [CompanyStorageProfileController::class, 'index'])->middleware('permission:company_storage.manage')->name('company-storage-profiles.index');
    Route::get('/company-storage-profiles/create', [CompanyStorageProfileController::class, 'create'])->middleware('permission:company_storage.manage')->name('company-storage-profiles.create');
    Route::post('/company-storage-profiles', [CompanyStorageProfileController::class, 'store'])->middleware('permission:company_storage.manage')->name('company-storage-profiles.store');
    Route::get('/company-storage-profiles/{companyStorageProfile}/edit', [CompanyStorageProfileController::class, 'edit'])->middleware('permission:company_storage.manage')->name('company-storage-profiles.edit');
    Route::put('/company-storage-profiles/{companyStorageProfile}', [CompanyStorageProfileController::class, 'update'])->middleware('permission:company_storage.manage')->name('company-storage-profiles.update');
    Route::post('/company-storage-profiles/{companyStorageProfile}/test', [CompanyStorageProfileController::class, 'testConnection'])->middleware('permission:company_storage.manage')->name('company-storage-profiles.test');
    Route::post('/company-storage-profiles/{companyStorageProfile}/default', [CompanyStorageProfileController::class, 'makeDefault'])->middleware('permission:company_storage.manage')->name('company-storage-profiles.default');
});

/*
|--------------------------------------------------------------------------
| Asset Repair Requests
|--------------------------------------------------------------------------
*/

Route::get('/asset-repairs', [
    AssetRepairRequestController::class,
    'index',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_repairs.view',
    ])
    ->name('asset-repairs.index');

Route::get('/asset-repairs/create', [
    AssetRepairRequestController::class,
    'create',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_repairs.create',
    ])
    ->name('asset-repairs.create');

Route::post('/asset-repairs', [
    AssetRepairRequestController::class,
    'store',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_repairs.create',
    ])
    ->name('asset-repairs.store');

Route::get('/asset-repairs/{assetRepair}', [
    AssetRepairRequestController::class,
    'show',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_repairs.view',
    ])
    ->name('asset-repairs.show');

Route::post('/asset-repairs/{assetRepair}/submit', [
    AssetRepairRequestController::class,
    'submit',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_repairs.create',
    ])
    ->name('asset-repairs.submit');

Route::post('/asset-repairs/{assetRepair}/start', [
    AssetRepairRequestController::class,
    'start',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_repairs.manage',
    ])
    ->name('asset-repairs.start');

Route::post('/asset-repairs/{assetRepair}/complete', [
    AssetRepairRequestController::class,
    'complete',
])
    ->middleware([
        EnsureActiveLoginSession::class,
        'permission:asset_repairs.manage',
    ])
    ->name('asset-repairs.complete');

Route::post('/asset-repairs/{assetRepair}/cancel', [AssetRepairRequestController::class, 'cancel'])
    ->middleware([EnsureActiveLoginSession::class, 'permission:asset_repairs.manage'])
    ->name('asset-repairs.cancel');

Route::post('/asset-repairs/{assetRepair}/reopen', [AssetRepairRequestController::class, 'reopen'])
    ->middleware([EnsureActiveLoginSession::class, 'permission:asset_repairs.manage'])
    ->name('asset-repairs.reopen');
