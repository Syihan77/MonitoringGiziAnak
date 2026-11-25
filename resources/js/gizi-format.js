/**
 * Tanggal hari ini menurut zona waktu browser (YYYY-MM-DD).
 * Jangan memakai toISOString(): nilainya UTC dan dapat menggeser tanggal
 * menjadi kemarin pada pukul 00.00-06.59 WIB.
 */
export function tanggalLokal(sekarang = new Date()) {
    const tahun = sekarang.getFullYear();
    const bulan = String(sekarang.getMonth() + 1).padStart(2, '0');
    const hari = String(sekarang.getDate()).padStart(2, '0');

    return `${tahun}-${bulan}-${hari}`;
}

const LABEL_STATUS_GIZI = {
    tidak_tersedia: 'Data tidak tersedia',
    belum_ada_data: 'Belum ada data',
    belum_tercatat: 'Belum ada data',
    terpenuhi: 'Terpenuhi',
    kurang_terpenuhi: 'Kurang terpenuhi',
    tidak_terpenuhi: 'Tidak terpenuhi',
    data_tidak_lengkap: 'Data tidak lengkap',
    sangat_kurang: 'Sangat kurang',
    kurang: 'Kurang',
    cukup: 'Cukup',
    baik: 'Baik',
    lebih: 'Lebih',
};

export const PESAN_TANPA_CATATAN = 'Belum ada catatan makan hari ini';

export function labelStatusGizi(status) {
    if (status === null || status === undefined || status === '') {
        return 'Data tidak tersedia';
    }

    const kunci = String(status).trim().toLowerCase().replace(/\s+/g, '_');

    return LABEL_STATUS_GIZI[kunci] || String(status).replace(/_/g, ' ');
}

export function tidakAdaCatatanMakan(data) {
    if (Array.isArray(data)) {
        return data.length === 0;
    }

    if (!data || typeof data !== 'object') {
        return true;
    }

    if (data.jumlah_konsumsi !== null && data.jumlah_konsumsi !== undefined && data.jumlah_konsumsi !== '') {
        const jumlah = Number(data.jumlah_konsumsi);
        if (Number.isFinite(jumlah)) {
            return jumlah <= 0;
        }
    }

    const status = String(data.status_analisis || '').toUpperCase();
    if (status === 'TIDAK_DAPAT_DIANALISIS_BELUM_ADA_DATA') {
        return true;
    }
    if (status === 'DIANALISIS'
        || status === 'TIDAK_DAPAT_DIANALISIS_ANAK_SAKIT'
        || status === 'TIDAK_DAPAT_DIANALISIS_TIDAK_ADA_DATA_VALID') {
        return false;
    }

    return !Array.isArray(data.ringkasan) || data.ringkasan.length === 0;
}

export function daftarBulanAnalisis(tanggalLahir, sekarang = new Date(), jumlah = 12) {
    const batas = Math.max(0, Math.floor(Number(jumlah) || 0));
    if (batas === 0) {
        return [];
    }

    const tahunSekarang = sekarang.getFullYear();
    const bulanSekarang = sekarang.getMonth() + 1;
    const lahir = bacaBulan(tanggalLahir);
    const hasil = [];

    let tahun = tahunSekarang;
    let bulan = bulanSekarang;

    while (hasil.length < batas) {
        if (lahir && (tahun < lahir.tahun || (tahun === lahir.tahun && bulan < lahir.bulan))) {
            break;
        }

        hasil.push(`${tahun}-${String(bulan).padStart(2, '0')}`);

        bulan -= 1;
        if (bulan === 0) {
            bulan = 12;
            tahun -= 1;
        }
    }

    return hasil;
}

function bacaBulan(tanggal) {
    if (tanggal instanceof Date && !Number.isNaN(tanggal.getTime())) {
        return { tahun: tanggal.getFullYear(), bulan: tanggal.getMonth() + 1 };
    }

    const cocok = /^(\d{4})-(\d{2})/.exec(String(tanggal || ''));
    if (!cocok) {
        return null;
    }

    const tahun = Number(cocok[1]);
    const bulan = Number(cocok[2]);

    if (bulan < 1 || bulan > 12) {
        return null;
    }

    return { tahun, bulan };
}

export function formatPersentaseAkg(persentase, status) {
    if (persentase === null || persentase === undefined) {
        return 'Data tidak tersedia';
    }

    return status ? `${persentase}% (${status})` : `${persentase}%`;
}
