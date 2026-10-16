const WA_NUMBER = "6285139281257";
const WA_DISPLAY = "0851-3928-1257";
const IMG = (id) => {
  if(!id) return "";
  return id;
};

// Fallback bawaan (dipakai jika menu.json gagal dimuat, misal buka via file://).
// Untuk ubah menu: edit menu.json saja, tidak perlu sentuh file ini.
// Fallback mini (bukan sumber utama): hanya 3 item agar halaman tidak kosong
// jika menu.json gagal dimuat (misal buka via file:// atau jaringan gagal).
// Sumber utama SATU-SATUNYA adalah menu.json — edit di sana saja.
let MENU = [
  { cat:"cakery", badge:"Best Seller", signature:true, tag:{id:"Manis • Lembut",en:"Sweet • Delicate"}, img:"images/menu/opera-cake-noir.jpg",
    id:{n:"Opera Cake Noir",d:"Lapisan almond, ganache dark chocolate, glaze mengilap."},
    en:{n:"Noir Opera Cake",d:"Almond layers, dark ganache, mirror glaze."} },
  { cat:"coffee", badge:"Signature", signature:true, tag:{id:"Hangat • Creamy",en:"Warm • Creamy"}, img:"images/menu/cafe-creme-brulee-latte.jpg",
    id:{n:"Café Crème Brûlée Latte",d:"Espresso, susu creamy, gula brûlée di atas."},
    en:{n:"Crème Brûlée Latte",d:"Espresso with creamy milk, torched sugar top."} },
  { cat:"savoury", badge:"Best Seller", signature:true, tag:{id:"Gurih • Favorit",en:"Savoury • Crowd Favourite"}, img:"images/menu/risol-mayo-smoked-beef.jpg",
    id:{n:"Risol Mayo Smoked Beef",d:"Smoked beef, mayo, dan telur dalam kulit tipis yang renyah."},
    en:{n:"Smoked Beef Risol Mayo",d:"Smoked beef, mayo & egg in crisp thin skin."} },
];

function normMenu(items){
  if(!Array.isArray(items)) return null;
  const clean = items.filter(m => m && m.cat && (m.id || m.en)).map(m => ({ ...m, img: IMG(m.img || "") }));
  return clean.length ? clean : null;
}

fetch("menu.json", { cache: "reload" })
  .then(r => { if(!r.ok) throw 0; return r.json(); })
  .then(j => { const c = normMenu(j); if(c){ MENU = c; renderMenus(); } })
  .catch(()=>{ /* fallback bawaan tetap dipakai */ });

