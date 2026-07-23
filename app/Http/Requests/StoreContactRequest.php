<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],
            'phone' => [
                'required',
                'string',
                'min:7',
                'max:20',
                'regex:/^\+?[0-9\s\-()]+$/',
            ],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
            ],
            'comment' => [
                'required',
                'string',
                'min:10',
                'max:3000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'name.string' => 'Имя должно быть строкой.',
            'name.min' => 'Имя должно содержать не менее 2 символов.',
            'name.max' => 'Имя должно содержать не более 100 символов.',
            'phone.required' => 'Укажите телефон.',
            'phone.string' => 'Телефон должен быть строкой.',
            'phone.min' => 'Телефон должен содержать не менее 7 символов.',
            'phone.max' => 'Телефон должен содержать не более 20 символов.',
            'phone.regex' => 'Телефон может содержать только цифры, пробелы, скобки, дефисы и символ +.',
            'email.required' => 'Укажите email.',
            'email.email' => 'Укажите корректный email.',
            'email.max' => 'Email должен содержать не более 255 символов.',
            'comment.required' => 'Укажите комментарий.',
            'comment.string' => 'Комментарий должен быть строкой.',
            'comment.min' => 'Комментарий должен содержать не менее 10 символов.',
            'comment.max' => 'Комментарий должен содержать не более 3000 символов.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Данные не прошли проверку.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
