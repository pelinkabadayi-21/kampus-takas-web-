// Gelen kutusu + sohbet. Yeni mesajlar 8 saniyede bir "after" parametresiyle çekilir (sadece yeniler gelir).
document.addEventListener('DOMContentLoaded', async () => {
  let me;
  try { me = await requireLogin(); } catch (e) { return; }

  const threadsEl = document.getElementById('threads');
  const threadEl = document.getElementById('thread');
  const chat = document.getElementById('chat');
  // Açık olan konuşma ve yeni mesajları kontrol eden zamanlayıcı
  let current = null;      // { listingId, otherId, lastId }
  let threads = [];
  let poll;

  // Konuşma listesini sunucudan alır.
  async function loadInbox() {
    try {
      // Gelen kutusunu (konuşma listesini) getir
      threads = (await apiGet('/messages')).data;
      drawInbox();
    } catch (e) { threadsEl.innerHTML = `<p class="error-box">${esc(e.message)}</p>`; }
  }

  // Konuşma listesini çizer (son mesaj, okunmamış sayısı).
  function drawInbox() {
    threadsEl.innerHTML = threads.map(t => `
      <a class="thread-item ${current && current.listingId == t.listing_id && current.otherId == t.other_id ? 'active' : ''} ${t.unread > 0 ? 'unread' : ''}"
         href="?ilan=${t.listing_id}&kisi=${t.other_id}" data-l="${t.listing_id}" data-u="${t.other_id}">
        <div class="thread-thumb">${t.cover_image ? `<img src="${esc(imgUrl(t.cover_image))}" alt="">` : '📦'}</div>
        <div class="thread-text">
          <div class="thread-top"><b>${esc(t.other_name)}</b><small class="muted">${timeAgo(t.last_at)}</small></div>
          <div class="thread-listing">${esc(t.listing_title)}${t.listing_owner_id == me.id ? ' <small class="muted">(ilanınız)</small>' : ''}</div>
          <div class="thread-last">${t.last_sender_id == me.id ? 'Siz: ' : ''}${esc(t.last_body)}</div>
        </div>
        ${t.unread > 0 ? `<span class="nav-count">${t.unread}</span>` : ''}
      </a>`).join('') || '<p class="muted" style="padding:12px">Henüz mesajınız yok.</p>';
  }

  threadsEl.addEventListener('click', e => {
    // Bir konuşmaya tıklanınca sayfayı yenilemeden aç
    const a = e.target.closest('.thread-item'); if (!a) return;
    e.preventDefault();
    history.pushState(null, '', a.getAttribute('href'));
    open(+a.dataset.l, +a.dataset.u);
  });

  // Seçilen konuşmayı açar: mesajları gösterir, mesaj yazma kutusunu bağlar, 8 sn'de bir yeni mesaj kontrolü başlatır.
  async function open(listingId, otherId) {
    // Önceki konuşmanın zamanlayıcısını durdur
    clearInterval(poll);
    current = { listingId, otherId, lastId: 0 };
    chat.classList.add('show-thread');            // mobilde sohbet panelini göster
    threadEl.innerHTML = '<p class="muted" style="padding:20px">Yükleniyor…</p>';
    let r;
    // Konuşmanın mesajlarını ve ilan bilgisini getir
    try { r = await apiGet(`/messages/${listingId}/${otherId}`); }
    catch (e) { threadEl.innerHTML = `<p class="error-box" style="margin:16px">${esc(e.message)}</p>`; return; }

    const l = r.listing;
    const t = threads.find(x => x.listing_id == listingId && x.other_id == otherId);
    // Karşı tarafın adı
    const otherName = t ? t.other_name : (l.user_id == otherId ? 'İlan sahibi' : 'Kullanıcı');
    threadEl.innerHTML = `
      <header class="thread-head">
        <button class="btn-back" id="back" aria-label="Geri">←</button>
        <a class="thread-thumb" href="ilan.html?id=${l.id}">${l.cover_image ? `<img src="${esc(imgUrl(l.cover_image))}" alt="">` : '📦'}</a>
        <div><b>${esc(otherName)}</b><br><a href="ilan.html?id=${l.id}" class="muted">${esc(l.title)}</a>
          ${l.status !== 'aktif' ? ` <span class="badge badge-status-${l.status}">${STATUS_LABEL[l.status]}</span>` : ''}</div>
      </header>
      <div class="messages" id="messages"></div>
      <form class="composer" id="composer">
        <textarea name="body" rows="1" maxlength="2000" placeholder="Mesajınızı yazın…" required></textarea>
        <button class="btn" type="submit">Gönder</button>
      </form>
      <small class="composer-error" id="send-error"></small>`;
    document.getElementById('back').addEventListener('click', () => chat.classList.remove('show-thread'));
    // Mesajları ekrana yaz
    appendMessages(r.messages);
    if (!r.messages.length) document.getElementById('messages').innerHTML = '<p class="muted center">Henüz mesaj yok. İlk mesajı siz yazın 👋</p>';

    const form = document.getElementById('composer');
    const ta = form.elements.body;
    // Enter gönderir, Shift+Enter yeni satır
    ta.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    ta.addEventListener('input', () => { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 140) + 'px'; });
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const body = ta.value.trim(); if (!body) return;
      const err = document.getElementById('send-error'); err.textContent = '';
      form.querySelector('button').disabled = true;
      try {
        // Mesajı gönder, kutuyu temizle, yeni mesajları ve gelen kutusunu yenile
        await apiPost('/messages', { listing_id: listingId, receiver_id: otherId, body });
        ta.value = ''; ta.style.height = 'auto';
        await fetchNew(); loadInbox();
      } catch (ex) { err.textContent = ex.message; }
      finally { form.querySelector('button').disabled = false; ta.focus(); }
    });
    ta.focus();
    drawInbox(); refreshCounts();
    // Her 8 saniyede yeni mesaj var mı kontrol et (sekme açıksa)
    poll = setInterval(() => { if (!document.hidden) { fetchNew(); loadInbox(); } }, 8000);
  }

  // Yeni gelen mesajları balon olarak ekler ve en alta kaydırır.
  function appendMessages(list) {
    const box = document.getElementById('messages');
    if (!box || !list.length) return;
    // "Henüz mesaj yok" yazısını kaldır
    if (box.querySelector('.center')) box.innerHTML = '';
    box.insertAdjacentHTML('beforeend', list.map(m => `
      <div class="msg ${m.sender_id == me.id ? 'mine' : 'theirs'}">
        <div class="bubble">${esc(m.body)}</div>
        <small>${timeAgo(m.created_at)}${m.sender_id == me.id && m.is_read == 1 ? ' · ✓✓' : ''}</small>
      </div>`).join(''));
    // Son mesajın id'sini sakla, en alta kaydır
    current.lastId = list[list.length - 1].id;
    box.scrollTop = box.scrollHeight;
  }

  // Sadece son mesajdan sonra gelen yeni mesajları çeker.
  async function fetchNew() {
    if (!current) return;
    // Sadece son id'den sonraki yeni mesajları getir
    try { appendMessages((await apiGet(`/messages/${current.listingId}/${current.otherId}`, { after: current.lastId })).messages); }
    catch (e) {}
  }

  // Tarayıcının geri tuşuyla konuşmalar arasında gezin
  window.addEventListener('popstate', () => {
    const p = new URLSearchParams(location.search);
    if (p.get('ilan')) open(+p.get('ilan'), +p.get('kisi')); else chat.classList.remove('show-thread');
  });

  await loadInbox();
  const p = new URLSearchParams(location.search);
  // Adreste konuşma bilgisi varsa (bildirimden gelindiyse) onu aç
  if (p.get('ilan') && p.get('kisi')) open(+p.get('ilan'), +p.get('kisi'));
});
