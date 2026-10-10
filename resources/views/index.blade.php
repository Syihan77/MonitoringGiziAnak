<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Beranda - NutriTumbuh</title>
    
    <!-- Memanggil CSS Global -->
    @vite(['resources/css/globals.css', 'resources/css/styleguide.css'])
  </head>
  <body style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background-color: #e8f5e9; font-family: sans-serif; margin: 0;">
    
    <div style="text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: calc(100vw - 32px); box-sizing: border-box;">
        <h1 style="color: #181d18; font-size: 24px; margin-bottom: 8px;">Selamat Datang di NutriTumbuh</h1>
        <p style="color: #3f4940; margin-bottom: 24px;">Platform monitoring gizi anak Anda.</p>
        
        <!-- Ini adalah Link yang akan membuka halaman Login -->
        <a href="/login" style="background-color: #006a38; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">Mulai & Masuk</a>
    </div>

  </body>
</html>
