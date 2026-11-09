document.documentElement.classList.add("enhanced");
// Aktivasi font dari script eksternal: kompatibel dengan CSP script-src 'self'.
const fontStyles = document.getElementById("site-fonts");
if(fontStyles) fontStyles.media = "all";

const WA_NUMBER = "6285139281257";
const IMG = (id) => {
  if(!id) return "";
  return id;
};

// Snapshot HTML dan interaksi memakai data yang sama, tanpa katalog contoh ketika JSON rusak.
const catalogueSnapshot = JSON.parse(document.getElementById("catalog-data").textContent);
let MENU = catalogueSnapshot.menu;

const I18N = {
  id:{ "nav.home":"Beranda","nav.menu":"Menu","nav.visit":"Lokasi","nav.atmos":"Suasana","nav.info":"Info","nav.contact":"WhatsApp","nav.skip":"Langsung ke menu",
    "hero.eyebrow":"N°01 — Patisserie Artisan di Cikarang","hero.title":'Snack Box &amp; Kue di <em>Cikarang</em>',
    "hero.french":"kue klasik · camilan gurih · snackbox",
    "hero.sub":"Kenali snack box, Bolu Jadoel, brownies, Lapis Surabaya, dan camilan gurih Ruethies Patisserie di Lippo Cikarang. Tanyakan pilihan dan pemesanan melalui WhatsApp. #MadeWithDelicacy.",
    "hero.cta1":"Lihat Menu","hero.cta2":"Hubungi via WhatsApp","hero.p1":"Ruko Neo Patio East No. 51","hero.p2":"Untuk dinikmati bersama","hero.p3":"Buka setiap hari",
    "hero.snack":"Kenali snack box kami →","hero.b1":"Untuk Momen Anda","hero.b2":"Kue & Kudapan Pilihan",
    "sig.eyebrow":"01 — Signature","sig.title":'Favorit yang Paling <em>Dirindukan</em>',"sig.sub":"Kenali pilihan kue, camilan gurih, dan paket untuk momen Anda.",
    "quote.text":'"Bagi kami, kue bukan sekadar hidangan — melainkan <em>momen lembut</em> untuk dinikmati."',
    "menu.eyebrow":"02 — Menu","menu.title":'Pilih <em>Menu Favorit Anda</em>',"menu.t1":"Kue & Jajanan","menu.t2":"Camilan Gurih","menu.t3":"Paket & Sajian",
    "menu.note":"Tanyakan ketersediaan, varian isi, ukuran, harga, dan pilihan paket melalui WhatsApp.",
    "menu.searchPh":"Cari brownies, pastel, snackbox…","menu.empty":"Tidak ditemukan. Silakan coba kata kunci lain atau tanya via WhatsApp.","menu.ask":"Tanya via WA",
    "atmos.eyebrow":"03 — Suasana","atmos.title":'Sudut yang <em>Fotogenik</em>',"atmos.c1":"Interior hangat — kayu dan cahaya sore","atmos.c2":"Dibuat manual dengan teliti","atmos.c3":"Detail lapisan yang renyah",
    "visit.eyebrow":"04 — Kunjungi Kami","visit.title":'Mampir, <em>Duduk Manis</em>',"visit.hours":"Setiap Hari • 09.00 – 21.00",
    "visit.dir":"Dapatkan Rute","visit.copy":"Salin Alamat","visit.contact":"Hubungi via WhatsApp","visit.call":"Telepon",
    "visit.map":"Tampilkan Peta Interaktif","visit.mapTitle":"Peta Ruethies Patisserie — N°51",
    "faq.eyebrow":"05 — Informasi","faq.title":"Pertanyaan Umum",
    "foot.visit":"Kunjungi","foot.contact":"Kontak" },
  en:{ "nav.home":"Home","nav.menu":"Menu","nav.visit":"Visit","nav.atmos":"Atmosphere","nav.info":"Info","nav.contact":"WhatsApp","nav.skip":"Skip to menu",
    "hero.eyebrow":"N°01 — Artisan Patisserie, Cikarang","hero.title":'Snack Boxes &amp; Cakes in <em>Cikarang</em>',
    "hero.french":"classic cakes · savoury snacks · snackboxes",
    "hero.sub":"Explore snack boxes, classic cakes, brownies and savoury snacks at Ruethies Patisserie in Lippo Cikarang. Ask about selections and ordering on WhatsApp. #MadeWithDelicacy.",
    "hero.cta1":"Explore Menu","hero.cta2":"Contact via WhatsApp","hero.p1":"Ruko Neo Patio East N°51","hero.p2":"Made for sharing","hero.p3":"Open daily",
    "hero.snack":"Explore our snack boxes →","hero.b1":"For Your Moments","hero.b2":"Cakes & Treats",
    "sig.eyebrow":"01 — Signature","sig.title":'Most <em>Missed</em> Favourites',"sig.sub":"Discover cakes, savoury snacks and boxes for your next gathering.",
    "quote.text":'"We don\'t sell cake. We sell <em>tender moments</em> you can taste."',
    "menu.eyebrow":"02 — Menu","menu.title":'Choose Your <em>Craving</em>',"menu.t1":"Cakes & Desserts","menu.t2":"Savoury Snacks","menu.t3":"Boxes & Platters",
    "menu.note":"Ask about availability, fillings, sizes, prices and package options via WhatsApp.",
    "menu.searchPh":"Search brownies, pastel, snackbox…","menu.empty":"No match. Try another word or ask on WhatsApp.","menu.ask":"Ask on WA",
    "atmos.eyebrow":"03 — Atmosphere","atmos.title":'Most <em>Photogenic</em> Corners',"atmos.c1":"Warm interior — wood & evening light","atmos.c2":"Handcrafted with care","atmos.c3":"Crisp layered details",
    "visit.eyebrow":"04 — Visit Us","visit.title":'Drop by, <em>Sit Pretty</em>',"visit.hours":"Daily • 9 AM – 9 PM",
    "visit.dir":"Get Directions","visit.copy":"Copy Address","visit.contact":"Contact via WhatsApp","visit.call":"Call",
    "visit.map":"Show Interactive Map","visit.mapTitle":"Map of Ruethies Patisserie — N°51",
    "faq.eyebrow":"05 — Information","faq.title":"Frequently Asked Questions",
    "foot.visit":"Visit","foot.contact":"Contact" }
};

