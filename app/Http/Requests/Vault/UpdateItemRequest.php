<?php

namespace App\Http\Requests\Vault;

class UpdateItemRequest extends StoreItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // The item stays in its vault unless a new (member-validated) one is sent.
        $rules['vault_id'][0] = 'sometimes';

        return $rules;
    }
}
