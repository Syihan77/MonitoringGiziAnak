import assert from 'node:assert/strict';
import test from 'node:test';

import { formatPersentaseAkg } from '../../resources/js/gizi-format.js';

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
