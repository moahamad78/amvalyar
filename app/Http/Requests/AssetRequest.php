<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\AssetAttributeDefinition;
use App\Models\AssetAttributeOption;
use App\Models\AssetType;
use App\Support\JalaliDate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        $user =
            $this->user();

        $asset =
            $this->route('asset');

        $companyId =
            null;


        if (
            $asset instanceof Asset
        ) {

            $companyId =
                $asset->company_id;

        } elseif (
            $user !== null
            &&
            !$user->isSuperAdmin()
        ) {

            $companyId =
                $user->company_id;
        }


        $inventoryCodeRule =
            Rule::unique(
                'assets',
                'inventory_code'
            );


        if (
            $companyId !== null
        ) {

            $inventoryCodeRule->where(
                'company_id',
                $companyId
            );
        }


        if (
            $asset instanceof Asset
        ) {

            $inventoryCodeRule->ignore(
                $asset->id
            );
        }


        $rules = [

            'asset_category_id' => [
                'required',
                'integer',
                'exists:asset_categories,id',
            ],


            'asset_type_id' => [
                'required',
                'integer',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ) use (
                    $companyId
                ): void {

                    $type =
                        AssetType::query()
                            ->whereKey(
                                $value
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->first();


                    if (
                        $type === null
                    ) {

                        $fail(
                            'نوع دارایی انتخاب‌شده معتبر یا فعال نیست.'
                        );

                        return;
                    }


                    if (
                        (int) $type->asset_category_id
                        !==
                        (int) $this->input(
                            'asset_category_id'
                        )
                    ) {

                        $fail(
                            'نوع دارایی انتخاب‌شده متعلق به دسته‌بندی انتخاب‌شده نیست.'
                        );

                        return;
                    }


                    if (
                        $companyId !== null
                        &&
                        (int) $type->company_id
                        !==
                        (int) $companyId
                    ) {

                        $fail(
                            'نوع دارایی انتخاب‌شده متعلق به شرکت دیگری است.'
                        );
                    }
                },
            ],


            'inventory_code' => [
                'nullable',
                'string',
                'max:100',
                $inventoryCodeRule,
            ],


            'title' => [
                'required',
                'string',
                'max:255',
            ],


            'brand' => [
                'nullable',
                'string',
                'max:255',
            ],


            'model' => [
                'nullable',
                'string',
                'max:255',
            ],


            'serial_number' => [
                'nullable',
                'string',
                'max:255',
            ],


            'manufacturer' => [
                'nullable',
                'string',
                'max:255',
            ],


            'country' => [
                'nullable',
                'string',
                'max:255',
            ],


            'purchase_date' => [
                'nullable',
                'string',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {

                    if (
                        $value === null
                        ||
                        trim(
                            (string) $value
                        ) === ''
                    ) {

                        return;
                    }


                    try {

                        JalaliDate::toGregorianDate(
                            (string) $value
                        );

                    } catch (\Throwable) {

                        $fail(
                            'تاریخ خرید معتبر نیست. نمونه صحیح: 1405/05/16'
                        );
                    }
                },
            ],


            'purchase_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],


            'description' => [
                'nullable',
                'string',
            ],


            'photos' => [
                'nullable',
                'array',
                'max:8',
            ],


            'photos.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],


            'dynamic_attributes' => [
                'nullable',
                'array',
            ],


            'dynamic_attribute_files' => [
                'nullable',
                'array',
            ],


            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];


        $typeId =
            (int) $this->input(
                'asset_type_id',
                0
            );


        if (
            $typeId > 0
        ) {

            $definitions =
                AssetAttributeDefinition::query()
                    ->where(
                        'asset_type_id',
                        $typeId
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->when(
                        $companyId !== null,
                        fn ($query) =>
                            $query->where(
                                'company_id',
                                $companyId
                            )
                    )
                    ->with([
                        'options' => function ($query) {
                            $query->where(
                                'is_active',
                                true
                            );
                        },
                    ])
                    ->get();


            foreach (
                $definitions as $definition
            ) {

                $id =
                    (int) $definition->id;


                $requiredAtWarehouse =
                    $definition->required_stage
                    ===
                    'warehouse_entry';


                if (
                    $definition->data_type
                    ===
                    'photo'
                ) {

                    $field =
                        'dynamic_attribute_files.'
                        . $id;


                    $photoRules = [
                        'nullable',
                        'file',
                        'image',
                        'mimes:jpg,jpeg,png,webp',
                        'max:5120',
                    ];


                    if (
                        $requiredAtWarehouse
                        &&
                        !(
                            $asset instanceof Asset
                            &&
                            $asset->attributeValues()
                                ->where(
                                    'asset_attribute_definition_id',
                                    $id
                                )
                                ->whereNotNull(
                                    'file_path'
                                )
                                ->exists()
                        )
                    ) {

                        $photoRules[0] =
                            'required';
                    }


                    $rules[$field] =
                        $photoRules;


                    continue;
                }


                $field =
                    'dynamic_attributes.'
                    . $id;


                $fieldRules = [
                    $requiredAtWarehouse
                        ? 'required'
                        : 'nullable',
                ];


                switch (
                    $definition->data_type
                ) {

                    case 'number':

                        $fieldRules[] =
                            'numeric';

                        break;


                    case 'date':

                        $fieldRules[] =
                            'date';

                        break;


                    case 'boolean':

                        $fieldRules[] =
                            'boolean';

                        break;


                    case 'select':

                        $fieldRules[] =
                            'integer';

                        $fieldRules[] =
                            function (
                                string $attribute,
                                mixed $value,
                                Closure $fail
                            ) use (
                                $definition
                            ): void {

                                if (
                                    $value === null
                                    ||
                                    $value === ''
                                ) {
                                    return;
                                }


                                $valid =
                                    AssetAttributeOption::query()
                                        ->whereKey(
                                            $value
                                        )
                                        ->where(
                                            'asset_attribute_definition_id',
                                            $definition->id
                                        )
                                        ->where(
                                            'is_active',
                                            true
                                        )
                                        ->exists();


                                if (!$valid) {

                                    $fail(
                                        'گزینه انتخاب‌شده برای '
                                        . $definition->name
                                        . ' معتبر نیست.'
                                    );
                                }
                            };

                        break;


                    case 'textarea':

                        $fieldRules[] =
                            'string';

                        break;


                    case 'text':
                    default:

                        $fieldRules[] =
                            'string';

                        $fieldRules[] =
                            'max:2000';

                        break;
                }


                $rules[$field] =
                    $fieldRules;
            }
        }


        return $rules;
    }


    protected function prepareForValidation(): void
    {
        $dates = $this->input('dynamic_dates_jalali', []);
        if (! is_array($dates)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['dynamic_dates_jalali' => 'تاریخ‌ها معتبر نیستند.']);
        }
        if ($dates === [] || ! is_scalar($this->input('asset_type_id'))) {
            return;
        }
        $values = $this->input('dynamic_attributes', []);
        if (! is_array($values)) {
            return; // The existing array validation reports the malformed input.
        }
        $dateIds = AssetAttributeDefinition::query()
            ->where('asset_type_id', $this->input('asset_type_id'))
            ->where('data_type', 'date')->pluck('id')->all();
        foreach ($dates as $id => $value) {
            if (! in_array((int) $id, $dateIds, true)) {
                continue;
            }
            try {
                if (! is_string($value) && $value !== null) {
                    throw new \InvalidArgumentException;
                }
                $values[$id] = JalaliDate::toGregorianDate($value);
            } catch (\Throwable) {
                throw \Illuminate\Validation\ValidationException::withMessages(['dynamic_dates_jalali.'.$id => 'تاریخ شمسی معتبر وارد کنید.']);
            }
        }
        $this->merge(['dynamic_attributes' => $values]);
        /*
         * Checkbox/Booleanهای Dynamic اگر ارسال نشده باشند null باقی
         * می‌مانند. این رفتار برای Optional مناسب است.
         */
    }


    public function messages(): array
    {
        return [

            'asset_category_id.required' =>
                'دسته‌بندی الزامی است.',

            'asset_category_id.exists' =>
                'دسته‌بندی انتخاب‌شده معتبر نیست.',

            'asset_type_id.required' =>
                'نوع دارایی الزامی است.',

            'asset_type_id.integer' =>
                'نوع دارایی انتخاب‌شده معتبر نیست.',

            'inventory_code.unique' =>
                'این کد انبار قبلاً در همین شرکت ثبت شده است.',

            'title.required' =>
                'عنوان دارایی الزامی است.',

            'purchase_price.numeric' =>
                'مبلغ خرید معتبر نیست.',
        ];
    }
}
