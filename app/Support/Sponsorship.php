<?php

namespace App\Support;

/**
 * What a sponsor availed (see config ereserve.sponsorship): Individual Sponsors choose a tier
 * (Gold, Silver, Bronze); Event Sponsors choose a package (Puso, Dedikasyon, Talino).
 * One place validates and words it, so the sign-up forms, the ticket and the Records agree.
 */
final class Sponsorship
{
    /** @return array<string,array{label:string,short:string,tier_label:string,tiers:array<string,string>}> */
    public static function options(): array
    {
        return (array) config('ereserve.sponsorship');
    }

    /** Both parts must be one of the known pairs. */
    public static function valid(?string $type, ?string $tier): bool
    {
        $o = self::options();

        return $type !== null && $tier !== null && isset($o[$type]['tiers'][$tier]);
    }

    /** "Individual Sponsor · Gold", "Event Sponsor · Puso Package"; '' when nothing is recorded. */
    public static function label(?string $type, ?string $tier): string
    {
        if (!self::valid($type, $tier)) {
            return '';
        }
        $o = self::options()[$type];

        return $o['short'] . ' · ' . $o['tiers'][$tier] . ($type === 'event' ? ' Package' : '');
    }
}
