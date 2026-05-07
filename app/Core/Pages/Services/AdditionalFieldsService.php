<?php

namespace App\Core\Pages\Services;

use App\Core\Pages\Models\AdditionalField;
use App\Core\Pages\Models\AdditionalFieldGroup;
use App\Core\Pages\Models\AdditionalFieldValue;
use App\Core\Pages\Models\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdditionalFieldsService
{
    public function __construct(
        protected FieldLocationResolver $locationResolver,
    ) {
    }

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
            'layout' => (string) ($template ?? $page?->template ?? 'default'),
            'page_id' => $page !== null ? (string) $page->id : '',
            'page_slug' => (string) ($page?->slug ?? ''),
            'page_path' => (string) ($page?->path ?? ''),
            'is_home' => $page !== null && (bool) $page->is_home ? '1' : '0',
        ];

        return $this->listGroups()
            ->filter(fn (AdditionalFieldGroup $group): bool => $group->is_active)
            ->filter(fn (AdditionalFieldGroup $group): bool => $this->locationResolver->matches($group, $context))
            ->values();
    }

    public function supportedFieldTypes(): array
    {
        return [
            'text',
            'textarea',
            'editor',
            'image',
            'file',
            'number',
            'checkbox',
            'toggle',
            'switch',
            'select',
            'radio',
            'color',
            'date',
            'url',
            'email',
            'repeater',
            'group',
            'gallery',
        ];
    }

    public function supportsFieldType(string $type): bool
    {
        return in_array($this->normalizeFieldType($type), $this->supportedFieldTypes(), true);
    }

    public function validateFieldDefinitions(array $fieldPayload, ?AdditionalFieldGroup $group = null): array
    {
        $errors = [];
        $seenKeys = [];
        $reservedKeys = AdditionalField::query()
            ->when($group !== null, fn ($query) => $query->where('group_id', '!=', $group->id))
            ->pluck('key')
            ->map(fn (mixed $key): string => strtolower(trim((string) $key)))
            ->filter()
            ->values()
            ->all();

        foreach (array_values($fieldPayload) as $index => $field) {
            if (! is_array($field)) {
                $errors["fields.{$index}"][] = 'Поле должно быть объектом.';
                continue;
            }

            $this->validateFieldDefinition($field, "fields.{$index}", $errors, $seenKeys, $reservedKeys);
        }

        return $errors;
    }

    public function validatePageValues(?Page $page, ?string $template, array $payload): array
    {
        $errors = [];
        $fields = $this->resolveApplicableGroupsForPage($page, $template)->flatMap(fn (AdditionalFieldGroup $group) => $group->fields);

        foreach ($fields as $field) {
            $key = (string) $field->key;
            $path = "additional_fields.{$key}";
            $hasValue = array_key_exists($key, $payload);
            $value = $payload[$key] ?? null;

            if ($field->is_required && (! $hasValue || ! $this->valueExists($field, $value))) {
                $errors[$path][] = 'Поле обязательно для заполнения.';
            }

            if (! $hasValue) {
                continue;
            }

            $this->validateValueAgainstDefinition([
                'type' => $field->type,
                'settings' => $field->settings ?? [],
                'is_required' => (bool) $field->is_required,
            ], $value, $path, $errors);
        }

        return $errors;
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

    public function hasPageValue(Page $page, string $key, ?string $template = null): bool
    {
        return array_key_exists($key, $this->combinedValuesForPage($page, $template));
    }

    public function pageValue(Page $page, string $key, mixed $default = null, ?string $template = null): mixed
    {
        $values = $this->combinedValuesForPage($page, $template);

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public function combinedValuesForPage(Page $page, ?string $template = null): array
    {
        $groups = $this->resolveApplicableGroupsForPage($page, $template);
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

        foreach ($this->normalizeFieldPayload($fieldPayload) as $index => $field) {
            AdditionalField::query()->create([
                'group_id' => $group->id,
                'label' => $field['label'],
                'key' => $field['key'],
                'type' => $field['type'],
                'settings' => $field['settings'],
                'default_value' => $this->encodeDefaultValue($field['default_value']),
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

    public function normalizeFieldPayload(array $fieldPayload): array
    {
        return collect(array_values($fieldPayload))
            ->filter(static fn (mixed $field): bool => is_array($field))
            ->map(function (array $field, int $index): array {
                return $this->normalizeFieldDefinition($field, $index);
            })
            ->values()
            ->all();
    }

    protected function normalizeFieldValue(AdditionalField $field, mixed $value): mixed
    {
        return $this->normalizeValueByDefinition([
            'type' => $field->type,
            'settings' => $field->settings ?? [],
            'default_value' => $this->defaultFieldValue($field),
        ], $value);
    }

    protected function normalizeFieldDefinition(array $field, int $index = 0): array
    {
        $type = $this->normalizeFieldType((string) ($field['type'] ?? 'text'));
        $settings = $this->normalizeFieldSettings($type, is_array($field['settings'] ?? null) ? $field['settings'] : []);

        return [
            'label' => trim((string) ($field['label'] ?? '')),
            'key' => strtolower(trim((string) ($field['key'] ?? ''))),
            'type' => $this->supportsFieldType($type) ? $type : 'text',
            'settings' => $settings,
            'default_value' => $this->normalizeValueByDefinition([
                'type' => $type,
                'settings' => $settings,
            ], $field['default_value'] ?? null, true),
            'is_required' => (bool) ($field['is_required'] ?? false),
            'sort_order' => isset($field['sort_order']) ? (int) $field['sort_order'] : $index,
        ];
    }

    protected function normalizeValueByDefinition(array $definition, mixed $value, bool $allowFallbackDefault = false): mixed
    {
        $type = $this->normalizeFieldType((string) ($definition['type'] ?? 'text'));

        $normalized = match ($type) {
            'number' => $this->normalizeNumberValue($value),
            'checkbox', 'toggle', 'switch' => $this->normalizeBooleanValue($value),
            'select', 'radio' => $this->normalizeChoiceValue($definition, $value),
            'image', 'file' => $this->normalizeMediaValue($value),
            'gallery' => $this->normalizeGalleryValue($value),
            'group' => $this->normalizeGroupValueFromDefinition($definition, $value),
            'repeater' => $this->normalizeRepeaterValueFromDefinition($definition, $value),
            'color' => $this->normalizeColorValue($value),
            'date' => $this->normalizeDateValue($value),
            'editor', 'textarea', 'url', 'email', 'text' => $this->normalizeStringValue($value),
            default => $this->normalizeStringValue($value),
        };

        if (! $allowFallbackDefault) {
            return $normalized;
        }

        if ($normalized !== null) {
            return $normalized;
        }

        return $this->emptyValueForFieldType($type);
    }

    protected function normalizeChoiceValue(array|AdditionalField $definition, mixed $value): ?string
    {
        $normalized = $this->normalizeStringValue($value);

        if ($normalized === null) {
            return null;
        }

        $options = $this->definitionOptions($definition)
            ->map(fn (array $option): string => (string) ($option['value'] ?? ''))
            ->filter(fn (string $optionValue): bool => $optionValue !== '')
            ->values()
            ->all();

        if ($options === []) {
            return $normalized;
        }

        return in_array($normalized, $options, true) ? $normalized : null;
    }

    protected function normalizeGroupValue(AdditionalField $field, mixed $value): array
    {
        return $this->normalizeGroupValueFromDefinition([
            'type' => $field->type,
            'settings' => $field->settings ?? [],
        ], $value);
    }

    protected function normalizeGroupValueFromDefinition(array $definition, mixed $value): array
    {
        $payload = is_array($value) ? $value : [];
        $normalized = [];

        foreach ($this->definitionNestedFields($definition) as $nested) {
            $key = (string) ($nested['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $normalized[$key] = $this->normalizeValueByDefinition($nested, $payload[$key] ?? null);
        }

        return $normalized;
    }

    protected function normalizeRepeaterValue(AdditionalField $field, mixed $value): array
    {
        return $this->normalizeRepeaterValueFromDefinition([
            'type' => $field->type,
            'settings' => $field->settings ?? [],
        ], $value);
    }

    protected function normalizeRepeaterValueFromDefinition(array $definition, mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            $item = is_array($item) ? $item : [];
            $entry = [];

            foreach ($this->definitionNestedFields($definition) as $nested) {
                $key = (string) ($nested['key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $entry[$key] = $this->normalizeValueByDefinition($nested, $item[$key] ?? null);
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    protected function nestedFieldDefinitions(AdditionalField $field): array
    {
        return $this->definitionNestedFields([
            'settings' => $field->settings ?? [],
        ]);
    }

    protected function definitionNestedFields(array|AdditionalField $definition): array
    {
        $settings = $definition instanceof AdditionalField
            ? ($definition->settings ?? [])
            : (is_array($definition['settings'] ?? null) ? $definition['settings'] : []);

        $definitions = Arr::get($settings, 'fields', []);

        if (! is_array($definitions)) {
            return [];
        }

        return collect($definitions)
            ->filter(static fn (mixed $field): bool => is_array($field))
            ->map(fn (array $field, int $index): array => $this->normalizeFieldDefinition($field, $index))
            ->values()
            ->all();
    }

    protected function definitionOptions(array|AdditionalField $definition): Collection
    {
        if ($definition instanceof AdditionalField) {
            return $this->selectOptions($definition);
        }

        $settings = is_array($definition['settings'] ?? null) ? $definition['settings'] : [];
        $options = Arr::get($settings, 'options', []);

        if (! is_array($options)) {
            return collect();
        }

        return collect($options)
            ->map(function (mixed $option): array {
                if (is_string($option)) {
                    return ['label' => $option, 'value' => $option];
                }

                if (! is_array($option)) {
                    return ['label' => '', 'value' => ''];
                }

                $value = trim((string) ($option['value'] ?? $option['label'] ?? ''));

                return [
                    'label' => trim((string) ($option['label'] ?? $value)),
                    'value' => $value,
                ];
            })
            ->filter(fn (array $option): bool => $option['value'] !== '')
            ->values();
    }

    protected function normalizeMediaValue(mixed $value): array|int|string|null
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_array($value)) {
            $normalized = [];

            if (isset($value['id']) && is_numeric($value['id'])) {
                $normalized['id'] = (int) $value['id'];
            }

            if (isset($value['value']) && is_numeric($value['value']) && ! isset($normalized['id'])) {
                $normalized['id'] = (int) $value['value'];
            }

            foreach (['url', 'preview_url', 'label', 'title', 'original_name', 'alt_text', 'caption', 'mime_type', 'extension'] as $key) {
                $string = $this->normalizeStringValue($value[$key] ?? null);

                if ($string !== null) {
                    $normalized[$key] = $string;
                }
            }

            foreach (['width', 'height', 'size', 'folder_id'] as $key) {
                if (isset($value[$key]) && is_numeric($value[$key])) {
                    $normalized[$key] = (int) $value[$key];
                }
            }

            if (isset($value['variants']) && is_array($value['variants'])) {
                $normalized['variants'] = $value['variants'];
            }

            return $normalized === [] ? null : $normalized;
        }

        return $this->normalizeStringValue($value);
    }

    protected function normalizeGalleryValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $item): array|int|string|null => $this->normalizeMediaValue($item))
            ->filter(fn (mixed $item): bool => $item !== null && $item !== '')
            ->values()
            ->all();
    }

    protected function normalizeBooleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    protected function normalizeNumberValue(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    protected function normalizeColorValue(mixed $value): ?string
    {
        $normalized = $this->normalizeStringValue($value);

        if ($normalized === null) {
            return null;
        }

        $candidate = str_starts_with($normalized, '#') ? $normalized : '#'.$normalized;

        return preg_match('/^#[0-9a-fA-F]{6}$/', $candidate) === 1 ? strtolower($candidate) : null;
    }

    protected function normalizeDateValue(mixed $value): ?string
    {
        $normalized = $this->normalizeStringValue($value);

        if ($normalized === null) {
            return null;
        }

        try {
            return Carbon::parse($normalized)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function selectOptions(AdditionalField $field): Collection
    {
        return $this->definitionOptions($field);
    }

    protected function normalizeStringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    protected function validateFieldDefinition(array $field, string $path, array &$errors, array &$seenKeys, array $reservedKeys): void
    {
        $label = trim((string) ($field['label'] ?? ''));
        $key = strtolower(trim((string) ($field['key'] ?? '')));
        $type = $this->normalizeFieldType((string) ($field['type'] ?? 'text'));
        $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];

        if ($label === '') {
            $errors["{$path}.label"][] = 'Укажите label поля.';
        }

        if ($key === '') {
            $errors["{$path}.key"][] = 'Укажите key поля.';
        } elseif (preg_match('/^[a-z0-9_-]+$/', $key) !== 1) {
            $errors["{$path}.key"][] = 'Key может содержать только латиницу, цифры, дефис и underscore.';
        } elseif (in_array($key, $seenKeys, true)) {
            $errors["{$path}.key"][] = 'Key должен быть уникальным внутри набора.';
        } elseif (in_array($key, $reservedKeys, true)) {
            $errors["{$path}.key"][] = 'Такой key уже используется в другом наборе.';
        }

        $seenKeys[] = $key;

        if (! $this->supportsFieldType($type)) {
            $errors["{$path}.type"][] = 'Указан неподдерживаемый тип поля.';
            return;
        }

        if (in_array($type, ['select', 'radio'], true)) {
            $options = Arr::get($settings, 'options', []);

            if (! is_array($options) || $options === []) {
                $errors["{$path}.settings.options"][] = 'Для select/radio нужно добавить хотя бы один вариант.';
            }

            $optionValues = [];

            foreach ((array) $options as $optionIndex => $option) {
                if (! is_array($option)) {
                    $errors["{$path}.settings.options.{$optionIndex}"][] = 'Вариант должен быть объектом.';
                    continue;
                }

                $optionLabel = trim((string) ($option['label'] ?? ''));
                $optionValue = trim((string) ($option['value'] ?? ''));

                if ($optionLabel === '') {
                    $errors["{$path}.settings.options.{$optionIndex}.label"][] = 'Укажите label варианта.';
                }

                if ($optionValue === '') {
                    $errors["{$path}.settings.options.{$optionIndex}.value"][] = 'Укажите value варианта.';
                } elseif (in_array($optionValue, $optionValues, true)) {
                    $errors["{$path}.settings.options.{$optionIndex}.value"][] = 'Value варианта должно быть уникальным.';
                }

                $optionValues[] = $optionValue;
            }
        }

        if (in_array($type, ['group', 'repeater'], true)) {
            $nestedFields = Arr::get($settings, 'fields', []);

            if (! is_array($nestedFields)) {
                $errors["{$path}.settings.fields"][] = 'Вложенные поля должны быть массивом.';
                return;
            }

            $nestedSeenKeys = [];

            foreach (array_values($nestedFields) as $nestedIndex => $nestedField) {
                if (! is_array($nestedField)) {
                    $errors["{$path}.settings.fields.{$nestedIndex}"][] = 'Вложенное поле должно быть объектом.';
                    continue;
                }

                $this->validateFieldDefinition(
                    $nestedField,
                    "{$path}.settings.fields.{$nestedIndex}",
                    $errors,
                    $nestedSeenKeys,
                    [],
                );
            }
        }

        $defaultValue = $field['default_value'] ?? null;

        if ($defaultValue !== null && $defaultValue !== '' && $this->normalizeValueByDefinition([
            'type' => $type,
            'settings' => $this->normalizeFieldSettings($type, $settings),
        ], $defaultValue) === null && ! in_array($type, ['text', 'textarea', 'editor', 'image', 'file', 'url', 'email'], true)) {
            $errors["{$path}.default_value"][] = 'Значение по умолчанию не соответствует типу поля.';
        }
    }

    protected function validateValueAgainstDefinition(array $definition, mixed $value, string $path, array &$errors): void
    {
        $type = $this->normalizeFieldType((string) ($definition['type'] ?? 'text'));

        if ($value === null || $value === '') {
            return;
        }

        if ($type === 'number' && ! is_numeric($value)) {
            $errors[$path][] = 'Нужно указать число.';
            return;
        }

        if ($type === 'url' && ! $this->isValidLinkValue($value)) {
            $errors[$path][] = 'Нужно указать корректный URL.';
            return;
        }

        if ($type === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $errors[$path][] = 'Нужно указать корректный email.';
            return;
        }

        if ($type === 'date' && $this->normalizeDateValue($value) === null) {
            $errors[$path][] = 'Нужно указать корректную дату.';
            return;
        }

        if ($type === 'color' && $this->normalizeColorValue($value) === null) {
            $errors[$path][] = 'Нужно указать цвет в hex-формате.';
            return;
        }

        if (in_array($type, ['select', 'radio'], true) && $this->normalizeChoiceValue($definition, $value) === null) {
            $errors[$path][] = 'Выберите значение из доступных вариантов.';
            return;
        }

        if ($type === 'group') {
            if (! is_array($value)) {
                $errors[$path][] = 'Группа должна сохраняться как объект.';
                return;
            }

            foreach ($this->definitionNestedFields($definition) as $nested) {
                $nestedKey = (string) ($nested['key'] ?? '');
                $nestedPath = "{$path}.{$nestedKey}";
                $nestedValue = $value[$nestedKey] ?? null;
                $nestedRequired = (bool) ($nested['is_required'] ?? false);

                if ($nestedRequired && ! $this->valueExists($nested, $nestedValue)) {
                    $errors[$nestedPath][] = 'Поле обязательно для заполнения.';
                }

                $this->validateValueAgainstDefinition($nested, $nestedValue, $nestedPath, $errors);
            }

            return;
        }

        if ($type === 'repeater') {
            if (! is_array($value)) {
                $errors[$path][] = 'Repeater должен сохраняться как массив.';
                return;
            }

            foreach ($value as $rowIndex => $row) {
                if (! is_array($row)) {
                    $errors["{$path}.{$rowIndex}"][] = 'Элемент repeatable-поля должен быть объектом.';
                    continue;
                }

                foreach ($this->definitionNestedFields($definition) as $nested) {
                    $nestedKey = (string) ($nested['key'] ?? '');
                    $nestedPath = "{$path}.{$rowIndex}.{$nestedKey}";
                    $nestedValue = $row[$nestedKey] ?? null;
                    $nestedRequired = (bool) ($nested['is_required'] ?? false);

                    if ($nestedRequired && ! $this->valueExists($nested, $nestedValue)) {
                        $errors[$nestedPath][] = 'Поле обязательно для заполнения.';
                    }

                    $this->validateValueAgainstDefinition($nested, $nestedValue, $nestedPath, $errors);
                }
            }

            return;
        }

        if ($type === 'gallery' && ! is_array($value)) {
            $errors[$path][] = 'Галерея должна сохраняться как массив.';
        }
    }

    protected function valueExists(array|AdditionalField $definition, mixed $value): bool
    {
        $type = $definition instanceof AdditionalField
            ? $this->normalizeFieldType((string) $definition->type)
            : $this->normalizeFieldType((string) ($definition['type'] ?? 'text'));

        if (in_array($type, ['checkbox', 'toggle', 'switch'], true)) {
            return $value !== null;
        }

        if (in_array($type, ['group'], true)) {
            return is_array($value) && collect($value)->contains(fn (mixed $item): bool => $item !== null && $item !== '' && $item !== []);
        }

        if (in_array($type, ['repeater', 'gallery'], true)) {
            return is_array($value) && $value !== [];
        }

        return $value !== null && $value !== '';
    }

    protected function isValidLinkValue(mixed $value): bool
    {
        $normalized = $this->normalizeStringValue($value);

        if ($normalized === null) {
            return false;
        }

        if (Str::startsWith($normalized, ['/', '#', 'mailto:', 'tel:'])) {
            return true;
        }

        return filter_var($normalized, FILTER_VALIDATE_URL) !== false;
    }

    protected function normalizeFieldSettings(string $type, array $settings): array
    {
        $normalized = [];

        foreach (['placeholder', 'help_text'] as $key) {
            $value = $this->normalizeStringValue($settings[$key] ?? null);

            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        if (isset($settings['rows']) && is_numeric($settings['rows'])) {
            $normalized['rows'] = max(2, (int) $settings['rows']);
        }

        if (in_array($type, ['select', 'radio'], true)) {
            $normalized['options'] = $this->definitionOptions(['settings' => $settings])->all();
        }

        if (in_array($type, ['group', 'repeater'], true)) {
            $normalized['fields'] = $this->definitionNestedFields(['settings' => $settings]);
        }

        return array_merge($settings, $normalized);
    }

    protected function normalizeFieldType(string $type): string
    {
        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'wysiwyg' => 'editor',
            'switch' => 'switch',
            default => $normalized,
        };
    }

    protected function emptyValueForFieldType(string $type): mixed
    {
        return match ($type) {
            'checkbox', 'toggle', 'switch' => false,
            'group' => [],
            'repeater', 'gallery' => [],
            default => null,
        };
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
