<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AssetCompletenessService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

final class AssetCompletenessQueueController extends Controller
{
    public function index(
        Request $request,
        AssetCompletenessService $completenessService
    ): View {

        $user =
            $request->user();


        $status =
            (string) $request->query(
                'status',
                'incomplete'
            );


        if (
            !in_array(
                $status,
                [
                    'all',
                    'incomplete',
                    'complete',
                ],
                true
            )
        ) {

            $status =
                'incomplete';
        }


        $search =
            trim(
                (string) $request->query(
                    'q',
                    ''
                )
            );


        $query =
            Asset::withoutGlobalScopes()
                ->with([
                    'category',
                    'assetType.attributeDefinitions.options',
                    'attributeValues.definition',
                    'attributeValues.option',
                ]);


        if (
            !$user->isSuperAdmin()
        ) {

            $query->where(
                'company_id',
                $user->company_id
            );
        }


        if (
            $search !== ''
        ) {

            $query->where(
                function ($query) use ($search): void {

                    $query
                        ->where(
                            'title',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'asset_code',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'inventory_code',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'asset_code',
                            'like',
                            '%' . $search . '%'
                        );
                }
            );
        }


        $allRows =
            $query
                ->orderByDesc('id')
                ->get()
                ->map(
                    function (
                        Asset $asset
                    ) use (
                        $completenessService
                    ): array {

                        $result =
                            $completenessService->check(
                                $asset,
                                AssetCompletenessService::CONTEXT_ASSET_MANAGER_REVIEW
                            );


                        return [

                            'asset' =>
                                $asset,

                            'complete' =>
                                $result['complete'],

                            'percentage' =>
                                $result['percentage'],

                            'missing' =>
                                $result['missing'],

                            'completed_count' =>
                                $result['completed_count'],

                            'total_count' =>
                                $result['total_count'],
                        ];
                    }
                );


        $summary = [

            'all' =>
                $allRows->count(),

            'incomplete' =>
                $allRows
                    ->where(
                        'complete',
                        false
                    )
                    ->count(),

            'complete' =>
                $allRows
                    ->where(
                        'complete',
                        true
                    )
                    ->count(),
        ];


        $filteredRows =
            match ($status) {

                'complete' =>
                    $allRows
                        ->where(
                            'complete',
                            true
                        )
                        ->values(),

                'incomplete' =>
                    $allRows
                        ->where(
                            'complete',
                            false
                        )
                        ->values(),

                default =>
                    $allRows
                        ->values(),
            };


        $perPage =
            20;


        $page =
            max(
                1,
                (int) $request->query(
                    'page',
                    1
                )
            );


        $items =
            $filteredRows
                ->forPage(
                    $page,
                    $perPage
                )
                ->values();


        $rows =
            new LengthAwarePaginator(

                $items,

                $filteredRows->count(),

                $perPage,

                $page,

                [
                    'path' =>
                        $request->url(),

                    'query' =>
                        $request->query(),
                ]
            );


        return view(
            'asset_completeness.index',
            compact(
                'rows',
                'summary',
                'status',
                'search'
            )
        );
    }
}