/**
 * Detail Resep - sumber data: API backend (tanpa mock).
 * Dibuka dari hasil rekomendasi (selectedResepId) atau tautan langsung.
 */

document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const resepId = params.get('id') || sessionStorage.getItem('selectedResepId');

  if (!resepId) {
    alert('Pilih resep dari halaman rekomendasi terlebih dahulu.');
    window.location.href = '/input-ortu';
    return;
  }

  if (!(window.NutriApi && window.NutriApi.getToken())) {
    window.location.href = '/login';
    return;
  }

  let data = null;
  try {
    data = await window.NutriApi.resepDetail(resepId);
  } catch (e) {
    console.error('Gagal memuat resep:', e && e.message);
    alert('Detail resep tidak dapat dimuat.');
    window.location.href = '/input-ortu';
    return;
  }

  // ============================================
  // RENDER HEADER
  // ============================================
  setText('resep-title', data.nama_resep);
  setText('resep-waktu', '-');
  setText('resep-kesulitan', data.tingkat_kesulitan || '-');
  setText('resep-kategori', data.kategori_gizi || '-');
  setText('resep-description', data.catatan_resep || 'Menu ini disusun berdasarkan kebutuhan gizi dan riwayat tumbuh kembang balita Anda.');

  const peringatan = document.getElementById('resep-peringatan');
  if (peringatan) {
    const pesan = [];
    if (data.usia_minimal_bulan_jika_dimodifikasi && data.modifikasi_untuk_turunkan_usia) {
      pesan.push(`Usia minimum resep ini ${data.usia_min_bulan} bulan. Untuk usia ${data.usia_minimal_bulan_jika_dimodifikasi} bulan, resep perlu dimodifikasi: ${data.modifikasi_untuk_turunkan_usia}`);
    }
    if (Array.isArray(data.alergen) && data.alergen.length) {
      pesan.push(`Mengandung alergen: ${data.alergen.join(', ')}.`);
    }
    if (pesan.length) {
      peringatan.innerHTML = pesan.join('<br>');
      peringatan.style.display = 'block';
    }
  }

  const gizi = data.gizi_per_porsi || {};
  const angka = (nilai) => (nilai === null || nilai === undefined ? null : nilai);
  const teks = (nilai, satuan) => (nilai === null ? 'Tidak tersedia' : `${nilai}${satuan}`);
  const energi = angka(gizi.energi);
  setText('resep-kalori', energi === null ? 'Energi tidak tersedia' : `±${energi} kkal`);

  const img = document.getElementById('resep-image');
  if (img) img.src = '/img/placeholder-menu.svg';

  // ============================================
  // RENDER BAHAN
  // ============================================
  const bahans = Array.isArray(data.bahans) ? data.bahans : [];
  setText('bahan-total', `Total: ${bahans.length} bahan`);

  const bahanList = document.getElementById('bahan-list');
  if (bahanList) {
    bahanList.innerHTML = '';
    bahans.forEach(bahan => {
      const jumlah = bahan.takaran_jumlah
        ? `${bahan.takaran_jumlah} ${bahan.takaran_satuan || 'g'}`
        : (bahan.takaran_gram_ekuivalen === null || bahan.takaran_gram_ekuivalen === undefined
          ? 'Takaran belum tersedia'
          : `${bahan.takaran_gram_ekuivalen} gram`);
      const li = document.createElement('li');
      li.innerHTML = `
        <div class="bahan-bullet"></div>
        <span class="bahan-nama">${bahan.nama_bahan}</span>
        <span class="bahan-jumlah">${jumlah}</span>
      `;
      bahanList.appendChild(li);
    });
  }

  // ============================================
  // RENDER GIZI PER PORSI
  // ============================================
  const protein = angka(gizi.protein);
  const karbo = angka(gizi.karbohidrat);
  const lemak = angka(gizi.lemak);

  setText('gizi-energi', teks(energi, ' kkal'));
  setText('gizi-protein', teks(protein, ' g'));
  setText('gizi-karbo', teks(karbo, ' g'));
  setText('gizi-lemak', teks(lemak, ' g'));

  const maxValues = { energi: 400, protein: 30, karbo: 60, lemak: 20 };
  setLebar('.gizi-bar-fill.calorie', energi === null ? 0 : (energi / maxValues.energi) * 100);
  setLebar('.gizi-bar-fill.protein', protein === null ? 0 : (protein / maxValues.protein) * 100);
  setLebar('.gizi-bar-fill.carb', karbo === null ? 0 : (karbo / maxValues.karbo) * 100);
  setLebar('.gizi-bar-fill.fat', lemak === null ? 0 : (lemak / maxValues.lemak) * 100);

  // ============================================
  // RENDER LANGKAH MEMASAK
  // ============================================
  const timeline = document.getElementById('langkah-timeline');
  if (timeline) {
    timeline.innerHTML = '';
    String(data.cara_memasak || '').split('\n').map((s) => s.trim()).filter(Boolean)
      .forEach((desc, index) => {
        const item = document.createElement('div');
        item.className = 'langkah-item';
        item.innerHTML = `
          <div class="langkah-number">${index + 1}</div>
          <div class="langkah-header">
            <h3 class="langkah-title">Langkah ${index + 1}</h3>
          </div>
          <p class="langkah-desc">${desc}</p>
        `;
        timeline.appendChild(item);
      });
  }

  function setText(id, nilai) {
    const el = document.getElementById(id);
    if (el) el.textContent = nilai;
  }

  function setLebar(selector, persen) {
    const el = document.querySelector(selector);
    if (el) el.style.width = `${Math.max(0, Math.min(100, persen))}%`;
  }
});
