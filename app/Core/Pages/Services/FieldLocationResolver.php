<?php

namespace App\Core\Pages\Services;

use App\Core\Pages\Models\AdditionalFieldGroup;
use Illuminate\Support\Arr;

class FieldLocationResolver
{
    public function matches(AdditionalFieldGroup $group, array $context): bool
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

            $field = $this->normalizeRuleField((string) ($rule['field'] ?? ''));

            if ($field === '') {
                return false;
            }

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

    public function supportedFields(): array
    {
        return [
            'entity_type',
            'template',
            'layout',
            'page_id',
            'page_slug',
            'page_path',
            'is_home',
        ];
    }

    public function supportedOperators(): array
    {
        return ['=', '!=', 'in', 'not_in'];
    }

    public function normalizeRuleField(string $field): string
    {
        return match (strtolower(trim($field))) {
            'layout', 'page_template' => 'template',
            default => strtolower(trim($field)),
        };
    }

    protected function matchRule(string $actual, string $operator, string $expected): bool
    {
        return match ($operator) {
            '=' => $actual === $expected,
            '!=' => $actual !== $expected,
            'in' => in_array($actual, $this->explodeList($expected), true),
            'not_in' => ! in_array($actual, $this->explodeList($expected), true),
            default => false,
        };
    }

    protected function explodeList(string $expected): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $expected)),
            static fn (string $item): bool => $item !== '',
        ));
    }
}