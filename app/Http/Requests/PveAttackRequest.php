<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PveAttackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'npc_id' => ['required', 'integer', 'min:1'],
            'use_dao_techniques' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('use_dao_techniques')) {
            $v = $this->input('use_dao_techniques');
            if ($v === '1' || $v === '0' || $v === 1 || $v === 0) {
                $this->merge([
                    'use_dao_techniques' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }
}
