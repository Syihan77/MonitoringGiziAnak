/**
 * Dashboard Kader - Dynamic Data Controller
 * File: db-kader.js
 * Sumber data: API backend (tanpa mock).
 */

import { renderPrevalensi } from './grafik.js';

// ==========================================
// KATEGORI PEMANTAUAN -> LABEL & WARNA
// ==========================================
const KATEGORI_META = {
  NORMAL_LOW_RISK: { label: 'Normal / Risiko Rendah', cls: 'green' },
  AT_RISK_MONITORING: { label: 'Perlu Pemantauan', cls: 'yellow' },
  HIGH_RISK_REFERRAL: { label: 'Perlu Rujukan', cls: 'red' },
};

function metaKategori(ukur) {
  if (!ukur) return { label: 'Belum Diukur', cls: 'gray' };
  return KATEGORI_META[ukur.kategori_pemantauan] || { label: 'Belum Diukur', cls: 'gray' };
}

function inisial(nama) {
  if (!nama) return '?';
  return nama.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

function formatTanggal(tanggal) {
  if (!tanggal) return '-';
  const d = new Date(`${tanggal}T00:00:00`);
  if (Number.isNaN(d.getTime())) return tanggal;
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ==========================================
// API SERVICE CLASS (window.NutriApi)
// ==========================================
class DashboardAPI {
  async getProfil() {
    return window.NutriApi.me();
  }

  async getSummary(wilayah) {
    return window.NutriApi.get(`/kader/dashboard?wilayah=${encodeURIComponent(wilayah)}`);
  }

  async getChildrenList() {
    const list = await window.NutriApi.get('/anak?ringkas_ukur=1&limit=100');
    return Array.isArray(list) ? list : (list && list.data) || [];
  }
}

// ==========================================
// NAVIGATION CONTROLLER
// ==========================================
class NavigationController {
  constructor() {
    this.navItems = document.querySelectorAll('.nav-item');
    this.pages = {
      dashboard: document.getElementById('dashboard-page'),
      history: document.getElementById('history-page')
    };
    this.init();
  }

  init() {
    this.navItems.forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        const pageName = item.getAttribute('data-page');
        this.switchPage(pageName);
      });
    });
  }

  switchPage(pageName) {
    this.navItems.forEach(item => {
      if (item.getAttribute('data-page') === pageName) {
        item.classList.add('active');
      } else {
        item.classList.remove('active');
      }
    });

    Object.keys(this.pages).forEach(key => {
      if (key === pageName) {
        this.pages[key].classList.add('active');
      } else {
        this.pages[key].classList.remove('active');
      }
    });

    if (pageName === 'history') {
      document.title = 'Riwayat Data - NutriTumbuh';
    } else {
      document.title = 'Dashboard Kader - NutriTumbuh';
    }
  }
}

// ==========================================
// DASHBOARD CONTROLLER CLASS
// ==========================================
class DashboardController {
  constructor() {
    this.api = new DashboardAPI();
    this.childrenList = [];
    this.wilayah = null;
    this.childrenPromise = null;
    this.init();
  }

  /**
   * Ambil daftar anak sekali saja (di-cache sebagai promise) agar dashboard
   * dan halaman riwayat memakai data yang sama tanpa request ganda.
   */
  loadChildren() {
    if (!this.childrenPromise) {
      this.childrenPromise = this.api.getChildrenList().catch((error) => {
        this.childrenPromise = null; // izinkan percobaan ulang
        throw error;
      });
    }
    return this.childrenPromise;
  }

  async init() {
    try {
      if (!(window.NutriApi && window.NutriApi.getToken())) {
        window.location.href = '/login';
        return;
      }

      const profil = await this.api.getProfil();
      if (profil.role !== 'kader' && profil.role !== 'admin') {
        window.location.href = '/db-ortu';
        return;
      }

      this.wilayah = profil.wilayah_posyandu;
      const [summary, childrenList] = await Promise.all([
        this.api.getSummary(this.wilayah),
        this.loadChildren()
      ]);
      this.childrenList = childrenList;

      this.updateSummaryCards(summary);
      this.renderPrevalensiChart(summary.tren_prevalensi || []);
      this.updateDonutChart(summary);
      this.updateTable(childrenList);

    } catch (error) {
      console.error('Gagal memuat data dashboard:', error);
      this.showError('Gagal memuat data. Silakan refresh halaman.');
    }
  }