let lang = localStorage.getItem("ruethies-lang") || new URLSearchParams(location.search).get("lang") || "id";
let activeCat = "cakery";

function t(k){ return (I18N[lang] && I18N[lang][k]) || I18N.id[k] || k; }

function whatsappURL(message){ return `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(message)}`; }
function contactMessage(){
  return lang === "en"
    ? "Hello Ruethies Patisserie! I would like to ask about your menu and placing an order."
    : "Halo Ruethies Patisserie! Saya ingin bertanya tentang menu dan pemesanan.";
}
function productMessage(name){
  return lang === "en"
    ? `Hello Ruethies Patisserie! Is "${name}" available? I would like to ask about prices and ordering.`
    : `Halo Ruethies Patisserie! Apakah "${name}" tersedia? Saya ingin bertanya tentang harga dan pemesanan.`;
}

// Escape teks data sebelum masuk HTML (anti-XSS abadi untuk era Sheets/admin)
const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));

let FAQ = catalogueSnapshot.faq;
const faqMarkup = text => esc(text).replace(
  /\[([^\[\]]{1,80})\]\((https?:\/\/[^\s()<>"]{1,300})\)/g,
  (_, label, url) => `<a href="${url}" target="_blank" rel="noopener">${label}</a>`
);

function renderFAQ(){
  const section = document.getElementById("faq");
  if(!section || !FAQ) return;
  const rows = Array.from(section.querySelectorAll("details"));
  FAQ.forEach((item, i)=>{
    let row = rows[i];
    if(!row){
      row = document.createElement("details");
      row.innerHTML = "<summary></summary><p></p>";
      section.appendChild(row);
    }
    row.querySelector("summary").textContent = lang === "en" ? (item.qe || item.q) : item.q;
    row.querySelector("p").innerHTML = faqMarkup(lang === "en" ? (item.ae || item.a) : item.a);
  });
  rows.slice(FAQ.length).forEach(row=>row.remove());
}

let faqLoading = false;
function loadFAQ(){
  if(faqLoading || FAQ) return;
  faqLoading = true;
  fetch("faq.json", { cache: "reload" })
  .then(r => { if(!r.ok) throw 0; return r.json(); })
  .then(items => {
    if(!Array.isArray(items) || !items.length || !items.every(item =>
      item && typeof item.q === "string" && typeof item.a === "string" &&
      (item.qe == null || typeof item.qe === "string") &&
      (item.ae == null || typeof item.ae === "string")
    )) return;
    FAQ = items;
    renderFAQ();
  })
  .catch(()=>{ /* FAQ statis tetap tampil jika data gagal dimuat. */ })
  .finally(()=>{ faqLoading = false; });
}

// WebP bila tersedia, fallback JPEG. Semua foto lokal dinormalkan 640x480 (hero 880x1100, lightbox 1200x900).
const webp = src => (typeof src === "string" && src.endsWith(".jpg")) ? src.slice(0, -4) + ".webp" : src;
const pic = (src, alt, w, h, eager) =>
  `<picture><source type="image/webp" srcset="${esc(webp(src))}" /><img ${eager ? 'fetchpriority="high" decoding="async"' : 'loading="lazy" decoding="async"'} src="${esc(src)}" width="${w}" height="${h}" alt="${esc(alt)}" /></picture>`;

// Data bawah halaman dimuat menjelang terlihat, bukan bersamaan dengan hero.
function onceNear(id, load){
  const element = document.getElementById(id);
  if(!element) return;
  const observer = new IntersectionObserver(entries=>{
    if(entries.some(entry=>entry.isIntersecting)){ observer.disconnect(); load(); }
  }, {rootMargin:"600px 0px"});
  observer.observe(element);
}

function applyLang(){
  document.documentElement.lang = lang;
  document.querySelectorAll("[data-contact-wa]").forEach(link=>{ link.href = whatsappURL(contactMessage()); });
  document.querySelectorAll("[data-i18n]").forEach(el=>{ el.textContent = t(el.dataset.i18n); });
  document.querySelectorAll("[data-i18n-html]").forEach(el=>{ el.innerHTML = t(el.dataset.i18nHtml); });
  document.querySelectorAll("[data-i18n-ph]").forEach(el=>{ el.placeholder = t(el.dataset.i18nPh); });
  document.getElementById("btn-id").classList.toggle("on", lang==="id");
  document.getElementById("btn-en").classList.toggle("on", lang==="en");
  if(!document.getElementById("lightbox").hidden) showLB(lbIdx);
  renderMenus();
  renderAtmos();
  renderFAQ();
  if(lang === "en") loadFAQ(); // EN tersimpan/tombol bahasa tidak perlu menunggu scroll.
  const map = document.getElementById("visit-map");
  if(map) map.title = t("visit.mapTitle");
}

function productIndex(m){ return MENU.indexOf(m); }
function productURL(m){ return m.url; }
function productPhotoLabel(name){ return (lang === "en" ? "View photos: " : "Lihat foto: ") + name; }
function cardHTML(m, i){
  const v = m[lang] || m.id;
  const no = String(i+1).padStart(2,"0");
  return `<article class="card rv"><a href="${esc(productURL(m))}" class="ph product-photo" data-product="${productIndex(m)}" aria-haspopup="dialog" aria-label="${esc(productPhotoLabel(v.n))}">${pic(m.img, v.n, 640, 480)}</a>
    <div class="bd"><span class="signo" aria-hidden="true">N°${no}</span>${m.badge?`<span class="chip">${esc(m.badge)}</span>`:""}<h3>${esc(v.n)}</h3><p>${esc(v.d)}</p></div></article>`;
}

function rowHTML(m, i){
  const v = m[lang] || m.id;
  const tag = m.tag ? (m.tag[lang] || m.tag.id) : "";
  const num = String(i+1).padStart(2,"0");
  return `<article class="mrow rv">
    <span class="mnum">${num}</span>
    <div class="mbody"><h3><a href="${esc(productURL(m))}" class="menu-title-link" data-product="${productIndex(m)}" aria-haspopup="dialog">${esc(v.n)}</a></h3><p>${esc(v.d)}</p>
      <div class="mtags">${m.badge?`<span class="chip">${esc(m.badge)}</span>`:""}${tag?`<span class="tagline">${esc(tag)}</span>`:""}<span class="menu-actions"><a href="${esc(productURL(m))}" class="detail-link" data-product="${productIndex(m)}" aria-haspopup="dialog">${lang === "en" ? "View details" : "Lihat detail"}<span aria-hidden="true"> ↗</span></a><a class="askbtn" href="${esc(whatsappURL(productMessage(v.n)))}" target="_blank" rel="noopener">${t("menu.ask")} →</a></span></div></div>
    <span class="mdots" aria-hidden="true"></span>
    <a href="${esc(productURL(m))}" class="mpeek product-photo" data-product="${productIndex(m)}" aria-haspopup="dialog" aria-label="${esc(productPhotoLabel(v.n))}">${pic(m.img, "", 640, 480)}</a>
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
  const sig = [...flagged, ...vis.filter(m=>!m.signature)].slice(0,3);
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
    const key = tb.dataset.cat==="cakery" ? "menu.t1" : tb.dataset.cat==="savoury" ? "menu.t2" : "menu.t3";
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

let pillFrame = 0;
function movePill(){
  if(pillFrame) return;
  pillFrame = requestAnimationFrame(()=>{
    pillFrame = 0;
    const btn = document.querySelector(`.tab[data-cat="${activeCat}"]`);
    const pill = document.getElementById("pill");
    const tabs = document.querySelector(".tabs");
    if(!btn || !pill) return;
    const left = btn.offsetLeft - (tabs ? tabs.scrollLeft : 0);
    const width = btn.offsetWidth;
    pill.style.left = left+"px";
    pill.style.width = width+"px";
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

// Shared by the gallery lightbox and mobile contact bar.
const stickybar = document.getElementById("stickybar");
let modalOpen = false;
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
let scrollFrame = 0;
function onScroll(){
  if(scrollFrame) return;
  scrollFrame = requestAnimationFrame(()=>{
    scrollFrame = 0;
    const y = scrollY;
    const hero = document.querySelector(".hero");
    const height = hero ? hero.offsetHeight : 0;
    const end = hero ? hero.offsetTop + height : 720;
    const past = y > end - 120;
    document.getElementById("nav").classList.toggle("scrolled", y > 24);
    stickybar.classList.toggle("show", past && !modalOpen);
    stickybar.classList.toggle("hide", modalOpen);
    if(can3D && heroVis && hero && y < height){
      plx = Math.min(12, y * 0.05);
      queueHero();
    }
  });
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
let atmosLoading = false, atmosReady = false;
function renderAtmos(){
  if(!atmosReady) return;
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
function loadAtmos(){
  if(atmosLoading || atmosReady) return;
  atmosLoading = true;
  fetch("atmosphere.json", { cache: "reload" })
    .then(r => { if(!r.ok) throw 0; return r.json(); })
    .then(j => { const c = normAtmos(j); if(c) ATMOS = c; })
    .catch(()=>{ /* fallback bawaan tetap dipakai */ })
    .finally(()=>{ atmosReady = true; atmosLoading = false; renderAtmos(); });
}

// Satu lightbox untuk suasana dan foto produk. Foto detail dimuat saat dibuka.
let lbIdx = 0, lbProduct = null, lbReturnFocus = null;
const lb = document.getElementById("lightbox"), lbImg = document.getElementById("lb-img"), lbCap = document.getElementById("lb-cap"), lbCount = document.getElementById("lb-count"), lbWA = document.getElementById("lb-wa");
function productPhotos(m){
  return [m.img, ...(Array.isArray(m.detail_images) ? m.detail_images.slice(0,2) : [])].filter(src=>typeof src === "string" && /^images\/menu\/[a-z0-9-]+\.(jpg|webp)$/i.test(src));
}
function showLB(i){
  const photos = lbProduct ? productPhotos(lbProduct) : ATMOS.map(a=>a.imgFull || a.img);
  if(!photos.length) return;
  if(lb.hidden) lbReturnFocus = document.activeElement;
  lbIdx = (i + photos.length) % photos.length;
  const cap = lbProduct ? (lbProduct[lang] || lbProduct.id).n : atmosCap(ATMOS[lbIdx]);
  lbImg.src = photos[lbIdx]; lbImg.alt = cap; lbCap.textContent = cap;
  const description = document.getElementById("lb-description");
  if(description){
    description.textContent = lbProduct ? ((lbProduct[lang] || lbProduct.id).detail || (lbProduct[lang] || lbProduct.id).d || "") : "";
    description.hidden = !description.textContent;
  }
  lbCount.textContent = `${String(lbIdx+1).padStart(2,"0")} / ${String(photos.length).padStart(2,"0")}`;
  document.getElementById("lb-prev").hidden = document.getElementById("lb-next").hidden = photos.length < 2;
  lbWA.hidden = !lbProduct;
  if(lbProduct) { lbWA.href = whatsappURL(productMessage(cap)); lbWA.textContent = t("menu.ask") + " →"; }
  lb.querySelector('[role="dialog"]').setAttribute("aria-label", lbProduct ? productPhotoLabel(cap) : (lang === "en" ? "Atmosphere gallery" : "Galeri suasana"));
  lb.hidden = false; modalOpen = true; stickybar.classList.add("hide");
  document.getElementById("lb-close").focus();
}
function hideLB(){ lb.hidden = true; lbImg.removeAttribute("src"); modalOpen = false; stickybar.classList.remove("hide"); onScroll(); if(lbReturnFocus?.isConnected) lbReturnFocus.focus(); }
document.addEventListener("click", e=>{
  const product = e.target.closest("[data-product]");
  if(product){
    if(e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    lbProduct = MENU[Number(product.dataset.product)];
    if(lbProduct && lbProduct.available !== false){ e.preventDefault(); showLB(0); }
    return;
  }
  const b = e.target.closest("[data-lb]");
  if(b){ lbProduct = null; showLB(+b.dataset.lb); }
});
document.getElementById("lb-close").onclick = hideLB;
document.getElementById("lb-prev").onclick = (e)=>{ e.stopPropagation(); showLB(lbIdx-1); };
document.getElementById("lb-next").onclick = (e)=>{ e.stopPropagation(); showLB(lbIdx+1); };
lb.addEventListener("click",e=>{ if(e.target===lb) hideLB(); });
document.addEventListener("keydown",e=>{
  if(lb.hidden) return;
  if(e.key==="Escape") hideLB();
  if(e.key==="ArrowLeft") { e.preventDefault(); showLB(lbIdx-1); }
  if(e.key==="ArrowRight") { e.preventDefault(); showLB(lbIdx+1); }
  if(e.key==="Tab"){
    const buttons = [...lb.querySelectorAll('button, a[href]')].filter(el=>!el.hidden);
    const first=buttons[0], last=buttons[buttons.length-1];
    if(e.shiftKey && document.activeElement===first){ e.preventDefault(); last.focus(); }
    else if(!e.shiftKey && document.activeElement===last){ e.preventDefault(); first.focus(); }
  }
});
let tx = 0;
lb.addEventListener("touchstart",e=>{ tx = e.touches[0].clientX; },{passive:true});
lb.addEventListener("touchend",e=>{
  const dx = e.changedTouches[0].clientX - tx;
  if(Math.abs(dx) > 40) showLB(lbIdx + (dx < 0 ? 1 : -1));
},{passive:true});
window.addEventListener("scroll",onScroll,{passive:true});
window.addEventListener("resize",onScroll);
if("ResizeObserver" in window){
  const layoutObserver = new ResizeObserver(()=>{ movePill(); onScroll(); });
  const hero = document.querySelector(".hero");
  if(hero) layoutObserver.observe(hero);
}
const mapButton = document.getElementById("load-map");
if(mapButton){
  mapButton.hidden = false;
  mapButton.addEventListener("click",()=>{
    const frame = document.getElementById("visit-map");
    if(!frame || frame.hasAttribute("src")) return;
    frame.src = frame.dataset.src;
    frame.hidden = false;
    document.getElementById("map-placeholder").hidden = true;
    frame.tabIndex = 0;
    frame.focus();
  });
}
applyLang();
onScroll();
onceNear("faq", loadFAQ);
onceNear("atmosphere", loadAtmos);
