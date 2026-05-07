<?php

namespace App\Http\Controllers\Admin;

use App\Core\Pages\Models\AdditionalFieldGroup;
use App\Core\Pages\Models\Page;
use App\Core\Pages\Services\AdditionalFieldsService;
use App\Core\Security\Services\SecurityAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pages\StoreAdditionalFieldGroupRequest;
use App\Http\Requests\Admin\Pages\UpdateAdditionalFieldGroupRequest;
use App\Http\Resources\Admin\AdditionalFieldGroupResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdditionalFieldGroupController extends Controller
{
    public function index(AdditionalFieldsService $fields): AnonymousResourceCollection
    {
        abort_unless(request()->user()?->hasPermission('pages.additional_fields.manage'), 403);

        return AdditionalFieldGroupResource::collection(
            $fields->listGroups()->load('fields'),
        );
    }

    public function applicable(Request $request, AdditionalFieldsService $fields): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('pages.access'), 403);

        $pageId = $request->integer('page_id');
        $template = trim((string) $request->query('template', ''));

        $page = $pageId > 0 ? Page::query()->find($pageId) : null;
        $groups = $fields->resolveApplicableGroupsForPage($page, $template !== '' ? $template : null)->load('fields');

        $values = $page !== null
            ? $fields->combinedValuesForPage($page, $template !== '' ? $template : null)
            : $groups->flatMap(fn (AdditionalFieldGroup $group) => $group->fields)
                ->mapWithKeys(fn ($field): array => [$field->key => null])
                ->all();

        return response()->json([
            'data' => [
                'groups' => AdditionalFieldGroupResource::collection($groups)->resolve(),
                'values' => $values,
            ],
        ]);
    }

    public function store(StoreAdditionalFieldGroupRequest $request, AdditionalFieldsService $fields, SecurityAuditLogger $audit): JsonResponse
    {
        $payload = $request->validated();

        $group = AdditionalFieldGroup::query()->create([
            'name' => $payload['name'],
            'key' => $payload['key'],
            'description' => $payload['description'] ?? null,
            'location_rules' => $payload['location_rules'] ?? ['mode' => 'all', 'rules' => [['field' => 'entity_type', 'operator' => '=', 'value' => 'page']]],
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ]);

        $group = $fields->replaceGroupFields($group, $payload['fields'] ?? []);

        $audit->log('pages.additional_fields.group_created', $request->user(), [
            'group_id' => $group->id,
            'group_key' => $group->key,
        ]);

        return AdditionalFieldGroupResource::make($group)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAdditionalFieldGroupRequest $request, AdditionalFieldGroup $group, AdditionalFieldsService $fields, SecurityAuditLogger $audit): AdditionalFieldGroupResource
    {
        $payload = $request->validated();

        $group->update([
            'name' => $payload['name'],
            'key' => $payload['key'],
            'description' => $payload['description'] ?? null,
            'location_rules' => $payload['location_rules'] ?? ['mode' => 'all', 'rules' => [['field' => 'entity_type', 'operator' => '=', 'value' => 'page']]],
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ]);

        $group = $fields->replaceGroupFields($group, $payload['fields'] ?? []);

        $audit->log('pages.additional_fields.group_updated', $request->user(), [
            'group_id' => $group->id,
            'group_key' => $group->key,
        ]);

        return AdditionalFieldGroupResource::make($group);
    }

    public function destroy(Request $request, AdditionalFieldGroup $group, SecurityAuditLogger $audit): Response
    {
        abort_unless($request->user()?->hasPermission('pages.additional_fields.manage'), 403);

        $audit->log('pages.additional_fields.group_deleted', $request->user(), [
            'group_id' => $group->id,
            'group_key' => $group->key,
        ]);

        $group->delete();

        return response()->noContent();
    }
}