  updateSummaryCards(data) {
    const kategori = data.distribusi_kategori_pemantauan || {};
    const baik = kategori.NORMAL_LOW_RISK ?? 0;
    const kurang = kategori.AT_RISK_MONITORING ?? 0;
    const buruk = kategori.HIGH_RISK_REFERRAL ?? 0;

    this.animateValue('total-anak', 0, data.total_anak_binaan || 0, 1000);
    this.animateValue('gizi-baik', 0, baik, 1000);
    this.animateValue('gizi-kurang', 0, kurang, 1000);
    this.animateValue('gizi-buruk', 0, buruk, 1000);

    // Petunjuk saat wilayah belum punya anak binaan (bukan bug).
    document.querySelectorAll('.kader-empty-hint').forEach((el) => el.remove());
    if ((data.total_anak_binaan || 0) === 0) {
      const info = document.createElement('p');
      info.className = 'kader-empty-hint';
      info.setAttribute('style', 'background:#fffbeb;border:1px solid #fcd34d;border-radius:12px;padding:12px 16px;color:#92400e;font-size:14px;margin:0 0 16px;');
      info.textContent = 'Belum ada anak binaan di wilayah ini. Pastikan ejaan wilayah orang tua sama persis dengan profil kader.';
      const konten = document.querySelector('.dashboard-content');
      if (konten) konten.prepend(info);
    }

  }

  renderPrevalensiChart(data) {
    const canvas = document.getElementById('trendChart');
    const wadah = document.querySelector('.trend-graph-placeholder');
    if (!canvas || !wadah) return;

    wadah.querySelectorAll('.trend-empty').forEach((el) => el.remove());

    if (!Array.isArray(data) || data.length === 0) {
      canvas.style.display = 'none';
      const info = document.createElement('p');
      info.className = 'trend-empty';
      info.style.cssText = 'text-align:center;color:#6b7280;padding:48px 16px;margin:0;';
      info.textContent = 'Belum ada data pengukuran pada periode ini.';
      wadah.appendChild(info);
      this.updatePrevalensiBadge(null);
      return;
    }

    canvas.style.display = '';
    renderPrevalensi(canvas, data);
    this.updatePrevalensiBadge(data);
  }

  updatePrevalensiBadge(data) {
    const badge = document.getElementById('trend-badge');
    const text = document.getElementById('trend-text');
    if (!badge || !text) return;

    badge.className = 'badge-neutral';

    if (!Array.isArray(data) || data.length === 0) {
      text.textContent = 'Belum ada data';
      return;
    }

    const terakhir = data[data.length - 1];
    if (terakhir.persen_perlu_perhatian === null) {
      text.textContent = 'Belum ada data bulan ini';
      return;
    }

    const bagian = [
      `${Number(terakhir.persen_perlu_perhatian).toLocaleString('id-ID', {
        maximumFractionDigits: 1,
      })}% perlu perhatian · n=${terakhir.total_diukur}`,
    ];

    if (terakhir.denominator_kecil) {
      bagian.push('denominator kecil');
    }

    const sebelumnya = data[data.length - 2];
    if (sebelumnya && sebelumnya.persen_perlu_perhatian !== null) {
      const selisih = terakhir.persen_perlu_perhatian - sebelumnya.persen_perlu_perhatian;
      const tanda = selisih > 0 ? '+' : '';
      bagian.push(`${tanda}${selisih.toLocaleString('id-ID', {
        maximumFractionDigits: 1,
      })} poin dari bulan lalu`);
    }

    text.textContent = bagian.join(' · ');
  }

