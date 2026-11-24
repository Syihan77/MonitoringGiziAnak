import assert from 'node:assert/strict';
import test from 'node:test';

import {
    daftarBulanAnalisis,
    formatPersentaseAkg,
    labelStatusGizi,
    PESAN_TANPA_CATATAN,
    tanggalLokal,
    tidakAdaCatatanMakan,
} from '../../resources/js/gizi-format.js';

test('tanggal lokal tidak bergeser menjadi kemarin pada dini hari', () => {
    assert.equal(tanggalLokal(new Date(2026, 0, 1, 0, 30)), '2026-01-01');
});

test('tanggal lokal memakai bulan dan hari dua digit', () => {
    assert.equal(tanggalLokal(new Date(2026, 10, 9, 23, 59)), '2026-11-09');
});

test('persentase_akg null menampilkan data tidak tersedia, bukan null%', () => {
    const hasil = formatPersentaseAkg(null, 'tidak_tersedia');

    assert.equal(hasil, 'Data tidak tersedia');
    assert.doesNotMatch(hasil, /null%?/i);
});

test('persentase_akg undefined juga ditangani sebagai data tidak tersedia', () => {
    assert.equal(formatPersentaseAkg(undefined, 'tidak_tersedia'), 'Data tidak tersedia');
});

test('persentase_akg nol tetap ditampilkan sebagai 0%', () => {
    assert.equal(formatPersentaseAkg(0, 'tidak_tersedia'), '0% (tidak_tersedia)');
});

test('persentase_akg tersedia menampilkan nilai dan status', () => {
    assert.equal(formatPersentaseAkg(75.5, 'cukup'), '75.5% (cukup)');
});

test('daftar bulan analisis dimulai dari bulan lahir sampai bulan sekarang', () => {
    const hasil = daftarBulanAnalisis('2026-10-15', new Date(2026, 10, 16));

    assert.deepEqual(hasil, ['2026-11', '2026-10']);
});

test('daftar bulan analisis dibatasi maksimal 12 bulan terbaru', () => {
    const hasil = daftarBulanAnalisis('2024-01-15', new Date(2026, 10, 16));

    assert.equal(hasil.length, 12);
    assert.equal(hasil[0], '2026-11');
    assert.equal(hasil[11], '2025-12');
});

test('daftar bulan analisis memakai batas awal bulan', () => {
    const hasil = daftarBulanAnalisis('2026-10-31', new Date(2026, 10, 1));

    assert.deepEqual(hasil, ['2026-11', '2026-10']);
});

test('daftar bulan analisis hanya satu bulan bila lahir di bulan sekarang', () => {
    const hasil = daftarBulanAnalisis('2026-11-02', new Date(2026, 10, 16));

    assert.deepEqual(hasil, ['2026-11']);
});

test('tanpa tanggal lahir memakai perilaku lama 12 bulan', () => {
    const hasil = daftarBulanAnalisis(null, new Date(2026, 10, 16));

    assert.equal(hasil.length, 12);
    assert.equal(hasil[0], '2026-11');
});

test('daftar bulan analisis menerima tanggal lahir bertipe Date', () => {
    const hasil = daftarBulanAnalisis(new Date(2026, 9, 15), new Date(2026, 10, 16));

    assert.deepEqual(hasil, ['2026-11', '2026-10']);
});

test('label status gizi menggunakan teks Indonesia', () => {
    assert.equal(labelStatusGizi('tidak_tersedia'), 'Data tidak tersedia');
    assert.equal(labelStatusGizi('belum_ada_data'), 'Belum ada data');
    assert.equal(labelStatusGizi('TERPENUHI'), 'Terpenuhi');
    assert.equal(labelStatusGizi('KURANG_TERPENUHI'), 'Kurang terpenuhi');
    assert.equal(labelStatusGizi('KURANG TERPENUHI'), 'Kurang terpenuhi');
    assert.equal(labelStatusGizi('TIDAK_TERPENUHI'), 'Tidak terpenuhi');
    assert.equal(labelStatusGizi('DATA_TIDAK_LENGKAP'), 'Data tidak lengkap');
    assert.equal(labelStatusGizi(null), 'Data tidak tersedia');
});

test('catatan kosong hanya ditentukan jumlah konsumsi, bukan status zat', () => {
    assert.equal(PESAN_TANPA_CATATAN, 'Belum ada catatan makan hari ini');
    assert.equal(tidakAdaCatatanMakan({ jumlah_konsumsi: 0, ringkasan: [] }), true);
    assert.equal(tidakAdaCatatanMakan({ jumlah_konsumsi: 1, ringkasan: [] }), false);
    assert.equal(tidakAdaCatatanMakan({
        jumlah_konsumsi: 1,
        ringkasan: [{ status: 'tidak_tersedia' }],
    }), false);
    assert.equal(tidakAdaCatatanMakan({
        jumlah_konsumsi: 3,
        ringkasan: [{ status: 'tidak_tersedia' }, { status: 'cukup' }],
    }), false);
    assert.equal(tidakAdaCatatanMakan({
        status_analisis: 'TIDAK_DAPAT_DIANALISIS_BELUM_ADA_DATA',
        ringkasan: [],
    }), true);
    assert.equal(tidakAdaCatatanMakan({
        status_analisis: 'DIANALISIS',
        ringkasan: [{ status: 'tidak_tersedia' }],
    }), false);
    assert.equal(tidakAdaCatatanMakan([]), true);
    assert.equal(tidakAdaCatatanMakan([{ status: 'tidak_tersedia' }]), false);
    assert.equal(tidakAdaCatatanMakan([{ status: 'cukup' }]), false);
});
