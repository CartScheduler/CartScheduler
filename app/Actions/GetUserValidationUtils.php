<?php

namespace App\Actions;

use App\Enums\Appointment;
use App\Enums\MaritalStatus;
use App\Enums\Role;
use App\Enums\ServingAs;
use App\Models\User;
use App\Rules\SpouseIsOppositeGenderRule;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GetUserValidationUtils
{
    public function prepare(array $data): array
    {
        $data['mobile_phone'] = isset($data['mobile_phone'])
            ? Str::of($data['mobile_phone'])
                ->tap(fn(string $value) => Str::startsWith($value, '+') ? "0$value" : $value)
                ->replaceMatches('/[^A-Za-z0-9]++/', '')
                ->trim()
                ->toString()
            : null;

        $data['email'] = isset($data['email'])
            ? Str::of($data['email'])->lower()->trim()->toString()
            : null;

        if (isset($data['gender']) && $data['gender'] === 'm') {
            $data['gender'] = 'male';
        }
        if (isset($data['gender']) && $data['gender'] === 'f') {
            $data['gender'] = 'female';
        }

        if (isset($data['spouse_email'])) {
            $spouse = User::where('email', $data['spouse_email'])->first();
            if ($spouse) {
                $data['spouse_id'] = $spouse->id;
            }
        }

        if (!isset($data['year_of_birth'])) {
            $data['year_of_birth'] = null;
        }
        if (!isset($data['appointment'])) {
            $data['appointment'] = null;
        }
        if (!isset($data['serving_as'])) {
            $data['serving_as'] = null;
        }
        if (!isset($data['marital_status'])) {
            $data['marital_status'] = null;
        }
        if (!isset($data['spouse_id'])) {
            $data['spouse_id'] = null;
        }

        $this->tidyBoolean($data, 'responsible_brother');
        $this->tidyBoolean($data, 'is_unrestricted');

        return $data;
    }

    protected function tidyBoolean(array &$data, string $fieldName): void
    {
        if (!isset($data[$fieldName])
            || trim((string) $data[$fieldName]) === '0'
            || strtolower(trim((string) $data[$fieldName])) === 'false') {
            $data[$fieldName] = false;
        } elseif (trim((string) $data[$fieldName]) === '1'
            || strtolower(trim((string) $data[$fieldName])) === 'true') {
            $data[$fieldName] = true;
        }
    }

    public function rules(): array
    {
        return [
            'id'                  => ['sometimes', 'integer'],
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255'],
            'role'                => ['required', 'string', Rule::enum(Role::class)],
            'gender'              => ['required', 'string', 'in:male,female'],
            'mobile_phone'        => ['required', 'string', 'regex:/^([0-9\+\-\s]+)$/', 'min:8', 'max:15'],
            'year_of_birth'       => [
                'nullable', 'integer', 'min:' . Carbon::now()->year - 100, 'max:' . Carbon::now()->year
            ],
            'appointment'         => ['nullable', 'string', Rule::enum(Appointment::class)],
            'serving_as'          => ['nullable', 'string', Rule::enum(ServingAs::class)],
            'spouse_id'           => [
                'nullable', 'integer', 'exists:users,id', 'unique:users,spouse_id', new SpouseIsOppositeGenderRule,
            ],
            'marital_status'      => ['nullable', 'string', Rule::enum(MaritalStatus::class)],
            'responsible_brother' => ['nullable', 'boolean'],
            'is_unrestricted'     => ['boolean'],
        ];
    }

    public function extraValidation(bool $isPrecognitive, ?string $key = '', ?array $fields = null): Closure
    {
        return static function (Validator $validator) use ($isPrecognitive, $key, $fields) {
            if (!$fields) {
                $fields = $validator->validated();
            }

            if ($key && !Str::endsWith($key, '.')) {
                $key .= '.';
            }

            if (
                isset($fields['gender'], $fields['appointment']) && !$isPrecognitive && $fields['gender'] === 'female' && $fields['appointment']
            ) {
                $validator->errors()->add("{$key}gender", 'A sister user cannot have an appointment.');
            }
            if (
                !$isPrecognitive
                && isset($fields['is_unrestricted'], $fields['role'])
                && !$fields['is_unrestricted']
                && $fields['role'] === Role::Admin->value
            ) {
                $validator->errors()->add("{$key}is_unrestricted", 'Restricted users cannot be an administrator.');
            }
        };
    }
}
