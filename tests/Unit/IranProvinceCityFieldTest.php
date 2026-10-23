<?php

namespace Tests\Unit;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\FormSchema;
use App\Services\FormSubmissionPresenter;
use App\Services\IranLocations;
use App\Services\SubmissionAnswerSnapshot;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class IranProvinceCityFieldTest extends TestCase
{
    public function test_local_data_covers_all_provinces_and_partitions_real_cities_and_zones(): void
    {
        $locations = app(IranLocations::class);
        $all = json_decode(file_get_contents(resource_path('data/iran/cities.json')), true, 512, JSON_THROW_ON_ERROR);
        $zones = json_decode(file_get_contents(resource_path('data/iran/urban-zones.json')), true, 512, JSON_THROW_ON_ERROR);
        $cities = $locations->cities();
        $provinces = $locations->provinces();
        $this->assertCount(31, $provinces);
        $this->assertCount(1481, $cities);
        $this->assertCount(191, $zones);
        $this->assertCount(1672, $all);
        $this->assertEqualsCanonicalizing(array_keys($provinces), array_unique(array_column($cities, 'province_id')));
        $this->assertSame([], array_intersect(array_keys($cities), array_column($zones, 'id')));
        $this->assertEqualsCanonicalizing(array_column($all, 'id'), [...array_keys($cities), ...array_column($zones, 'id')]);
        $invalidZones = array_filter($zones, fn (array $zone): bool => ! isset($cities[$zone['city_id']])
            || $cities[$zone['city_id']]['province_id'] !== $zone['province_id']);
        $this->assertSame([], $invalidZones);
        $this->assertSame('3.1.2', $locations->version());
        $checksums = json_decode(file_get_contents(resource_path('data/iran/checksums.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($checksums as $file => $checksum) {
            $this->assertSame($checksum, hash_file('sha256', resource_path('data/iran/'.$file)), $file);
        }
    }

    public function test_schema_validates_required_optional_partial_forged_and_cross_province_values(): void
    {
        $cities = array_values(app(IranLocations::class)->cities());
        $city = $cities[0];
        $other = collect($cities)->first(fn (array $item): bool => $item['province_id'] !== $city['province_id']);
        $valid = ['province_id' => (string) $city['province_id'], 'city_id' => (string) $city['id']];
        $zones = json_decode(file_get_contents(resource_path('data/iran/urban-zones.json')), true);
        foreach ([false, true] as $required) {
            $form = $this->form($required);
            $schema = app(FormSchema::class);
            $this->assertSame('iran_province_city', $schema->fields($form)[0]['type']);
            $this->assertSame(6, $schema->fields($form)[0]['layout']['span']);
            $cases = [
                [[], ! $required],
                [['location' => null], ! $required],
                [['location' => []], ! $required],
                [['location' => ['province_id' => '', 'city_id' => '']], ! $required],
                [['location' => $valid], true],
                [['location' => ['province_id' => $city['province_id'], 'city_id' => $city['id']]], true],
                [['location' => ['province_id' => $valid['province_id']]], false],
                [['location' => ['city_id' => $valid['city_id']]], false],
                [['location' => ['province_id' => $other['province_id'], 'city_id' => $city['id']]], false],
                [['location' => ['province_id' => $zones[0]['province_id'], 'city_id' => $zones[0]['id']]], false],
                [['location' => ['province_id' => '999', 'city_id' => '999']], false],
                [['location' => ['province_id' => [$city['province_id']], 'city_id' => $city['id']]], false],
                [['location' => ['province_id' => true, 'city_id' => $city['id']]], false],
                [['location' => $valid + ['city_name' => 'نام جعلی']], false],
                [['location' => 'تهران'], false],
            ];
            foreach ($cases as [$input, $passes]) {
                $this->assertSame($passes, Validator::make($input, $schema->validationRules($form), $schema->validationMessages($form))->passes(), json_encode($input));
            }
        }
    }

    public function test_saved_names_and_presenters_do_not_depend_on_future_catalogue_or_schema_changes(): void
    {
        $locations = app(IranLocations::class);
        $city = array_values($locations->cities())[0];
        $value = $locations->pair(['province_id' => $city['province_id'], 'city_id' => $city['id'], 'city_name' => 'جعلی']);
        $this->assertSame($city['name'], $value['city_name']);
        $this->assertIsString($value['city_id']);
        $this->assertIsString($value['province_id']);
        $answers = app(SubmissionAnswerSnapshot::class)->answers($this->form(), ['location' => $value]);
        $this->assertSame($value, $answers[0]['raw_value']);
        $this->assertSame($value['province_name'].'، '.$value['city_name'], $answers[0]['display_value']);
        $submission = new FormSubmission(['payload' => ['location' => $value, SubmissionAnswerSnapshot::PAYLOAD_KEY => $answers]]);
        $submission->setRelation('form', null)->setRelation('attachments', collect());
        $presenter = app(FormSubmissionPresenter::class);
        $this->assertSame($answers[0]['display_value'], $presenter->answers($submission)[0]['value']);
        $this->assertSame($answers[0]['display_value'], $presenter->rawFields($submission)['location']);
    }

    public function test_shared_public_view_renders_independent_dependent_selects_in_page_and_modal(): void
    {
        foreach (['page', 'modal'] as $mode) {
            $html = view('forms._form', [
                'form' => $this->form(), 'displayMode' => $mode, 'errors' => new ViewErrorBag,
            ])->render();
            $this->assertStringContainsString('name="location[province_id]"', $html);
            $this->assertStringContainsString('name="location[city_id]"', $html);
            $this->assertStringContainsString('data-iran-locations', $html);
            $this->assertSame(2, substr_count($html, 'data-form-select-search>'));
            $this->assertMatchesRegularExpression('/data-location-part="city_id"\s+required disabled/', $html);
        }
        $preview = view('filament.forms.components.iran-location-preview')->render();
        $this->assertStringContainsString('x-model="province"', $preview);
        $this->assertStringContainsString('x-model="city" :disabled="! province"', $preview);
        $selectPreview = view('filament.forms.components.form-select-preview', [
            'uuid' => 'sample', 'label' => 'نوع متقاضی',
            'itemState' => ['options' => [['value' => 'person', 'label' => 'حقیقی']]],
        ])->render();
        $this->assertStringContainsString('data-form-select-native', $selectPreview);
        $this->assertStringContainsString('<option value="person">حقیقی</option>', $selectPreview);
        $this->assertStringNotContainsString('name=', $selectPreview);
    }

    private function form(bool $required = true): Form
    {
        return new Form([
            'name' => 'فرم نمونه', 'slug' => 'iran-location', 'type' => 'lead',
            'schema' => ['fields' => [[
                'key' => 'location', 'label' => 'محل سکونت', 'type' => 'iran_province_city',
                'required' => $required, 'layout' => ['span' => 6],
            ]]],
        ]);
    }
}
