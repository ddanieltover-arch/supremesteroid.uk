<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSubmission extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'payment_id',
        'submission_type',
        'submitted_amount',
        'currency',
        // Bank transfer specific fields
        'bank_payment_reference',
        'sender_account_name',
        'transfer_date',
        'proof_document_url',
        'proof_file_id',
        // Cryptocurrency specific fields
        'crypto_network',
        'crypto_transaction_hash',
        'crypto_recipient_wallet',
        'crypto_submitted_amount',
        // Audit & Review status
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'submitted_amount' => 'decimal:2',
            'crypto_submitted_amount' => 'decimal:8',
            'transfer_date' => 'date',
            'reviewed_at' => 'datetime',
            'review_status' => PaymentStatus::class,
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function proofFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'proof_file_id');
    }
}
