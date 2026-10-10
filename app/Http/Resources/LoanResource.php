<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * @mixin Loan
 */
class LoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fineCents = $this->isReturned() ? $this->fine_cents : $this->calculateFine();

        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'book' => new BookResource($this->whenLoaded('book')),
            'loaned_at' => $this->loaned_at,
            'due_at' => $this->due_at,
            'returned_at' => $this->returned_at,
            'is_overdue' => $this->isOverdue(),
            'days_late' => $this->daysLate(),
            'fine_cents' => $fineCents,
            'fine' => Number::currency($fineCents / 100, in: 'BRL', locale: 'pt_BR'),
        ];
    }
}
