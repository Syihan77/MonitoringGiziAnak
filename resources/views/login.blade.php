<!DOCTYPE html>
<html lang="id">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="utf-8">
<meta name="description" content="Masuk ke akun NutriTumbuh">
<title>Masuk | NutriTumbuh</title>
    @vite(['resources/css/globals.css', 'resources/css/styleguide.css', 'resources/css/login.css'])
    @vite(['resources/js/api.js'])
</head>
<body>
<main class="login" aria-labelledby="page-title">
<div class="rectangle" aria-hidden="true"></div>
<section class="container" aria-label="Form masuk NutriTumbuh">
<img
        class="whatsapp-image"
        src="/img/NutrisiTumbuh-Logo.png"
        alt="Logo NutriTumbuh"
        width="449"
        height="170"
      >
<header class="desktop-welcome">
<div class="div">
<h1 class="text-wrapper" id="page-title">Selamat Datang</h1>
</div>
<div class="div">
<p class="p">Silakan masuk ke akun NutriTumbuh Anda.</p>
</div>
</header>
<form class="form" id="login-form" action="" method="post" novalidate>
<div class="email-field">
<div class="div">
<label class="label" for="input-1">Email atau No. Telepon</label>
</div>
<div class="div">
<div class="input">
<input
                class="container-2"
                placeholder="contoh@email.com"
                type="email"
                id="input-1"
                name="email"
                autocomplete="username"
                required
              >
</div>
<div class="icon-wrapper" aria-hidden="true">
<img class="icon" src="/img/Email.png" alt="" width="20" height="16">
</div>
</div>
</div>
<div class="password-field">
<div class="container-3">
<div class="div-wrapper">
<label class="text" for="password">Kata Sandi</label>
</div>
<div class="div-wrapper">
<a class="text-2" href="#forgot-password">Lupa sandi?</a>
</div>
</div>
<div class="div">
<div class="container-wrapper">
<div class="container-4">
<input
                  class="div-2"
                  placeholder="••••••••"
                  type="password"
                  id="password"
                  name="password"
                  autocomplete="current-password"
                  minlength="8"
                  required
                >
</div>
</div>
<div class="icon-wrapper" aria-hidden="true">
<img class="img" src="/img/Gembok.png" alt="" width="16" height="21">
</div>
<button
              class="button"
              type="button"
              aria-label="Tampilkan kata sandi"
              aria-controls="password"
              aria-pressed="false"
            >
<span class="img-wrapper" aria-hidden="true">
<img class="icon-2" src="/img/Eye.png" alt="" width="22" height="16">
</span>
</button>
</div>
</div>
<button class="submit-button-button" type="submit" id="btn-masuk">
<span class="text-3">Masuk</span>
</button>
<p id="login-error" style="display:none;color:#b91c1c;font-size:14px;margin-top:12px;" role="alert"></p>
</form>
<div class="divider" role="separator" aria-label="Pilihan lainnya">
<div class="horizontal-divider-wrapper">
<div class="horizontal-divider"></div>
</div>
<div class="background-wrapper">
<div class="background">
<span class="text-4">atau</span>
</div>
</div>
</div>
<footer class="register-link">
<p class="belum-memiliki-akun">
<span class="span">Belum memiliki akun? </span>
<a class="text-wrapper-2" href="/daftar">Daftar sekarang</a>
</p>
</footer>
</section>
</main>
<script>
const passwordInput = document.getElementById("password");
    const passwordToggle = document.querySelector(".button");

    passwordToggle.addEventListener("click", () => {
      const isVisible = passwordInput.type === "text";
      passwordInput.type = isVisible ? "password" : "text";
      passwordToggle.setAttribute("aria-pressed", String(!isVisible));
      passwordToggle.setAttribute(
        "aria-label",
        isVisible ? "Tampilkan kata sandi" : "Sembunyikan kata sandi"
      );
    });

    // Login via API Sanctum (Tahap D).
    const loginForm = document.getElementById("login-form");
    const loginError = document.getElementById("login-error");
    const btnMasuk = document.getElementById("btn-masuk");

    loginForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      loginError.style.display = "none";

      const email = document.getElementById("input-1").value.trim();
      const password = passwordInput.value;

      if (!email || !password) {
        loginError.textContent = "Email dan kata sandi wajib diisi.";
        loginError.style.display = "block";
        return;
      }

      const original = btnMasuk.innerHTML;
      btnMasuk.innerHTML = "Memeriksa...";
      btnMasuk.disabled = true;

      try {
        const data = await window.NutriApi.login(email, password);
        window.location.href = data.role === "kader" ? "/db-kader" : "/db-ortu";
      } catch (err) {
        if (err && err.error_code === "INVALID_CREDENTIALS") {
          loginError.textContent = "Email atau kata sandi salah.";
        } else if (err && err.error_code === "VALIDATION_ERROR") {
          loginError.textContent = "Periksa kembali format email dan kata sandi.";
        } else {
          loginError.textContent = (err && err.message) || "Login gagal. Coba lagi.";
        }
        loginError.style.display = "block";
        btnMasuk.innerHTML = original;
        btnMasuk.disabled = false;
      }
    });
  </script>
</body>
</html>
