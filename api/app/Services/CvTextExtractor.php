<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;

class CvTextExtractor
{
    public function extract(string $path): string
    {
        $absolutePath = Storage::disk('local')->path($path);
        if (! is_file($absolutePath)) {
            throw new RuntimeException('CV introuvable.');
        }

        $text = str_ends_with(strtolower($absolutePath), '.pdf')
            ? (new Parser)->parseFile($absolutePath)->getText()
            : (string) file_get_contents($absolutePath);

        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            throw new RuntimeException('Aucun texte exploitable trouvé dans le CV.');
        }

        return $text;
    }
}