const I18N = {
  id:{ "nav.home":"Beranda","nav.menu":"Menu","nav.visit":"Lokasi","nav.atmos":"Suasana","nav.info":"Info","nav.reserve":"Reservasi","nav.skip":"Langsung ke menu",
    "hero.eyebrow":"N°01 — Patisserie Artisan di Cikarang","hero.title":'Dipanggang dengan <em>Kelembutan</em>',
    "hero.french":"croissants · cakery · pastel — depuis Cikarang",
    "hero.sub":"Croissant berlapis, kue lembut, serta pastel, kroket, dan risol favorit — lengkap dengan espresso pilihan. Elegan dan hangat. #MadeWithDelicacy.",
    "hero.cta1":"Lihat Menu","hero.cta2":"Reservasi via WhatsApp","hero.p1":"Ruko Neo Patio East No. 51","hero.p2":"Fresh dari oven setiap pagi","hero.p3":"Buka setiap hari",
    "hero.b1":"Fresh Setiap Hari","hero.b2":"Croissant Andalan",
    "sig.eyebrow":"01 — Signature","sig.title":'Favorit yang Paling <em>Dirindukan</em>',"sig.sub":"Tiga menu yang paling sering dipesan tamu saat pertama datang.",
    "quote.text":'"Bagi kami, kue bukan sekadar hidangan — melainkan <em>momen lembut</em> untuk dinikmati."',
    "menu.eyebrow":"02 — Menu","menu.title":'Pilih <em>Menu Favorit Anda</em>',"menu.t1":"Cakery","menu.t2":"Coffee & Drinks","menu.t3":"Savoury Patisserie",
    "menu.note":"Pastel, kroket, dan risol digoreng dadakan — waktu tunggu sekitar 10 menit. Silakan tanya ketersediaan harian via WhatsApp.",
    "menu.searchPh":"Cari croissant, kopi, risol…","menu.empty":"Tidak ditemukan. Silakan coba kata kunci lain atau tanya via WhatsApp.","menu.ask":"Tanya via WA",
    "atmos.eyebrow":"03 — Suasana","atmos.title":'Sudut yang <em>Fotogenik</em>',"atmos.c1":"Interior hangat — kayu dan cahaya sore","atmos.c2":"Dibuat manual dengan teliti","atmos.c3":"Detail lapisan yang renyah",
    "visit.eyebrow":"04 — Kunjungi Kami","visit.title":'Mampir, <em>Duduk Manis</em>',"visit.hours":"Setiap Hari • 09.00 – 21.00",
    "visit.dir":"Dapatkan Rute","visit.copy":"Salin Alamat","visit.res":"Reservasi Meja","visit.call":"Telepon",
    "faq.eyebrow":"05 — Informasi","faq.title":"Pertanyaan Umum",
    "foot.visit":"Kunjungi","foot.contact":"Kontak",
    "wa.title":"Reservasi Meja","wa.sub":"Isi detail berikut, akan kami teruskan ke WhatsApp.","wa.name":"Nama","wa.pax":"Tamu","wa.date":"Tanggal","wa.time":"Jam","wa.note":"Catatan","wa.send":"Kirim ke WhatsApp",
    "wa.okt":"Siap dikirim!","wa.oks":"Detail reservasi sudah rapi. Silakan buka WhatsApp untuk mengirim.","wa.back":"Ubah detail",
    "wa.ereq":"Wajib diisi.","wa.epax":"1–20 tamu.","wa.edate":"Pilih hari ini atau setelahnya.","wa.etime":"Jam 09.00–21.00.","wa.open":"Buka WhatsApp" },
  en:{ "nav.home":"Home","nav.menu":"Menu","nav.visit":"Visit","nav.atmos":"Atmosphere","nav.info":"Info","nav.reserve":"Reserve","nav.skip":"Skip to menu",
    "hero.eyebrow":"N°01 — Artisan Patisserie, Cikarang","hero.title":'Baked with <em>Délicatesse</em>',
    "hero.french":"croissants · cakery · pastel — depuis Cikarang",
    "hero.sub":"Flaky croissants, delicate cakery and beloved savouries — pastel, kroket, risol — paired with fine espresso. Elegant, warm, #MadeWithDelicacy.",
    "hero.cta1":"Explore Menu","hero.cta2":"Reserve via WhatsApp","hero.p1":"Ruko Neo Patio East N°51","hero.p2":"Fresh from the oven daily","hero.p3":"Open daily",
    "hero.b1":"Fresh Daily","hero.b2":"Signature Croissant",
    "sig.eyebrow":"01 — Signature","sig.title":'Most <em>Missed</em> Favourites',"sig.sub":"Three must-tries on your first visit.",
    "quote.text":'"We don\'t sell cake. We sell <em>tender moments</em> you can taste."',
    "menu.eyebrow":"02 — Menu","menu.title":'Choose Your <em>Craving</em>',"menu.t1":"Cakery","menu.t2":"Coffee & Drinks","menu.t3":"Savoury Patisserie",
    "menu.note":"Pastel • Kroket • Risol are fried to order — approx. 10 min. Ask daily availability via WhatsApp.",
    "menu.searchPh":"Search croissant, coffee, risol…","menu.empty":"No match. Try another word or ask on WhatsApp.","menu.ask":"Ask on WA",
    "atmos.eyebrow":"03 — Atmosphere","atmos.title":'Most <em>Photogenic</em> Corners',"atmos.c1":"Warm interior — wood & evening light","atmos.c2":"Handcrafted with care","atmos.c3":"Crisp layered details",
    "visit.eyebrow":"04 — Visit Us","visit.title":'Drop by, <em>Sit Pretty</em>',"visit.hours":"Daily • 9 AM – 9 PM",
    "visit.dir":"Get Directions","visit.copy":"Copy Address","visit.res":"Reserve a Table","visit.call":"Call",
    "faq.eyebrow":"05 — Information","faq.title":"Frequently Asked Questions",
    "foot.visit":"Visit","foot.contact":"Contact",
    "wa.title":"Table Reservation","wa.sub":"Fill in, we'll forward to WhatsApp.","wa.name":"Name","wa.pax":"Guests","wa.date":"Date","wa.time":"Time","wa.note":"Notes","wa.send":"Send to WhatsApp",
    "wa.okt":"Ready to send!","wa.oks":"Details look good. Open WhatsApp to send.","wa.back":"Edit details",
    "wa.ereq":"Required.","wa.epax":"1–20 guests.","wa.edate":"Pick today or later.","wa.etime":"9 AM – 9 PM only.","wa.open":"Open WhatsApp" }
};

