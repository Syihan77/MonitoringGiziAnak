/**
 * Form ukur kader - catat pengukuran anak binaan (sinkron ke orang tua).
 */
document.addEventListener('DOMContentLoaded', async () => {
  if (!(window.NutriApi && window.NutriApi.getToken())) {
    window.location.href = '/login';
    return;
  }

  const form = document.getElementById('form-ukur-kader');
  const selectAnak = document.getElementById('anak-select');
  const inputTinggi = document.getElementById('tinggiBadan');
  const inputBerat = document.getElementById('beratBadan');
  const inputPosisi = document.getElementById('posisiUkur');
  const inputTanggal = document.getElementById('tanggalUkur');
  const elError = document.getElementById('ukur-error');
  const boxHasil = document.getElementById('ukur-hasil');
  const btnSimpan = document.getElementById('btn-simpan');

  const hariIni = new Date().toISOString().slice(0, 10);
  inputTanggal.max = hariIni;
  inputTanggal.value = hariIni;

  document.getElementById('btn-kembali').addEventListener('click', () => {
    window.history.back();
  });

  function tampilGalat(pesan) {
    elError.textContent = pesan;
    elError.style.display = 'block';
  }

  // Muat daftar anak binaan.
  let profil = null;
  try {
    profil = await window.NutriApi.me();
    if (profil.role !== 'kader' && profil.role !== 'admin') {
      window.location.href = '/db-ortu';
      return;
    }

    const list = await window.NutriApi.get('/anak?limit=100');
    const items = Array.isArray(list) ? list : (list && list.data) || [];

    selectAnak.innerHTML = items.length === 0
      ? '<option value="">Belum ada anak binaan</option>'
      : '<option value="">Pilih anak...</option>';

    items.forEach((a) => {
      const opt = document.createElement('option');
      opt.value = a.anak_id;
      opt.textContent = `${a.nama} (${a.usia_bulan} bln)`;
      selectAnak.appendChild(opt);
    });

    const dariUrl = new URLSearchParams(window.location.search).get('anak_id');
    if (dariUrl && items.some((a) => a.anak_id === dariUrl)) {
      selectAnak.value = dariUrl;
    }
  } catch (e) {
    tampilGalat((e && e.message) || 'Gagal memuat daftar anak.');
    return;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    elError.style.display = 'none';
    boxHasil.style.display = 'none';

    if (!selectAnak.value) {
      tampilGalat('Pilih anak binaan terlebih dahulu.');
      return;
    }
    if (!inputPosisi.value) {
      tampilGalat('Posisi ukur wajib diisi.');
      inputPosisi.focus();
      return;
    }

    btnSimpan.disabled = true;
    btnSimpan.textContent = 'Menyimpan...';

    try {
      const hasil = await window.NutriApi.post(`/anak/${selectAnak.value}/pertumbuhan`, {
        berat_badan_kg: Number(inputBerat.value),
        tinggi_badan_cm: Number(inputTinggi.value),
        tanggal_ukur: inputTanggal.value,
        posisi_ukur: inputPosisi.value,
      });

      boxHasil.innerHTML = `Tersimpan. Umur ${hasil.umur_bulan} bln — `
        + `Z BB/U ${hasil.zscore_bb_u}, TB/U ${hasil.zscore_tb_u}, BB/TB ${hasil.zscore_bb_tb}. `
        + `Status: <strong>${hasil.status_gizi_antropometri}</strong> `
        + `(<a href="/riwayat-kader?id=${selectAnak.value}">lihat riwayat</a>).`;
      boxHasil.style.display = 'block';
      form.querySelectorAll('input[type="number"]').forEach((i) => { i.value = ''; });
    } catch (err) {
      if (err && err.error_code === 'VALIDATION_ERROR' && err.errors) {
        const det = Object.entries(err.errors).map(([k, v]) => `${k}: ${[].concat(v).join(', ')}`).join('; ');
        tampilGalat(`Data belum valid: ${det}`);
      } else {
        tampilGalat((err && err.message) || 'Penyimpanan gagal. Coba lagi.');
      }
    } finally {
      btnSimpan.disabled = false;
      btnSimpan.textContent = 'Simpan Pengukuran';
    }
  });
});
