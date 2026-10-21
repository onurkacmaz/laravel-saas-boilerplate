<?php

namespace Infrastructure\Localization\Support;

/**
 * Picks the best supported locale for an Accept-Language header, such as
 * "tr-TR,tr;q=0.9,en;q=0.8".
 */
final class LocaleNegotiator
{
    /**
     * @param  list<string>  $supported
     */
    public static function negotiate(?string $acceptLanguage, array $supported, string $fallback): string
    {
        if ($acceptLanguage === null || trim($acceptLanguage) === '') {
            return $fallback;
        }

        $ranked = [];

        foreach (explode(',', $acceptLanguage) as $position => $part) {
            $segments = array_map('trim', explode(';', $part));
            $tag = strtolower(str_replace('_', '-', $segments[0]));
            $quality = 1.0;

            foreach (array_slice($segments, 1) as $parameter) {
                if (str_starts_with($parameter, 'q=')) {
                    $quality = (float) substr($parameter, 2);
                }
            }

            if ($tag !== '' && $quality > 0) {
                $ranked[] = ['tag' => $tag, 'quality' => $quality, 'position' => $position];
            }
        }

        // Highest quality first; equal qualities keep the client's order.
        usort($ranked, fn (array $a, array $b) => [$b['quality'], $a['position']] <=> [$a['quality'], $b['position']]);

        foreach ($ranked as $entry) {
            foreach ([$entry['tag'], explode('-', $entry['tag'])[0]] as $candidate) {
                if (in_array($candidate, $supported, true)) {
                    return $candidate;
                }
            }
        }

        return $fallback;
    }
}
