<?php

namespace App\Services;

final class IranLocations
{
    private static array $datasets = [];

    public function provinces(): array
    {
        return $this->dataset('provinces');
    }

    public function cities(): array
    {
        return $this->dataset('cities-filtered');
    }

    public function version(): string
    {
        return $this->dataset('manifest')['datasetVersion'];
    }

    public function pair(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $provinceId = $this->id($value['province_id'] ?? null);
        $cityId = $this->id($value['city_id'] ?? null);
        $province = $provinceId === null ? null : ($this->provinces()[$provinceId] ?? null);
        $city = $cityId === null ? null : ($this->cities()[$cityId] ?? null);

        if ($province === null || $city === null || (string) $city['province_id'] !== $provinceId) {
            return null;
        }

        return [
            'province_id' => $provinceId,
            'province_name' => $province['name'],
            'city_id' => $cityId,
            'city_name' => $city['name'],
            'dataset_version' => $this->version(),
        ];
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value)
            && in_array($value['province_id'] ?? null, [null, ''], true)
            && in_array($value['city_id'] ?? null, [null, ''], true));
    }

    public static function display(mixed $value): string
    {
        return is_array($value) && is_string($value['province_name'] ?? null) && is_string($value['city_name'] ?? null)
            ? $value['province_name'].'، '.$value['city_name']
            : '—';
    }

    public function browserData(): array
    {
        return array_map(static fn (array $city): array => [
            'id' => (string) $city['id'],
            'name' => $city['name'],
            'province_id' => (string) $city['province_id'],
        ], array_values($this->cities()));
    }

    private function id(mixed $value): ?string
    {
        return (is_string($value) || is_int($value)) && preg_match('/^[1-9][0-9]*$/D', (string) $value) === 1
            ? (string) $value : null;
    }

    private function dataset(string $name): array
    {
        if (! isset(self::$datasets[$name])) {
            $data = json_decode(file_get_contents(resource_path("data/iran/{$name}.json")), true, 512, JSON_THROW_ON_ERROR);
            self::$datasets[$name] = $name === 'manifest' ? $data : array_column($data, null, 'id');
        }

        return self::$datasets[$name];
    }
}
