<?php

namespace App\Actions\Search;

use App\Models\Asset;
use App\Models\Sim;
use App\Services\PhoneNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Resolves the navbar "lookup by tag" box.
 *
 * Asset behaviour is unchanged: an exact unique asset_tag always wins.
 * Only when no asset has that exact tag do we look for a SIM (ICCID,
 * MSISDN — including format-tolerant / partial digit match). A unique
 * SIM opens its card; several SIMs open the SIM index with the term.
 */
class ResolveTagLookupAction
{
    /**
     * @return array{
     *     outcome: 'asset'|'sim'|'assets_index'|'sims_index'|'not_found',
     *     asset?: Asset,
     *     sim?: Sim,
     *     tag: string
     * }
     */
    public static function run(string $tag): array
    {
        $tag = trim($tag);

        $assets = Asset::query()->where('asset_tag', '=', $tag);
        $assetCount = (clone $assets)->count();

        if ($assetCount === 1) {
            return [
                'outcome' => 'asset',
                'asset' => $assets->first(),
                'tag' => $tag,
            ];
        }

        // Multiple exact asset tags → asset list (same as before). Do not
        // also hunt for SIMs; the tag clearly belongs to hardware.
        if ($assetCount > 1) {
            return [
                'outcome' => 'assets_index',
                'tag' => $tag,
            ];
        }

        if ($tag !== '' && Gate::allows('view', Sim::class)) {
            $sims = self::simsMatching($tag);
            $simCount = (clone $sims)->count();

            if ($simCount === 1) {
                return [
                    'outcome' => 'sim',
                    'sim' => $sims->first(),
                    'tag' => $tag,
                ];
            }

            if ($simCount > 1) {
                return [
                    'outcome' => 'sims_index',
                    'tag' => $tag,
                ];
            }
        }

        return [
            'outcome' => 'not_found',
            'tag' => $tag,
        ];
    }

    /**
     * SIMs matching ICCID (normalized) or MSISDN (E.164 / digit needles).
     */
    private static function simsMatching(string $tag): Builder
    {
        $iccid = strtoupper(preg_replace('/[\s\-]/', '', $tag) ?? '');
        $needles = PhoneNumberService::searchNeedles($tag);
        $digits = PhoneNumberService::digits($tag);

        return Sim::query()->where(function (Builder $query) use ($iccid, $needles, $digits, $tag): void {
            if ($iccid !== '') {
                $query->orWhere('sims.iccid', '=', $iccid);
            }

            if ($digits !== '') {
                $query->orWhereRaw("REPLACE(sims.msisdn, '+', '') = ?", [$digits]);
            }

            foreach ($needles as $needle) {
                $query->orWhereRaw("REPLACE(sims.msisdn, '+', '') LIKE ?", ['%'.$needle.'%']);
            }

            // Named SIMs (rare) — exact name only, never a loose LIKE.
            if ($tag !== '') {
                $query->orWhere('sims.name', '=', $tag);
            }
        });
    }
}
