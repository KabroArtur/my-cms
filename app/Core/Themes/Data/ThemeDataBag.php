<?php

namespace App\Core\Themes\Data;

use App\Core\Themes\Services\ThemeRuntime;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class ThemeDataBag
{
    public function __construct(
        protected ThemeRuntime $cms,
        protected array $data,
    ) {
    }

    public function all(): array
    {
        return $this->data;
    }

    public function field(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public function customField(string $key, mixed $default = null): mixed
    {
        return $this->field($key, $default);
    }

    public function customFields(): array
    {
        return $this->all();
    }

    public function hasField(string $key): bool
    {
        return $this->cms->valueExists($this->field($key));
    }

    public function html(string $key, string $default = ''): HtmlString
    {
        return new HtmlString((string) $this->field($key, $default));
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->field($key, $default), FILTER_VALIDATE_BOOL);
    }

    public function number(string $key, int|float $default = 0): int|float
    {
        $value = $this->field($key, $default);

        return is_numeric($value) ? $value + 0 : $default;
    }

    public function url(string $key, string $default = '#'): string
    {
        $value = $this->field($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public function array(string $key): array
    {
        $value = $this->field($key, []);

        return is_array($value) ? $value : [];
    }

    public function hasImage(string $key): bool
    {
        return $this->cms->mediaFromValue($this->field($key)) !== null;
    }

    public function image(string $key, array $options = []): HtmlString
    {
        return $this->cms->imageFromValue($this->field($key), $options);
    }

    public function imageUrl(string $key, string $size = 'original'): ?string
    {
        return $this->cms->imageUrlFromValue($this->field($key), $size);
    }

    public function imageAlt(string $key, string $default = ''): string
    {
        return $this->cms->imageAltFromValue($this->field($key), $default);
    }
}