  updateDonutChart(data) {
    const kategori = data.distribusi_kategori_pemantauan || {};
    const total = data.anak_terukur || 0;
    const baik = kategori.NORMAL_LOW_RISK ?? 0;
    const kurang = kategori.AT_RISK_MONITORING ?? 0;
    const buruk = kategori.HIGH_RISK_REFERRAL ?? 0;

    const baikPersen = total > 0 ? ((baik / total) * 100).toFixed(0) : 0;
    const kurangPersen = total > 0 ? ((kurang / total) * 100).toFixed(0) : 0;
    const burukPersen = total > 0 ? ((buruk / total) * 100).toFixed(0) : 0;

    document.getElementById('donut-total').textContent = total;

    const donutChart = document.getElementById('donutChart');
    donutChart.style.background = `conic-gradient(
      #3c9a5f 0% ${baikPersen}%,
      #fdb813 ${baikPersen}% ${Number(baikPersen) + Number(kurangPersen)}%,
      #e53935 ${Number(baikPersen) + Number(kurangPersen)}% 100%
    )`;

    const legendHTML = `
      <div class="legend-item">
        <div class="legend-color" style="background: #3c9a5f;"></div>
        <span class="legend-text">Normal / Risiko Rendah</span>
        <span class="legend-value">${baikPersen}%</span>
      </div>
      <div class="legend-item">
        <div class="legend-color" style="background: #fdb813;"></div>
        <span class="legend-text">Perlu Pemantauan</span>
        <span class="legend-value">${kurangPersen}%</span>
      </div>
      <div class="legend-item">
        <div class="legend-color" style="background: #e53935;"></div>
        <span class="legend-text">Perlu Rujukan</span>
        <span class="legend-value">${burukPersen}%</span>
      </div>
    `;
    document.getElementById('chart-legend').innerHTML = legendHTML;
  }

  // ✅ PERBAIKAN: Menggunakan Event Listener, BUKAN inline onclick (agar terbaca di module)
  updateTable(children) {
    const tbody = document.getElementById('table-body');

    if (!children || children.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">Belum ada data anak binaan di wilayah ini</td></tr>';
      return;
    }

    const rows = children.map(child => {
      const ukur = child.ukur_terakhir || null;
      const meta = metaKategori(ukur);
      const nama = child.nama || '-';

      return `
      <tr class="clickable-row" data-id="${child.anak_id}" style="cursor: pointer;" onmouseover="this.style.backgroundColor='#f0fdf4'" onmouseout="this.style.backgroundColor='transparent'">
        <td>
          <div class="user-cell">
            <div class="avatar-${meta.cls}">
              ${inisial(nama)}
            </div>
            <strong style="color: #006a38;">${nama}</strong>
          </div>
        </td>
        <td>${child.usia_bulan} Bulan<br><span style="color:#64748b">(${child.jenis_kelamin})</span></td>
        <td>${child.pemilik_nama || '-'}</td>
        <td>${child.wilayah || '-'}</td>
        <td><span class="badge-status ${meta.cls}">${meta.label}</span></td>
        <td>
          <button class="btn-action" data-action="lihat" data-id="${child.anak_id}" style="color:#006a38;font-weight:600;">
            Lihat →
          </button>
          <button class="btn-action" data-action="ukur" data-id="${child.anak_id}" style="color:#006a38;font-weight:600;">
            Ukur
          </button>
        </td>
      </tr>
    `}).join('');

    tbody.innerHTML = rows;

    tbody.querySelectorAll('.clickable-row').forEach(row => {
      row.addEventListener('click', () => {
        this.viewDetail(row.getAttribute('data-id'));
      });
    });

    tbody.querySelectorAll('[data-action="lihat"]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.viewDetail(btn.getAttribute('data-id'));
      });
    });

    tbody.querySelectorAll('[data-action="ukur"]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        window.location.href = `/ukur-kader?anak_id=${btn.getAttribute('data-id')}`;
      });
    });
  }

  viewDetail(anakId) {
    sessionStorage.setItem('selectedAnakKader', JSON.stringify({ anak_id: anakId }));
    window.location.href = `/riwayat-kader?id=${anakId}`;
  }

  animateValue(id, start, end, duration) {
    const obj = document.getElementById(id);
    const range = end - start;
    const minTimer = 50;
    let stepTime = Math.abs(Math.floor(duration / range));
    stepTime = Math.max(stepTime, minTimer);
    
    let startTime = new Date().getTime();
    let endTime = startTime + duration;
    
    const run = () => {
      let now = new Date().getTime();
      let remaining = Math.max((endTime - now) / duration, 0);
      let value = Math.round(end - (remaining * range));
      obj.innerHTML = value;
      if (value === end) {
        clearInterval(timer);
      }
    };
    
    let timer = setInterval(run, stepTime);
    run();
  }

  showError(message) {
    const tbody = document.getElementById('table-body');
    tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: #e53935;">${message}</td></tr>`;
  }
}

// ==========================================
// ==========================================
// HISTORY CONTROLLER (ukur terakhir tiap anak binaan)
// ==========================================
class HistoryController {
  constructor(getChildren) {
    this.getChildren = getChildren;
    this.historyData = [];
    this.loaded = false;
  }