let lang = localStorage.getItem("ruethies-lang") || new URLSearchParams(location.search).get("lang") || "id";
let activeCat = "cakery";

function t(k){ return (I18N[lang] && I18N[lang][k]) || I18N.id[k] || k; }

// Escape teks data sebelum masuk HTML (anti-XSS abadi untuk era Sheets/admin)
const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));

// WebP bila tersedia, fallback JPEG. Semua foto lokal dinormalkan 640x480 (hero 880x1100, lightbox 1200x900).
const webp = src => (typeof src === "string" && src.endsWith(".jpg")) ? src.slice(0, -4) + ".webp" : src;
const pic = (src, alt, w, h, eager) =>
  `<picture><source type="image/webp" srcset="${esc(webp(src))}" /><img ${eager ? 'fetchpriority="high" decoding="async"' : 'loading="lazy" decoding="async"'} src="${esc(src)}" width="${w}" height="${h}" alt="${esc(alt)}" /></picture>`;

// Tugas non-kritis (galeri bawah, efek tilt) dikerjakan saat browser idle agar tidak menahan render awal.
const onIdle = fn => { if("requestIdleCallback" in window) requestIdleCallback(fn, { timeout: 2500 }); else setTimeout(fn, 1); };

function applyLang(){
  document.documentElement.lang = lang;
  document.querySelectorAll("[data-i18n]").forEach(el=>{ el.textContent = t(el.dataset.i18n); });
  document.querySelectorAll("[data-i18n-html]").forEach(el=>{ el.innerHTML = t(el.dataset.i18nHtml); });
  document.querySelectorAll("[data-i18n-ph]").forEach(el=>{ el.placeholder = t(el.dataset.i18nPh); });
  document.getElementById("btn-id").classList.toggle("on", lang==="id");
  document.getElementById("btn-en").classList.toggle("on", lang==="en");
  if(!document.getElementById("lightbox").hidden && ATMOS[lbIdx]) lbCap.textContent = atmosCap(ATMOS[lbIdx]);
  renderMenus();
  renderAtmos();
}

function cardHTML(m, i){
  const v = m[lang] || m.id;
  const no = String(i+1).padStart(2,"0");
  return `<article class="card rv"><div class="ph">${pic(m.img, v.n, 640, 480)}</div>
    <div class="bd"><span class="signo" aria-hidden="true">N°${no}</span>${m.badge?`<span class="chip">${esc(m.badge)}</span>`:""}<h3>${esc(v.n)}</h3><p>${esc(v.d)}</p></div></article>`;
}

