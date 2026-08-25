@extends('layouts.app')

@section('title', 'کارتابل جمعدار اموال')

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
                کارتابل جمعدار اموال
            </h2>

            <div class="text-muted">
                درخواست‌هایی که تأییدهای قبلی آنها تکمیل شده و منتظر بررسی شناسنامه اموال هستند
            </div>

        </div>

        <a
            href="{{ route('approvals.index') }}"
            class="btn btn-outline-secondary"
        >
            بازگشت
        </a>

    </div>


    <div class="card shadow-sm">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead>

                    <tr>

                        <th>
                            شماره درخواست
                        </th>

                        <th>
                            درخواست‌کننده
                        </th>

                        <th>
                            سایت / واحد
                        </th>

                        <th>
                            تعداد اموال
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
                            {{ $row['request']->requesterEmployee?->display_name ?? '-' }}
                        </td>


                        <td>

                            {{ $row['request']->site?->name ?? '-' }}

                            /

                            {{ $row['request']->department?->name ?? '-' }}

                        </td>


                        <td>
                            {{ $row['allocation_count'] }}
                        </td>


                        <td>

                            <a
                                href="{{ route(
                                    'asset-manager-requests.show',
                                    $row['step']
                                ) }}"
                                class="btn btn-sm btn-primary"
                            >
                                بررسی اموال درخواست
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="text-center text-muted py-5"
                        >
                            درخواستی در انتظار بررسی جمعدار اموال نیست.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection