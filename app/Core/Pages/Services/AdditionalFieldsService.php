<?php

namespace App\Core\Pages\Services;

use App\Core\Pages\Models\AdditionalField;
use App\Core\Pages\Models\AdditionalFieldGroup;
use App\Core\Pages\Models\AdditionalFieldValue;
use App\Core\Pages\Models\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AdditionalFieldsService
{
    public function listGroups(): Collection
    {
        return AdditionalFieldGroup::query()
            ->with(['fields' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function resolveApplicableGroupsForPage(?Page $page = null, ?string $template = null): Collection
    {
        $context = [
            'entity_type' => 'page',
            'template' => (string) ($template ?? $page?->template ?? 'default'),
            'page_id' => $page !== null ? (string) $page->id : '',
            'page_slug' => (string) ($page?->slug ?? ''),
            'page_path' => (string) ($page?->path ?? ''),
            'is_home' => $page !== null && (bool) $page->is_home ? '1' : '0',
        ];

        return $this->listGroups()
            ->filter(fn (AdditionalFieldGroup $group): bool => $group->is_active)
            ->filter(fn (AdditionalFieldGroup $group): bool => $this->matchesLocationRules($group, $context))
            ->values();
    }

    public function valuesForPage(Page $page): array
    {
        return $this->valuesForEntity('page', (int) $page->id);
    }

    public function valuesForEntity(string $entityType, int $entityId): array
    {
        $values = AdditionalFieldValue::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->get(['field_key', 'value'])
            ->mapWithKeys(function (AdditionalFieldValue $item): array {
                return [$item->field_key => $this->decodeValue($item->value)];
            })
            ->all();

        return $values;
    }

    public function hasPageValue(Page $page, string $key): bool
    {
        return array_key_exists($key, $this->combinedValuesForPage($page));
    }

    public function pageValue(Page $page, string $key, mixed $default = null): mixed
    {
        $values = $this->combinedValuesForPage($page);

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public function combinedValuesForPage(Page $page): array
    {
        $groups = $this->resolveApplicableGroupsForPage($page);
        $values = $this->valuesForPage($page);
        $result = [];

        foreach ($groups as $group) {
            foreach ($group->fields as $field) {
                if (! array_key_exists($field->key, $values)) {
                    $result[$field->key] = $this->normalizeFieldValue($field, $this->defaultFieldValue($field));
                    continue;
                }

                $result[$field->key] = $this->normalizeFieldValue($field, $values[$field->key]);
            }
        }

        return $result;
    }

    public function syncPageValues(Page $page, array $payload): void
    {
        $groups = $this->resolveApplicableGroupsForPage($page);
        $fields = $groups->flatMap(fn (AdditionalFieldGroup $group) => $group->fields)->keyBy('key');

        foreach ($fields as $key => $field) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $normalized = $this->normalizeFieldValue($field, $payload[$key]);

            AdditionalFieldValue::query()->updateOrCreate(
                [
                    'entity_type' => 'page',
                    'entity_id' => $page->id,
                    'field_key' => $key,
                ],
                [
                    'value' => $this->encodeValue($normalized),
                ],
            );
        }

        $this->forgetEntityCache('page', (int) $page->id);
    }

    public function replaceGroupFields(AdditionalFieldGroup $group, array $fieldPayload): AdditionalFieldGroup
    {
        $group->fields()->delete();

        foreach (array_values($fieldPayload) as $index => $field) {
            AdditionalField::query()->create([
                'group_id' => $group->id,
                'label' => trim((string) ($field['label'] ?? '')),
                'key' => trim((string) ($field['key'] ?? '')),
                'type' => trim((string) ($field['type'] ?? 'text')),
                'settings' => is_array($field['settings'] ?? null) ? $field['settings'] : [],
                'default_value' => $this->encodeDefaultValue($field['default_value'] ?? null),
                'is_required' => (bool) ($field['is_required'] ?? false),
                'sort_order' => isset($field['sort_order']) ? (int) $field['sort_order'] : $index,
            ]);
        }

        return $group->fresh(['fields']);
    }

    public function forgetEntityCache(string $entityType, int $entityId): void
    {
        // Метод сохранен для совместимости с существующими вызовами.
    }

    protected function matchesLocationRules(AdditionalFieldGroup $group, array $context): bool
    {
        $rules = Arr::get($group->location_rules, 'rules', []);
        $mode = strtolower((string) Arr::get($group->location_rules, 'mode', 'all'));

        if (! is_array($rules) || $rules === []) {
            return true;
        }

        $matches = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                return false;
            }

            $field = (string) ($rule['field'] ?? '');
            $operator = (string) ($rule['operator'] ?? '=');
            $value = (string) ($rule['value'] ?? '');
            $actual = (string) ($context[$field] ?? '');

            $matches[] = $this->matchRule($actual, $operator, $value);
        }

        if ($mode === 'any') {
            return in_array(true, $matches, true);
        }

        return ! in_array(false, $matches, true);
    }

    protected function matchRule(string $actual, string $operator, string $expected): bool
    {
        return match ($operator) {
            '=' => $actual === $expected,
            '!=' => $actual !== $expected,
            'in' => in_array($actual, array_values(array_filter(array_map('trim', explode(',', $expected)), fn (string $item): bool => $item !== '')), true),
            'not_in' => ! in_array($actual, array_values(array_filter(array_map('trim', explode(',', $expected)), fn (string $item): bool => $item !== '')), true),
            default => false,
        };
    }

    protected function normalizeFieldValue(AdditionalField $field, mixed $value): mixed
    {
        $type = strtolower((string) $field->type);

        return match ($type) {
            'number' => is_numeric($value) ? $value + 0 : null,
            'toggle' => (bool) $value,
            'select' => $this->normalizeSelectValue($field, $value),
            'image' => is_numeric($value) ? (int) $value : null,
            'group' => $this->normalizeGroupValue($field, $value),
            'repeater' => $this->normalizeRepeaterValue($field, $value),
            'editor', 'textarea', 'url', 'text' => $this->normalizeStringValue($value),
            default => $value,
        };
    }

    protected function normalizeSelectValue(AdditionalField $field, mixed $value): ?string
    {
        $normalized = $this->normalizeStringValue($value);

        if ($normalized === null) {
            return null;
        }

        $allowed = $this->selectOptions($field)
            ->map(fn (array $option): string => (string) ($option['value'] ?? ''))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        if ($allowed === []) {
            return $normalized;
        }

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    protected function normalizeGroupValue(AdditionalField $field, mixed $value): array
    {
        $payload = is_array($value) ? $value : [];
        $normalized = [];

        foreach ($this->nestedFieldDefinitions($field) as $nested) {
            $key = (string) ($nested['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $normalized[$key] = $this->normalizeNestedFieldValue($nested, $payload[$key] ?? null);
        }

        return $normalized;
    }

    protected function normalizeRepeaterValue(AdditionalField $field, mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            $item = is_array($item) ? $item : [];
            $entry = [];

            foreach ($this->nestedFieldDefinitions($field) as $nested) {
                $key = (string) ($nested['key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $entry[$key] = $this->normalizeNestedFieldValue($nested, $item[$key] ?? null);
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    protected function nestedFieldDefinitions(AdditionalField $field): array
    {
        $definitions = Arr::get($field->settings ?? [], 'fields', []);

        return is_array($definitions) ? array_values(array_filter($definitions, 'is_array')) : [];
    }

    protected function normalizeNestedFieldValue(array $fieldDefinition, mixed $value): mixed
    {
        $type = strtolower((string) ($fieldDefinition['type'] ?? 'text'));

        return match ($type) {
            'number' => is_numeric($value) ? $value + 0 : null,
            'toggle' => (bool) $value,
            'image' => is_numeric($value) ? (int) $value : null,
            default => $this->normalizeStringValue($value),
        };
    }

    protected function selectOptions(AdditionalField $field): Collection
    {
        $options = Arr::get($field->settings ?? [], 'options', []);

        if (! is_array($options)) {
            return collect();
        }

        return collect($options)
            ->map(function (mixed $option): array {
                if (is_string($option)) {
                    return ['label' => $option, 'value' => $option];
                }

                if (is_array($option)) {
                    $value = (string) ($option['value'] ?? $option['label'] ?? '');

                    return [
                        'label' => (string) ($option['label'] ?? $value),
                        'value' => $value,
                    ];
                }

                return ['label' => '', 'value' => ''];
            })
            ->filter(fn (array $option): bool => $option['value'] !== '');
    }

    protected function normalizeStringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    protected function defaultFieldValue(AdditionalField $field): mixed
    {
        if ($field->default_value === null || $field->default_value === '') {
            return null;
        }

        return $this->decodeValue($field->default_value);
    }

    protected function encodeDefaultValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->encodeValue($value);
    }

    protected function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function decodeValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }
}
