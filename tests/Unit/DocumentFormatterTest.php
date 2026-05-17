<?php

namespace Tests\Unit;

use App\Support\DocumentFormatter;
use PHPUnit\Framework\TestCase;

class DocumentFormatterTest extends TestCase
{
    public function test_cpf_formats_unmasked_digits(): void
    {
        $this->assertSame('111.222.333-44', DocumentFormatter::cpf('11122233344'));
    }

    public function test_cpf_normalizes_already_masked_value(): void
    {
        $this->assertSame('111.222.333-44', DocumentFormatter::cpf('111.222.333-44'));
    }

    public function test_cpf_strips_non_digit_characters(): void
    {
        $this->assertSame('111.222.333-44', DocumentFormatter::cpf('111-222-333.44'));
    }

    public function test_cpf_returns_null_for_empty_input(): void
    {
        $this->assertNull(DocumentFormatter::cpf(null));
        $this->assertNull(DocumentFormatter::cpf(''));
    }

    public function test_rg_formats_unmasked_digits(): void
    {
        $this->assertSame('11.222.333-4', DocumentFormatter::rg('112223334'));
    }

    public function test_rg_normalizes_already_masked_value(): void
    {
        $this->assertSame('11.222.333-4', DocumentFormatter::rg('11.222.333-4'));
    }

    public function test_rg_returns_null_for_empty_input(): void
    {
        $this->assertNull(DocumentFormatter::rg(null));
        $this->assertNull(DocumentFormatter::rg(''));
    }

    public function test_cep_formats_unmasked_digits(): void
    {
        $this->assertSame('01310-100', DocumentFormatter::cep('01310100'));
    }

    public function test_cep_normalizes_already_masked_value(): void
    {
        $this->assertSame('01310-100', DocumentFormatter::cep('01310-100'));
    }

    public function test_cep_returns_null_for_empty_input(): void
    {
        $this->assertNull(DocumentFormatter::cep(null));
        $this->assertNull(DocumentFormatter::cep(''));
    }
}
