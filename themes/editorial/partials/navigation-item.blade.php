@php
    $menuOptions = $menuOptions ?? [];

    $itemTag = $menuOptions['item_tag'] ?? 'div';
    $linkTag = $menuOptions['link_tag'] ?? 'a';
    $childrenTag = $menuOptions['children_tag'] ?? 'div';

    $replaceTokens = static function (string $value, array $node): string {
        return strtr($value, [
            '{id}' => (string) ($node['id'] ?? ''),
            '{title}' => (string) ($node['title'] ?? ''),
            '{url}' => (string) ($node['url'] ?? ''),
            '{path}' => (string) ($node['path'] ?? ''),
        ]);
    };

    $renderAttributes = static function (array $attributes): string {
        $pairs = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $pairs[] = e((string) $name);
                continue;
            }

            $pairs[] = e((string) $name).'="'.e((string) $value).'"';
        }

        return implode(' ', $pairs);
    };

    $itemAttributes = $menuOptions['item_attributes'] ?? [];
    $itemClasses = trim(implode(' ', array_filter([
        $menuOptions['item_class'] ?? 'editorial-nav__group',
        ! empty($item['is_current']) ? ($menuOptions['item_class_current'] ?? 'is-current') : null,
        ! empty($item['is_ancestor']) ? ($menuOptions['item_class_ancestor'] ?? 'is-ancestor') : null,
        $itemAttributes['class'] ?? null,
    ])));

    if ($itemClasses !== '') {
        $itemAttributes['class'] = $itemClasses;
    }

    $linkAttributes = $menuOptions['link_attributes'] ?? [];
    $linkClasses = trim(implode(' ', array_filter([
        $menuOptions['link_class'] ?? 'editorial-nav__link',
        $linkAttributes['class'] ?? null,
    ])));

    if ($linkClasses !== '') {
        $linkAttributes['class'] = $linkClasses;
    }

    $linkAttributes['href'] = $item['url'] ?? '#';

    $childrenAttributes = $menuOptions['children_attributes'] ?? [];
    $childrenClasses = trim(implode(' ', array_filter([
        $menuOptions['children_class'] ?? 'editorial-nav__children',
        $childrenAttributes['class'] ?? null,
    ])));

    if ($childrenClasses !== '') {
        $childrenAttributes['class'] = $childrenClasses;
    }

    $beforeItem = $replaceTokens((string) ($menuOptions['before_item'] ?? ''), $item);
    $afterItem = $replaceTokens((string) ($menuOptions['after_item'] ?? ''), $item);
    $beforeLink = $replaceTokens((string) ($menuOptions['before_link'] ?? ''), $item);
    $afterLink = $replaceTokens((string) ($menuOptions['after_link'] ?? ''), $item);
    $beforeChildren = $replaceTokens((string) ($menuOptions['before_children'] ?? ''), $item);
    $afterChildren = $replaceTokens((string) ($menuOptions['after_children'] ?? ''), $item);
@endphp

{!! $beforeItem !!}
<{{ $itemTag }} {!! $renderAttributes($itemAttributes) !!}>
    {!! $beforeLink !!}
    <{{ $linkTag }} {!! $renderAttributes($linkAttributes) !!}>{{ $item['title'] }}</{{ $linkTag }}>
    {!! $afterLink !!}

    @if (! empty($item['children']))
        {!! $beforeChildren !!}
        <{{ $childrenTag }} {!! $renderAttributes($childrenAttributes) !!}>
            @foreach ($item['children'] as $child)
                @include('theme::partials.navigation-item', ['item' => $child, 'menuOptions' => $menuOptions])
            @endforeach
        </{{ $childrenTag }}>
        {!! $afterChildren !!}
    @endif
</{{ $itemTag }}>
{!! $afterItem !!}