<?php

namespace App\Http\Requests;

use App\Enums\SplitMethod;
use App\Enums\UtilityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UtilityBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('building'));
    }

    public function rules(): array
    {
        $building = $this->route('building');

        return [
            'scope' => ['required', Rule::in(['apartment', 'building'])],
            'apartment_id' => [
                'nullable', 'required_if:scope,apartment', 'integer',
                Rule::exists('apartments', 'id')->where('building_id', $building->id),
            ],
            'type' => ['required', Rule::enum(UtilityType::class)],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'split_method' => ['nullable', 'required_if:scope,building', Rule::enum(SplitMethod::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'apartment_id' => 'stanovanje',
            'type' => 'vrsta',
            'amount_cents' => 'znesek',
            'period_start' => 'začetek obdobja',
            'period_end' => 'konec obdobja',
            'split_method' => 'način delitve',
        ];
    }

    public function messages(): array
    {
        return [
            'period_end.after_or_equal' => 'Konec obdobja ne sme biti pred začetkom.',
            'apartment_id.required_if' => 'Izberite stanovanje.',
            'split_method.required_if' => 'Izberite način delitve med stanovanja.',
        ];
    }

    /** Podatki za BillSplitter: pri položnici za stavbo ni stanovanja, pri stanovanju ni načina delitve. */
    public function billData(): array
    {
        $data = $this->validated();
        if ($data['scope'] === 'building') {
            $data['apartment_id'] = null;
        } else {
            $data['split_method'] = null;
        }

        return $data;
    }
}
