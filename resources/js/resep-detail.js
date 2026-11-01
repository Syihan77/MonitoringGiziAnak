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
    if (data.porsi_saji === null) {
      pesan.push('Takaran porsi saji belum tersedia; gunakan jumlah porsi hasil sebagai acuan.');
    }
    if (pesan.length) {
      peringatan.innerHTML = pesan.join('<br>');
      peringatan.style.display = 'block';
    }
  }

  const gizi = data.gizi_per_porsi || {};
  const energi = gizi.energi ?? 0;
  setText('resep-kalori', `±${energi} kkal`);

  const img = document.getElementById('resep-image');
  if (img) img.src = 'https://placehold.co/600x400/006a38/ffffff?text=Resep';

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
        : (bahan.takaran_gram === null ? 'Takaran belum tersedia' : `${bahan.takaran_gram} gram`);
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
  setText('gizi-energi', `${energi} kkal`);
  setText('gizi-protein', `${gizi.protein ?? 0} g`);
  setText('gizi-karbo', `${gizi.karbohidrat ?? 0} g`);
  setText('gizi-lemak', `${gizi.lemak ?? 0} g`);

  const maxValues = { energi: 400, protein: 30, karbo: 60, lemak: 20 };
  setLebar('.gizi-bar-fill.calorie', (energi / maxValues.energi) * 100);
  setLebar('.gizi-bar-fill.protein', ((gizi.protein ?? 0) / maxValues.protein) * 100);
  setLebar('.gizi-bar-fill.carb', ((gizi.karbohidrat ?? 0) / maxValues.karbo) * 100);
  setLebar('.gizi-bar-fill.fat', ((gizi.lemak ?? 0) / maxValues.lemak) * 100);

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
