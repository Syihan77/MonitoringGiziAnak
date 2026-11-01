(function () {
  const state = {
    activeTab: 'bahan',
    me: null,
    bahan: { page: 1, lastPage: 1, total: 0, q: '', items: [], loaded: false },
    resep: { page: 1, lastPage: 1, total: 0, q: '', items: [], loaded: false, statusAsli: null },
    bahanOptions: [],
  };

  const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[char]));
  }

  function valueOf(id) {
    const element = document.getElementById(id);
    return element ? element.value.trim() : '';
  }

  function numberOrNull(id) {
    const raw = valueOf(id);
    if (raw === '') return null;
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function integerOrNull(id) {
    const parsed = numberOrNull(id);
    return parsed === null ? null : Math.trunc(parsed);
  }

  function formatNumber(value, suffix) {
    if (value === null || value === undefined || value === '') return '-';
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return '-';
    return numberFormat.format(parsed) + (suffix || '');
  }

  function formatAge(min, max) {
    if (min === null && max === null) return '-';
    return `${min ?? '?'}-${max ?? '?'} bln`;
  }

  function formatKategori(value) {
    const label = {
      makanan_utama: 'Makanan Utama',
      camilan_dessert: 'Camilan / Dessert',
      minuman: 'Minuman',
      puree_buah_sayur: 'Puree Buah / Sayur',
    };

    return label[value] || value || 'umum';
  }

  function apiMessage(error) {
    const messages = [];
    if (error && error.errors && typeof error.errors === 'object') {
      Object.values(error.errors).forEach((group) => {
        const list = Array.isArray(group) ? group : [group];
        list.forEach((message) => {
          if (message && !messages.includes(String(message))) messages.push(String(message));
        });
      });
    }
    if (!messages.length) messages.push((error && error.message) || 'Terjadi kesalahan. Silakan coba lagi.');
    return messages.join('\n');
  }

  let noticeTimer = null;

  function showNotice(message, isError) {
    const element = document.getElementById('admin-alert');
    if (!element) return;
    window.clearTimeout(noticeTimer);
    element.textContent = message;
    element.classList.toggle('is-error', Boolean(isError));
    element.hidden = false;
    if (!isError) {
      noticeTimer = window.setTimeout(() => {
        element.hidden = true;
      }, 4500);
    }
  }

  function setFormError(id, message) {
    const element = document.getElementById(id);
    if (!element) return;
    element.textContent = message;
    element.hidden = false;
  }

  function clearFormError(id) {
    const element = document.getElementById(id);
    if (!element) return;
    element.textContent = '';
    element.hidden = true;
  }

  function loadingRow(columnCount, text) {
    return `<tr><td colspan="${columnCount}" class="empty-cell">${esc(text || 'Memuat data...')}</td></tr>`;
  }

  function setValue(id, value) {
    const element = document.getElementById(id);
    if (!element) return;
    element.value = value === null || value === undefined ? '' : value;
  }

  function renderBahan(items) {
    const tbody = document.getElementById('bahan-table-body');
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="empty-cell">Belum ada bahan makanan yang cocok.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map((item) => `
      <tr>
        <td class="cell-title">${esc(item.nama_bahan || '-')}</td>
        <td><span class="badge badge-category">${esc(item.kelompok_gizi || '-')}</span></td>
        <td class="cell-number">${formatNumber(item.energi_per100g, ' kkal')}</td>
        <td class="cell-number">${formatNumber(item.protein_per100g, ' g')}</td>
        <td class="cell-number">${formatNumber(item.lemak_per100g, ' g')}</td>
        <td class="cell-number">${formatNumber(item.karbohidrat_per100g, ' g')}</td>
        <td class="cell-number">${formatNumber(item.zat_besi_per100g, ' mg')}<br><span class="cell-muted">${formatNumber(item.zinc_per100g, ' mg')}</span></td>
        <td class="cell-muted">${esc(item.sumber_rujukan || '-')}</td>
        <td class="col-action">
          <div class="row-actions">
            <button type="button" class="btn-ghost" data-action="edit-bahan" data-id="${esc(item.id)}">Ubah</button>
            <button type="button" class="btn-danger" data-action="delete-bahan" data-id="${esc(item.id)}">Hapus</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  function renderResep(items) {
    const tbody = document.getElementById('resep-table-body');
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="empty-cell">Belum ada resep yang cocok.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map((item) => {
      const status = item.status_verifikasi_data || 'PERLU_VERIFIKASI';
      const statusLabel = status === 'DATA_LENGKAP'
        ? 'Data Lengkap'
        : (status === 'TERVERIFIKASI' ? 'Terverifikasi' : 'Perlu Verifikasi');
      const badgeClass = status === 'PERLU_VERIFIKASI' ? 'badge-pending' : 'badge-verified';
      return `
        <tr>
          <td class="cell-title">${esc(item.nama_resep || '-')}</td>
          <td><span class="badge badge-category">${esc(formatKategori(item.kategori_gizi))}</span></td>
          <td class="cell-number">${formatNumber(item.jumlah_porsi_hasil || item.porsi_saji)}</td>
          <td class="cell-number">${formatAge(item.usia_min_bulan, item.usia_maks_bulan)}</td>
          <td>${esc(item.tekstur_sajian || '-')}</td>
          <td class="cell-number">${formatNumber(item.jumlah_bahan)}</td>
          <td>
            <span class="badge ${badgeClass}">${statusLabel}</span>
          </td>
          <td class="col-action">
            <div class="row-actions">
              ${status === 'PERLU_VERIFIKASI' ? `<button type="button" class="btn-verify" data-action="verify-resep" data-id="${esc(item.id)}">Data Lengkap</button>` : ''}
              <button type="button" class="btn-ghost" data-action="edit-resep" data-id="${esc(item.id)}">Ubah</button>
              <button type="button" class="btn-danger" data-action="delete-resep" data-id="${esc(item.id)}">Hapus</button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  function renderPagination(kind, paginator) {
    const info = document.getElementById(`${kind}-page-info`);
    const container = document.getElementById(`${kind}-pagination`);
    if (!info || !container) return;

    const lastPage = Math.max(Number(paginator.last_page) || 1, 1);
    state[kind].page = Number(paginator.current_page) || 1;
    state[kind].lastPage = lastPage;
    state[kind].total = Number(paginator.total) || 0;

    container.hidden = lastPage <= 1;
    info.textContent = `Halaman ${state[kind].page} dari ${lastPage} • ${state[kind].total} data`;
    container.querySelector('[data-page-prev]').disabled = state[kind].page <= 1;
    container.querySelector('[data-page-next]').disabled = state[kind].page >= lastPage;
  }

  async function loadBahan() {
    const tbody = document.getElementById('bahan-table-body');
    if (!tbody) return;
    tbody.innerHTML = loadingRow(9);
    clearFormError('bahan-form-error');

    const params = new URLSearchParams({ page: String(state.bahan.page), limit: '10' });
    if (state.bahan.q) params.set('q', state.bahan.q);

    try {
      const paginator = await window.NutriApi.get(`/bahan-makanan?${params.toString()}`);
      const items = Array.isArray(paginator) ? paginator : (paginator && paginator.data) || [];
      state.bahan.items = items;
      state.bahan.loaded = true;
      renderBahan(items);
      renderPagination('bahan', paginator || { current_page: 1, last_page: 1, total: items.length });
    } catch (error) {
      state.bahan.items = [];
      tbody.innerHTML = `<tr><td colspan="9" class="empty-cell">Gagal memuat bahan: ${esc(apiMessage(error))}</td></tr>`;
      showNotice(apiMessage(error), true);
    }
  }

  async function loadResep() {
    const tbody = document.getElementById('resep-table-body');
    if (!tbody) return;
    tbody.innerHTML = loadingRow(8);

    const params = new URLSearchParams({ page: String(state.resep.page), limit: '10' });
    if (state.resep.q) params.set('q', state.resep.q);
    if (valueOf('resep-status-filter')) params.set('status_verifikasi_data', valueOf('resep-status-filter'));
    if (valueOf('resep-kategori-filter')) params.set('kategori_gizi', valueOf('resep-kategori-filter'));

    try {
      const paginator = await window.NutriApi.get(`/resep?${params.toString()}`);
      const items = Array.isArray(paginator) ? paginator : (paginator && paginator.data) || [];
      state.resep.items = items;
      state.resep.loaded = true;
      renderResep(items);
      renderPagination('resep', paginator || { current_page: 1, last_page: 1, total: items.length });
    } catch (error) {
      state.resep.items = [];
      tbody.innerHTML = `<tr><td colspan="8" class="empty-cell">Gagal memuat resep: ${esc(apiMessage(error))}</td></tr>`;
      showNotice(apiMessage(error), true);
    }
  }

  async function loadBahanOptions() {
    const all = [];
    let page = 1;
    let lastPage = 1;

    try {
      do {
        const paginator = await window.NutriApi.get(`/bahan-makanan?limit=100&page=${page}`);
        const items = Array.isArray(paginator) ? paginator : (paginator && paginator.data) || [];
        all.push(...items);
        lastPage = Number(paginator && paginator.last_page) || 1;
        page += 1;
      } while (page <= lastPage && page <= 10);

      state.bahanOptions = all.sort((a, b) => String(a.nama_bahan || '').localeCompare(String(b.nama_bahan || ''), 'id'));
    } catch (error) {
      showNotice(`Gagal memuat pilihan bahan: ${apiMessage(error)}`, true);
    }
  }

  function optionsForBahan(selectedId) {
    const selected = selectedId ? String(selectedId) : '';
    return state.bahanOptions.map((item) => (
      `<option value="${esc(item.bahan_id)}" ${String(item.bahan_id) === selected ? 'selected' : ''}>${esc(item.nama_bahan)}</option>`
    )).join('');
  }

  function openModal(id) {
    document.querySelectorAll('.modal-backdrop').forEach((element) => {
      if (element.id !== id) element.hidden = true;
    });
    const modal = document.getElementById(id);
    if (modal) {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
    }
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.hidden = true;
    if (!document.querySelector('.modal-backdrop:not([hidden])')) {
      document.body.style.overflow = '';
    }
  }

  function openBahanModal(item) {
    const form = document.getElementById('bahan-form');
    if (!form) return;
    form.reset();
    clearFormError('bahan-form-error');
    setValue('bahan-id', item ? item.id : '');
    setValue('bahan-nama', item ? item.nama_bahan : '');
    setValue('bahan-kelompok', item ? item.kelompok_gizi : '');
    setValue('bahan-energi', item ? item.energi_per100g : '');
    setValue('bahan-karbo', item ? item.karbohidrat_per100g : '');
    setValue('bahan-protein', item ? item.protein_per100g : '');
    setValue('bahan-lemak', item ? item.lemak_per100g : '');
    setValue('bahan-besi', item ? item.zat_besi_per100g : '');
    setValue('bahan-zinc', item ? item.zinc_per100g : '');
    setValue('bahan-sumber', item ? item.sumber_rujukan : '');
    document.getElementById('bahan-modal-title').textContent = item ? 'Ubah Bahan Makanan' : 'Tambah Bahan Makanan';
    openModal('bahan-modal');
    document.getElementById('bahan-nama').focus();
  }

  function bahanPayload() {
    return {
      nama_bahan: valueOf('bahan-nama'),
      kelompok_gizi: valueOf('bahan-kelompok'),
      energi_per100g: numberOrNull('bahan-energi'),
      karbohidrat_per100g: numberOrNull('bahan-karbo'),
      protein_per100g: numberOrNull('bahan-protein'),
      lemak_per100g: numberOrNull('bahan-lemak'),
      zat_besi_per100g: numberOrNull('bahan-besi'),
      zinc_per100g: numberOrNull('bahan-zinc'),
      sumber_rujukan: valueOf('bahan-sumber') || null,
    };
  }

  async function submitBahan(event) {
    event.preventDefault();
    clearFormError('bahan-form-error');

    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    const id = valueOf('bahan-id');
    const payload = bahanPayload();

    if (!payload.nama_bahan || !payload.kelompok_gizi) {
      setFormError('bahan-form-error', 'Nama bahan dan kelompok gizi wajib diisi.');
      return;
    }

    submit.disabled = true;
    try {
      if (id) {
        await window.NutriApi.put(`/bahan-makanan/${id}`, payload);
        showNotice('Bahan makanan berhasil diperbarui.');
      } else {
        await window.NutriApi.post('/bahan-makanan', payload);
        showNotice('Bahan makanan berhasil ditambahkan.');
      }
      closeModal('bahan-modal');
      await loadBahan();
      await loadBahanOptions();
    } catch (error) {
      setFormError('bahan-form-error', apiMessage(error));
    } finally {
      submit.disabled = false;
    }
  }

  function addBahanRow(item) {
    const container = document.getElementById('bahan-rows');
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'bahan-row';
    row.dataset.autoEq = item ? '0' : '1';

    const currentUnit = item && item.takaran_satuan ? String(item.takaran_satuan) : 'g';
    const units = ['g', 'gram', 'kg', 'ml', 'sdm', 'sdt', 'porsi', 'buah', 'butir'];
    if (!units.includes(currentUnit)) units.unshift(currentUnit);

    row.innerHTML = `
      <label>
        <span>Bahan</span>
        <select data-field="bahan_id" required>
          <option value="">Pilih bahan</option>
          ${optionsForBahan(item && item.bahan_id)}
        </select>
      </label>
      <label>
        <span>Jumlah</span>
        <input type="number" data-field="takaran_jumlah" min="0.1" max="5000" step="0.01" value="${esc(item && item.takaran_jumlah !== null && item.takaran_jumlah !== undefined ? item.takaran_jumlah : '')}" required>
      </label>
      <label>
        <span>Satuan</span>
        <select data-field="takaran_satuan">
          ${units.map((unit) => `<option value="${esc(unit)}" ${unit === currentUnit ? 'selected' : ''}>${esc(unit)}</option>`).join('')}
        </select>
      </label>
      <label>
        <span>Ekuivalen gram</span>
        <input type="number" data-field="takaran_gram_ekuivalen" min="0.1" max="5000" step="0.01" value="${esc(item && item.takaran_gram_ekuivalen !== null && item.takaran_gram_ekuivalen !== undefined ? item.takaran_gram_ekuivalen : '')}">
      </label>
      <label>
        <span>Catatan</span>
        <input type="text" data-field="catatan_bahan" maxlength="200" value="${esc(item && item.catatan_bahan ? item.catatan_bahan : '')}" placeholder="Opsional">
      </label>
      <button type="button" class="remove-row" data-action="remove-row" aria-label="Hapus bahan"><span aria-hidden="true">&times;</span></button>
    `;

    container.appendChild(row);

    const jumlah = row.querySelector('[data-field="takaran_jumlah"]');
    const satuan = row.querySelector('[data-field="takaran_satuan"]');
    const gram = row.querySelector('[data-field="takaran_gram_ekuivalen"]');

    const updateEquivalent = () => {
      if (row.dataset.autoEq !== '1') return;
      const amount = Number(jumlah.value);
      if (!Number.isFinite(amount) || amount <= 0) {
        gram.value = '';
        return;
      }
      const unit = satuan.value.toLowerCase();
      gram.value = unit === 'kg' ? (amount * 1000).toFixed(2) : amount.toFixed(2);
    };

    jumlah.addEventListener('input', updateEquivalent);
    satuan.addEventListener('change', updateEquivalent);
    gram.addEventListener('input', () => {
      row.dataset.autoEq = '0';
    });

    if (!item) updateEquivalent();
  }

  function clearBahanRows() {
    const container = document.getElementById('bahan-rows');
    if (container) container.innerHTML = '';
  }

  function setGiziFields(gizi) {
    const values = gizi || {};
    setValue('gizi-energi', values.energi);
    setValue('gizi-protein', values.protein);
    setValue('gizi-lemak', values.lemak);
    setValue('gizi-karbo', values.karbohidrat);
    setValue('gizi-serat', values.serat);
    setValue('gizi-kalsium', values.kalsium);
    setValue('gizi-besi', values.zat_besi);
    setValue('gizi-zinc', values.zinc);
    setValue('gizi-vita', values.vitamin_a);
    setValue('gizi-vitc', values.vitamin_c);
  }

  async function openResepModal(id) {
    const form = document.getElementById('resep-form');
    if (!form) return;
    form.reset();
    clearFormError('resep-form-error');
    setGiziFields(null);
    clearBahanRows();
    setValue('resep-id', id || '');
    setValue('resep-porsi', 1);
    setValue('resep-status', 'PERLU_VERIFIKASI');
    state.resep.statusAsli = null;
    document.getElementById('resep-modal-title').textContent = id ? 'Ubah Resep' : 'Tambah Resep';

    if (!id) {
      addBahanRow();
      openModal('resep-modal');
      document.getElementById('resep-nama').focus();
      return;
    }

    try {
      const detail = await window.NutriApi.resepDetail(id);
      setValue('resep-id', detail.resep_id);
      setValue('resep-nama', detail.nama_resep);
      setValue('resep-langkah', detail.cara_memasak);
      setValue('resep-kategori', detail.kategori_gizi || '');
      setValue('resep-porsi', detail.jumlah_porsi_hasil || detail.porsi_saji || 1);
      setValue('resep-kesulitan', detail.tingkat_kesulitan || '');
      setValue('resep-tekstur', detail.tekstur_sajian || '');
      setValue('resep-usia-min', detail.usia_min_bulan);
      setValue('resep-usia-maks', detail.usia_maks_bulan);
      setValue('resep-usia-modifikasi', detail.usia_minimal_bulan_jika_dimodifikasi);
      setValue('resep-jenis-menu', detail.jenis_menu || '');
      setValue('resep-keyakinan', detail.keyakinan_label || '');
      setValue('resep-modifikasi', detail.modifikasi_untuk_turunkan_usia || '');
      setValue('resep-alergen', Array.isArray(detail.alergen) ? detail.alergen.join(', ') : '');
      setValue('resep-catatan', detail.catatan_resep || '');
      setValue('resep-sumber', detail.sumber_resep || '');
      setValue('resep-status', detail.status_verifikasi_data || 'PERLU_VERIFIKASI');
      state.resep.statusAsli = detail.status_verifikasi_data || null;
      setGiziFields(detail.gizi_per_porsi);

      const ingredients = Array.isArray(detail.bahans) ? detail.bahans : [];
      if (ingredients.length) {
        ingredients.forEach((item) => addBahanRow(item));
      } else {
        addBahanRow();
      }

      openModal('resep-modal');
      document.getElementById('resep-nama').focus();
    } catch (error) {
      showNotice(apiMessage(error), true);
    }
  }

  function collectBahanRows() {
    const rows = Array.from(document.querySelectorAll('#bahan-rows .bahan-row'));
    if (!rows.length) throw new Error('Minimal satu bahan resep wajib diisi.');

    const result = [];
    for (const [index, row] of rows.entries()) {
      const bahanId = row.querySelector('[data-field="bahan_id"]').value;
      const jumlah = Number(row.querySelector('[data-field="takaran_jumlah"]').value);
      const satuan = row.querySelector('[data-field="takaran_satuan"]').value.trim() || 'g';
      const catatan = row.querySelector('[data-field="catatan_bahan"]').value.trim();
      const gramRaw = row.querySelector('[data-field="takaran_gram_ekuivalen"]').value.trim();

      if (!bahanId) throw new Error(`Bahan pada baris ${index + 1} belum dipilih.`);
      if (!Number.isFinite(jumlah) || jumlah <= 0) throw new Error(`Jumlah bahan pada baris ${index + 1} wajib lebih dari 0.`);

      let gram = gramRaw === '' ? null : Number(gramRaw);
      if (gramRaw !== '' && (!Number.isFinite(gram) || gram <= 0)) {
        throw new Error(`Ekuivalen gram pada baris ${index + 1} tidak valid.`);
      }
      if (gram === null && satuan.toLowerCase() === 'kg') {
        gram = jumlah * 1000;
      }

      result.push({
        bahan_id: bahanId,
        takaran_jumlah: jumlah,
        takaran_satuan: satuan,
        takaran_gram_ekuivalen: gram,
        catatan_bahan: catatan || null,
      });
    }

    return result;
  }

  function resepPayload() {
    const alergen = valueOf('resep-alergen')
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean);

    return {
      nama_resep: valueOf('resep-nama'),
      cara_memasak: valueOf('resep-langkah'),
      kategori_gizi: valueOf('resep-kategori') || null,
      jumlah_porsi_hasil: integerOrNull('resep-porsi'),
      tingkat_kesulitan: valueOf('resep-kesulitan') || null,
      energi_per_porsi: numberOrNull('gizi-energi'),
      protein_per_porsi: numberOrNull('gizi-protein'),
      lemak_per_porsi: numberOrNull('gizi-lemak'),
      karbohidrat_per_porsi: numberOrNull('gizi-karbo'),
      serat_per_porsi: numberOrNull('gizi-serat'),
      kalsium_per_porsi: numberOrNull('gizi-kalsium'),
      zat_besi_per_porsi: numberOrNull('gizi-besi'),
      zinc_per_porsi: numberOrNull('gizi-zinc'),
      vitamin_a_per_porsi: numberOrNull('gizi-vita'),
      vitamin_c_per_porsi: numberOrNull('gizi-vitc'),
      usia_min_bulan: integerOrNull('resep-usia-min'),
      usia_maks_bulan: integerOrNull('resep-usia-maks'),
      usia_minimal_bulan_jika_dimodifikasi: integerOrNull('resep-usia-modifikasi'),
      modifikasi_untuk_turunkan_usia: valueOf('resep-modifikasi') || null,
      keyakinan_label: valueOf('resep-keyakinan') || null,
      jenis_menu: valueOf('resep-jenis-menu') || null,
      alergen,
      catatan_resep: valueOf('resep-catatan') || null,
      tekstur_sajian: valueOf('resep-tekstur') || null,
      sumber_resep: valueOf('resep-sumber') || null,
      status_verifikasi_data: valueOf('resep-status') || state.resep.statusAsli || 'PERLU_VERIFIKASI',
      bahans: collectBahanRows(),
    };
  }

  async function submitResep(event) {
    event.preventDefault();
    clearFormError('resep-form-error');

    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    const id = valueOf('resep-id');
    let payload;

    try {
      payload = resepPayload();
    } catch (error) {
      setFormError('resep-form-error', error.message);
      return;
    }

    if (!payload.nama_resep || !payload.cara_memasak) {
      setFormError('resep-form-error', 'Nama resep dan cara memasak wajib diisi.');
      return;
    }

    submit.disabled = true;
    try {
      if (id) {
        await window.NutriApi.put(`/resep/${id}`, payload);
        showNotice('Resep berhasil diperbarui.');
      } else {
        await window.NutriApi.post('/resep', payload);
        showNotice('Resep berhasil ditambahkan.');
      }
      closeModal('resep-modal');
      await loadResep();
    } catch (error) {
      setFormError('resep-form-error', apiMessage(error));
    } finally {
      submit.disabled = false;
    }
  }

  async function deleteBahan(id) {
    const item = state.bahan.items.find((row) => String(row.id) === String(id));
    if (!window.confirm(`Hapus bahan "${item ? item.nama_bahan : ''}"?`)) return;

    try {
      await window.NutriApi.del(`/bahan-makanan/${id}`);
      showNotice('Bahan makanan dihapus.');
      await loadBahan();
      await loadBahanOptions();
    } catch (error) {
      showNotice(apiMessage(error), true);
    }
  }

  async function deleteResep(id) {
    const item = state.resep.items.find((row) => String(row.id) === String(id));
    if (!window.confirm(`Hapus resep "${item ? item.nama_resep : ''}"?`)) return;

    try {
      await window.NutriApi.del(`/resep/${id}`);
      showNotice('Resep dihapus.');
      await loadResep();
    } catch (error) {
      showNotice(apiMessage(error), true);
    }
  }

  async function verifyResep(id) {
    const item = state.resep.items.find((row) => String(row.id) === String(id));
    if (!window.confirm(`Tandai resep "${item ? item.nama_resep : ''}" sebagai data lengkap? Pastikan porsi, usia, alergen, gizi per porsi, dan bahan sudah lengkap.`)) return;

    try {
      await window.NutriApi.put(`/resep/${id}`, { status_verifikasi_data: 'DATA_LENGKAP' });
      showNotice('Resep berhasil ditandai data lengkap.');
      await loadResep();
    } catch (error) {
      showNotice(apiMessage(error), true);
    }
  }

  function switchTab(tab) {
    state.activeTab = tab;
    document.querySelectorAll('.admin-tab').forEach((button) => {
      const active = button.dataset.tab === tab;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    const bahanPanel = document.getElementById('panel-bahan');
    const resepPanel = document.getElementById('panel-resep');
    if (bahanPanel) bahanPanel.hidden = tab !== 'bahan';
    if (resepPanel) resepPanel.hidden = tab !== 'resep';

    const label = document.getElementById('btn-create-label');
    if (label) label.textContent = tab === 'bahan' ? 'Tambah Bahan' : 'Tambah Resep';

    if (!state[tab].loaded) {
      if (tab === 'bahan') loadBahan();
      else loadResep();
    }
  }

  function debounce(callback, delay) {
    let timer = null;
    return (...args) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => callback(...args), delay);
    };
  }

  function bindEvents() {
    document.querySelectorAll('.admin-tab').forEach((button) => {
      button.addEventListener('click', () => switchTab(button.dataset.tab));
    });

    document.getElementById('btn-create').addEventListener('click', () => {
      if (state.activeTab === 'bahan') openBahanModal(null);
      else openResepModal(null);
    });

    document.getElementById('bahan-form').addEventListener('submit', submitBahan);
    document.getElementById('resep-form').addEventListener('submit', submitResep);

    document.getElementById('btn-add-bahan-row').addEventListener('click', () => {
      if (!state.bahanOptions.length) {
        showNotice('Belum ada bahan referensi. Tambahkan bahan makanan terlebih dahulu.', true);
      }
      addBahanRow();
    });

    document.getElementById('bahan-rows').addEventListener('click', (event) => {
      const button = event.target.closest('[data-action="remove-row"]');
      if (!button) return;
      const row = button.closest('.bahan-row');
      const rows = document.querySelectorAll('#bahan-rows .bahan-row');
      if (rows.length <= 1) {
        setFormError('resep-form-error', 'Minimal satu bahan resep wajib diisi.');
        return;
      }
      if (row) row.remove();
    });

    document.getElementById('bahan-table-body').addEventListener('click', (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      if (button.dataset.action === 'edit-bahan') {
        openBahanModal(state.bahan.items.find((item) => String(item.id) === String(button.dataset.id)));
      } else if (button.dataset.action === 'delete-bahan') {
        deleteBahan(button.dataset.id);
      }
    });

    document.getElementById('resep-table-body').addEventListener('click', (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      if (button.dataset.action === 'edit-resep') {
        openResepModal(button.dataset.id);
      } else if (button.dataset.action === 'delete-resep') {
        deleteResep(button.dataset.id);
      } else if (button.dataset.action === 'verify-resep') {
        verifyResep(button.dataset.id);
      }
    });

    document.getElementById('bahan-search').addEventListener('input', debounce((event) => {
      state.bahan.q = event.target.value.trim();
      state.bahan.page = 1;
      loadBahan();
    }, 320));

    document.getElementById('resep-search').addEventListener('input', debounce((event) => {
      state.resep.q = event.target.value.trim();
      state.resep.page = 1;
      loadResep();
    }, 320));

    document.getElementById('resep-status-filter').addEventListener('change', () => {
      state.resep.page = 1;
      loadResep();
    });

    document.getElementById('resep-kategori-filter').addEventListener('change', () => {
      state.resep.page = 1;
      loadResep();
    });

    document.getElementById('bahan-refresh').addEventListener('click', loadBahan);
    document.getElementById('resep-refresh').addEventListener('click', loadResep);

    document.querySelectorAll('.pagination [data-page-prev], .pagination [data-page-next]').forEach((button) => {
      button.addEventListener('click', () => {
        const kind = button.dataset.pagePrev || button.dataset.pageNext;
        const direction = button.dataset.pagePrev ? -1 : 1;
        state[kind].page = Math.min(Math.max(state[kind].page + direction, 1), state[kind].lastPage);
        if (kind === 'bahan') loadBahan();
        else loadResep();
      });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
      button.addEventListener('click', () => closeModal(button.dataset.closeModal));
    });

    document.querySelectorAll('.modal-backdrop').forEach((modal) => {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal(modal.id);
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      document.querySelectorAll('.modal-backdrop:not([hidden])').forEach((modal) => closeModal(modal.id));
    });
  }

  async function boot() {
    if (!window.NutriApi || !window.NutriApi.getToken()) {
      window.location.href = '/login';
      return;
    }

    try {
      const me = await window.NutriApi.me();
      if (me.role !== 'admin') {
        window.location.href = me.role === 'kader' ? '/db-kader' : '/db-ortu';
        return;
      }
      state.me = me;
      document.getElementById('admin-name').textContent = me.nama || me.email || 'Admin';
    } catch (error) {
      showNotice(apiMessage(error), true);
      return;
    }

    bindEvents();
    await loadBahanOptions();
    await Promise.all([loadBahan(), loadResep()]);
  }

  document.addEventListener('DOMContentLoaded', boot);
})();
