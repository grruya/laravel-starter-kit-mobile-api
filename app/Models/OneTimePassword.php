<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OneTimePasswordPurpose;
use Carbon\CarbonInterface;
use Database\Factories\OneTimePasswordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read OneTimePasswordPurpose $purpose
 * @property-read string $code_hash
 * @property-read string|null $device_id_hash
 * @property-read int $attempts
 * @property-read CarbonInterface|null $sent_at
 * @property-read CarbonInterface $expires_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable(['user_id', 'purpose', 'code_hash', 'device_id_hash', 'attempts', 'sent_at', 'expires_at'])]
final class OneTimePassword extends Model
{
    /** @use HasFactory<OneTimePasswordFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'purpose' => OneTimePasswordPurpose::class,
            'code_hash' => 'string',
            'device_id_hash' => 'string',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('expires_at', '<=', now());
    }
}
