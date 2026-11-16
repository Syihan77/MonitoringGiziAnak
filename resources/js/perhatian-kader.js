/**
 * Perhatian Kader - Data Controller
 * File: perhatian-kader.js
 * Sumber data: API backend (tanpa mock). Hanya anak yang perlu perhatian
 * (status selain normal, termasuk yang belum diukur).
 */

const PERHATIAN_STATUS = {
  AT_RISK_MONITORING: { label: 'Perlu Pemantauan', cls: 'gizi-kurang', avatar: 'yellow' },
  HIGH_RISK_REFERRAL: { label: 'Perlu Rujukan', cls: 'gizi-buruk', avatar: 'red' },
};

function inisial(nama) {
  if (!nama) return '?';
  return nama.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

// ==========================================
// RENDER TABEL
// ==========================================
function renderTable(data) {
  const tbody = document.getElementById('riwayat-table-body');

  if (!tbody) return;

  if (!data || data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">Tidak ada balita yang perlu perhatian saat ini</td></tr>';
    return;
  }

  const rows = data.map(child => {
    const ukur = child.ukur_terakhir || null;
    const meta = (ukur && PERHATIAN_STATUS[ukur.kategori_pemantauan])
      || { label: 'Belum Diukur', cls: 'belum-ukur', avatar: 'gray' };

    return `
    <tr onclick="viewDetailAnak('${child.anak_id}')" style="cursor: pointer;" onmouseover="this.style.backgroundColor='#f0fdf4'" onmouseout="this.style.backgroundColor='transparent'">
      <td>
        <div class="user-cell">
          <div class="user-avatar ${meta.avatar}">${inisial(child.nama)}</div>
          <span class="user-name" style="color: #006a38; font-weight: 600;">${child.nama || '-'}</span>
        </div>
      </td>
      <td>${child.usia_bulan} Bulan<br><span style="color:#64748b">(${child.jenis_kelamin})</span></td>
      <td>${child.pemilik_nama || '-'}</td>
      <td>${child.wilayah || '-'}</td>
      <td>
        <span class="badge-status ${meta.cls}">${meta.label}</span>
      </td>
      <td>
        <button class="btn-lihat" onclick="event.stopPropagation(); viewDetailAnak('${child.anak_id}')">
          Lihat
        </button>
        <button class="btn-lihat" onclick="event.stopPropagation(); window.location.href='/ukur-kader?anak_id=${child.anak_id}'">
          Ukur
        </button>
      </td>
    </tr>
  `}).join('');

  tbody.innerHTML = rows;
}

// ==========================================
// VIEW DETAIL ANAK (Menuju ke riwayat-kader)
// ==========================================
function viewDetailAnak(anakId) {
  sessionStorage.setItem('selectedAnakKader', JSON.stringify({ anak_id: anakId }));
  window.location.href = `/riwayat-kader?id=${anakId}`;
}

// ==========================================
// INISIALISASI
// ==========================================
document.addEventListener('DOMContentLoaded', async () => {
  try {
    if (!(window.NutriApi && window.NutriApi.getToken())) {
      window.location.href = '/login';
      return;
    }

    const profil = await window.NutriApi.me();
    if (profil.role !== 'kader' && profil.role !== 'admin') {
      window.location.href = '/db-ortu';
      return;
    }

    const list = await window.NutriApi.get('/anak?ringkas_ukur=1&limit=100');
    const items = Array.isArray(list) ? list : (list && list.data) || [];

    const perlu = items.filter((c) => {
      const ukur = c.ukur_terakhir;
      if (!ukur || !ukur.kategori_pemantauan) return true;
      return ukur.kategori_pemantauan !== 'NORMAL_LOW_RISK';
    });

    renderTable(perlu);
  } catch (e) {
    console.error('Gagal memuat data perhatian:', e && e.message);
    renderTable(null);
  }
});
