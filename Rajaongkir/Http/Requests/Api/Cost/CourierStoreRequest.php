<?php

namespace Modules\Rajaongkir\Http\Requests\Api\Cost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Geocodes\Models\Geocodes;
use Modules\Rajaongkir\Models\Rajaongkir;

class CourierStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $couriers = (new Rajaongkir)->getCouriersId();

        return [
            'origin' => [
                'required', 'integer', 'digits_between:1,20',
                Rule::exists((new Geocodes)->getTable(), 'rajaongkir_id')->where(function ($query) {
                    $query->where('type', 'regency');
                }),
            ],
            'destination' => [
                'required', 'integer', 'digits_between:1,20',
                Rule::exists((new Geocodes)->getTable(), 'rajaongkir_id')->where(function ($query) {
                    $query->where('type', 'regency');
                }),
            ],
            'weight' => ['required', 'integer', 'digits_between:1,20'],
            'courier' => [
                'required',
                Rule::in($couriers),
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}