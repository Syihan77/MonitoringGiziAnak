/**
 * Input Orang Tua - Rencana Makan Mingguan
 * File: input-ortu.js
 */
document.addEventListener('DOMContentLoaded', () => {

  // 1. Ingredients Selection (katalog TKPI via API + custom)
  const tagsContainer = document.getElementById('ingredients-tags');
  const ingredientSearch = document.getElementById('ingredient-search');
  let selectedIngredients = [];
  if (document.querySelector('.ingredient-tag.selected')) {
    document.querySelectorAll('.ingredient-tag.selected').forEach((t) => {
      selectedIngredients.push({ id: t.dataset.bahanId || null, nama: t.dataset.ingredient });
    });
  }

  function tambahTag(nama, bahanId) {
    const sudah = selectedIngredients.some((i) => i.nama.toLowerCase() === nama.toLowerCase());
    if (sudah) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ingredient-tag selected';
    btn.dataset.ingredient = nama;
    if (bahanId) btn.dataset.bahanId = bahanId;
    btn.innerHTML = `<span class="check-icon">✓</span> ${nama}`;
    btn.addEventListener('click', () => {
      btn.remove();
      selectedIngredients = selectedIngredients.filter((i) => i.nama.toLowerCase() !== nama.toLowerCase());
    });
    tagsContainer.appendChild(btn);
    selectedIngredients.push({ id: bahanId || null, nama });
  }

  function hapusTag(nama) {
    selectedIngredients = selectedIngredients.filter((i) => i.nama.toLowerCase() !== nama.toLowerCase());
  }

  document.querySelectorAll('.ingredient-tag').forEach(tag => {
    tag.addEventListener('click', () => {
      const ingredient = tag.dataset.ingredient;

      if (tag.classList.contains('selected')) {
        tag.classList.remove('selected');
        tag.innerHTML = ingredient;
        hapusTag(ingredient);
      } else {
        tag.classList.add('selected');
        tag.innerHTML = '<span class="check-icon">✓</span> ' + ingredient;
        selectedIngredients.push({ id: tag.dataset.bahanId || null, nama: ingredient });
      }
    });
  });

  // Autocomplete katalog bahan
  const searchWrap = ingredientSearch.closest('.search-ingredients') || ingredientSearch.parentElement;
  const saranBox = document.createElement('div');
  saranBox.id = 'ingredient-suggest';
  saranBox.setAttribute('style', 'display:none;margin:8px 0 4px;max-height:240px;overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08);');
  searchWrap.insertAdjacentElement('afterend', saranBox);

  let debounceTimer = null;

  function tutupSaran() {
    saranBox.style.display = 'none';
    saranBox.innerHTML = '';
  }

  ingredientSearch.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    const kata = ingredientSearch.value.trim();
    if (kata.length < 2 || !(window.NutriApi && window.NutriApi.getToken())) {
      tutupSaran();
      return;
    }
    debounceTimer = setTimeout(async () => {
      try {
        const hasil = await window.NutriApi.bahanSearch(kata, 10);
        const items = Array.isArray(hasil) ? hasil : (hasil && hasil.data) || [];
        if (items.length === 0) {
          tutupSaran();
          return;
        }
        saranBox.innerHTML = '';
        items.forEach((b) => {
          const el = document.createElement('button');
          el.type = 'button';
          el.setAttribute('style', 'display:block;width:100%;text-align:left;padding:10px 14px;border:0;background:#fff;cursor:pointer;font-size:14px;');
          el.innerHTML = `<strong>${b.nama_bahan}</strong> <span style="color:#6b7280;font-size:12px;">${b.kelompok_gizi || ''}</span>`;
          el.addEventListener('mousedown', (e) => {
            e.preventDefault();
            tambahTag(b.nama_bahan, b.id);
            ingredientSearch.value = '';
            tutupSaran();
          });
          saranBox.appendChild(el);
        });
        saranBox.style.display = 'block';
      } catch {
        tutupSaran();
      }
    }, 300);
  });

  ingredientSearch.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') tutupSaran();
  });
  ingredientSearch.addEventListener('blur', () => setTimeout(tutupSaran, 150));

  ingredientSearch.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && ingredientSearch.value.trim()) {
      e.preventDefault();
      tambahTag(ingredientSearch.value.trim(), null);
      ingredientSearch.value = '';
      tutupSaran();
    }
  });

  // 2. Generate Recommendations (API real)
  const btnGenerate = document.getElementById('btn-generate');
  const recommendationsSection = document.getElementById('recommendations-section');

  async function resolveBahanId(item) {
    if (item.id) return item.id;
    if (!(window.NutriApi && window.NutriApi.getToken())) return null;
    try {
      const hasil = await window.NutriApi.bahanSearch(item.nama, 3);
      const items = Array.isArray(hasil) ? hasil : (hasil && hasil.data) || [];
      return items.length > 0 ? items[0].id : null;
    } catch {
      return null;
    }
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

  function tampilkanHasilReal(data) {
    const grid = recommendationsSection.querySelector('.menu-cards-grid');
    let info = '';
    if (data.defisiensi_terdeteksi && data.defisiensi_terdeteksi.length > 0) {
      info = `<p style="margin:0 0 12px;color:#4b5563;">Fokus perbaikan: ${data.defisiensi_terdeteksi.join(', ')}.</p>`;
    }
    if (data.peringatan) {
      info += `<p style="margin:0 0 12px;color:#92400e;background:#fef3c7;padding:10px 14px;border-radius:10px;">${data.peringatan}</p>`;
    }
    if (data.saran) {
      info += `<p style="margin:0 0 12px;color:#92400e;background:#fef3c7;padding:10px 14px;border-radius:10px;">${data.saran}</p>`;
    }
    if (data.pemeriksaan_pantangan === 'tidak_dilakukan') {
      info += `<p style="margin:0 0 12px;color:#4b5563;font-size:13px;">Data alergi/pantangan anak belum diisi, sehingga penyaringan alergen tidak dilakukan. Lengkapi dari dashboard bila ada alergi.</p>`;
    }
    if (data.jumlah_rekomendasi === 0 && !data.saran) {
      info += '<p>Tidak ada rekomendasi yang cocok. Coba tambah bahan yang berbeda.</p>';
    }
    const kartu = data.menu.map((m, idx) => `
      <div class="menu-card">
        <div class="menu-card-header">
          <h3 class="menu-title">Opsi ${idx + 1}</h3>
          <span class="menu-calories">${m.kecocokan_bahan ? `${m.kecocokan_bahan} cocok` : 'Rekomendasi umum'}</span>
        </div>
        <div class="menu-porsi-badge">
          Menghasilkan: ${m.jumlah_porsi_hasil || '-'} Porsi
        </div>
        ${m.perlu_modifikasi ? `<div style="margin:10px 0;padding:10px 12px;border-radius:10px;background:#fef3c7;color:#92400e;font-size:13px;"><strong>Perlu modifikasi:</strong> ${m.instruksi_modifikasi || m.peringatan_modifikasi || ''}</div>` : ''}
        <div class="meal-section">
          <div class="meal-info">
            <h4 class="meal-name">${m.nama_resep}</h4>
            <p style="color:#6b7280;font-size:13px;">${m.kategori_gizi || 'Menu bergizi'}</p>
            <p style="color:#6b7280;font-size:13px;">${m.alasan_peringkat || ''}</p>
            ${Array.isArray(m.alergen) && m.alergen.length ? `<p style="color:#92400e;font-size:12px;">Alergen: ${m.alergen.join(', ')}</p>` : ''}
            ${m.catatan_resep ? `<p style="color:#6b7280;font-size:12px;">${m.catatan_resep}</p>` : ''}
          </div>
        </div>
        <a href="#" class="btn-see-more" data-resep-id="${m.resep_id}">Lihat Resep →</a>
      </div>`).join('');
    grid.innerHTML = info + kartu;

    grid.querySelectorAll('.btn-see-more[data-resep-id]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        sessionStorage.setItem('selectedResepId', btn.getAttribute('data-resep-id'));
        sessionStorage.removeItem('selectedResep');
        window.location.href = '/resep-detail';
      });
    });

    recommendationsSection.style.display = 'block';
    recommendationsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  btnGenerate.addEventListener('click', async () => {
    const originalText = btnGenerate.innerHTML;
    btnGenerate.innerHTML = 'Sedang Membuat Rekomendasi...';
    btnGenerate.disabled = true;

    try {
      if (!(window.NutriApi && window.NutriApi.getToken())) {
        alert('Silakan login terlebih dahulu untuk rekomendasi personal.');
        window.location.href = '/login';
        return;
      }

      const ids = (await Promise.all(selectedIngredients.map(resolveBahanId))).filter(Boolean);
      const dilewati = selectedIngredients.length - ids.length;

      const anakId = await anakAktifId();
      if (!anakId) {
        alert('Belum ada data anak. Lengkapi pendaftaran anak terlebih dahulu.');
        btnGenerate.innerHTML = originalText;
        btnGenerate.disabled = false;
        return;
      }

      const hasil = await window.NutriApi.rekomendasi(anakId, {
        bahan_tersedia: ids,
        top_k: 5
      });

      if (dilewati > 0) {
        hasil.saran = (hasil.saran ? hasil.saran + ' ' : '')
          + `${dilewati} bahan tidak dikenal di katalog dan dilewati.`;
      }

      tampilkanHasilReal(hasil);
    } catch (err) {
      console.error('Rekomendasi API gagal:', err && err.message);
      alert((err && err.message) || 'Rekomendasi gagal dimuat. Periksa koneksi lalu coba lagi.');
    } finally {
      btnGenerate.innerHTML = originalText;
      btnGenerate.disabled = false;
    }
  });

  // 3. Navigation
  const navItems = document.querySelectorAll('.nav-item-ortu');
  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      if (item.getAttribute('href') === '#') {
        e.preventDefault();
        alert('Halaman ini akan segera hadir!');
      }
    });
  });

});