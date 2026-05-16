<?php

namespace App\Support;

class DocumentFormatter
{
    public static function cpf(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
    }

    public static function rg(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{1})/', '$1.$2.$3-$4', $digits);
    }

    public static function cep(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits);
    }
}