function rowHTML(m, i){
  const v = m[lang] || m.id;
  const tag = m.tag ? (m.tag[lang] || m.tag.id) : "";
  const num = String(i+1).padStart(2,"0");
  const idx = MENU.indexOf(m);
  return `<article class="mrow rv">
    <span class="mnum">${num}</span>
    <div class="mbody"><h3>${esc(v.n)}</h3><p>${esc(v.d)}</p>
      <div class="mtags">${m.badge?`<span class="chip">${esc(m.badge)}</span>`:""}${tag?`<span class="tagline">${esc(tag)}</span>`:""}<button class="askbtn" data-ask="${idx}">${t("menu.ask")} →</button></div></div>
    <span class="mdots" aria-hidden="true"></span>
    <span class="mpeek">${pic(m.img, "", 640, 480)}</span>
  </article>`;
}

let query = "";
function matchQ(m){
  if(!query) return true;
  const q = query.toLowerCase();
  return ((m.id?.n||"")+" "+(m.id?.d||"")+" "+(m.en?.n||"")+" "+(m.en?.d||"")).toLowerCase().includes(q);
}

function renderMenus(){
  const vis = MENU.filter(m=>m.available !== false);
  const flagged = vis.filter(m=>m.signature);
  const sig = flagged.length >= 3 ? flagged.slice(0,3) : [vis[0], vis[3], vis[8]].filter(Boolean);
  document.getElementById("signature").innerHTML = sig.map((m,i)=>cardHTML(m,i)).join("");
  document.getElementById("signature").removeAttribute("aria-busy");
  const items = vis.filter(m=>m.cat===activeCat).filter(matchQ);
  const grid = document.getElementById("menugrid");
  grid.innerHTML = items.length ? items.map(rowHTML).join("") : `<p class="muted empty-note">${t("menu.empty")}</p>`;
  grid.removeAttribute("aria-busy");
  grid.classList.remove("swap");
  requestAnimationFrame(()=>grid.classList.add("swap"));
  document.querySelectorAll(".tab").forEach(tb=>{
    const on = tb.dataset.cat===activeCat;
    tb.setAttribute("aria-selected", on);
    const key = tb.dataset.cat==="cakery" ? "menu.t1" : tb.dataset.cat==="coffee" ? "menu.t2" : "menu.t3";
    const n = vis.filter(m=>m.cat===tb.dataset.cat).length;
    tb.innerHTML = `${t(key)} <span class="tcount">${n}</span>`;
  });
  movePill();
  observeReveals();
  attachCardTilt();
}

document.getElementById("msearch").addEventListener("input", e=>{
  query = e.target.value.trim();
  renderMenus();
});
document.addEventListener("click", e=>{
  const b = e.target.closest("[data-ask]");
  if(!b) return;
  const m = MENU[+b.dataset.ask];
  if(!m) return;
  const v = m[lang] || m.id;
  const pre = lang==="id" ? `Halo! Apakah "${v.n}" tersedia hari ini? ` : `Hello! Is "${v.n}" available today? `;
  document.getElementById("f-note").value = pre;
  openWA();
});

// Nav aktif mengikuti section
const secIO = new IntersectionObserver(es=>{
  es.forEach(en=>{
    if(!en.isIntersecting) return;
    const id = en.target.id || "home";
    document.querySelectorAll(".links a").forEach(a=>a.classList.toggle("on", a.getAttribute("href")==="#"+id));
  });
},{rootMargin:"-40% 0px -55% 0px"});
["home","menu","atmosphere","visit","faq"].forEach(id=>{ const s=document.getElementById(id); if(s) secIO.observe(s); });

