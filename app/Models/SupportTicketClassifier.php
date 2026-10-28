<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicketClassifier extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The ticket categories the AI classifier may assign.
     *
     * @var list<string>
     */
    public const CATEGORIES = ['billing', 'technical', 'complaint'];

    /**
     * The urgency levels the AI classifier may assign.
     *
     * @var list<string>
     */
    public const URGENCY_LEVELS = ['low', 'medium', 'high'];

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'customer_name',
        'company_name',
        'email',
        'ticket_subject',
        'ticket_message',
        'ticket_category',
        'urgency_level',
        'ai_summary',
        'status',
        'classified_at',
        'closed_at',
        'classification_requested_at',
        'classification_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classified_at' => 'datetime',
            'closed_at' => 'datetime',
            'classification_requested_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeClassified(Builder $query): Builder
    {
        return $query->whereNotNull('classified_at');
    }

    public function scopeUnclassified(Builder $query): Builder
    {
        return $query->whereNull('classified_at');
    }

    public function scopeHighUrgency(Builder $query): Builder
    {
        return $query->where('urgency_level', 'high');
    }

    /**
     * Tickets whose queued classification has been requested but has not
     * finished (or failed) yet.
     */
    public function scopePendingClassification(Builder $query): Builder
    {
        return $query->whereNotNull('classification_requested_at')
            ->whereNull('classified_at')
            ->whereNull('classification_error');
    }

    /**
     * Filter tickets by a free-text term across customer and ticket content.
     *
     * PostgreSQL's LIKE is case-sensitive, so ILIKE is used there; on
     * drivers whose LIKE is already case-insensitive (SQLite, MySQL)
     * LIKE is kept.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($operator, $like): void {
            $query->where('customer_name', $operator, $like)
                ->orWhere('company_name', $operator, $like)
                ->orWhere('email', $operator, $like)
                ->orWhere('ticket_subject', $operator, $like)
                ->orWhere('ticket_message', $operator, $like);
        });
    }

    public function isClassified(): bool
    {
        return $this->classified_at !== null
            && $this->ticket_category !== null
            && $this->urgency_level !== null
            && $this->ai_summary !== null;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * A queued classification has been requested and is still running.
     * Trashed tickets are never reported as pending.
     */
    public function isClassificationPending(): bool
    {
        return ! $this->trashed()
            && $this->classified_at === null
            && $this->classification_error === null
            && $this->classification_requested_at !== null;
    }

    /**
     * A queued classification ran but did not produce a valid result.
     */
    public function hasClassificationFailed(): bool
    {
        return $this->classified_at === null && $this->classification_error !== null;
    }

    /**
     * Mark the ticket as awaiting a queued classification and clear any
     * previous failure so the UI can show it as in progress.
     */
    public function markClassificationRequested(): void
    {
        $this->forceFill([
            'classification_requested_at' => now(),
            'classification_error' => null,
        ])->save();
    }

    /**
     * Record a failed classification attempt without touching the ticket
     * content, so the ticket stays open and can be retried.
     */
    public function markClassificationFailed(string $message): void
    {
        $this->forceFill([
            'classification_error' => $message,
        ])->save();
    }

    /**
     * Stop tracking the ticket as awaiting classification without recording
     * a failure, used when a pending ticket leaves the active list.
     */
    public function clearClassificationRequest(): void
    {
        $this->forceFill([
            'classification_requested_at' => null,
        ])->save();
    }
}
