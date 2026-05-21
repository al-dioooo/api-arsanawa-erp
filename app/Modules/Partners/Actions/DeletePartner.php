<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Models\Partner;

class DeletePartner
{
    public function execute(Partner $partner): void
    {
        $partner->delete();
    }
}