// 3D subtle — hanya pointer presisi + tanpa reduced-motion (HP otomatis skip)
const can3D = matchMedia("(pointer:fine)").matches && !matchMedia("(prefers-reduced-motion: reduce)").matches;
const heroVis = document.querySelector(".hero-visual");
let tiltX = 0, tiltY = 0, plx = 0, heroQueued = false;
function paintHero(){
  heroQueued = false;
  if(!heroVis) return;
  heroVis.style.transform = `translate3d(0,${plx.toFixed(1)}px,0) rotateX(${tiltX.toFixed(2)}deg) rotateY(${tiltY.toFixed(2)}deg)`;
}
function queueHero(){ if(!heroQueued){ heroQueued = true; requestAnimationFrame(paintHero); } }
if(can3D && heroVis){
  heroVis.classList.add("has-tilt");
  const heroSec = document.querySelector(".hero");
  heroSec.addEventListener("pointermove", e=>{
    const r = heroVis.getBoundingClientRect();
    const dx = (e.clientX - (r.left + r.width/2)) / r.width;
    const dy = (e.clientY - (r.top + r.height/2)) / r.height;
    tiltY = Math.max(-5, Math.min(5, dx * 10));
    tiltX = Math.max(-5, Math.min(5, -dy * 10));
    queueHero();
  });
  heroSec.addEventListener("pointerleave", ()=>{ tiltX = 0; tiltY = 0; queueHero(); });
}
function attachCardTilt(){
  if(!can3D) return;
  document.querySelectorAll("#signature .card").forEach(c=>{
    c.addEventListener("pointermove", e=>{
      const r = c.getBoundingClientRect();
      const dx = (e.clientX - (r.left + r.width/2)) / r.width;
      const dy = (e.clientY - (r.top + r.height/2)) / r.height;
      c.style.transform = `translateY(-4px) rotateX(${(-dy*8).toFixed(2)}deg) rotateY(${(dx*8).toFixed(2)}deg)`;
    });
    c.addEventListener("pointerleave", ()=>{ c.style.transform = ""; });
    c.addEventListener("click", ()=>{ c.style.transform = ""; });
  });
}

const io = new IntersectionObserver(es=>es.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add("in"); io.unobserve(e.target); } }),{threshold:.12});
function observeReveals(){ document.querySelectorAll(".rv:not(.in)").forEach(el=>io.observe(el)); }

function movePill(){
  const btn = document.querySelector(`.tab[data-cat="${activeCat}"]`);
  const pill = document.getElementById("pill");
  const tabs = document.querySelector(".tabs");
  if(!btn || !pill) return;
  requestAnimationFrame(()=>{
    pill.style.left = (btn.offsetLeft - (tabs ? tabs.scrollLeft : 0))+"px";
    pill.style.width = btn.offsetWidth+"px";
  });
}

document.querySelectorAll(".tab").forEach(b=>b.addEventListener("click",()=>{
  document.querySelectorAll(".tab").forEach(x=>x.classList.remove("on"));
  b.classList.add("on"); activeCat=b.dataset.cat; renderMenus();
}));
document.getElementById("btn-id").onclick=()=>{lang="id";localStorage.setItem("ruethies-lang",lang);applyLang();};
document.getElementById("btn-en").onclick=()=>{lang="en";localStorage.setItem("ruethies-lang",lang);applyLang();};

// Mobile hamburger
const burger = document.getElementById("burger"), mmenu = document.getElementById("mmenu");
burger.onclick=()=>{
  const open = mmenu.hidden;
  mmenu.hidden = !open;
  burger.classList.toggle("open", open);
  burger.setAttribute("aria-expanded", open);
};
mmenu.querySelectorAll("a").forEach(a=>a.addEventListener("click",()=>{
  mmenu.hidden = true; burger.classList.remove("open"); burger.setAttribute("aria-expanded","false");
}));

