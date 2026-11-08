<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('apartment')->building);
    }

    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'notice_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'monthly_rent_cents' => ['required', 'integer', 'min:1'],
            'deposit_cents' => ['required', 'integer', 'min:0'],
            'tenants' => ['required', 'array', 'min:1'],
            'tenants.*.name' => ['required', 'string', 'max:255'],
            'tenants.*.email' => ['required', 'email', 'max:255', 'distinct:ignore_case'],
            'tenants.*.phone' => ['nullable', 'string', 'max:50'],
            'tenants.*.rent_share_cents' => ['required', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'starts_on' => 'datum začetka',
            'ends_on' => 'datum konca',
            'notice_period_days' => 'odpovedni rok',
            'monthly_rent_cents' => 'mesečna najemnina',
            'deposit_cents' => 'varščina',
            'tenants' => 'najemniki',
            'tenants.*.name' => 'ime',
            'tenants.*.email' => 'e-naslov',
            'tenants.*.rent_share_cents' => 'delež najemnine',
        ];
    }

    public function messages(): array
    {
        return [
            'tenants.required' => 'Najem mora imeti vsaj enega najemnika.',
            'tenants.min' => 'Najem mora imeti vsaj enega najemnika.',
            'ends_on.after' => 'Datum konca mora biti po datumu začetka.',
            'tenants.*.email.distinct' => 'Isti e-naslov je naveden večkrat.',
        ];
    }

    /**
     * Vsota deležev mora biti enaka mesečni najemnini (US-01, kriterij 2).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $rent = (int) $this->input('monthly_rent_cents');
                $sum = collect($this->input('tenants'))->sum('rent_share_cents');
                $diff = $sum - $rent;

                if ($diff !== 0) {
                    $direction = $diff < 0 ? 'nižji' : 'višji';
                    $validator->errors()->add(
                        'tenants',
                        'Deleži so za '.Money::format(abs($diff))." {$direction} od najemnine."
                    );
                }
            },
        ];
    }
}
