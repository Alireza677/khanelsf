<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const IDENTIFIER = 'construction_process_v1';

    public function up(): void
    {
        DB::table('forms')
            ->where('type', 'calculator')
            ->where('calculator_identifier', self::IDENTIFIER)
            ->orderBy('id')
            ->chunkById(100, function ($forms): void {
                foreach ($forms as $form) {
                    $schema = json_decode($form->schema ?: '{}', true);
                    $schema = is_array($schema) ? $schema : [];
                    $existingFields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];

                    if (collect($existingFields)->contains(fn (mixed $field): bool => is_array($field)
                        && in_array($field['type'] ?? null, ['image_choice', 'radio_card'], true))) {
                        continue;
                    }

                    $schema['fields'] = [
                        ...$this->questions(),
                        ...($existingFields === [] ? [] : [[
                            'type' => 'page',
                            'name' => 'contact_details',
                            'label' => 'اطلاعات تماس',
                            'description' => 'برای دریافت نتیجه و ادامه مشاوره، اطلاعات تماس خود را وارد کنید.',
                        ]]),
                        ...$existingFields,
                    ];
                    $schema['calculator'] = ['recommendations' => [
                        'prefabricated' => 'پیش ساخته',
                        'site_assembly' => 'مونتاژ در سایت',
                        'site_build' => 'ساخت و مونتاژ در سایت',
                    ]];

                    DB::table('forms')->where('id', $form->id)->update([
                        'schema_version' => 2,
                        'schema' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });
    }

    public function down(): void
    {
        $questionNames = collect($this->questions())
            ->whereIn('type', ['image_choice', 'radio_card'])
            ->pluck('name')
            ->all();

        DB::table('forms')
            ->where('type', 'calculator')
            ->where('calculator_identifier', self::IDENTIFIER)
            ->orderBy('id')
            ->chunkById(100, function ($forms) use ($questionNames): void {
                foreach ($forms as $form) {
                    $schema = json_decode($form->schema ?: '{}', true);
                    $schema = is_array($schema) ? $schema : [];
                    $schema['fields'] = array_values(array_filter(
                        is_array($schema['fields'] ?? null) ? $schema['fields'] : [],
                        fn (mixed $field): bool => ! is_array($field)
                            || (! in_array($field['name'] ?? null, $questionNames, true)
                                && ! in_array($field['name'] ?? null, ['project_profile', 'usage_and_design', 'delivery_conditions', 'contact_details'], true)),
                    ));
                    unset($schema['calculator']);

                    DB::table('forms')->where('id', $form->id)->update([
                        'schema_version' => 1,
                        'schema' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });
    }

    private function questions(): array
    {
        return [
            $this->page('project_profile', 'مشخصات پروژه'),
            $this->question('project_volume', 'حجم پروژه شما چیست؟', [
                $this->option('single_unit', 'تک واحد', 3, 2, 1),
                $this->option('villa_complex', 'مجتمع ویلایی', 2, 3, 1),
                $this->option('apartment_complex', 'مجتمع آپارتمانی', 1, 3, 2),
                $this->option('township', 'شهرک', 3, 2, 1),
            ]),
            $this->question('construction_space', 'در چه فضایی می خواهید ساختمان را بسازید؟', [
                $this->option('existing_building', 'بنای موجود', 2, 3, 1),
                $this->option('garden_land', 'زمین باغی', 3, 2, 1),
                $this->option('villa_land', 'زمین ویلایی', 2, 3, 1),
                $this->option('apartment_land', 'زمین آپارتمانی', 1, 2, 3),
            ]),
            $this->page('usage_and_design', 'کاربری و طراحی'),
            $this->question('building_use', 'نحوه بهره برداری ساختمان:', [
                $this->option('temporary', 'موقت', 3, 1, 0),
                $this->option('movable', 'قابل جابجایی', 3, 1, 0),
                $this->option('expandable', 'قابل توسعه', 2, 3, 1),
                $this->option('maximum_life', 'مقاوم با حداکثر عمر مفید', 1, 2, 3),
            ]),
            $this->question('architecture_style', 'سبک معماری:', [
                $this->option('traditional', 'سنتی', 0, 1, 3),
                $this->option('classic', 'کلاسیک', 1, 2, 3),
                $this->option('modern', 'مدرن', 3, 2, 1),
                $this->option('postmodern', 'پست مدرن', 2, 3, 1),
            ]),
            $this->page('delivery_conditions', 'زمان، بودجه و شرایط اجرا'),
            $this->question('construction_duration', 'مدت زمان ساخت:', [
                $this->option('one_to_three_months', '۱ تا ۳ ماه', 3, 1, 0),
                $this->option('three_to_six_months', '۳ تا ۶ ماه', 2, 3, 1),
                $this->option('six_to_nine_months', '۶ تا ۹ ماه', 1, 3, 2),
                $this->option('nine_to_twelve_months', '۹ تا ۱۲ ماه', 0, 2, 3),
            ]),
            $this->question('financing', 'نحوه تامین هزینه:', [
                $this->option('twenty_five_percent', '۲۵ درصد', 1, 3, 2),
                $this->option('fifty_percent', '۵۰ درصد', 2, 3, 1),
                $this->option('seventy_five_percent', '۷۵ درصد', 3, 2, 1),
                $this->option('one_hundred_percent', '۱۰۰ درصد', 3, 2, 1),
            ]),
            $this->question('construction_environment', 'محیط ساخت:', [
                $this->option('urban', 'شهری', 1, 2, 3),
                $this->option('rural', 'روستایی', 2, 3, 1),
                $this->option('dirt_road', 'مسیر خاکی', 2, 3, 1),
                $this->option('difficult_access', 'صعب العبور', 1, 3, 2),
            ]),
            $this->question('transport_limits', 'محدودیت حمل و باراندازی:', [
                $this->option('none', 'بدون محدودیت حمل و باراندازی', 3, 2, 1),
                $this->option('unloading', 'محدودیت باراندازی', 1, 3, 2),
                $this->option('transport', 'محدودیت حمل', 0, 2, 3),
                $this->option('transport_and_unloading', 'محدودیت حمل و باراندازی', 0, 1, 3),
            ]),
        ];
    }

    private function page(string $name, string $label): array
    {
        return ['type' => 'page', 'name' => $name, 'label' => $label];
    }

    private function question(string $name, string $label, array $options): array
    {
        return compact('name', 'label', 'options') + ['type' => 'radio_card', 'required' => true];
    }

    private function option(string $value, string $label, int $prefabricated, int $siteAssembly, int $siteBuild): array
    {
        return [
            'value' => $value,
            'label' => $label,
            'image' => null,
            'scores' => [
                'prefabricated' => $prefabricated,
                'site_assembly' => $siteAssembly,
                'site_build' => $siteBuild,
            ],
        ];
    }
};
