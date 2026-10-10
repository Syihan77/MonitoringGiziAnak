/**
 * Dashboard Orang Tua - Dynamic Controller
 * File: db-ortu.js
 */

document.addEventListener('DOMContentLoaded', async () => {
  // 1. AMBIL DATA: utama dari API, fallback ke sessionStorage (alur lama).
  const legacyOrtu = safeParse(sessionStorage.getItem('ortuDataStep1'));
  const legacyAnak = safeParse(sessionStorage.getItem('anakDataStep2'));
  const legacyGrowth = safeParse(sessionStorage.getItem('pertumbuhanDataStep3'));

  let namaOrtu = legacyOrtu && legacyOrtu.namaLengkap;
  let namaAnak = legacyAnak && legacyAnak.namaAnak;
  let tglLahirAnak = legacyAnak && legacyAnak.tanggalLahirAnak;
  let tinggi = legacyGrowth && legacyGrowth.tinggiBadan;
  let berat = legacyGrowth && legacyGrowth.beratBadan;

  if (window.NutriApi && window.NutriApi.getToken()) {
    try {
      const me = await window.NutriApi.me();
      if (me && me.nama) namaOrtu = me.nama;

      const list = await window.NutriApi.anakList();
      const items = Array.isArray(list) ? list : (list && list.data) || [];
      if (items.length > 0) {
        const pertama = items[0];
        const detail = await window.NutriApi.anakDetail(pertama.anak_id);
        namaAnak = detail.nama;
        tglLahirAnak = detail.tanggal_lahir;

        const riwayat = await window.NutriApi.ukurRiwayat(pertama.anak_id);
        const arr = Array.isArray(riwayat) ? riwayat : [];
        if (arr.length > 0) {
          const terakhir = arr[arr.length - 1];
          tinggi = terakhir.tinggi_cm;
          berat = terakhir.berat_kg;
        }
      }
    } catch (e) {
      console.warn('Gagal memuat data API, memakai data lokal:', e && e.message);
    }
  }

  function safeParse(s) {
    try {
      return s ? JSON.parse(s) : null;
    } catch {
      return null;
    }
  }

  function inisial(nama) {
    if (!nama) return '?';
    return nama.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
  }

  function isiAvatar() {
    const avOrtu = document.getElementById('avatar-ortu');
    const avAnak = document.getElementById('avatar-anak');
    if (avOrtu && namaOrtu) avOrtu.textContent = inisial(namaOrtu);
    if (avAnak && namaAnak) avAnak.textContent = inisial(namaAnak);
  }

  isiAvatar();

  // 2. FUNGSI HITUNG UMUR ANAK
  function hitungUmur(tanggalLahir) {
    if (!tanggalLahir) return "-";
    const lahir = new Date(tanggalLahir);
    const hariIni = new Date();
    let tahun = hariIni.getFullYear() - lahir.getFullYear();
    let bulan = hariIni.getMonth() - lahir.getMonth();
    
    if (bulan < 0 || (bulan === 0 && hariIni.getDate() < lahir.getDate())) {
      tahun--;
      bulan += 12;
    }
    
    if (tahun > 0) {
      return `${tahun} Tahun ${bulan} Bulan`;
    } else {
      return `${bulan} Bulan`;
    }
  }

  // 3. ISI DATA KE DASHBOARD
  if (namaOrtu) {
    document.getElementById('greeting-parent').textContent = namaOrtu;
  }

  if (namaAnak) {
    const namaDepan = namaAnak.split(' ')[0]; // Ambil nama depan saja untuk sapaan AI

    document.getElementById('child-name').textContent = namaAnak;
    document.getElementById('child-name-inline').textContent = namaDepan;
    document.getElementById('child-name-analysis').textContent = namaDepan;
    document.getElementById('child-age').textContent = hitungUmur(tglLahirAnak);
  }

  if (tinggi !== undefined && tinggi !== null) {
    document.getElementById('tb-value').textContent = tinggi || '-';
    document.getElementById('bb-value').textContent = berat || '-';
  }

  // ==========================================
  // CATAT NUTRISI (API real: autocomplete + porsi + analisis inline)
  // ==========================================
  const foodInput = document.getElementById('food-input');
  const foodTags = document.getElementById('food-tags');
  const foodPorsi = document.getElementById('food-porsi');
  const btnCalculate = document.getElementById('btn-calculate');
  const catatError = document.getElementById('catat-error');
  const catatHasil = document.getElementById('catat-hasil');
  const saranBox = document.getElementById('food-suggest');

  // State: [{id: uuid|null, nama, porsi}]
  let catatItems = [];
  document.querySelectorAll('#food-tags .food-tag').forEach((tag) => {
    const btn = tag.querySelector('.tag-remove');
    const nama = btn ? btn.dataset.food : tag.textContent.trim();
    if (nama) catatItems.push({ id: null, nama, porsi: 100 });
  });

  function gambarUlangTag() {
    if (!foodTags) return;
    foodTags.innerHTML = '';
    catatItems.forEach((item) => {
      const tag = document.createElement('span');
      tag.className = 'food-tag';
      tag.innerHTML = `${item.nama} `
        + `<button type="button" class="tag-porsi" data-nama="${item.nama}" title="Ubah porsi">${item.porsi}g</button> `
        + `<button class="tag-remove" data-nama="${item.nama}">`
        + `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:12px;height:12px;flex:none;"><path d="M18 6 6 18M6 6l12 12"/></svg></button>`;
      tag.querySelector('.tag-remove').addEventListener('click', () => {
        catatItems = catatItems.filter((i) => i.nama.toLowerCase() !== item.nama.toLowerCase());
        gambarUlangTag();
      });
      tag.querySelector('.tag-porsi').addEventListener('click', (e) => {
        e.stopPropagation();
        editPorsi(tag, item);
      });
      foodTags.appendChild(tag);
    });
  }
  gambarUlangTag();

  function editPorsi(tagEl, item) {
    const btn = tagEl.querySelector('.tag-porsi');
    if (!btn || tagEl.querySelector('.tag-porsi-input')) return;
    const input = document.createElement('input');
    input.type = 'number';
    input.min = '1';
    input.max = '2000';
    input.value = item.porsi;
    input.className = 'tag-porsi-input';
    input.setAttribute('aria-label', `Porsi ${item.nama} dalam gram`);
    btn.replaceWith(input);
    input.focus();
    input.select();

    const commit = (batal) => {
      if (!batal) {
        const v = parseFloat(input.value);
        if (Number.isFinite(v) && v >= 1) {
          item.porsi = Math.min(Math.round(v), 2000);
        }
      }
      gambarUlangTag();
    };

    input.addEventListener('keydown', (e) => {
      e.stopPropagation();
      if (e.key === 'Enter') {
        e.preventDefault();
        commit(false);
      } else if (e.key === 'Escape') {
        commit(true);
      }
    });
    input.addEventListener('blur', () => commit(false));
    input.addEventListener('click', (e) => e.stopPropagation());
  }

  function porsiAktif() {
    const v = foodPorsi ? parseFloat(foodPorsi.value) : 100;
    return Number.isFinite(v) && v > 0 ? Math.min(v, 2000) : 100;
  }

  function tambahItem(nama, bahanId) {
    if (!nama) return;
    if (catatItems.some((i) => i.nama.toLowerCase() === nama.toLowerCase())) return;
    catatItems.push({ id: bahanId || null, nama, porsi: porsiAktif() });
    gambarUlangTag();
  }

  // Autocomplete katalog (debounce, min 2 huruf). keydown agar jalan di HP.
  let catatTimer = null;
  function tutupSaran() {
    if (saranBox) { saranBox.style.display = 'none'; saranBox.innerHTML = ''; }
  }

  if (foodInput) {
    foodInput.addEventListener('input', () => {
      clearTimeout(catatTimer);
      const kata = foodInput.value.trim();
      if (kata.length < 2 || !(window.NutriApi && window.NutriApi.getToken())) {
        tutupSaran();
        return;
      }
      catatTimer = setTimeout(async () => {
        try {
          const hasil = await window.NutriApi.bahanSearch(kata, 8);
          const items = Array.isArray(hasil) ? hasil : (hasil && hasil.data) || [];
          if (!saranBox || items.length === 0) {
            tutupSaran();
            return;
          }
          saranBox.innerHTML = '';
          items.forEach((b) => {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'saran-item';
            el.innerHTML = `<strong>${b.nama_bahan}</strong> <span>${b.kelompok_gizi || ''}</span>`;
            el.addEventListener('mousedown', (e) => {
              e.preventDefault();
              tambahItem(b.nama_bahan, b.id);
              foodInput.value = '';
              tutupSaran();
              foodInput.focus();
            });
            saranBox.appendChild(el);
          });
          saranBox.style.display = 'block';
        } catch {
          tutupSaran();
        }
      }, 300);
    });

    foodInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') tutupSaran();
      if (e.key === 'Enter') {
        e.preventDefault();
        if (foodInput.value.trim()) {
          tambahItem(foodInput.value.trim(), null);
          foodInput.value = '';
          tutupSaran();
        }
      }
    });
    foodInput.addEventListener('blur', () => setTimeout(tutupSaran, 150));
  }

  function tampilGalatCatat(pesan) {
    if (!catatError) return;
    catatError.textContent = pesan;
    catatError.style.display = 'block';
  }

  async function anakAktifId() {
    const tersimpan = sessionStorage.getItem('lastAnakId');
    if (tersimpan) return tersimpan;
    const list = await window.NutriApi.anakList();
    const items = Array.isArray(list) ? list : (list && list.data) || [];
    if (items.length === 0) return null;
    sessionStorage.setItem('lastAnakId', items[0].anak_id);
    return items[0].anak_id;
  }

  async function resolveBahanId(item) {
    if (item.id) return item.id;
    const hasil = await window.NutriApi.bahanSearch(item.nama, 3);
    const items = Array.isArray(hasil) ? hasil : (hasil && hasil.data) || [];
    return items.length > 0 ? items[0].id : null;
  }

  if (btnCalculate) {
    btnCalculate.addEventListener('click', async () => {
      if (catatError) catatError.style.display = 'none';
      if (catatHasil) catatHasil.style.display = 'none';

      if (catatItems.length === 0) {
        alert('Silakan tambahkan makanan terlebih dahulu!');
        return;
      }
      if (!(window.NutriApi && window.NutriApi.getToken())) {
        alert('Silakan login terlebih dahulu.');
        window.location.href = '/login';
        return;
      }

      const asli = btnCalculate.innerHTML;
      btnCalculate.innerHTML = 'Menyimpan...';
      btnCalculate.disabled = true;

      try {
        const ids = [];
        const dilewati = [];
        for (const item of catatItems) {
          const id = await resolveBahanId(item);
          if (id) ids.push({ bahan_id: id, porsi_gram: item.porsi });
          else dilewati.push(item.nama);
        }

        if (ids.length === 0) {
          tampilGalatCatat('Tak ada bahan yang dikenal di katalog. Coba pilih dari saran pencarian.');
          return;
        }

        const anakId = await anakAktifId();
        if (!anakId) {
          tampilGalatCatat('Belum ada data anak. Lengkapi pendaftaran anak terlebih dahulu.');
          return;
        }

        const hariIni = new Date().toISOString().slice(0, 10);
        await window.NutriApi.post(`/anak/${anakId}/konsumsi`, {
          tanggal_konsumsi: hariIni,
          items: ids,
        });

        const analisis = await window.NutriApi.get(`/anak/${anakId}/analisis-gizi?tanggal=${hariIni}`);
        tampilkanAnalisis(analisis, dilewati);
        catatItems = [];
        gambarUlangTag();
      } catch (err) {
        tampilGalatCatat((err && err.message) || 'Gagal menyimpan. Periksa koneksi lalu coba lagi.');
      } finally {
        btnCalculate.innerHTML = asli;
        btnCalculate.disabled = false;
      }
    });
  }

  function tampilkanAnalisis(data, dilewati) {
    if (!catatHasil) return;
    const rows = (data.ringkasan || []).map((r) =>
      `<div class="hasil-row"><span>${r.zat_gizi}</span>`
      + `<strong>${r.persentase_akg}% (${r.status})</strong></div>`
    ).join('');
    catatHasil.innerHTML = `<h4>Hasil analisis hari ini (${data.kelompok_umur || ''})</h4>`
      + rows
      + (dilewati.length > 0 ? `<p class="hasil-note">${dilewati.join(', ')} dilewati (tak dikenal).</p>` : '')
      + `<a href="/history-ortu" class="hasil-link">Lihat History →</a>`;
    catatHasil.style.display = 'block';
    catatHasil.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
});