<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\DeviceValidationRules;
use App\Http\Requests\Concerns\OneTimePasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyEmailCodeRequest extends FormRequest
{
    use DeviceValidationRules;
    use OneTimePasswordValidationRules;

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => $this->oneTimePasswordCodeRules(),
            'device_id' => $this->deviceIdRules(),
        ];
    }
}
