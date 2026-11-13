// Giriş sayfası: e-posta ve parolayı sunucuya gönderir, başarılıysa kullanıcıyı geldiği sayfaya (veya Hesabım'a) yönlendirir.
document.addEventListener('DOMContentLoaded', async () => {
  // Zaten giriş yaptıysa giriş sayfasını gösterme
  if (await currentUser()) { location.href = safeNext('hesabim.html'); return; }
  // Kayıttan yeni geldiyse başarı mesajı göster
  if (new URLSearchParams(location.search).get('kayit') === '1') {
    document.getElementById('form-msg').innerHTML = '<p class="success-box">Kayıt başarılı! Şimdi giriş yapabilirsiniz.</p>';
  }

  const form = document.getElementById('login-form');
  form.addEventListener('submit', async e => {
    // Sayfanın yenilenmesini engelle, formu JavaScript ile gönder
    e.preventDefault();
    const btn = form.querySelector('button');
    const msg = document.getElementById('form-msg');
    // Butonu kilitle (iki kez basılmasın)
    btn.disabled = true; btn.textContent = 'Giriş yapılıyor…'; msg.innerHTML = '';
    try {
      // E-posta ve parolayı gönder; başarılıysa yönlendir
      await apiPost('/auth/login', Object.fromEntries(new FormData(form)));
      location.href = safeNext('hesabim.html');
    } catch (err) {
      // Hatalıysa mesajı göster ve parola alanını temizle
      msg.innerHTML = `<p class="error-box">${esc(err.message)}</p>`;
      form.elements.password.value = '';
      form.elements.password.focus();
    } finally {
      btn.disabled = false; btn.textContent = 'Giriş Yap';
    }
  });
});
