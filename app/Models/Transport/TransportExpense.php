<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Other transport costs: EMI, rent, insurance premium, road tax, toll… */
class TransportExpense extends Model
{
    use BelongsToSchool;

    public const CATEGORIES = ['emi' => 'Vehicle EMI', 'rent' => 'Vehicle rent', 'insurance' => 'Insurance premium', 'tax' => 'Road tax',
        'permit' => 'Permit / fitness fees', 'toll' => 'Toll & parking', 'cleaning' => 'Cleaning', 'misc' => 'Miscellaneous'];

    protected $fillable = ['school_id', 'vehicle_id', 'expense_date', 'category', 'title', 'amount', 'method', 'bill_path', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
