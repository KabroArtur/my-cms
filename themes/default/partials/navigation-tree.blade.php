@php
    $menuOptions = $menuOptions ?? [];
    $rootTag = $menuOptions['root_tag'] ?? 'ul';

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

    $rootAttributes = $menuOptions['root_attributes'] ?? [];
    $rootClasses = trim(implode(' ', array_filter([
        $menuOptions['root_class'] ?? 'site-nav__tree',
        $rootAttributes['class'] ?? null,
    ])));

    if ($rootClasses !== '') {
        $rootAttributes['class'] = $rootClasses;
    }

    $beforeRoot = (string) ($menuOptions['before_root'] ?? '');
    $afterRoot = (string) ($menuOptions['after_root'] ?? '');
@endphp

{!! $beforeRoot !!}
<{{ $rootTag }} {!! $renderAttributes($rootAttributes) !!}>
    @foreach ($items as $item)
        @include('theme::partials.navigation-item', ['item' => $item, 'menuOptions' => $menuOptions])
    @endforeach
</{{ $rootTag }}>
{!! $afterRoot !!}