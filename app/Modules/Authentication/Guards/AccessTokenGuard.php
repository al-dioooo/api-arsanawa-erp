<?php

namespace App\Modules\Authentication\Guards;

use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request;

class AccessTokenGuard extends RequestGuard
{
    public function setRequest(Request $request): static
    {
        $this->forgetUser();

        parent::setRequest($request);

        return $this;
    }
}
