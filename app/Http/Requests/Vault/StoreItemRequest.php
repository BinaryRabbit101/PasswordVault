<?php

namespace App\Http\Requests\Vault;

use App\Models\ItemField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'url' => ['nullable', 'string', 'max:2048'],
            'username' => ['nullable', 'string', 'max:1024'],
            'password' => ['nullable', 'string', 'max:1024'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'totp_secret' => ['nullable', 'string', 'regex:/^[A-Z2-7]+=*$/i', 'max:256'],
            'favorite' => ['boolean'],
            'folder' => ['nullable', 'string', 'max:255'],
            'fields' => ['array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(ItemField::TYPES)],
            'fields.*.value' => ['nullable', 'string', 'max:20000'],
            'fields.*.is_secret' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $secret = $this->input('totp_secret');

        if (is_string($secret) && $secret !== '') {
            // Accept a pasted otpauth:// URI and reduce it to its secret param.
            if (Str::startsWith($secret, 'otpauth://')) {
                parse_str((string) parse_url($secret, PHP_URL_QUERY), $query);

                if (is_string($query['secret'] ?? null)) {
                    $secret = $query['secret'];
                }
            }

            $this->merge(['totp_secret' => strtoupper(str_replace(' ', '', $secret))]);
        }
    }
}
