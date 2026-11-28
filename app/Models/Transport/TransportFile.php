<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Photo or document of a vehicle or a driver / conductor. */
class TransportFile extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'owner_type', 'owner_id', 'kind', 'title', 'path'];

    public function isImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp)$/i', $this->path);
    }
}
