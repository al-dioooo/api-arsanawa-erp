<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Services\PostingService;

class VoidJournalEntry
{
    public function __construct(protected PostingService $postingService) {}

    public function execute(JournalEntry $entry, User $user): JournalEntry
    {
        return $this->postingService->reverse($entry, $user);
    }
}