// WA modal + validation + success
const bd = document.getElementById("backdrop");
const stickybar = document.getElementById("stickybar");
let modalOpen = false;
let lastMsg = "", lastPretty = "";
const openWA = ()=>{
  modalOpen = true;
  document.getElementById("wa-form").hidden = false;
  document.getElementById("wa-success").hidden = true;
  bd.hidden = false; stickybar.classList.add("hide");
  setTimeout(()=>document.getElementById("f-name").focus(), 50);
};
const closeWA = ()=>{ modalOpen = false; bd.hidden = true; stickybar.classList.remove("hide"); onScroll(); };
document.querySelectorAll("[data-open-wa]").forEach(b=>b.onclick=openWA);
document.getElementById("closewa").onclick=closeWA;
bd.addEventListener("click",e=>{ if(e.target===bd) closeWA(); });
document.addEventListener("keydown",e=>{ if(e.key==="Escape" && !bd.hidden) closeWA(); });
const fDate = document.getElementById("f-date");
const todayStr = new Date().toISOString().slice(0,10);
fDate.min = todayStr;
if(!fDate.value) fDate.value = todayStr;
function setErr(id, msg){ const el = document.getElementById(id); if(!msg){ el.hidden = true; return true; } el.textContent = msg; el.hidden = false; return false; }
function buildMsg(){
  const n=document.getElementById("f-name").value.trim(),
        p=parseInt(document.getElementById("f-pax").value,10),
        d=fDate.value, tm=document.getElementById("f-time").value,
        no=document.getElementById("f-note").value.trim()||"-";
  let ok = true;
  ok = setErr("e-name", n ? "" : t("wa.ereq")) && ok;
  ok = setErr("e-pax", (p>=1 && p<=20) ? "" : t("wa.epax")) && ok;
  ok = setErr("e-date", (d && d >= todayStr) ? "" : t("wa.edate")) && ok;
  ok = setErr("e-time", (tm >= "09:00" && tm <= "21:00") ? "" : t("wa.etime")) && ok;
  if(!ok) return null;
  const msg = lang==="id"
    ? `Halo Ruethies Patisserie! Saya ${n} — reservasi ${p} orang, ${d} pukul ${tm}. Catatan: ${no}. Terima kasih! #MadeWithDelicacy`
    : `Hello Ruethies Patisserie! I'm ${n} — reserving for ${p}, ${d} at ${tm}. Notes: ${no}. Thank you! #MadeWithDelicacy`;
  return { msg, pretty: `${n} • ${p} 👥 • ${d} ${tm}` };
}
document.getElementById("sendwa").onclick=()=>{
  const r = buildMsg(); if(!r) return;
  lastMsg = r.msg; lastPretty = r.pretty;
  document.getElementById("wa-preview").textContent = lastPretty;
  document.getElementById("wa-form").hidden = true;
  document.getElementById("wa-success").hidden = false;
};
document.getElementById("openwa").onclick=()=>{ window.open(`https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(lastMsg)}`,"_blank"); };
document.getElementById("backwa").onclick=()=>{
  document.getElementById("wa-success").hidden = true;
  document.getElementById("wa-form").hidden = false;
};
const copyBtn = document.getElementById("copyaddr");
const copyOrig = { id:"Salin Alamat", en:"Copy Address" };
copyBtn.onclick=async ()=>{
  try{ await navigator.clipboard.writeText("Ruko The Neo Patio East NO. 51, Lippo, Cikarang Sel., Kabupaten Bekasi, Jawa Barat 17530"); }catch(e){}
  const done = lang==="id" ? "Tersalin ✓" : "Copied ✓";
  copyBtn.textContent = done;
  copyBtn.disabled = true;
  setTimeout(()=>{ copyBtn.textContent = copyOrig[lang] || copyOrig.en; copyBtn.disabled = false; },1500);
};
document.querySelector(".tabs")?.addEventListener("scroll", movePill, {passive:true});
window.addEventListener("resize",movePill);
if(document.fonts?.ready) document.fonts.ready.then(movePill);
function onScroll(){
  document.getElementById("nav").style.boxShadow = scrollY>24?"0 12px 32px -18px rgba(61,0,0,.25)":"none";
  const hero = document.querySelector(".hero");
  const past = hero ? scrollY > hero.offsetTop + hero.offsetHeight - 120 : scrollY > 600;
  stickybar.classList.toggle("show", past && !modalOpen);
  stickybar.classList.toggle("hide", modalOpen);
  if(can3D && heroVis && hero && scrollY < hero.offsetHeight){
    plx = Math.min(12, scrollY * 0.05);
    queueHero();
  }
}

