@extends('layouts.app')

@section('title', 'گردش اموال')

@section('content')

<div class="container">

    <div class="d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-3
                mb-4">

        <div>

            <h1 class="h3 mb-1">
                گردش اموال
            </h1>

            <p class="text-muted mb-0">
                سوابق تحویل، بازگشت و انتقال دارایی‌ها
            </p>

        </div>


        @if(
            auth()->user()->isSuperAdmin()
            || auth()->user()->hasAnyPermission([
                'assets.delivery',
                'assets.return',
                'assets.transfer',
                'assets.delete',
            ])
        )

            <a
                href="{{ route('asset-transactions.create') }}"
                class="btn btn-primary"
            >
                + ثبت گردش جدید
            </a>

        @endif

    </div>


    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table
                              table-hover
                              align-middle
                              mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>
                                دارایی
                            </th>

                            <th>
                                نوع
                            </th>

                            <th>
                                تحویل‌گیرنده
                            </th>

                            <th>
                                پلاک
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    @forelse($transactions as $item)

                        <tr>

                            <td>

                                <strong>
                                    {{ $item->asset?->title ?? '-' }}
                                </strong>

                                @if($item->asset?->asset_code)

                                    <div class="text-muted small">
                                        {{ $item->asset->asset_code }}
                                    </div>

                                @endif

                            </td>


                            <td>

                                @switch($item->type)

                                    @case('delivery')

                                        <span class="badge text-bg-primary">
                                            تحویل
                                        </span>

                                    @break


                                    @case('return')

                                        <span class="badge text-bg-success">
                                            بازگشت
                                        </span>

                                    @break


                                    @case('transfer')

                                        <span class="badge text-bg-warning">
                                            انتقال
                                        </span>

                                    @break


                                    @case('destroy')

                                        <span class="badge text-bg-danger">
                                            اسقاط
                                        </span>

                                    @break


                                    @default

                                        <span class="badge text-bg-secondary">
                                            {{ $item->type }}
                                        </span>

                                @endswitch

                            </td>


                            <td>
                                {{ $item->toUser?->name ?? '-' }}
                            </td>


                            <td>
                                {{ $item->plate_number ?? '-' }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="4"
                                class="text-center text-muted py-5"
                            >
                                هنوز گردش اموالی ثبت نشده است.
                            </td>

                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>


            <div class="mt-4">

                {{ $transactions->links() }}

            </div>

        </div>

    </div>

</div>

@endsection