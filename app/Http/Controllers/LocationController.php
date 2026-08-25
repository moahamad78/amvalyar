<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LocationRequest;
use App\Models\Company;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class LocationController extends Controller
{
    public function index(
        Request $request
    ): View {

        $query =
            Location::query()
                ->with([
                    'company',
                    'site',
                    'parent',
                ])
                ->withCount([
                    'children',
                ])
                ->orderBy('site_id')
                ->orderBy('sort_order')
                ->orderBy('name');


        if (
            $request->user()
                ->isSuperAdmin()
            &&
            $request->filled(
                'company_id'
            )
        ) {

            $query->where(
                'company_id',
                $request->integer(
                    'company_id'
                )
            );
        }


        if (
            $request->filled(
                'site_id'
            )
        ) {

            $query->where(
                'site_id',
                $request->integer(
                    'site_id'
                )
            );
        }


        $locations =
            $query->get();


        $tree =
            $this->buildTree(
                $locations
            );


        $companies =
            $request->user()
                ->isSuperAdmin()

                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])

                : collect();


        $sites =
            Site::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'code',
                ]);


        return view(
            'locations.index',
            compact(
                'locations',
                'tree',
                'companies',
                'sites'
            )
        );
    }


    public function create(
        Request $request
    ): View {

        $companies =
            $request->user()
                ->isSuperAdmin()

                ? Company::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])

                : collect();


        $sites =
            Site::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'code',
                ]);


        $parentLocations =
            Location::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'site_id',
                    'name',
                    'code',
                ]);


        return view(
            'locations.create',
            compact(
                'companies',
                'sites',
                'parentLocations'
            )
        );
    }


    public function store(
        LocationRequest $request,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $data =
            $request->validated();


        if (
            !$request->user()
                ->isSuperAdmin()
        ) {

            $data['company_id'] =
                $request->user()
                    ->company_id;
        }


        $data['parent_id'] =
            !empty(
                $data['parent_id']
            )
                ? (int) $data['parent_id']
                : null;


        $data['is_active'] =
            $request->boolean(
                'is_active'
            );


        $data['sort_order'] =
            (int) (
                $data['sort_order']
                ?? 0
            );


        $location =
            Location::query()
                ->create(
                    $data
                );


        $auditLogService->log(
            action:
                'location.created',

            subject:
                $location,

            newValues:
                $location->only([
                    'company_id',
                    'site_id',
                    'parent_id',
                    'name',
                    'code',
                    'type',
                    'description',
                    'is_active',
                    'sort_order',
                ]),

            description:
                'ایجاد محل استقرار - '
                . $location->name,

            request:
                $request
        );


        return redirect()
            ->route(
                'locations.index'
            )
            ->with(
                'success',
                'محل استقرار با موفقیت ثبت شد.'
            );
    }


    public function edit(
        Request $request,
        Location $location
    ): View {

        $this->ensureVisible(
            $request->user(),
            $location
        );


        $sites =
            Site::query()
                ->where(
                    'company_id',
                    $location->company_id
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'name',
                    'code',
                ]);


        $excludedIds =
            $this->descendantIds(
                $location
            );

        $excludedIds[] =
            $location->id;


        $parentLocations =
            Location::query()
                ->where(
                    'company_id',
                    $location->company_id
                )
                ->where(
                    'site_id',
                    $location->site_id
                )
                ->whereNotIn(
                    'id',
                    $excludedIds
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'site_id',
                    'name',
                    'code',
                ]);


        return view(
            'locations.edit',
            compact(
                'location',
                'sites',
                'parentLocations'
            )
        );
    }


    public function update(
        LocationRequest $request,
        Location $location,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $location
        );


        $oldValues =
            $location->only([
                'company_id',
                'site_id',
                'parent_id',
                'name',
                'code',
                'type',
                'description',
                'is_active',
                'sort_order',
            ]);


        $data =
            $request->validated();


        unset(
            $data['company_id']
        );


        $data['parent_id'] =
            !empty(
                $data['parent_id']
            )
                ? (int) $data['parent_id']
                : null;


        $data['is_active'] =
            $request->boolean(
                'is_active'
            );


        $data['sort_order'] =
            (int) (
                $data['sort_order']
                ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | Moving Location Between Sites
        |--------------------------------------------------------------------------
        |
        | اگر محل زیرمجموعه داشته باشد، تغییر Site مجاز نیست؛
        | چون تمام فرزندان باید داخل همان Site باقی بمانند.
        |
        */

        if (
            (int) $location->site_id
            !==
            (int) $data['site_id']
            &&
            $location->children()
                ->exists()
        ) {

            return back()
                ->withInput()
                ->withErrors([
                    'site_id' =>
                        'محلی که زیرمجموعه دارد را نمی‌توان مستقیم به سایت دیگری منتقل کرد.',
                ]);
        }


        $location->update(
            $data
        );


        $location->refresh();


        $newValues =
            $location->only([
                'company_id',
                'site_id',
                'parent_id',
                'name',
                'code',
                'type',
                'description',
                'is_active',
                'sort_order',
            ]);


        $auditLogService->log(
            action:
                'location.updated',

            subject:
                $location,

            oldValues:
                $oldValues,

            newValues:
                $newValues,

            description:
                'ویرایش محل استقرار - '
                . $location->name,

            request:
                $request
        );


        return redirect()
            ->route(
                'locations.index'
            )
            ->with(
                'success',
                'محل استقرار با موفقیت ویرایش شد.'
            );
    }


    public function destroy(
        Request $request,
        Location $location,
        AuditLogService $auditLogService
    ): RedirectResponse {

        $this->ensureVisible(
            $request->user(),
            $location
        );


        if (
            $location->children()
                ->exists()
        ) {

            return back()
                ->withErrors([
                    'location' =>
                        'این محل دارای زیرمجموعه است و قابل حذف نیست. ابتدا زیرمجموعه‌ها را منتقل یا غیرفعال کنید.',
                ]);
        }


        $oldValues =
            $location->only([
                'company_id',
                'site_id',
                'parent_id',
                'name',
                'code',
                'type',
                'description',
                'is_active',
                'sort_order',
            ]);


        $auditLogService->log(
            action:
                'location.deleted',

            subject:
                $location,

            oldValues:
                $oldValues,

            description:
                'حذف محل استقرار - '
                . $location->name,

            request:
                $request
        );


        $location->delete();


        return redirect()
            ->route(
                'locations.index'
            )
            ->with(
                'success',
                'محل استقرار با موفقیت حذف شد.'
            );
    }


    private function ensureVisible(
        User $currentUser,
        Location $location
    ): void {

        if (
            $currentUser->isSuperAdmin()
        ) {
            return;
        }


        if (
            (int) $location->company_id
            !==
            (int) $currentUser->company_id
        ) {
            abort(404);
        }
    }


    private function descendantIds(
        Location $location
    ): array {

        $all =
            Location::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $location->company_id
                )
                ->where(
                    'site_id',
                    $location->site_id
                )
                ->get([
                    'id',
                    'parent_id',
                ]);


        $result = [];

        $queue = [
            $location->id,
        ];


        while ($queue !== []) {

            $currentId =
                array_shift(
                    $queue
                );


            $children =
                $all->where(
                    'parent_id',
                    $currentId
                );


            foreach (
                $children as $child
            ) {

                if (
                    in_array(
                        $child->id,
                        $result,
                        true
                    )
                ) {
                    continue;
                }


                $result[] =
                    $child->id;

                $queue[] =
                    $child->id;
            }
        }


        return $result;
    }


    private function buildTree(
        Collection $locations
    ): Collection {

        $groups =
            $locations->groupBy(
                fn (Location $location) =>
                    $location->company_id
                    . ':'
                    . $location->site_id
            );


        $result =
            collect();


        foreach (
            $groups as $siteLocations
        ) {

            $childrenByParent =
                $siteLocations
                    ->groupBy(
                        fn (Location $item) =>
                            $item->parent_id
                            ?? 0
                    );


            $appendChildren =
                function (
                    int $parentId,
                    int $depth
                ) use (
                    &$appendChildren,
                    $childrenByParent,
                    &$result
                ): void {

                    $children =
                        $childrenByParent->get(
                            $parentId,
                            collect()
                        );


                    foreach (
                        $children as $location
                    ) {

                        $location->tree_depth =
                            $depth;

                        $result->push(
                            $location
                        );


                        $appendChildren(
                            (int) $location->id,
                            $depth + 1
                        );
                    }
                };


            $appendChildren(
                0,
                0
            );
        }


        return $result;
    }
}