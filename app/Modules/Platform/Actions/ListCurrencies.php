<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Currency;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ListCurrencies
{
    /**
     * @return Collection<int, Currency>
     */
    public function execute(): Collection
    {
        return Cache::rememberForever(
            Currency::ACTIVE_CACHE_KEY,
            fn (): Collection => Currency::query()->active()->orderBy('code')->get(),
        );
    }
}