  async init() {
    const tbody = document.getElementById('history-table-body');
    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">Memuat data...</td></tr>';
    }

    try {
      const children = await this.getChildren();
      this.setData(children);
    } catch (error) {
      console.error('Gagal memuat data history:', error);
      this.showError('Gagal memuat riwayat pengukuran. Silakan refresh halaman.');
    }
  }

  /** Dipanggil setelah daftar anak tersedia (dari dashboard maupun fetch sendiri). */
  setData(children) {
    this.loaded = true;
    this.historyData = (Array.isArray(children) ? children : [])
      .map((c) => {
        const ukur = c.ukur_terakhir || null;
        const meta = metaKategori(ukur);
        return {
          anak_id: c.anak_id,
          nama: c.nama || '-',
          usia: `${c.usia_bulan ?? '-'} Bulan`,
          tanggal: ukur ? formatTanggal(ukur.tanggal_ukur) : '-',
          tanggalUrut: ukur ? ukur.tanggal_ukur : '',
          status: meta.label,
          statusCls: ukur ? meta.cls : 'gray',
        };
      })
      // Yang sudah diukur tampil lebih dulu (terbaru di atas), sisanya alfabetis.
      .sort((a, b) => {
        if (a.tanggalUrut && b.tanggalUrut) return a.tanggalUrut < b.tanggalUrut ? 1 : -1;
        if (a.tanggalUrut) return -1;
        if (b.tanggalUrut) return 1;
        return a.nama.localeCompare(b.nama, 'id');
      });

    this.renderHistoryTable(this.historyData);
  }

  renderHistoryTable(data) {
    const tbody = document.getElementById('history-table-body');
    if (!tbody) return;

    if (!data || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">Belum ada balita binaan di wilayah ini</td></tr>';
      return;
    }

    const rows = data.map(item => `
      <tr class="clickable-history-row" data-id="${item.anak_id}" style="cursor: pointer;" onmouseover="this.style.backgroundColor='#f0fdf4'" onmouseout="this.style.backgroundColor='transparent'">
        <td><strong style="color: #006a38;">${item.nama}</strong></td>
        <td>${item.usia}</td>
        <td>${item.tanggal}</td>
        <td><span class="status-badge ${item.statusCls}">${item.status}</span></td>
        <td><span style="color: #006a38; font-weight: 600;">Lihat Detail</span></td>
      </tr>
    `).join('');

    tbody.innerHTML = rows;

    tbody.querySelectorAll('.clickable-history-row').forEach(row => {
      row.addEventListener('click', () => {
        const id = row.getAttribute('data-id');
        sessionStorage.setItem('selectedAnakKader', JSON.stringify({ anak_id: id }));
        window.location.href = `/riwayat-kader?id=${id}`;
      });
    });
  }

  showError(message) {
    const tbody = document.getElementById('history-table-body');
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 40px; color: #e53935;">${message}</td></tr>`;
  }

  filterHistory(keyword) {
    const filtered = this.historyData.filter(item =>
      item.nama.toLowerCase().includes(keyword.toLowerCase())
    );
    this.renderHistoryTable(filtered);
  }
}
// INISIALISASI
// ==========================================
let dashboard;
let historyController;
let navigation;

document.addEventListener('DOMContentLoaded', () => {
  navigation = new NavigationController();
  dashboard = new DashboardController();
  // Pakai promise yang sama dengan dashboard: tidak ada request ganda dan
  // riwayat tidak lagi terbaca kosong saat dashboard masih memuat data.
  historyController = new HistoryController(() => dashboard.loadChildren());
  historyController.init();

  const searchInput = document.getElementById('search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const keyword = e.target.value.toLowerCase();
      const filtered = (dashboard.childrenList || []).filter(child =>
        (child.nama || '').toLowerCase().includes(keyword) ||
        (child.pemilik_nama || '').toLowerCase().includes(keyword)
      );
      dashboard.updateTable(filtered);
    });
  }

  const historySearchInput = document.getElementById('history-search-input');
  if (historySearchInput) {
    historySearchInput.addEventListener('input', (e) => {
      const keyword = e.target.value.toLowerCase();
      if (!historyController.loaded) return;
      historyController.filterHistory(keyword);
    });
  }
});
