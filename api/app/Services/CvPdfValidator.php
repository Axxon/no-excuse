<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class CvPdfValidator
{
    public function validate(UploadedFile $file): void
    {
        $path = $file->getRealPath();
        $contents = is_string($path) ? file_get_contents($path) : false;
        if (! is_string($contents) || ! str_starts_with($contents, '%PDF-') || ! str_contains(substr($contents, -2048), '%%EOF')) {
            throw ValidationException::withMessages(['cv' => 'Le fichier ne possède pas une structure PDF valide.']);
        }
        if (preg_match('/\/Encrypt\b/', $contents) === 1) {
            throw ValidationException::withMessages(['cv' => 'Les PDF protégés par mot de passe ne sont pas acceptés.']);
        }
        if (preg_match_all('/\/Type\s*\/Page\b/', $contents) > 100) {
            throw ValidationException::withMessages(['cv' => 'Le CV PDF ne peut pas dépasser 100 pages.']);
        }
    }
}
