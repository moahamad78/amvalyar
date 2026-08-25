<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\Site;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ReferenceStructureCommitService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }


    public function commit(
        Company $company,
        array $preview,
        Request $request
    ): array {
        if (($preview['can_commit'] ?? false) !== true) {
            throw new RuntimeException(
                'Invalid preview cannot be committed.'
            );
        }

        return DB::transaction(
            function () use (
                $company,
                $preview,
                $request
            ): array {
                $siteMap =
                    $this->existingSiteMap(
                        (int) $company->id
                    );

                $departmentMap =
                    $this->existingDepartmentMap(
                        (int) $company->id
                    );

                $locationMap =
                    $this->existingLocationMap(
                        (int) $company->id,
                        $siteMap
                    );

                $createdSites = collect();
                $createdLocations = collect();
                $createdDepartments = collect();

                /*
                 * 1) Sites first.
                 */
                foreach (
                    $preview['sites']['rows']
                    as $row
                ) {
                    $this->assertValidRow($row);

                    $data = $row['data'];

                    $site = new Site();
                    $site->company_id =
                        (int) $company->id;
                    $site->name =
                        $data['name'];
                    $site->code =
                        $data['code'];
                    $site->type =
                        $data['type'];
                    $site->address =
                        $data['address'];
                    $site->description =
                        $data['description'];
                    $site->is_active =
                        (bool) $data['is_active'];
                    $site->sort_order =
                        (int) $data['sort_order'];
                    $site->save();

                    $siteMap[
                        $this->normalizeCode(
                            $site->code
                        )
                    ] = (int) $site->id;

                    $this->audit(
                        'site.created',
                        $site,
                        'ایجاد گروهی سایت - '
                            . $site->name,
                        $request
                    );

                    $createdSites->push($site);
                }

                /*
                 * 2) Locations in file order.
                 * Parent-before-child is guaranteed by fresh preview.
                 */
                foreach (
                    $preview['locations']['rows']
                    as $row
                ) {
                    $this->assertValidRow($row);

                    $data = $row['data'];

                    $siteCode =
                        $this->normalizeCode(
                            $data['site_code']
                        );

                    $siteId =
                        $siteMap[$siteCode]
                        ?? null;

                    if ($siteId === null) {
                        throw new RuntimeException(
                            'Location site could not be resolved at commit.'
                        );
                    }

                    $parentId = null;

                    if (
                        ($data['parent_code'] ?? null)
                        !== null
                    ) {
                        $parentKey =
                            $this->locationKey(
                                $siteCode,
                                $this->normalizeCode(
                                    $data['parent_code']
                                )
                            );

                        $parentId =
                            $locationMap[$parentKey]
                            ?? null;

                        if ($parentId === null) {
                            throw new RuntimeException(
                                'Location parent could not be resolved at commit.'
                            );
                        }
                    }

                    $location = new Location();
                    $location->company_id =
                        (int) $company->id;
                    $location->site_id =
                        (int) $siteId;

                    $parentColumn =
                        $this->locationParentColumn();

                    if ($parentColumn !== null) {
                        $location->{$parentColumn} =
                            $parentId;
                    }

                    $location->name =
                        $data['name'];
                    $location->code =
                        $data['code'];
                    $location->type =
                        $data['type'];
                    $location->description =
                        $data['description'];
                    $location->is_active =
                        (bool) $data['is_active'];
                    $location->sort_order =
                        (int) $data['sort_order'];
                    $location->save();

                    $locationMap[
                        $this->locationKey(
                            $siteCode,
                            $this->normalizeCode(
                                $location->code
                            )
                        )
                    ] = (int) $location->id;

                    $this->audit(
                        'location.created',
                        $location,
                        'ایجاد گروهی مکان - '
                            . $location->name,
                        $request
                    );

                    $createdLocations->push(
                        $location
                    );
                }

                /*
                 * 3) Departments last, in file order.
                 */
                foreach (
                    $preview['departments']['rows']
                    as $row
                ) {
                    $this->assertValidRow($row);

                    $data = $row['data'];

                    $parentId = null;

                    if (
                        ($data['parent_code'] ?? null)
                        !== null
                    ) {
                        $parentId =
                            $departmentMap[
                                $this->normalizeCode(
                                    $data['parent_code']
                                )
                            ]
                            ?? null;

                        if ($parentId === null) {
                            throw new RuntimeException(
                                'Department parent could not be resolved at commit.'
                            );
                        }
                    }

                    $department = new Department();
                    $department->company_id =
                        (int) $company->id;

                    $parentColumn =
                        $this->departmentParentColumn();

                    if ($parentColumn !== null) {
                        $department->{$parentColumn} =
                            $parentId;
                    }

                    $department->name =
                        $data['name'];
                    $department->code =
                        $data['code'];
                    $department->description =
                        $data['description'];
                    $department->is_active =
                        (bool) $data['is_active'];
                    $department->sort_order =
                        (int) $data['sort_order'];
                    $department->save();

                    $departmentMap[
                        $this->normalizeCode(
                            $department->code
                        )
                    ] = (int) $department->id;

                    $this->audit(
                        'department.created',
                        $department,
                        'ایجاد گروهی واحد سازمانی - '
                            . $department->name,
                        $request
                    );

                    $createdDepartments->push(
                        $department
                    );
                }

                return [
                    'sites' => $createdSites,
                    'locations' => $createdLocations,
                    'departments' => $createdDepartments,
                    'created_count' =>
                        $createdSites->count()
                        + $createdLocations->count()
                        + $createdDepartments->count(),
                ];
            },
            3
        );
    }


    private function existingSiteMap(
        int $companyId
    ): array {
        return Site::query()
            ->where('company_id', $companyId)
            ->pluck('id', 'code')
            ->mapWithKeys(
                fn ($id, $code) => [
                    $this->normalizeCode($code) =>
                        (int) $id,
                ]
            )
            ->all();
    }


    private function existingDepartmentMap(
        int $companyId
    ): array {
        return Department::query()
            ->where('company_id', $companyId)
            ->pluck('id', 'code')
            ->mapWithKeys(
                fn ($id, $code) => [
                    $this->normalizeCode($code) =>
                        (int) $id,
                ]
            )
            ->all();
    }


    private function existingLocationMap(
        int $companyId,
        array $siteMap
    ): array {
        $siteCodeById =
            array_flip($siteMap);

        $map = [];

        foreach (
            Location::query()
                ->where('company_id', $companyId)
                ->get(['id', 'site_id', 'code'])
            as $location
        ) {
            $siteCode =
                $siteCodeById[
                    (int) $location->site_id
                ]
                ?? null;

            if ($siteCode === null) {
                continue;
            }

            $map[
                $this->locationKey(
                    $siteCode,
                    $this->normalizeCode(
                        $location->code
                    )
                )
            ] = (int) $location->id;
        }

        return $map;
    }


    private function assertValidRow(
        array $row
    ): void {
        if (($row['valid'] ?? false) !== true) {
            throw new RuntimeException(
                'Invalid row entered commit phase.'
            );
        }
    }


    private function audit(
        string $action,
        object $subject,
        string $description,
        Request $request
    ): void {
        $this->auditLogService->log(
            action: $action,
            subject: $subject,
            newValues: $subject->getAttributes(),
            description: $description,
            request: $request
        );
    }


    private function locationParentColumn(): ?string
    {
        foreach (
            ['parent_id', 'parent_location_id']
            as $column
        ) {
            if (
                Schema::hasColumn(
                    'locations',
                    $column
                )
            ) {
                return $column;
            }
        }

        return null;
    }


    private function departmentParentColumn(): ?string
    {
        foreach (
            ['parent_id', 'parent_department_id']
            as $column
        ) {
            if (
                Schema::hasColumn(
                    'departments',
                    $column
                )
            ) {
                return $column;
            }
        }

        return null;
    }


    private function normalizeCode(
        string $value
    ): string {
        return mb_strtolower(
            trim($value)
        );
    }


    private function locationKey(
        string $siteCode,
        string $locationCode
    ): string {
        return
            $siteCode
            . '|'
            . $locationCode;
    }
}