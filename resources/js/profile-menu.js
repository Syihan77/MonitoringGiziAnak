(function () {
  const ROLE_LABEL = {
    orang_tua: 'Orang Tua',
    kader: 'Kader Posyandu',
    admin: 'Admin',
  };

  function inisial(nama) {
    if (!nama) return '?';
    return nama.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
  }

  function buatPopover(id) {
    const popover = document.createElement('div');
    popover.className = 'profile-popover';
    popover.id = id;
    popover.hidden = true;
    popover.setAttribute('role', 'dialog');
    popover.setAttribute('aria-label', 'Informasi profil akun');
    popover.innerHTML = '<div class="profile-popover-head">'
      + '<span class="avatar-initial avatar-sm profile-popover-avatar" data-profile-avatar>?</span>'
      + '<div class="profile-popover-identity">'
      + '<strong data-profile-name>Memuat profil...</strong>'
      + '<span data-profile-role>Harap tunggu</span>'
      + '</div>'
      + '</div>'
      + '<dl class="profile-popover-list">'
      + '<div><dt>Email</dt><dd data-profile-email>-</dd></div>'
      + '<div><dt>No. Telepon</dt><dd data-profile-phone>-</dd></div>'
      + '<div><dt>Wilayah Posyandu</dt><dd data-profile-wilayah>-</dd></div>'
      + '</dl>';
    return popover;
  }

  function isiProfil(popover, data) {
    const nama = data && data.nama ? data.nama : '-';
    const peran = data && data.role ? (ROLE_LABEL[data.role] || data.role) : '-';

    const setText = (selector, value) => {
      const element = popover.querySelector(selector);
      if (element) element.textContent = value || '-';
    };

    setText('[data-profile-avatar]', inisial(nama));
    setText('[data-profile-name]', nama);
    setText('[data-profile-role]', peran);
    setText('[data-profile-email]', data && data.email);
    setText('[data-profile-phone]', data && data.no_telp);
    setText('[data-profile-wilayah]', data && data.wilayah_posyandu);
  }

  function tampilkanError(popover, error) {
    const pesan = (error && error.message) || 'Gagal memuat profil.';
    const nama = popover.querySelector('[data-profile-name]');
    const peran = popover.querySelector('[data-profile-role]');
    if (nama) nama.textContent = 'Profil tidak dapat dimuat';
    if (peran) peran.textContent = pesan;
  }

  function tutupPopover(popover, trigger, kembalikanFokus) {
    if (popover.hidden) return;
    popover.hidden = true;
    if (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
      if (kembalikanFokus) trigger.focus();
    }
  }

  function bindTrigger(trigger, index) {
    const container = trigger.closest('.profile-account') || trigger.parentElement;
    if (!container) return;

    const popover = buatPopover(`profile-popover-${index}`);
    container.appendChild(popover);

    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', popover.id);

    let selesai = false;
    let memuat = false;

    const buka = async () => {
      document.querySelectorAll('.profile-popover').forEach((other) => {
        if (other === popover) return;
        other.hidden = true;
        const otherTrigger = document.querySelector(`[aria-controls="${other.id}"]`);
        if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
      });

      popover.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');

      if (selesai || memuat) return;

      if (!window.NutriApi || typeof window.NutriApi.me !== 'function') {
        tampilkanError(popover, { message: 'API profil tidak tersedia.' });
        return;
      }

      memuat = true;
      try {
        const data = await window.NutriApi.me();
        isiProfil(popover, data);
        selesai = true;
      } catch (error) {
        tampilkanError(popover, error);
        selesai = false;
      } finally {
        memuat = false;
      }
    };

    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (popover.hidden) buka();
      else tutupPopover(popover, trigger, false);
    });

    popover.addEventListener('click', (event) => event.stopPropagation());
  }

  function init() {
    document.querySelectorAll('[data-profile-trigger]').forEach((trigger, index) => {
      bindTrigger(trigger, index);
    });

    document.addEventListener('click', () => {
      document.querySelectorAll('.profile-popover').forEach((popover) => {
        if (popover.hidden) return;
        const trigger = document.querySelector(`[aria-controls="${popover.id}"]`);
        tutupPopover(popover, trigger, false);
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      const popover = Array.from(document.querySelectorAll('.profile-popover'))
        .find((element) => !element.hidden);
      if (!popover) return;
      const trigger = document.querySelector(`[aria-controls="${popover.id}"]`);
      tutupPopover(popover, trigger, true);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
