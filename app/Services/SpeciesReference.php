<?php

namespace App\Services;

/**
 * Reference soil parameter ranges for the mangrove species this project
 * evaluates sites for -- transcribed directly from the project's own
 * literature review (thesis Table II, "Soil Parameter Ranges for Target
 * Mangrove Species"). Every number here comes from that table; nothing is
 * estimated or invented.
 *
 * Only pH and salinity have reference ranges in that table -- moisture is
 * explicitly marked "pending field calibration" for every species there,
 * and nitrogen/phosphorus/potassium aren't in the table at all. Those
 * three stay unclassified/uncolored throughout the app; this class simply
 * has no ranges for them, by design.
 *
 * Salinity is in ppt (parts per thousand), not mS/cm -- confirmed with
 * the project owner to follow the reference table's unit. Where the table
 * gives an extended "tolerant to X" ceiling beyond the optimal band, that
 * becomes the amber (warning) tier; species with no such figure (only
 * Sonneratia alba) have no amber tier, just green/red. Where the table
 * gives a tolerant ceiling but no corresponding tolerant floor, the floor
 * is treated as 0 -- salinity can't be negative, so this isn't a real gap
 * in the data, just the natural lower bound.
 */
class SpeciesReference
{
    public const RANGES = [
        'rhizophora_mucronata' => [
            'label' => 'Rhizophora mucronata (Bakhaw bunga)',
            'photo' => 'rhizophora_mucronata.jpg',
            'ph' => ['min' => 7.0, 'max' => 7.9],
            'salinity_ppt' => ['optimal_min' => 3.5, 'optimal_max' => 17.5, 'tolerant_max' => 90],
        ],
        'rhizophora_apiculata' => [
            'label' => 'Rhizophora apiculata (Bakhaw lalaki)',
            'photo' => 'rhizophora_apiculata.jpg',
            'ph' => ['min' => 7.0, 'max' => 7.3],
            'salinity_ppt' => ['optimal_min' => 8, 'optimal_max' => 15, 'tolerant_max' => 65],
        ],
        'avicennia_marina' => [
            'label' => 'Avicennia marina (Bungalon)',
            'photo' => 'avicennia_marina.jpg',
            'ph' => ['min' => 6.0, 'max' => 8.5],
            'salinity_ppt' => ['optimal_min' => 5, 'optimal_max' => 15, 'tolerant_max' => 45],
        ],
        'sonneratia_alba' => [
            'label' => 'Sonneratia alba (Pagatpat)',
            'photo' => 'sonneratia_alba.jpg',
            'ph' => ['min' => 7.1, 'max' => 8.7],
            // No tolerant ceiling given for this species -- the optimal
            // band is the only band; classify() has no amber tier for it.
            'salinity_ppt' => ['optimal_min' => 1.75, 'optimal_max' => 17.5, 'tolerant_max' => null],
        ],
    ];

    public static function label(?string $species): ?string
    {
        return self::RANGES[$species]['label'] ?? null;
    }

    public static function photoUrl(?string $species): ?string
    {
        if (! $species || ! isset(self::RANGES[$species])) {
            return null;
        }

        return asset('images/species/' . self::RANGES[$species]['photo']);
    }

    /**
     * @param string $field 'ph' or 'salinity_ppt' -- any other field always returns null (no reference data exists for it).
     * @return 'good'|'warning'|'critical'|null null when species/value is missing or the field has no reference range.
     */
    public static function classify(string $field, ?string $species, ?float $value): ?string
    {
        if ($species === null || $value === null || ! isset(self::RANGES[$species][$field])) {
            return null;
        }

        $range = self::RANGES[$species][$field];

        if ($field === 'ph') {
            return ($value >= $range['min'] && $value <= $range['max']) ? 'good' : 'critical';
        }

        if ($field === 'salinity_ppt') {
            if ($value >= $range['optimal_min'] && $value <= $range['optimal_max']) {
                return 'good';
            }
            if ($range['tolerant_max'] !== null && $value >= 0 && $value <= $range['tolerant_max']) {
                return 'warning';
            }
            return 'critical';
        }

        return null;
    }
}
