<?php

namespace App\Support;

final class TakaranCatatan
{
    /**
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private const KHUSUS = [
        'R040' => [
            ['kayu manis', 'Takaran tidak tersedia di sumber; perlu verifikasi'],
        ],
    ];

    public static function untuk(
        string $idResep,
        string $nama,
        bool $pelengkap,
        ?string $satuan,
        ?float $gram,
    ): ?string {
        $nama = TakaranConverter::normalisasi($nama);

        foreach (self::KHUSUS[$idResep] ?? [] as [$kunci, $catatan]) {
            if (str_contains($nama, $kunci)) {
                return $catatan;
            }
        }

        if ($pelengkap && $gram === null) {
            return 'Pelengkap tanpa takaran';
        }

        if (! $pelengkap && $satuan === 'secukupnya' && $gram === null) {
            return 'Takaran tidak tersedia di sumber; perlu verifikasi';
        }

        return null;
    }
}
