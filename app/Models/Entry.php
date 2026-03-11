<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $fillable = [
        'wallet_id',
        'posting_date',
        'entry_type',
        'amount',
        'title',
        'comment',
        'reversal_of_id',
        'cash_transfer_id',
        'hash',
        'receipt_path',
        'client_request_id',
        'created_by',
        'synced_to_erp',
        'erp_ref',
        'erp_journal_entry_name',
        'erp_submitted_at',
        'erp_error',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'amount' => 'decimal:2',
        'synced_to_erp' => 'boolean',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