// Galeri atmosphere — sumber utama atmosphere.json (edit di sana saja)
let ATMOS = [
  { img:"images/suasana-1.jpg",
    imgFull:"images/suasana-1-xl.jpg",
    id:{cap:"Interior hangat — kayu dan cahaya sore"}, en:{cap:"Warm interior — wood & evening light"} },
  { img:"images/suasana-2.jpg",
    imgFull:"images/suasana-2-xl.jpg",
    id:{cap:"Dibuat manual dengan teliti"}, en:{cap:"Handcrafted with care"} },
  { img:"images/suasana-3.jpg",
    imgFull:"images/suasana-3-xl.jpg",
    id:{cap:"Detail lapisan yang renyah"}, en:{cap:"Crisp layered details"} },
];
function atmosCap(a){ return (a[lang] && a[lang].cap) || a.id.cap; }
function renderAtmos(){
  const row = document.getElementById("archrow");
  if(!row) return;
  row.innerHTML = ATMOS.map((a,i)=>{
    const cap = atmosCap(a);
    return `<figure class="rv"><button class="arch-btn" data-lb="${i}" aria-haspopup="dialog" aria-label="${esc(cap)}"><span class="arch">${pic(a.img, cap, 640, 480)}</span></button><figcaption>${esc(cap)}</figcaption></figure>`;
  }).join("");
  observeReveals();
}
function normAtmos(items){
  if(!Array.isArray(items)) return null;
  const clean = items.filter(a => a && a.img && (a.id || a.en)).map(a => ({
    img: IMG(a.img), imgFull: IMG(a.imgFull || a.img), id: a.id || a.en, en: a.en || a.id,
  }));
  return clean.length ? clean : null;
}
// Galeri di bawah lipatan: datanya diambil saat browser idle agar tidak berebut bandwidth dengan hero/menu.
onIdle(()=>{
  fetch("atmosphere.json", { cache: "reload" })
    .then(r => { if(!r.ok) throw 0; return r.json(); })
    .then(j => { const c = normAtmos(j); if(c){ ATMOS = c; renderAtmos(); } })
    .catch(()=>{ /* fallback bawaan tetap dipakai */ });
});

// Lightbox (dari data master ATMOS yang sama)
let lbIdx = 0;
const lb = document.getElementById("lightbox"), lbImg = document.getElementById("lb-img"), lbCap = document.getElementById("lb-cap"), lbCount = document.getElementById("lb-count");
function showLB(i){
  lbIdx = (i + ATMOS.length) % ATMOS.length;
  const a = ATMOS[lbIdx], cap = atmosCap(a);
  lbImg.src = a.imgFull || a.img; lbImg.alt = cap; lbCap.textContent = cap;
  if(lbCount) lbCount.textContent = `0${lbIdx+1} / 0${ATMOS.length}`;
  lb.hidden = false; modalOpen = true; stickybar.classList.add("hide");
  document.getElementById("lb-close").focus();
}
function hideLB(){ lb.hidden = true; modalOpen = false; stickybar.classList.remove("hide"); onScroll(); }
document.getElementById("archrow").addEventListener("click", e=>{
  const b = e.target.closest("[data-lb]");
  if(b) showLB(+b.dataset.lb);
});
document.getElementById("lb-close").onclick = hideLB;
document.getElementById("lb-prev").onclick = (e)=>{ e.stopPropagation(); showLB(lbIdx-1); };
document.getElementById("lb-next").onclick = (e)=>{ e.stopPropagation(); showLB(lbIdx+1); };
lb.addEventListener("click",e=>{ if(e.target===lb) hideLB(); });
document.addEventListener("keydown",e=>{
  if(lb.hidden) return;
  if(e.key==="Escape") hideLB();
  if(e.key==="ArrowLeft") showLB(lbIdx-1);
  if(e.key==="ArrowRight") showLB(lbIdx+1);
});
let tx = 0;
lb.addEventListener("touchstart",e=>{ tx = e.touches[0].clientX; },{passive:true});
lb.addEventListener("touchend",e=>{
  const dx = e.changedTouches[0].clientX - tx;
  if(Math.abs(dx) > 40) showLB(lbIdx + (dx < 0 ? 1 : -1));
},{passive:true});
window.addEventListener("scroll",onScroll,{passive:true});
applyLang();
onScroll();
