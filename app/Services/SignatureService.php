<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignatureService
{
    private const MAX_FILE_SIZE = 2 * 1024 * 1024;

    public function storeLoanSignature(string $base64Signature): string
    {
        $binary = $this->decodePng($base64Signature);

        $path = sprintf(
            'signatures/loans/%s.png',
            Str::uuid()
        );

        $stored = Storage::disk('local')->put($path, $binary);

        if (!$stored) {
            throw ValidationException::withMessages([
                'signature' => 'No fue posible almacenar la firma.',
            ]);
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function decodePng(string $base64Signature): string
    {
        if (
            !preg_match(
                '/^data:image\/png;base64,(.+)$/',
                $base64Signature,
                $matches
            )
        ) {
            throw ValidationException::withMessages([
                'signature' => 'El formato de la firma no es válido.',
            ]);
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'signature' => 'La firma está vacía o dañada.',
            ]);
        }

        if (strlen($binary) > self::MAX_FILE_SIZE) {
            throw ValidationException::withMessages([
                'signature' => 'La firma supera el tamaño permitido.',
            ]);
        }

        $imageInformation = getimagesizefromstring($binary);

        if (
            $imageInformation === false
            || ($imageInformation['mime'] ?? null) !== 'image/png'
        ) {
            throw ValidationException::withMessages([
                'signature' => 'La firma debe ser una imagen PNG válida.',
            ]);
        }

        return $binary;
    }
}