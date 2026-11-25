<?php

namespace App\Http\Controllers\Admin;

use App\Models\BaseItem;
use Illuminate\Http\Request;

class AiImageController extends BaseItemController
{
    protected string $module = 'ai-image';

    protected array $searchColumns = ['prompt'];

    protected function rules(Request $request, ?BaseItem $item): array
    {
        return [
            'category_id' => 'required|exists:' . $this->categoryTable() . ',id',
            'image'       => $this->fileRule($item, 'image|mimes:webp|max:10240'),
            'prompt'      => 'required|string|max:10000',
            'status'      => 'nullable|boolean',
        ];
    }

    protected function imageFields(): array
    {
        return ['image'];
    }

    protected function persist(Request $request, BaseItem $item): void
    {
        $old = $this->oldCategoryId($item);
        $item->category_id = $request->integer('category_id');
        $item->prompt      = trim($request->input('prompt'));
        $item->status      = $request->boolean('status');
        // Per request: upload/ai-image/{Category Name}/{original file name}
        $this->putFile($request, $item, 'image', 'image', $old);
        $item->save();
    }
}
