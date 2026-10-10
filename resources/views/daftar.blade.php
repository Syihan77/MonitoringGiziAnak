<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Formulir pendaftaran akun NutriTumbuh">
  <title>Daftar Akun NutriTumbuh</title>
    @vite(['resources/css/globals.css', 'resources/css/styleguide.css', 'resources/css/daftar.css'])
</head>
<body>
  <main class="daftar">
    <section class="form-daftar" aria-labelledby="welcome-title">
      <div class="container">
        <img class="whatsapp-image" src="/img/NutrisiTumbuh-Logo.png" alt="Logo NutriTumbuh">
        
        <header class="desktop-welcome">
          <div class="div">
            <h1 class="text-wrapper" id="welcome-title">Selamat Datang</h1>
          </div>
          <div class="div">
            <p class="silakan-daftar-untuk">
              Silakan daftar untuk masuk&nbsp;&nbsp;ke akun NutriTumbuh Anda.
            </p>
          </div>
        </header>

        <!-- Ditambahkan ID untuk menangkap event submit -->
        <form class="form" id="registration-form" action="#" method="post">
          
          <!-- Input tersembunyi untuk menyimpan role yang dipilih -->
          <input type="hidden" id="selected-role" name="role" required>

          <div class="email-field">
            <div class="div">
              <label class="label" for="input-1">Email atau No. Telepon</label>
            </div>
            <div class="div">
              <div class="input">
                <input class="container-2" placeholder="contoh@email.com" type="email" id="input-1" name="email" autocomplete="email" required aria-required="true">
              </div>
              <div class="icon-wrapper" aria-hidden="true">
                <img class="icon" src="/img/Email.png" alt="">
              </div>
            </div>
          </div>

          <div class="password-field">
            <div class="label-wrapper">
              <div class="div-wrapper">
                <label class="text-wrapper-2" for="password-input">Kata Sandi</label>
              </div>
            </div>
            <div class="container-3">
              <div class="container-wrapper">
                <div class="container-4">
                  <input class="text-wrapper-3" id="password-input" name="password" type="password" placeholder="buat kata sandi" autocomplete="new-password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" title="Minimal 8 karakter dengan kombinasi huruf dan angka" required aria-required="true" aria-describedby="password-hint">
                </div>
              </div>
              <div class="icon-wrapper" aria-hidden="true">
                <img class="img" src="/img/Gembok.png" alt="">
              </div>
              <button class="button" type="button" aria-label="Tampilkan kata sandi" aria-controls="password-input" aria-pressed="false">
                <span class="img-wrapper" aria-hidden="true">
                  <img class="icon-2" src="/img/Eye.png" alt="">
                </span>
              </button>
            </div>
            <p id="password-hint" style="font-size:12px;color:#6b7280;margin:6px 0 0;">Minimal 8 karakter dengan kombinasi huruf dan angka.</p>
            <p id="password-error" style="display:none;font-size:13px;color:#b91c1c;margin:6px 0 0;" role="alert"></p>
          </div>

          <fieldset class="container-5">
            <legend class="label-2">
              <span class="text-wrapper-2">Pemilihan Role</span>
            </legend>
            <div class="role-options">
              <button class="orang-tua-option" type="button" name="role_btn" value="orang-tua" aria-pressed="false">
                <span class="text-wrapper-4">Orang Tua</span>
              </button>
              <button class="posyandu-option" type="button" name="role_btn" value="posyandu" aria-pressed="false">
                <span class="text-wrapper-5">Posyandu</span>
              </button>
            </div>
          </fieldset>

          <button class="submit-button-button" type="submit">
            <span class="text-wrapper-6">Daftar</span>
          </button>

          <!-- BAGIAN INI DIKEMBALIKAN PERSIS SEPERTI ASLI (Termasuk semua <br> dan <span>) -->
          <label class="label-3" for="terms">
            <input class="input-2" id="terms" name="terms" type="checkbox" required aria-required="true">
            <span class="saya-telah-membaca-wrapper">
              <span class="saya-telah-membaca">
                <span class="span">Saya telah membaca dan menyetujui [</span>
                <a class="text-wrapper-7" href="/kebijakan">
                  Kebijakan Privasi &amp;<br>Syarat Ketentuan
                </a>
                <span class="span">
                  ] yang berlaku. Saya memahami bahwa data <br>
                  di dalam sistem ini digunakan secara khusus untuk keperluan <br>
                  pemantauan gizi anak dan kerahasiaannya akan dijaga.
                </span>
              </span>
            </span>
          </label>
        </form>
      </div>
    </section>
  </main>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      // 1. Logika Toggle Password
      const passwordInput = document.getElementById("password-input");
      const toggleButton = document.querySelector(".button");

      if (toggleButton && passwordInput) {
        toggleButton.addEventListener("click", () => {
          const isPassword = passwordInput.type === "password";
          passwordInput.type = isPassword ? "text" : "password";
          toggleButton.setAttribute("aria-pressed", String(isPassword));
          toggleButton.setAttribute("aria-label", isPassword ? "Sembunyikan kata sandi" : "Tampilkan kata sandi");
        });
      }

      // 2. Logika Pemilihan Role
      const roleButtons = document.querySelectorAll(".orang-tua-option, .posyandu-option");
      const hiddenRoleInput = document.getElementById("selected-role");

      roleButtons.forEach((roleButton) => {
        roleButton.addEventListener("click", () => {
          roleButtons.forEach((button) => {
            button.setAttribute("aria-pressed", "false");
          });
          roleButton.setAttribute("aria-pressed", "true");
          hiddenRoleInput.value = roleButton.value; // Simpan nilai role
        });
      });

      // 3. Logika Submit Form & Redirect
      const form = document.getElementById("registration-form");

      if (form) {
        form.addEventListener("submit", (e) => {
          e.preventDefault(); // Mencegah reload halaman

          const selectedRole = hiddenRoleInput.value;
          const termsCheckbox = document.getElementById("terms");

          if (!selectedRole) {
            alert("Silakan pilih role (Orang Tua atau Posyandu) terlebih dahulu.");
            return;
          }

          if (!termsCheckbox.checked) {
            alert("Anda harus menyetujui Kebijakan Privasi & Syarat Ketentuan.");
            return;
          }

          // Aturan kekuatan password — SAMA dengan backend (RegisterRequest):
          // minimal 8 karakter dengan kombinasi huruf dan angka.
          const passwordError = document.getElementById("password-error");
          const passwordValid = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/.test(passwordInput.value);
          if (!passwordValid) {
            passwordError.textContent = "Kata sandi minimal 8 karakter dengan kombinasi huruf dan angka.";
            passwordError.style.display = "block";
            passwordInput.focus();
            return;
          }
          passwordError.style.display = "none";

          // Simpan kredensial sementara utk registrasi API di langkah akhir.
          // Password hanya transit di sessionStorage tab ini, dibersihkan seusai simpan.
          const email = document.getElementById("input-1").value.trim();
          const password = passwordInput.value;
          const roleApi = selectedRole === "posyandu" ? "kader" : "orang_tua";
          sessionStorage.setItem("regAkun", JSON.stringify({
            email, password, role: roleApi, consent_approved: termsCheckbox.checked,
          }));

          // Redirect berdasarkan role yang dipilih
          if (selectedRole === "orang-tua") {
            window.location.href = "/data-ortu";
          } else if (selectedRole === "posyandu") {
            window.location.href = "/data-kader";
          }
        });
      }
    });
  </script>
</body>
</html>
