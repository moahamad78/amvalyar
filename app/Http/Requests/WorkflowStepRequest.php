<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class WorkflowStepRequest extends FormRequest
{
    /**
     * Approver types that require an Employee reference.
     *
     * @var array<int,string>
     */
    private const EMPLOYEE_REFERENCE_TYPES = [
        'employee',
        'department_manager',
        'asset_manager',
        'warehouse_manager',
    ];


    /**
     * Approver types that MUST NOT receive a reference ID.
     *
     * @var array<int,string>
     */
    private const AUTOMATIC_APPROVER_TYPES = [
        'direct_manager',
        'requester',
        'system',
    ];


    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        return [

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:100',
            ],

            'step_type' => [
                'required',
                Rule::in([
                    'approval',
                    'action',
                    'notification',
                    'condition',
                ]),
            ],

            'approver_type' => [
                'nullable',
                Rule::in([
                    'direct_manager',
                    'employee',
                    'role',
                    'department_manager',
                    'asset_manager',
                    'warehouse_manager',
                    'requester',
                    'system',
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Smart Approver Reference
            |--------------------------------------------------------------------------
            |
            | وجود خود Reference اینجا فقط از نظر نوع داده بررسی می‌شود.
            |
            | تطبیق آن با:
            |
            | - نوع تأییدکننده
            | - شرکت Workflow
            | - Employee فعال
            | - Role معتبر
            |
            | در withValidator انجام می‌شود.
            |
            */

            'approver_reference_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'is_required' => [
                'nullable',
                'boolean',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'rejection_action' => [
                'required',
                Rule::in([
                    'terminate',
                    'return_previous',
                    'return_requester',
                ]),
            ],

            'due_hours' => [
                'nullable',
                'integer',
                'min:1',
                'max:8760',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }


    public function withValidator(
        Validator $validator
    ): void {

        $validator->after(
            function (
                Validator $validator
            ): void {

                /*
                |--------------------------------------------------------------------------
                | Base validation failed?
                |--------------------------------------------------------------------------
                |
                | اگر approver_type یا Reference از نظر اولیه نامعتبر باشد،
                | Validation ثانویه را ادامه نمی‌دهیم.
                |
                */

                if (
                    $validator->errors()->has(
                        'approver_type'
                    )
                    ||
                    $validator->errors()->has(
                        'approver_reference_id'
                    )
                ) {
                    return;
                }


                $workflow =
                    $this->route(
                        'workflow'
                    );


                if (
                    !($workflow instanceof Workflow)
                ) {

                    $validator->errors()->add(
                        'workflow',
                        'گردش کاری معتبر شناسایی نشد.'
                    );

                    return;
                }


                $approverType =
                    $this->input(
                        'approver_type'
                    );


                $referenceId =
                    $this->filled(
                        'approver_reference_id'
                    )
                        ? (int) $this->input(
                            'approver_reference_id'
                        )
                        : null;


                /*
                |--------------------------------------------------------------------------
                | Approval Step Requires Approver
                |--------------------------------------------------------------------------
                |
                | یک مرحله از نوع approval نباید بدون مسئول ساخته شود.
                |
                */

                if (
                    $this->input('step_type')
                    ===
                    'approval'
                    &&
                    empty($approverType)
                ) {

                    $validator->errors()->add(
                        'approver_type',
                        'برای مرحله تأیید، تعیین تأییدکننده الزامی است.'
                    );

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | No Approver Type
                |--------------------------------------------------------------------------
                |
                | اگر مرحله اصلاً تأییدکننده ندارد، Reference هم نباید داشته باشد.
                |
                */

                if (
                    empty($approverType)
                ) {

                    if ($referenceId !== null) {

                        $validator->errors()->add(
                            'approver_reference_id',
                            'برای مرحله بدون تأییدکننده، شناسه مرجع نباید ارسال شود.'
                        );
                    }

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Automatic Approvers
                |--------------------------------------------------------------------------
                |
                | direct_manager
                | requester
                | system
                |
                | این موارد در Runtime Resolve می‌شوند و ID دستی ممنوع است.
                |
                */

                if (
                    in_array(
                        $approverType,
                        self::AUTOMATIC_APPROVER_TYPES,
                        true
                    )
                ) {

                    if ($referenceId !== null) {

                        $validator->errors()->add(
                            'approver_reference_id',
                            'برای این نوع تأییدکننده، انتخاب شناسه مرجع مجاز نیست.'
                        );
                    }

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Employee-backed Approvers
                |--------------------------------------------------------------------------
                |
                | employee
                | department_manager
                | asset_manager
                | warehouse_manager
                |
                */

                if (
                    in_array(
                        $approverType,
                        self::EMPLOYEE_REFERENCE_TYPES,
                        true
                    )
                ) {

                    if ($referenceId === null) {

                        $validator->errors()->add(
                            'approver_reference_id',
                            'انتخاب پرسنل تأییدکننده الزامی است.'
                        );

                        return;
                    }


                    $employee =
                        Employee::withoutGlobalScopes()
                            ->where(
                                'company_id',
                                $workflow->company_id
                            )
                            ->whereKey(
                                $referenceId
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->first();


                    if ($employee === null) {

                        $validator->errors()->add(
                            'approver_reference_id',
                            'پرسنل انتخاب‌شده معتبر، فعال یا متعلق به این شرکت نیست.'
                        );
                    }

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Role Approver
                |--------------------------------------------------------------------------
                */

                if (
                    $approverType
                    ===
                    'role'
                ) {

                    if ($referenceId === null) {

                        $validator->errors()->add(
                            'approver_reference_id',
                            'انتخاب نقش تأییدکننده الزامی است.'
                        );

                        return;
                    }


                    $roleExists =
                        Role::query()
                            ->whereKey(
                                $referenceId
                            )
                            ->exists();


                    if (!$roleExists) {

                        $validator->errors()->add(
                            'approver_reference_id',
                            'نقش انتخاب‌شده معتبر نیست.'
                        );
                    }
                }
            }
        );
    }


    public function messages(): array
    {
        return [

            'name.required' =>
                'نام مرحله الزامی است.',

            'code.required' =>
                'کد مرحله الزامی است.',

            'step_type.required' =>
                'نوع مرحله الزامی است.',

            'step_type.in' =>
                'نوع مرحله معتبر نیست.',

            'approver_type.in' =>
                'نوع تأییدکننده معتبر نیست.',

            'approver_reference_id.integer' =>
                'شناسه مرجع تأییدکننده معتبر نیست.',

            'approver_reference_id.min' =>
                'شناسه مرجع تأییدکننده معتبر نیست.',

            'rejection_action.required' =>
                'رفتار زمان رد درخواست را مشخص کنید.',

            'rejection_action.in' =>
                'رفتار انتخاب‌شده برای رد درخواست معتبر نیست.',

            'due_hours.integer' =>
                'مهلت انجام باید برحسب ساعت و به‌صورت عدد صحیح باشد.',

            'due_hours.min' =>
                'مهلت انجام باید حداقل یک ساعت باشد.',

            'due_hours.max' =>
                'مهلت انجام بیش از حد مجاز است.',
        ];
    }
}