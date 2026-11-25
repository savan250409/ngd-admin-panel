<?php

namespace App\Http\Controllers\Admin;

use App\Models\BaseItem;
use Illuminate\Http\Request;

class TemplateController extends BaseItemController
{
    protected string $module = 'template';

    protected array $searchColumns = ['count'];

    protected function rules(Request $request, ?BaseItem $item): array
    {
        return [
            'category_id' => 'required|exists:' . $this->categoryTable() . ',id',
            'frame'       => $this->fileRule($item, 'image|mimes:webp|max:10240'),
            'thumbnail'   => $this->fileRule($item, 'image|mimes:webp|max:5120'),
            'count'       => 'required|integer|min:1|max:100',
            'status'      => 'nullable|boolean',
        ];
    }

    protected function imageFields(): array
    {
        return ['frame', 'thumbnail'];
    }

    protected function messages(): array
    {
        return parent::messages() + ['count.required' => 'Enter how many photos this template holds.'];
    }

    protected function persist(Request $request, BaseItem $item): void
    {
        $old = $this->oldCategoryId($item);
        $item->category_id = $request->integer('category_id');
        $item->count       = $request->integer('count');
        $item->status      = $request->boolean('status');
        $this->putFile($request, $item, 'frame', 'frame', $old);
        $this->putFile($request, $item, 'thumbnail', 'thumbnail', $old);
        $item->save();
    }
}
