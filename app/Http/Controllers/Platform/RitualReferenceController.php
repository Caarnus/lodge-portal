<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Ritual\RitualReferenceService;
use App\Http\Controllers\Controller;
use App\Models\MasonicDegree;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Models\RitualProgramLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RitualReferenceController extends Controller
{
    public function index(RitualReferenceService $reference)
    {
        return Inertia::render('platform/RitualReference', [
            'categories' => RitualCategory::query()->with('parts')->orderBy('sort_order')->orderBy('name')->get()->map(fn(RitualCategory $category) => $this->category($category, $reference)),
            'levels' => RitualProgramLevel::query()->orderBy('point_threshold')->orderBy('sort_order')->get()->map(fn(RitualProgramLevel $level) => $this->level($level, $reference)),
            'degrees' => MasonicDegree::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    private function category(RitualCategory $category, RitualReferenceService $reference): array
    {
        return ['id' => $category->id, 'key' => $category->key, 'name' => $category->name, 'description' => $category->description, 'masonic_degree_id' => $category->masonic_degree_id, 'sort_order' => $category->sort_order, 'is_active' => $category->is_active, 'affected_person_count' => $reference->impactCount($category), 'parts' => $category->parts->sortBy(['sort_order', 'name'])->map(fn(RitualPart $part) => $this->part($part, $reference))->values()];
    }

    private function part(RitualPart $part, RitualReferenceService $reference): array
    {
        return ['id' => $part->id, 'key' => $part->key, 'name' => $part->name, 'description' => $part->description, 'ritual_category_id' => $part->ritual_category_id, 'sort_order' => $part->sort_order, 'counts_toward_program' => $part->counts_toward_program, 'point_value' => $part->point_value, 'is_active' => $part->is_active, 'affected_person_count' => $reference->impactCount($part)];
    }

    private function level(RitualProgramLevel $level, RitualReferenceService $reference): array
    {
        return ['id' => $level->id, 'key' => $level->key, 'name' => $level->name, 'point_threshold' => $level->point_threshold, 'sort_order' => $level->sort_order, 'is_active' => $level->is_active, 'affected_person_count' => $reference->impactCount($level)];
    }

    public function storeCategory(Request $request, RitualReferenceService $reference)
    {
        $reference->createCategory($this->categoryData($request, true));
        return back()->with('notice', 'Ritual category created.');
    }

    private function categoryData(Request $request, bool $creating = false, ?string $currentKey = null): array
    {
        return $request->validate([
            'key' => $creating ? ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('ritual_categories', 'key')] : ['required', Rule::in([$currentKey])],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'masonic_degree_id' => ['nullable', 'integer', 'exists:masonic_degrees,id'], 'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => [$creating ? 'sometimes' : 'required', 'boolean'], 'confirm_impact' => ['nullable', 'boolean'],
        ]);
    }

    public function updateCategory(Request $request, RitualCategory $ritualCategory, RitualReferenceService $reference)
    {
        $reference->updateCategory($ritualCategory, $this->categoryData($request, false, $ritualCategory->key));
        return back()->with('notice', 'Ritual category saved.');
    }

    public function storePart(Request $request, RitualReferenceService $reference)
    {
        $reference->createPart($this->partData($request, true));
        return back()->with('notice', 'Ritual part created.');
    }

    private function partData(Request $request, bool $creating = false, ?string $currentKey = null): array
    {
        return $request->validate([
            'key' => $creating ? ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('ritual_parts', 'key')] : ['required', Rule::in([$currentKey])],
            'ritual_category_id' => ['required', 'integer', 'exists:ritual_categories,id'], 'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'], 'sort_order' => ['nullable', 'integer', 'min:0'],
            'counts_toward_program' => ['required', 'boolean'], 'point_value' => ['nullable', 'integer', 'min:1', 'required_if:counts_toward_program,true'],
            'is_active' => [$creating ? 'sometimes' : 'required', 'boolean'], 'confirm_impact' => ['nullable', 'boolean'],
        ]);
    }

    public function updatePart(Request $request, RitualPart $ritualPart, RitualReferenceService $reference)
    {
        $reference->updatePart($ritualPart, $this->partData($request, false, $ritualPart->key));
        return back()->with('notice', 'Ritual part saved.');
    }

    public function storeLevel(Request $request, RitualReferenceService $reference)
    {
        $reference->createLevel($this->levelData($request, true));
        return back()->with('notice', 'Ritual level created.');
    }

    private function levelData(Request $request, bool $creating = false, ?string $currentKey = null): array
    {
        return $request->validate([
            'key' => $creating ? ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('ritual_program_levels', 'key')] : ['required', Rule::in([$currentKey])],
            'name' => ['required', 'string', 'max:255'], 'point_threshold' => ['required', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'], 'is_active' => [$creating ? 'sometimes' : 'required', 'boolean'],
            'confirm_impact' => ['nullable', 'boolean'],
        ]);
    }

    public function updateLevel(Request $request, RitualProgramLevel $ritualProgramLevel, RitualReferenceService $reference)
    {
        $reference->updateLevel($ritualProgramLevel, $this->levelData($request, false, $ritualProgramLevel->key));
        return back()->with('notice', 'Ritual level saved.');
    }
}
