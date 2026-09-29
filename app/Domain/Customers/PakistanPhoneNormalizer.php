<?php

namespace App\Domain\Customers;

use Illuminate\Validation\ValidationException;

final class PakistanPhoneNormalizer
{
    public function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $trimmed = trim($phone);
        if (preg_match('/[A-Za-z]/', $trimmed) === 1) {
            throw ValidationException::withMessages(['phone' => 'Phone extensions are not supported in the normalized contact field.']);
        }
        $digits = preg_replace('/\D+/', '', $trimmed);
        if (! is_string($digits)) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Pakistan phone number.']);
        }
        if (str_starts_with($digits, '0092')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '92')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        $validMobile = preg_match('/^3[0-6][0-9]{8}$/', $digits) === 1;
        $validFixed = preg_match('/^[2456789][0-9]{8,9}$/', $digits) === 1;
        if (! $validMobile && ! $validFixed) {
            throw ValidationException::withMessages(['phone' => 'Enter a Pakistan mobile or fixed-line number with its area code.']);
        }

        return '+92'.$digits;
    }
}
