@extends('layouts.app')

@section('title', 'تحویل نهایی انبار')

@section('content')

<div class="container py-4">

    <div class="d-flex
                justify-content-between
                align-items-center
                flex-wrap
                gap-3
                mb-4">

        <div>

            <h2 class="mb-1">
                تحویل نهایی انبار
            </h2>

            <div class="text-muted">
                درخواست‌های آماده تحویل فیزیکی به درخواست‌کننده
            </div>

        </div>


        <a
            href="{{ route('approvals.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    <div class="card border-0 shadow-sm">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th>
                            شماره درخواست
                        </th>

                        <th>
                            درخواست‌کننده
                        </th>

                        <th>
                            محل
                        </th>

                        <th>
                            تعداد اموال
                        </th>

                        <th>
                            وضعیت
                        </th>

                        <th>
                            عملیات
                        </th>

                    </tr>

                </thead>


                <tbody>

                @forelse($rows as $row)

                    <tr>

                        <td>

                            <strong dir="ltr">
                                {{ $row['request']->request_number }}
                            </strong>

                        </td>


                        <td>

                            {{ $row['request']->requesterEmployee?->display_name
                                ?? $row['request']->requesterUser?->name
                                ?? '-' }}

                        </td>


                        <td>

                            {{ $row['request']->site?->name ?? '-' }}

                            @if($row['request']->department)

                                /
                                {{ $row['request']->department->name }}

                            @endif

                        </td>


                        <td>

                            <span class="badge bg-primary">

                                {{ $row['asset_count'] }}
                                قلم

                            </span>

                        </td>


                        <td>

                            <span class="badge bg-warning text-dark">
                                آماده تحویل
                            </span>

                        </td>


                        <td>

                            <a
                                href="{{ route(
                                    'final-warehouse-deliveries.show',
                                    $row['step']
                                ) }}"
                                class="btn btn-sm btn-primary"
                            >
                                مشاهده و تحویل
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-muted py-5"
                        >

                            هیچ درخواستی در انتظار تحویل نهایی نیست.

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection