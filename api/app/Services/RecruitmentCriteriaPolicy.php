<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class RecruitmentCriteriaPolicy
{
    /** @var array<string, string> */
    private const PROHIBITED = [
        'âge|age|date de naissance|(?:moins|plus) de \d{1,2} ans|\d{1,2}\s*ans' => 'âge',
        'sexe|genre|grossesse|situation familiale' => 'sexe, genre ou situation familiale',
        'origine|ethnie|nationalité|nationality' => 'origine ou nationalité',
        'religion|croyance' => 'religion ou croyances',
        'handicap|santé|health|maladie' => 'santé ou handicap',
        'orientation sexuelle|opinion politique|syndicat' => 'orientation, opinions ou activité syndicale',
        'photo|apparence physique|adresse personnelle' => 'apparence ou adresse personnelle',
    ];

    /** @param list<string> $criteria */
    public function assertAllowed(array $criteria): void
    {
        foreach ($criteria as $index => $criterion) {
            foreach (self::PROHIBITED as $pattern => $label) {
                if (preg_match('/\b(?:'.$pattern.')\b/iu', $criterion) === 1) {
                    throw ValidationException::withMessages([
                        "criteria.$index" => "Ce critère porte sur une caractéristique protégée ($label). Utilisez uniquement des preuves professionnelles nécessaires au poste.",
                    ]);
                }
            }
        }
    }
}
