<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Department;
use App\Models\Location;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class InventoryRequestDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        return [

            'site_id' => [
                'nullable',
                'integer',
            ],

            'department_id' => [
                'nullable',
                'integer',
            ],

            'delivery_target_type' => [
                'required',
                Rule::in([
                    'employee',
                    'organization',
                ]),
            ],

            'target_site_id' => [
                'nullable',
                'integer',
            ],

            'target_department_id' => [
                'nullable',
                'integer',
            ],

            'target_location_id' => [
                'nullable',
                'integer',
            ],

            'priority' => [
                'required',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ]),
            ],

            'purpose' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'description' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],

            'items.*.asset_category_id' => [
                'required',
                'integer',
                'exists:asset_categories,id',
            ],

            'items.*.item_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'items.*.item_name' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.unit' => [
                'nullable',
                'string',
                'max:50',
            ],

            'items.*.requested_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999999',
            ],

            'items.*.description' => [
                'nullable',
                'string',
                'max:1000',
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

                $user =
                    $this->user();

                if (
                    $user === null
                    ||
                    $user->company_id === null
                ) {

                    $validator->errors()->add(
                        'company',
                        'شرکت کاربر مشخص نیست.'
                    );

                    return;
                }


                $companyId =
                    (int) $user->company_id;


                if (
                    $this->filled('site_id')
                ) {

                    $validSite =
                        Site::withoutGlobalScopes()
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->whereKey(
                                (int) $this->input('site_id')
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->exists();


                    if (!$validSite) {

                        $validator->errors()->add(
                            'site_id',
                            'سایت انتخاب‌شده معتبر، فعال یا متعلق به شرکت شما نیست.'
                        );
                    }
                }


                if (
                    $this->input('delivery_target_type')
                    ===
                    'organization'
                ) {
                    if (
                        !$this->filled('target_site_id')
                        &&
                        !$this->filled('target_department_id')
                        &&
                        !$this->filled('target_location_id')
                    ) {
                        $validator->errors()->add(
                            'delivery_target_type',
                            'برای درخواست سازمانی حداقل یک مقصد شامل سایت، واحد یا محل باید مشخص شود.'
                        );
                    }

                    if ($this->filled('target_site_id')) {
                        $validTargetSite =
                            Site::withoutGlobalScopes()
                                ->where('company_id', $companyId)
                                ->whereKey(
                                    (int) $this->input('target_site_id')
                                )
                                ->where('is_active', true)
                                ->exists();

                        if (!$validTargetSite) {
                            $validator->errors()->add(
                                'target_site_id',
                                'سایت مقصد معتبر، فعال یا متعلق به شرکت شما نیست.'
                            );
                        }
                    }

                    if ($this->filled('target_department_id')) {
                        $validTargetDepartment =
                            Department::withoutGlobalScopes()
                                ->where('company_id', $companyId)
                                ->whereKey(
                                    (int) $this->input('target_department_id')
                                )
                                ->where('is_active', true)
                                ->exists();

                        if (!$validTargetDepartment) {
                            $validator->errors()->add(
                                'target_department_id',
                                'واحد مقصد معتبر، فعال یا متعلق به شرکت شما نیست.'
                            );
                        }
                    }

                    if ($this->filled('target_location_id')) {
                        $targetLocation =
                            Location::withoutGlobalScopes()
                                ->where('company_id', $companyId)
                                ->whereKey(
                                    (int) $this->input('target_location_id')
                                )
                                ->where('is_active', true)
                                ->first();

                        if ($targetLocation === null) {
                            $validator->errors()->add(
                                'target_location_id',
                                'محل مقصد معتبر، فعال یا متعلق به شرکت شما نیست.'
                            );
                        }
                        elseif (
                            $this->filled('target_site_id')
                            &&
                            $targetLocation->site_id !== null
                            &&
                            (int) $targetLocation->site_id
                                !== (int) $this->input('target_site_id')
                        ) {
                            $validator->errors()->add(
                                'target_location_id',
                                'محل مقصد متعلق به سایت مقصد انتخاب‌شده نیست.'
                            );
                        }
                    }
                }

                if (
                    $this->filled('department_id')
                ) {

                    $validDepartment =
                        Department::withoutGlobalScopes()
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->whereKey(
                                (int) $this->input('department_id')
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->exists();


                    if (!$validDepartment) {

                        $validator->errors()->add(
                            'department_id',
                            'واحد سازمانی انتخاب‌شده معتبر، فعال یا متعلق به شرکت شما نیست.'
                        );
                    }
                }
            }
        );
    }


    public function messages(): array
    {
        return [

            'delivery_target_type.required' =>
                'نوع مقصد درخواست مشخص نشده است.',

            'delivery_target_type.in' =>
                'نوع مقصد درخواست معتبر نیست.',

            'priority.required' =>
                'اولویت درخواست مشخص نشده است.',

            'priority.in' =>
                'اولویت درخواست معتبر نیست.',

            'items.required' =>
                'حداقل یک قلم کالا وارد کنید.',

            'items.array' =>
                'ساختار اقلام درخواست معتبر نیست.',

            'items.min' =>
                'حداقل یک قلم کالا وارد کنید.',

            'items.max' =>
                'حداکثر ۲۰۰ قلم را می‌توان در یک درخواست ثبت کرد.',

            'items.*.asset_category_id.required' =>
                'دسته‌بندی کالا برای همه ردیف‌ها الزامی است.',

            'items.*.asset_category_id.exists' =>
                'دسته‌بندی انتخاب‌شده معتبر نیست.',

            'items.*.item_name.required' =>
                'نام کالا برای همه ردیف‌ها الزامی است.',

            'items.*.requested_quantity.required' =>
                'تعداد درخواستی برای همه ردیف‌ها الزامی است.',

            'items.*.requested_quantity.numeric' =>
                'تعداد درخواستی باید عدد باشد.',

            'items.*.requested_quantity.gt' =>
                'تعداد درخواستی باید بیشتر از صفر باشد.',
        ];
    }
}