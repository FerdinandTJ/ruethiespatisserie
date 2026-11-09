// Ruethies Admin — progressive enhancement (tanpa JS pun semua form tetap jalan).
(function () {
  'use strict';

  // Tautan lama berbasis hash tetap menuju halaman yang sesuai. Tanpa JS ada tautan pengganti.
  var params = new URLSearchParams(window.location.search);
  if (!params.has('p')) {
    var legacyArea = /^(#galeri|#gitem-)/.test(window.location.hash) ? 'galeri' : (/^(#faqman|#fitem-)/.test(window.location.hash) ? 'faq' : '');
    if (legacyArea) {
      params.set('p', legacyArea);
      window.location.replace('?' + params.toString() + window.location.hash);
      return;
    }
  }


  // Konfirmasi aksi berbahaya (pengganti onclick inline yang diblokir CSP).
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-confirm]');
    if (f && !window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm-btn]');
    if (b && !window.confirm(b.getAttribute('data-confirm-btn'))) e.preventDefault();
  });

  // Preview foto sebelum upload.
  document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
    var box = document.getElementById(input.getAttribute('data-preview'));
    if (!box) return;
    input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      if (!f) return;
      var maxMB = input.name === 'gfoto' ? 8 : 5;
      if (f.size > maxMB * 1024 * 1024) { alert('Foto maksimal ' + maxMB + ' MB.'); input.value = ''; return; }
      var r = new FileReader();
      r.onload = function () {
        box.innerHTML = '';
        var img = document.createElement('img');
        img.src = r.result; img.alt = 'Pratinjau foto';
        box.appendChild(img);
        var cap = document.createElement('small');
        cap.textContent = f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
        box.appendChild(cap);
      };
      r.readAsDataURL(f);
    });
  });

  // Kirim foto sebagai base64 dalam POST biasa (bukan multipart).
  // Alasan: firewall hosting menolak request multipart berisi file (403)
  // bahkan untuk file kecil — jalur base64 tidak cocok pola aturan itu.
  // Tanpa JS: form terkirim multipart seperti biasa (server tetap menerima).
  document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(function (form) {
    form.addEventListener('submit', async function (e) {
      var inputs = Array.from(form.querySelectorAll('input[type="file"]')).filter(function (input) { return input.files && input.files[0]; });
      if (!inputs.length) return;
      e.preventDefault();
      if (form.dataset.sending) return;
      if (inputs.some(function (input) { return !/^image\/(jpeg|png|webp)$/i.test(input.files[0].type || '') || input.files[0].size > (input.name === 'gfoto' ? 8 : 5)*1024*1024; })) {
        alert('Gunakan JPG, PNG, atau WebP, maksimal 5 MB per foto menu (8 MB untuk galeri).'); return;
      }
      form.dataset.sending = '1';
      var btn = form.querySelector('.stickybar button');
      var original = btn && btn.innerHTML;
      if (btn) { btn.disabled = true; btn.textContent = 'Menyiapkan foto…'; }
      try {
        var results = [];
        // Proses berurutan agar tiga foto tidak didekode bersamaan di HP.
        for (var input of inputs) {
          var file = input.files[0];
          var dataUrl = await new Promise(function (resolve, reject) {
            var image = new Image(), reader = new FileReader();
            image.onload = function () {
              try {
                var scale = Math.min(1, 1600 / Math.max(image.width, image.height));
                var canvas = document.createElement('canvas');
                canvas.width = Math.max(2, Math.round(image.width*scale)); canvas.height = Math.max(2, Math.round(image.height*scale));
                canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                resolve(canvas.toDataURL('image/jpeg', 0.82));
              } catch (err) { reject(err); }
            };
            image.onerror = function () { reject(new Error('Foto tidak dapat dibaca.')); };
            // CSP hosting mengizinkan data:, bukan blob:. Gunakan jalur yang sama dengan preview.
            reader.onload = function () { image.src = reader.result; };
            reader.onerror = function () { reject(new Error('File foto tidak dapat dibaca.')); };
            reader.onabort = function () { reject(new Error('Pembacaan foto dibatalkan.')); };
            reader.readAsDataURL(file);
          });
          results.push([input, dataUrl]);
        }
        results.forEach(function (result) {
          var hid = document.createElement('input'); hid.type = 'hidden'; hid.name = result[0].name + '_b64'; hid.value = result[1]; form.appendChild(hid);
          result[0].value = '';
        });
        form.enctype = 'application/x-www-form-urlencoded'; form.submit();
      } catch (err) {
        delete form.dataset.sending;
        if (btn) { btn.disabled = false; btn.innerHTML = original; }
        alert('Gagal membaca atau memproses foto. Pilih ulang file dan coba lagi. Foto belum dikirim.');
      }
    });
  });

  // Penghitung karakter untuk input maxlength.
  document.querySelectorAll('[maxlength][data-count]').forEach(function (el) {
    var out = document.getElementById(el.getAttribute('data-count'));
    if (!out) return;
    var max = parseInt(el.getAttribute('maxlength'), 10);
    var upd = function () { out.textContent = (el.value.length) + '/' + max; };
    el.addEventListener('input', upd); upd();
  });

  // Anti double-submit umum: kunci tombol kirim saat form berangkat
  // (mencegah klik ganda yang bisa menukar pasangan file).
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || f.tagName !== 'FORM') return;
    setTimeout(function () {
      if (e.defaultPrevented) return;
      f.querySelectorAll('button:not([type]), button[type="submit"]').forEach(function (b) { b.disabled = true; });
    }, 0);
  });

  // Tampilkan/sembunyikan kata sandi.
  document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var inp = document.getElementById(btn.getAttribute('data-pw-toggle'));
      if (!inp) return;
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Sembunyikan' : 'Lihat';
      inp.focus();
    });
  });
})();
