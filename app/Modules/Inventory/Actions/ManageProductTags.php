<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Tag;

class ManageProductTags
{
    /**
     * @param  array<int, string>  $tagNames
     */
    public function execute(Product $product, array $tagNames): Product
    {
        $tagIds = [];

        foreach ($tagNames as $name) {
            $tag = Tag::firstOrCreate(
                ['company_id' => $product->company_id, 'name' => $name],
            );
            $tagIds[] = $tag->id;
        }

        $product->tags()->sync($tagIds);

        return $product->load('tags');
    }
}
