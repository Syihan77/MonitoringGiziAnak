/**
 * NutriTumbuh API client — dinas backend Laravel /api/v1 (envelope SKPL §5.4.1).
 *
 * Sukses: { success: true, data, message }
 * Galat:  { success: false, error_code, message, errors? }
 *
 * Diekspos sebagai window.NutriApi agar bisa dipakai skrip inline Blade.
 */
(function () {
  const BASE = '/api/v1';
  const K_TOKEN = 'nutrisi_token';
  const K_ROLE = 'nutrisi_role';
  const K_USER_ID = 'nutrisi_user_id';

  class ApiError extends Error {
    constructor(errorCode, message, status, errors) {
      super(message || errorCode);
      this.name = 'ApiError';
      this.error_code = errorCode;
      this.status = status;
      this.errors = errors || null;
    }
  }

  function getToken() {
    return localStorage.getItem(K_TOKEN);
  }

  function setSession({ token, role, user_id }) {
    if (token) localStorage.setItem(K_TOKEN, token);
    if (role) localStorage.setItem(K_ROLE, role);
    if (user_id) localStorage.setItem(K_USER_ID, user_id);
  }

  function clearSession() {
    localStorage.removeItem(K_TOKEN);
    localStorage.removeItem(K_ROLE);
    localStorage.removeItem(K_USER_ID);
  }

  function requireAuth() {
    if (!getToken()) {
      window.location.href = '/login';
      throw new ApiError('UNAUTHENTICATED', 'Silakan login terlebih dahulu.', 401);
    }
    return getToken();
  }

  async function request(method, path, body) {
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
    const token = getToken();
    if (token) headers.Authorization = `Bearer ${token}`;

    const res = await fetch(BASE + path, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });

    let json = null;
    try {
      json = await res.json();
    } catch {
      throw new ApiError('NETWORK_ERROR', `Respons server tidak valid (HTTP ${res.status}).`, res.status);
    }

    if (json && json.success === true) {
      return json.data;
    }

    const code = (json && json.error_code) || 'UNKNOWN_ERROR';
    const message = (json && json.message) || `Terjadi kesalahan (HTTP ${res.status}).`;

    // Sesi kedaluwarsa / tidak sah -> buang token, arahkan ke login.
    if ((code === 'TOKEN_EXPIRED' || code === 'UNAUTHENTICATED') && token) {
      clearSession();
      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = '/login';
      }
    }

    throw new ApiError(code, message, res.status, json && json.errors);
  }

  const get = (path) => request('GET', path);
  const post = (path, body) => request('POST', path, body);
  const put = (path, body) => request('PUT', path, body);
  const del = (path) => request('DELETE', path);

  window.NutriApi = {
    ApiError,
    getToken,
    setSession,
    clearSession,
    requireAuth,
    getRole: () => localStorage.getItem(K_ROLE),
    getUserId: () => localStorage.getItem(K_USER_ID),
    request,
    get,
    post,
    put,
    del,
    register: (payload) => post('/auth/register', payload),
    login: async (email, password) => {
      const data = await post('/auth/login', { email, password });
      setSession(data);
      return data;
    },
    logout: async () => {
      try {
        await post('/auth/logout', {});
      } finally {
        clearSession();
        try {
          sessionStorage.clear();
        } catch {
          // abaikan: privasi sebisa mungkin
        }
      }
    },
    me: () => get('/me'),
    anakList: () => get('/anak'),
    anakCreate: (payload) => post('/anak', payload),
    anakUpdate: (id, payload) => put(`/anak/${id}`, payload),
    anakDetail: (id) => get(`/anak/${id}`),
    ukurCreate: (anakId, payload) => post(`/anak/${anakId}/pertumbuhan`, payload),
    ukurRiwayat: (anakId, params) => {
      const q = params ? `?${new URLSearchParams(params).toString()}` : '';
      return get(`/anak/${anakId}/pertumbuhan${q}`);
    },
    konsumsiList: (anakId, tanggal) => get(`/anak/${anakId}/konsumsi?tanggal=${tanggal}`),
    konsumsiRentang: (anakId, dari, sampai) => get(`/anak/${anakId}/konsumsi?dari_tanggal=${dari}&sampai_tanggal=${sampai}`),
    bahanSearch: (q, limit) => get(`/bahan-makanan?q=${encodeURIComponent(q)}&limit=${limit || 10}`),
    rekomendasi: (anakId, payload) => post(`/anak/${anakId}/rekomendasi-menu`, payload),
    resepDetail: (id) => get(`/resep/${id}`),
    bindLogoutButtons,
  };

  /**
   * Popup konfirmasi logout + auto-bind ke semua [data-logout].
   * Dipanggil otomatis saat DOM siap; aman dipanggil ulang.
   */
  function bindLogoutButtons() {
    if (bindLogoutButtons._done) return;
    bindLogoutButtons._done = true;

    const css = '.btn-logout{display:inline-flex;align-items:center;justify-content:center;'
      + 'width:40px;height:40px;border-radius:50%;border:1px solid #e5e7eb;background:#fff;'
      + 'color:#6b7280;cursor:pointer;font-size:18px;flex:none;}'
      + '.btn-logout:hover{color:#dc2626;border-color:#dc2626;background:#fef2f2;}'
      + '.nl-overlay{position:fixed;inset:0;background:rgba(17,24,39,.5);display:flex;'
      + 'align-items:center;justify-content:center;padding:20px;z-index:9999;}'
      + '.nl-card{background:#fff;border-radius:16px;max-width:360px;width:100%;padding:24px;text-align:center;'
      + 'box-shadow:0 20px 60px rgba(0,0,0,.25);}'
      + '.nl-card h3{margin:0 0 8px;font-size:18px;color:#111827;}'
      + '.nl-card p{margin:0 0 20px;font-size:14px;color:#6b7280;line-height:1.5;}'
      + '.nl-actions{display:flex;gap:10px;}'
      + '.nl-btn{flex:1;border-radius:10px;padding:12px;font-size:14px;font-weight:700;cursor:pointer;border:1px solid #e5e7eb;}'
      + '.nl-batal{background:#fff;color:#374151;}'
      + '.nl-ya{background:#dc2626;border-color:#dc2626;color:#fff;}'
      + '.nl-ya:disabled{opacity:.6;cursor:default;}';
    const style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-logout]');
      if (!btn) return;
      e.preventDefault();
      tampilPopupLogout();
    });

    function tampilPopupLogout() {
      tutupPopupLogout();

      const overlay = document.createElement('div');
      overlay.className = 'nl-overlay';
      overlay.id = 'nl-overlay';
      overlay.innerHTML = '<div class="nl-card" role="alertdialog" aria-modal="true" aria-label="Konfirmasi keluar">'
        + '<h3>Keluar dari akun?</h3>'
        + '<p>Sesi berakhir dan data sementara di perangkat ini ikut dibersihkan.</p>'
        + '<div class="nl-actions">'
        + '<button type="button" class="nl-btn nl-batal">Batal</button>'
        + '<button type="button" class="nl-btn nl-ya">Ya, Keluar</button>'
        + '</div></div>';
      document.body.appendChild(overlay);

      const btnBatal = overlay.querySelector('.nl-batal');
      const btnYa = overlay.querySelector('.nl-ya');
      btnBatal.focus();

      const onKey = (ev) => {
        if (ev.key === 'Escape') tutupPopupLogout();
      };
      document.addEventListener('keydown', onKey, { once: false });
      overlay._onKey = onKey;

      overlay.addEventListener('mousedown', (ev) => {
        if (ev.target === overlay) tutupPopupLogout();
      });
      btnBatal.addEventListener('click', tutupPopupLogout);
      btnYa.addEventListener('click', async () => {
        btnYa.disabled = true;
        btnYa.textContent = 'Keluar...';
        try {
          await window.NutriApi.logout();
        } finally {
          window.location.href = '/login';
        }
      });
    }

    function tutupPopupLogout() {
      const overlay = document.getElementById('nl-overlay');
      if (!overlay) return;
      if (overlay._onKey) document.removeEventListener('keydown', overlay._onKey);
      overlay.remove();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindLogoutButtons);
  } else {
    bindLogoutButtons();
  }
})();
