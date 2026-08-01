<?php

namespace Tests\Unit;

use App\Services\FormSubmissionSubmitterResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormSubmissionSubmitterResolverTest extends TestCase
{
    #[DataProvider('submitters')]
    public function test_it_resolves_a_readable_submitter_from_generic_payloads(array $payload, string $expected): void
    {
        $this->assertSame($expected, app(FormSubmissionSubmitterResolver::class)->resolve($payload));
    }

    public static function submitters(): array
    {
        return [
            'name' => [['name' => 'علی رضایی', 'email' => 'ali@example.com'], 'علی رضایی'],
            'full name' => [['full_name' => 'سارا محمدی'], 'سارا محمدی'],
            'customer name' => [['customer_name' => 'شرکت نمونه'], 'شرکت نمونه'],
            'first and last name' => [['first_name' => 'مریم', 'last_name' => 'احمدی'], 'مریم احمدی'],
            'email' => [['email' => 'anonymous@example.com'], 'anonymous@example.com'],
            'phone' => [['phone' => '09120000000'], '09120000000'],
            'fallback' => [['message' => 'سلام'], 'بدون نام'],
        ];
    }
}
