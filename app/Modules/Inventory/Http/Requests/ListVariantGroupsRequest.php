<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Http\Requests\Concerns\AuthorizesInventoryRequests;
use Illuminate\Foundation\Http\FormRequest;

class ListVariantGroupsRequest extends FormRequest
{
    use AuthorizesInventoryRequests;

    public function authorize(): bool
    {
        return $this->canInActiveCompany('inventory.view');
    }

    public function rules(): array
    {
        return [];
    }
}
