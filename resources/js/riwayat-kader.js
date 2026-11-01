/**
 * Riwayat Anak Kader - sumber data: logbook API (tanpa mock).
 * Layout 1 (default): timeline konsumsi 7 hari terakhir.
 * Layout 2 (filter bulan): kartu pengukuran pada bulan terpilih.
 */

document.addEventListener('DOMContentLoaded', async () => {
  // ============================================
  // 1. IDENTITAS ANAK + MUAT LOGBOOK
  // ============================================
  const urlParams = new URLSearchParams(window.location.search);
  const tersimpan = safeParse(sessionStorage.getItem('selectedAnakKader'));
  const anakId = urlParams.get('id') || (tersimpan && tersimpan.anak_id);

  if (!anakId) {
    alert('Data anak tidak ditemukan.');
    window.location.href = '/perhatian-kader';
    return;
  }

  if (!(window.NutriApi && window.NutriApi.getToken())) {
    window.location.href = '/login';
    return;
  }

  let logbook = null;
  try {
    logbook = await window.NutriApi.get(`/kader/anak/${anakId}/logbook`);
  } catch (e) {
    console.error('Gagal memuat logbook:', e && e.message);
    alert('Data anak tidak dapat diakses.');
    window.location.href = '/perhatian-kader';
    return;
  }

  function safeParse(s) {
    try {
      return s ? JSON.parse(s) : null;
    } catch {
      return null;
    }
  }

  function hitungUmur(tanggalLahir) {
    if (!tanggalLahir) return '-';
    const lahir = new Date(tanggalLahir);
    const hariIni = new Date();
    let tahun = hariIni.getFullYear() - lahir.getFullYear();
    let bulan = hariIni.getMonth() - lahir.getMonth();
    if (bulan < 0 || (bulan === 0 && hariIni.getDate() < lahir.getDate())) {
      tahun--;
      bulan += 12;
    }
    return tahun > 0 ? `${tahun} Tahun ${bulan} Bulan` : `${bulan} Bulan`;
  }

  function inisial(nama) {
    if (!nama) return '?';
    return nama.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
  }

  function kelasStatus(status) {
    if (status === 'NORMAL_LOW_RISK' || status === 'normal') return 'baik';
    if (status === 'AT_RISK_MONITORING' || status === 'kurang') return 'kurang';
    return 'buruk';
  }

  function labelStatus(status) {
    const peta = {
      NORMAL_LOW_RISK: 'Normal / Risiko Rendah',
      AT_RISK_MONITORING: 'Perlu Pemantauan',
      HIGH_RISK_REFERRAL: 'Perlu Rujukan',
      normal: 'Gizi Baik',
      kurang: 'Gizi Kurang',
      stunting_terindikasi: 'Stunting Terindikasi',
      gizi_buruk: 'Gizi Buruk',
      lebih: 'Gizi Lebih',
    };
    return (status && peta[status]) || 'Belum Diukur';
  }

  // ============================================
  // 2. ISI DATA PROFIL
  // ============================================
  const riwayat = Array.isArray(logbook.riwayat) ? logbook.riwayat : [];
  const terakhir = riwayat.length > 0
    ? riwayat.reduce((a, b) =>
      new Date(a.tanggal_ukur) > new Date(b.tanggal_ukur) ? a : b
    )
    : null;
  const namaAnak = logbook.nama || 'Anak Binaan';

  setText('breadcrumb-name', namaAnak);
  setText('profile-name', namaAnak);
  setText('child-avatar', inisial(namaAnak));
  setText('profile-usia', hitungUmur(logbook.tanggal_lahir));
  setText('profile-bb', terakhir ? terakhir.berat_kg : '-');
  setText('profile-tb', terakhir ? terakhir.tinggi_cm : '-');
  setText('profile-ibu', logbook.pemilik_nama || '-');
  setText('profile-wilayah', logbook.wilayah || '-');
  setText('ai-child-name', namaAnak.split(' ')[0]);

  const statusTerakhir = terakhir
    ? (terakhir.kategori_pemantauan || terakhir.status_gizi_antropometri)
    : null;
  setText('profile-status-text', labelStatus(statusTerakhir));
  const statusEl = document.getElementById('profile-status');
  if (statusEl) {
    statusEl.className = `status-value ${kelasStatus(statusTerakhir)}`;
    if (terakhir && terakhir.pesan) statusEl.title = terakhir.pesan;
  }

  function setText(id, nilai) {
    const el = document.getElementById(id);
    if (el) el.textContent = nilai;
  }

  // ============================================
  // 3. TIMELINE KONSUMSI (Layout 1)
  // ============================================
  function tanggalLabel(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
  }

  function renderTimelineKonsumsi(hari) {
    const container = document.getElementById('timeline-container');
    if (!container) return;
    container.innerHTML = '';

    if (hari.length === 0) {
      container.innerHTML = '<p style="text-align:center;color:#6b7280;padding:32px 16px;">'
        + 'Belum ada konsumsi tercatat 7 hari terakhir.</p>';
      return;
    }

    hari.forEach((day, idx) => {
      const item = document.createElement('div');
      item.className = `timeline-item ${idx === 0 ? 'current' : ''}`;

      const daftar = day.items.map((it) => {
        const nama = it.nama_resep
          ? `${it.nama_bahan} (${it.nama_resep})`
          : it.nama_bahan;
        const status = it.status_validitas === 'INVALID' ? ' · dikecualikan' : '';
        return `<div class="meal-card"><div class="meal-content">${nama} &middot; ${it.porsi_gram} g${status}</div></div>`;
      }).join('');

      item.innerHTML = `
        <div class="timeline-dot"></div>
        <div class="timeline-date">${tanggalLabel(day.tanggal_konsumsi)}</div>
        ${day.status_kondisi === 'SAKIT' || day.penanda
          ? '<div style="margin:8px 0;padding:10px 14px;border-radius:10px;background:#fef3c7;color:#92400e;font-size:13px;">Anak tercatat sedang sakit — data konsumsi tidak digunakan dalam analisis.</div>'
          : ''}
        <div class="meals-grid">${daftar}</div>
      `;
      container.appendChild(item);
    });
  }

  const konsumsi = Array.isArray(logbook.konsumsi_terakhir) ? logbook.konsumsi_terakhir : [];
  renderTimelineKonsumsi(konsumsi);

  // Status pemenuhan gizi bulanan (real, 3 bulan terakhir).
  await muatStatusBulanan(logbook.anak_id);

  // ============================================
  // 3b. STATUS PEMENUHAN GIZI BULANAN (real, 3 bulan terakhir)
  // ============================================
  const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

  async function muatStatusBulanan(anakId) {
    const wadah = document.querySelector('.nutrition-status-list');
    if (!wadah || !anakId) return;
    wadah.classList.add('carousel-x');

    const now = new Date();
    const daftar = [];
    for (let i = 0; i < 12; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      daftar.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    }

    const hasil = await Promise.all(daftar.map((b) =>
      window.NutriApi.get(`/anak/${anakId}/gizi-bulanan?bulan=${b}`).catch(() => null)
    ));

    const meta = {
      'TERPENUHI': { dot: 'green', badge: 'fulfilled' },
      'KURANG TERPENUHI': { dot: 'yellow', badge: 'partial' },
      'TIDAK TERPENUHI': { dot: 'red', badge: 'unfulfilled' },
    };

    wadah.innerHTML = hasil.map((h, idx) => {
      const [y, m] = daftar[idx].split('-').map(Number);
      const label = `${NAMA_BULAN[m - 1]} ${y}`;
      if (!h) return '';
      if (h.status === 'belum_tercatat') {
        return `<div class="nutrition-status-item"><div class="status-dot gray"></div>`
          + `<div class="status-info"><div class="month-name">${label}</div>`
          + `<div class="month-desc">Belum ada catatan konsumsi.</div></div>`
          + `<div class="status-badge belum">BELUM TERCATAT</div></div>`;
      }
      const mt = meta[h.status] || { dot: 'gray', badge: 'belum' };
      const saran = h.zat_terendah ? ` Perlu peningkatan konsumsi ${h.zat_terendah}.` : '';
      return `<div class="nutrition-status-item"><div class="status-dot ${mt.dot}"></div>`
        + `<div class="status-info"><div class="month-name">${label}</div>`
        + `<div class="month-desc">${h.rata_persen_akg}% rata-rata pemenuhan (${h.hari_tercatat} hari tercatat).${saran}</div></div>`
        + `<div class="status-badge ${mt.badge}">${h.status}</div></div>`;
    }).join('');

    pasangNavigasiKarosel(wadah);
  }

  // Dots + panah ala postingan IG: 1 slide penuh per tampilan.
  function pasangNavigasiKarosel(wadah) {
    wadah.parentElement.querySelectorAll('.gizi-nav').forEach((el) => el.remove());

    const jumlah = wadah.querySelectorAll('.nutrition-status-item').length;
    if (jumlah === 0) return;

    const nav = document.createElement('div');
    nav.className = 'gizi-nav';

    const btnPrev = document.createElement('button');
    btnPrev.type = 'button';
    btnPrev.className = 'gizi-prev';
    btnPrev.textContent = '← Sebelumnya';

    const dots = document.createElement('div');
    dots.className = 'gizi-dots';

    const btnNext = document.createElement('button');
    btnNext.type = 'button';
    btnNext.className = 'gizi-next';
    btnNext.textContent = 'Berikutnya →';

    const tombol = [];
    for (let i = 0; i < jumlah; i++) {
      const d = document.createElement('button');
      d.type = 'button';
      d.className = `gizi-dot${i === 0 ? ' aktif' : ''}`;
      d.setAttribute('aria-label', `Slide ${i + 1}`);
      d.addEventListener('click', () => keSlide(i));
      dots.appendChild(d);
      tombol.push(d);
    }

    const keSlide = (i) => {
      const kartu = wadah.querySelectorAll('.nutrition-status-item')[i];
      if (kartu) wadah.scrollTo({ left: kartu.offsetLeft - wadah.offsetLeft, behavior: 'smooth' });
    };

    const segarkan = () => {
      const idx = Math.round(wadah.scrollLeft / Math.max(wadah.clientWidth, 1));
      tombol.forEach((d, i) => d.classList.toggle('aktif', i === idx));
    };

    btnPrev.addEventListener('click', () => {
      const idx = tombol.findIndex((d) => d.classList.contains('aktif'));
      keSlide((idx - 1 + jumlah) % jumlah);
    });
    btnNext.addEventListener('click', () => {
      const idx = tombol.findIndex((d) => d.classList.contains('aktif'));
      keSlide((idx + 1) % jumlah);
    });

    let scrollTimer = null;
    wadah.addEventListener('scroll', () => {
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(segarkan, 120);
    }, { passive: true });

    nav.appendChild(btnPrev);
    nav.appendChild(dots);
    nav.appendChild(btnNext);
    wadah.insertAdjacentElement('afterend', nav);
    segarkan();
  }

  // ============================================
  // 4. GRID PENGUKURAN PER BULAN (Layout 2)
  // ============================================
  function renderUkurGrid(data, bulanLabel) {
    const gridContainer = document.getElementById('daily-log-grid');
    if (!gridContainer) return;
    gridContainer.innerHTML = '';
    document.getElementById('grid-bulan-label').textContent = bulanLabel;

    if (data.length === 0) {
      gridContainer.innerHTML = '<p style="text-align: center; color: #6b7280; grid-column: 1/-1; padding: 40px;">Tidak ada pengukuran pada bulan ini.</p>';
      return;
    }

    data.forEach((ukur) => {
      const card = document.createElement('div');
      card.className = 'daily-card';

      const kategori = ukur.kategori_pemantauan || ukur.status_gizi_antropometri;
      const usia = ukur.usia_bulan_saat_ukur ?? ukur.umur_bulan;

      card.innerHTML = `
        <div class="daily-header">
          <div>
            <div class="daily-date">${tanggalLabel(ukur.tanggal_ukur)}</div>
            <div class="daily-meals-info">Usia ${usia} bln</div>
          </div>
          <button class="btn-lengkap" type="button">${labelStatus(kategori)}</button>
        </div>
        <div class="meal-item">
          <div class="meal-info">
            <div class="meal-type">Berat / Tinggi</div>
            <div class="meal-food">${ukur.berat_kg} kg &middot; ${ukur.tinggi_cm} cm</div>
          </div>
        </div>
        <div class="meal-item">
          <div class="meal-info">
            <div class="meal-type">Z-score BB/U · TB/U · BB/TB</div>
            <div class="meal-food">${ukur.zscore_bb_u ?? '-'} &middot; ${ukur.zscore_tb_u ?? '-'} &middot; ${ukur.zscore_bb_tb ?? '-'}</div>
          </div>
        </div>
        ${ukur.pesan ? `<div class="meal-item"><div class="meal-info"><div class="meal-food">${ukur.pesan}</div></div></div>` : ''}
      `;
      gridContainer.appendChild(card);
    });
  }

  function pastikanOpsiBulan() {
    const filterBulan = document.getElementById('filter-bulan');
    if (!filterBulan) return;
    filterBulan.innerHTML = '<option value="">Pilih Bulan...</option>';
    const now = new Date();
    for (let i = 0; i < 12; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const opt = document.createElement('option');
      opt.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
      opt.textContent = d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
      filterBulan.appendChild(opt);
    }
  }

  // ============================================
  // 5. TOGGLE TIMELINE / GRID + SEARCH + CETAK
  // ============================================
  pastikanOpsiBulan();

  const filterBulan = document.getElementById('filter-bulan');
  const timelineLayout = document.getElementById('timeline-layout');
  const gridLayout = document.getElementById('grid-layout');

  if (filterBulan) {
    filterBulan.addEventListener('change', (e) => {
      const bulanTerpilih = e.target.value;

      if (bulanTerpilih) {
        timelineLayout.style.display = 'none';
        gridLayout.style.display = 'block';

        const bulanLabel = e.target.options[e.target.selectedIndex].text;
        const data = riwayat.filter((u) => (u.tanggal_ukur || '').startsWith(bulanTerpilih));
        renderUkurGrid(data, bulanLabel);
      } else {
        timelineLayout.style.display = 'block';
        gridLayout.style.display = 'none';
      }
    });
  }

  const btnKembaliTimeline = document.getElementById('btn-kembali-timeline');
  if (btnKembaliTimeline) {
    btnKembaliTimeline.addEventListener('click', () => {
      timelineLayout.style.display = 'block';
      gridLayout.style.display = 'none';
      filterBulan.value = '';
    });
  }

  const searchInput = document.getElementById('search-makanan');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const keyword = e.target.value.toLowerCase();
      document.querySelectorAll('.meal-card, .daily-card').forEach(card => {
        const text = card.textContent.toLowerCase();
        const cocok = text.includes(keyword) || keyword === '';
        card.style.opacity = cocok ? '1' : '0.3';
        if (card.classList.contains('daily-card')) card.style.display = cocok ? 'block' : 'none';
      });
    });
  }

  const btnCetak = document.getElementById('btn-cetak-riwayat');
  if (btnCetak) {
    btnCetak.addEventListener('click', () => {
      window.print();
    });
  }
});
