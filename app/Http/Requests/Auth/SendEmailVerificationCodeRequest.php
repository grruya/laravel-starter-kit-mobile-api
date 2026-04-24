<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\DeviceValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class SendEmailVerificationCodeRequest extends FormRequest
{
    use DeviceValidationRules;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_id' => $this->deviceIdRules(),
        ];
    }
}
