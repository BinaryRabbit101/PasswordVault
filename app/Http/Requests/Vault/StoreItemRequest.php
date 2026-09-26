<?php

namespace App\Http\Requests\Vault;

use App\Models\ItemField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vault_id' => [
                'required',
                'integer',
                Rule::exists('user_vault', 'vault_id')->where('user_id', $this->user()?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'favorite' => ['boolean'],
            'folder' => ['nullable', 'string', 'max:255'],
            'fields' => ['array', 'max:100'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(ItemField::TYPES)],
            'fields.*.autofill' => ['nullable', Rule::in(ItemField::AUTOFILL)],
            'fields.*.value' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * One-time-code fields must hold a base32 secret.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('fields', []) as $index => $field) {
                    $value = $field['value'] ?? null;

                    if (($field['type'] ?? null) === 'totp' && is_string($value) && $value !== '' && ! preg_match('/^[A-Z2-7]+=*$/', $value)) {
                        $validator->errors()->add("fields.{$index}.value", __('A one-time code needs a base32 secret or an otpauth:// link.'));
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $fields = $this->input('fields');

        if (! is_array($fields)) {
            return;
        }

        foreach ($fields as $index => $field) {
            if (is_array($field) && ($field['type'] ?? null) === 'totp' && is_string($field['value'] ?? null)) {
                $fields[$index]['value'] = self::normaliseTotp($field['value']);
            }
        }

        $this->merge(['fields' => $fields]);
    }

    /**
     * Accept a pasted otpauth:// URI and reduce it to its secret param.
     */
    private static function normaliseTotp(string $secret): string
    {
        if (Str::startsWith($secret, 'otpauth://')) {
            parse_str((string) parse_url($secret, PHP_URL_QUERY), $query);

            if (is_string($query['secret'] ?? null)) {
                $secret = $query['secret'];
            }
        }

        return strtoupper(str_replace(' ', '', $secret));
    }
}
