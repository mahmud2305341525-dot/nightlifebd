<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'regex:/^01[3-9]\d{8}$/'],
            'gender' => ['nullable', 'in:female,male,other'],
            'city' => ['nullable', 'string', 'max:60'],
            'telegram_chat_id' => ['nullable', 'regex:/^-?\d{5,20}$/'],
            'is_adult_confirmed' => ['nullable', 'boolean'],
        ];
    }
}
