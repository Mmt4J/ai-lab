<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReview extends Model
{
    use HasFactory;

    /**
     * The sentiments the AI tagger may assign.
     *
     * @var list<string>
     */
    public const SENTIMENTS = ['positive', 'neutral', 'negative'];

    protected $fillable = [
        'customer_name',
        'review_text',
        'sentiment',
        'sentiment_error',
        'classified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classified_at' => 'datetime',
        ];
    }

    public function scopeClassified(Builder $query): Builder
    {
        return $query->whereNotNull('classified_at');
    }

    public function scopeUnclassified(Builder $query): Builder
    {
        return $query->whereNull('classified_at');
    }

    public function scopeWithSentiment(Builder $query, string $sentiment): Builder
    {
        return $query->where('sentiment', $sentiment);
    }

    public function isClassified(): bool
    {
        return $this->classified_at !== null
            && in_array($this->sentiment, self::SENTIMENTS, true);
    }

    /**
     * A tagging attempt ran but did not produce a valid sentiment.
     */
    public function hasSentimentFailed(): bool
    {
        return ! $this->isClassified() && $this->sentiment_error !== null;
    }

    /**
     * Record a failed tagging attempt without touching the review content,
     * so the review stays saved and can be retried.
     */
    public function markSentimentFailed(string $message): void
    {
        $this->forceFill([
            'sentiment_error' => $message,
        ])->save();
    }
}
