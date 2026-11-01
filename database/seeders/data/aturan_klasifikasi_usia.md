# Aturan Label Usia Resep (dataset_resep_gizi_berlabel_usia.csv)

Label dibuat otomatis dari teks bahan dan langkah memasak, dengan penilaian tekstur per resep secara manual.
**Belum divalidasi ahli gizi.** Gunakan sebagai draf awal untuk DSS, lalu minta Nutra Desa atau ahli gizi mengecek terutama baris dengan `keyakinan_label` = rendah.

## Kolom baru
| Kolom | Isi |
|---|---|
| usia_minimal_bulan | Usia minimal jika resep dibuat **persis seperti tertulis** (6 / 8 / 9 / 12) |
| usia_minimal_bulan_jika_dimodifikasi | Usia minimal setelah garam, gula, kecap/saus asin, cabai, dll. dihilangkan atau diganti |
| jenis_menu | makanan_utama, puree_buah_sayur, camilan_dessert, minuman |
| tekstur | halus/lumat halus, lumat/lembut, cincang/potongan kecil/lunak, padat/olahan/kenyal |
| alasan_usia_resep_asli | Faktor yang membuat usia resep asli lebih tinggi dari usia menurut tekstur |
| modifikasi_untuk_turunkan_usia | Apa yang perlu diganti atau dihilangkan agar kolom usia modifikasi berlaku |
| alergen | Telur, ikan, udang/ebi, susu sapi, kedelai, gluten, wijen, kacang-kacangan |
| ada_garam_atau_gula | ya/tidak (di bahan atau langkah memasak) |
| catatan | Duri ikan, santan, rempah tajam, risiko tersedak, dll. |
| keyakinan_label | tinggi / sedang / rendah: seberapa jelas tekstur disebut di resep |

## Usia dasar menurut tekstur (acuan tahapan MPASI Kemenkes/WHO)
- **6 bulan**: halus, puree, bubur saring, atau lumat halus
- **8 bulan**: lumat atau lembut, tekstur tidak dirinci
- **9 bulan**: cincang, potongan kecil lunak, nasi tim, finger food lunak
- **12 bulan**: makanan keluarga, olahan, atau tekstur kenyal

## Faktor yang menaikkan usia resep asli ke 12 bulan
Faktor ini berlaku jika bisa dihilangkan, dan usia setelah modifikasi kembali mengikuti tekstur:
- Garam, termasuk yang hanya disebut di langkah memasak (kecuali ditandai opsional)
- Gula tambahan (pasir/merah)
- Bahan natrium tinggi: kecap, saus tiram, kaldu jamur, abon, ebi, teri kering, terasi, keju
- Cabai/rawit
- Susu sapi sebagai cairan utama (bukan sedikit untuk memasak)
- Jus yang disaring

## Dipaksa 12 bulan (tidak turun walau dimodifikasi)
R015 (goreng + tepung panir), R018 (sempol), R041/R046/R092 (bakso olahan), R048 (dimsum), R042/R075 (bola ketan kenyal, risiko tersedak), R061 (lontong + sate pedas), R083 (lontong), R064/R073 (nasi goreng).

## Yang sengaja **tidak** menaikkan usia ke 12 bulan
- **Santan**: hanya diberi catatan karena tinggi lemak.
- **Udang/ebi dan telur/ikan**: alergen yang boleh dikenalkan sejak 6 bulan, satu per satu. Ebi kering tetap dihitung bahan asin.
- **Rempah tajam** (merica, pala, bunga lawang, kapulaga): hanya diberi catatan.
