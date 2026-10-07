<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Database\Factories\ClientDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone the mobile app is installed on, identified by its FCM registration
 * token. One account may sign in on several phones; a token belongs to exactly
 * one account at a time — whoever signed in on that phone last.
 *
 * `locale` is the app language at the moment of registration; pushes to this
 * phone are rendered in it.
 */
class ClientDevice extends Model
{
    /** @use HasFactory<ClientDeviceFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'client_id',
        'token',
        'platform',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
