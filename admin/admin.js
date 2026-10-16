// Ruethies Admin — progressive enhancement (tanpa JS pun semua form tetap jalan).
(function () {
  'use strict';

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
      if (f.size > 8 * 1024 * 1024) { alert('Foto maksimal 8 MB.'); input.value = ''; return; }
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
    form.addEventListener('submit', function (e) {
      var input = form.querySelector('input[type="file"]');
      var f = input && input.files && input.files[0];
      if (!f || !/^image\/(jpeg|png|webp)$/i.test(f.type || '')) return; // biar server yang menolak tipe lain
      e.preventDefault();
      var btn = form.querySelector('.stickybar button');
      var txt = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.innerHTML = 'Menyiapkan foto…'; }
      var finish = function (dataUrl) {
        if (dataUrl) {
          var hid = document.createElement('input');
          hid.type = 'hidden'; hid.name = input.name + '_b64'; hid.value = dataUrl;
          form.appendChild(hid);
          input.value = '';
          form.enctype = 'application/x-www-form-urlencoded';
        }
        if (btn) { btn.disabled = false; btn.innerHTML = txt; }
        form.submit();
      };
      var img = new Image();
      img.onload = function () {
        try {
          var MAX = 1600, w = img.width, h = img.height;
          var sc = Math.min(1, MAX / Math.max(w, h));
          var cw = Math.max(1, Math.round(w * sc)), ch = Math.max(1, Math.round(h * sc));
          var cv = document.createElement('canvas'); cv.width = cw; cv.height = ch;
          cv.getContext('2d').drawImage(img, 0, 0, cw, ch);
          finish(cv.toDataURL('image/jpeg', 0.82));
        } catch (err) { finish(null); }
        URL.revokeObjectURL(img.src);
      };
      img.onerror = function () { finish(null); };
      img.src = URL.createObjectURL(f);
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
