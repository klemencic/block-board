<?php

namespace App\Http\Requests;

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\LeaseTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRepairRequest extends FormRequest
{
    public const MAX_PHOTOS = 5;

    public const MAX_KB = 10240;

    /** Okvaro prijavi najemnik ali lastnik, izvajalec ne. */
    public function authorize(): bool
    {
        $building = $this->route('building');

        return $this->user()->isLandlordOf($building) || $this->user()->hasRole($building, Role::Tenant);
    }

    public function rules(): array
    {
        $building = $this->route('building');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', 'exists:repair_categories,id'],
            'apartment_id' => ['nullable', 'integer', 'required_without:shared_space_id', 'prohibits:shared_space_id'],
            'shared_space_id' => [
                'nullable', 'integer', 'required_without:apartment_id',
                Rule::exists('shared_spaces', 'id')->where('building_id', $building->id),
            ],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:'.self::MAX_KB],
        ];
    }

    public function attributes(): array
    {
        $attributes = [
            'title' => 'naslov',
            'description' => 'opis',
            'category_id' => 'vrsta okvare',
            'apartment_id' => 'lokacija',
            'shared_space_id' => 'lokacija',
        ];

        // Sporočilo navede ime zavrnjene datoteke.
        foreach ((array) $this->file('photos', []) as $i => $file) {
            $attributes["photos.$i"] = '»'.$file->getClientOriginalName().'«';
        }

        return $attributes;
    }

    public function messages(): array
    {
        return [
            'photos.max' => 'Priložite lahko največ '.self::MAX_PHOTOS.' fotografij.',
            'photos.*.mimes' => 'Datoteka :attribute ni v obliki JPG ali PNG.',
            'photos.*.mimetypes' => 'Datoteka :attribute ni v obliki JPG ali PNG.',
            'photos.*.max' => 'Datoteka :attribute je večja od 10 MB.',
            'apartment_id.required_without' => 'Izberite lokacijo okvare.',
            'shared_space_id.required_without' => 'Izberite lokacijo okvare.',
        ];
    }

    /**
     * Najemnik lahko izbere samo stanovanje, v katerem ima aktiven najem (US-09, kriterij 1).
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $apartmentId = $this->integer('apartment_id');
            if (! $apartmentId || $validator->errors()->has('apartment_id')) {
                return;
            }

            $building = $this->route('building');
            $user = $this->user();

            $allowed = $user->isLandlordOf($building)
                ? $building->apartments()->whereKey($apartmentId)->exists()
                : LeaseTenant::where('user_id', $user->id)
                    ->whereNull('moved_out_on')
                    ->whereHas('lease', fn ($q) => $q->where('apartment_id', $apartmentId)->whereIn('status', LeaseStatus::occupying()))
                    ->exists();

            if (! $allowed) {
                $validator->errors()->add('apartment_id', 'Okvaro lahko prijavite samo za stanovanje, v katerem imate aktiven najem.');
            }
        }];
    }
}
