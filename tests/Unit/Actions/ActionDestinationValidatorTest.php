<?php

namespace Tests\Unit\Actions;

use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use App\CMS\Actions\Validation\ActionDestinationValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActionDestinationValidatorTest extends TestCase
{
    #[DataProvider('validDestinations')]
    public function test_valid_local_destinations(array $input): void
    {
        $result = $this->validate($input);

        $this->assertTrue($result->isValid(), json_encode($result->errors));
    }

    public static function validDestinations(): array
    {
        return [
            'absolute https' => [['type' => 'custom_url', 'value' => 'https://example.com/a?q=1']],
            'absolute http' => [['type' => 'custom_url', 'value' => 'http://example.com']],
            'root relative' => [['type' => 'custom_url', 'value' => '/contact']],
            'relative' => [['type' => 'custom_url', 'value' => 'contact/team']],
            'query only' => [['type' => 'custom_url', 'value' => '?q=value']],
            'temporary placeholder' => [['type' => 'custom_url', 'value' => '#']],
            'trimmed temporary placeholder' => [['type' => 'custom_url', 'value' => " \t# \t"]],
            'page' => [['type' => 'page', 'reference_id' => 1]],
            'project numeric string' => [['type' => 'project', 'reference_id' => '2']],
            'product' => [['type' => 'product', 'reference_id' => 3]],
            'service' => [['type' => 'service', 'reference_id' => 4]],
            'form page' => [['type' => 'form', 'reference_id' => 5, 'display' => 'page']],
            'form modal' => [['type' => 'form', 'reference_id' => 5, 'display' => 'modal']],
            'form fallback' => [['type' => 'form', 'reference_id' => 5, 'display' => null]],
            'anchor' => [['type' => 'anchor', 'value' => '#contact']],
            'email' => [['type' => 'email', 'value' => 'mailto:info@example.com']],
            'uppercase email' => [['type' => 'email', 'value' => 'Info@Example.COM']],
            'local phone' => [['type' => 'phone', 'value' => '021-12345678']],
            'international phone' => [['type' => 'phone', 'value' => 'tel:+98 912 123 4567']],
            'new tab custom URL' => [['type' => 'custom_url', 'value' => '/docs', 'open_in_new_tab' => true]],
            'new tab reference' => [['type' => 'page', 'reference_id' => 1, 'open_in_new_tab' => true]],
        ];
    }

    #[DataProvider('invalidDestinations')]
    public function test_invalid_or_unsafe_destinations(
        array $input,
        string $field,
        string $error,
    ): void {
        $result = $this->validate($input);

        $this->assertTrue($result->isInvalid());
        $this->assertContains($error, $result->errorsFor($field));
    }

    public static function invalidDestinations(): array
    {
        return [
            'empty type' => [[], 'type', 'action_type_required'],
            'unknown type' => [['type' => 'popup'], 'type', 'unsupported_action_type'],
            'wrong case type' => [['type' => 'PAGE', 'reference_id' => 1], 'type', 'unsupported_action_type'],
            'future schema' => [['schema_version' => 2, 'type' => 'page', 'reference_id' => 1], 'schema_version', 'unsupported_schema_version'],
            'empty URL' => [['type' => 'custom_url', 'value' => ''], 'value', 'action_value_required'],
            'javascript' => [['type' => 'custom_url', 'value' => 'javascript:alert(1)'], 'value', 'unsafe_or_unsupported_url_scheme'],
            'data' => [['type' => 'custom_url', 'value' => 'data:text/html,test'], 'value', 'unsafe_or_unsupported_url_scheme'],
            'vbscript' => [['type' => 'custom_url', 'value' => 'vbscript:msgbox(1)'], 'value', 'unsafe_or_unsupported_url_scheme'],
            'control characters' => [['type' => 'custom_url', 'value' => "/contact\r\nX-Test: yes"], 'value', 'url_contains_unsafe_characters'],
            'URL whitespace' => [['type' => 'custom_url', 'value' => '/contact us'], 'value', 'url_contains_unsafe_characters'],
            'protocol relative' => [['type' => 'custom_url', 'value' => '//example.com'], 'value', 'protocol_relative_or_backslash_url_not_allowed'],
            'backslash' => [['type' => 'custom_url', 'value' => '\\\\example.com'], 'value', 'protocol_relative_or_backslash_url_not_allowed'],
            'anchor through URL' => [['type' => 'custom_url', 'value' => '#contact'], 'value', 'anchor_requires_anchor_type'],
            'double hash' => [['type' => 'custom_url', 'value' => '##'], 'value', 'anchor_requires_anchor_type'],
            'zero reference' => [['type' => 'page', 'reference_id' => 0], 'reference_id', 'positive_reference_id_required'],
            'negative reference' => [['type' => 'project', 'reference_id' => -2], 'reference_id', 'positive_reference_id_required'],
            'non numeric reference' => [['type' => 'product', 'reference_id' => 'abc'], 'reference_id', 'positive_reference_id_required'],
            'URL instead of reference' => [['type' => 'service', 'reference_id' => '/services/a'], 'reference_id', 'positive_reference_id_required'],
            'invalid form display' => [['type' => 'form', 'reference_id' => 1, 'display' => 'drawer'], 'display', 'invalid_form_display'],
            'empty anchor' => [['type' => 'anchor', 'value' => '#'], 'value', 'action_value_required'],
            'anchor whitespace' => [['type' => 'anchor', 'value' => 'contact us'], 'value', 'invalid_anchor'],
            'anchor unsafe characters' => [['type' => 'anchor', 'value' => 'x\"><script'], 'value', 'invalid_anchor'],
            'invalid email' => [['type' => 'email', 'value' => 'not-an-email'], 'value', 'invalid_email'],
            'invalid phone letters' => [['type' => 'phone', 'value' => 'tel:CALL-ME'], 'value', 'invalid_phone'],
            'invalid phone double plus' => [['type' => 'phone', 'value' => '++98912'], 'value', 'invalid_phone'],
        ];
    }

    #[DataProvider('newTabIsCanonicalFalse')]
    public function test_incompatible_new_tab_is_normalized_to_false(string $type, array $extra): void
    {
        $destination = $this->normalizer()->normalize([
            'type' => $type,
            ...$extra,
            'open_in_new_tab' => true,
        ]);

        $this->assertFalse($destination->openInNewTab);
        $this->assertTrue((new ActionDestinationValidator)->validate($destination)->isValid());
    }

    public static function newTabIsCanonicalFalse(): array
    {
        return [
            ['form', ['reference_id' => 1]],
            ['anchor', ['value' => 'section']],
            ['email', ['value' => 'info@example.com']],
            ['phone', ['value' => '+989121234567']],
        ];
    }

    private function validate(array $input)
    {
        return (new ActionDestinationValidator)->validate(
            $this->normalizer()->normalize($input),
        );
    }

    private function normalizer(): ActionDestinationNormalizer
    {
        return new ActionDestinationNormalizer;
    }
}
