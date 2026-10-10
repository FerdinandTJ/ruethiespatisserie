const WA_NUMBER = "6281234567890"; // dummy, ganti nomor asli
const IMG = (id) => `https://images.unsplash.com/${id}?q=80&w=700&auto=format&fit=crop`;

const MENU = [
  // CAKERY
  { cat:"cakery", badge:"Best Seller", tag:{id:"Manis • Lembut",en:"Sweet • Delicate"}, img:IMG("photo-1578985545062-69928b1d9587"),
    id:{n:"Opera Cake Noir",d:"Lapis almond, ganache dark chocolate, glaze mengilap."},
    en:{n:"Noir Opera Cake",d:"Almond layers, dark ganache, mirror glaze."} },
  { cat:"cakery", badge:"Signature", tag:{id:"Manis • Segar",en:"Sweet • Fresh"}, img:IMG("photo-1565958011703-44f9829ba187"),
    id:{n:"Strawberry Fraisier",d:"Strawberry segar, mousseline vanilla, sponge lembut."},
    en:{n:"Strawberry Fraisier",d:"Fresh strawberries, vanilla mousseline, soft sponge."} },
  { cat:"cakery", badge:"", tag:{id:"Manis • Lumer",en:"Sweet • Molten"}, img:IMG("photo-1533134242443-d4fd215305ad"),
    id:{n:"Burnt Basque Cheesecake",d:"Creamy gosong atas, lumer di tengah. Wajib coba."},
    en:{n:"Burnt Basque Cheesecake",d:"Caramelised top, molten centre. A must-try."} },
  // COFFEE
  { cat:"coffee", badge:"Signature", tag:{id:"Hangat • Creamy",en:"Warm • Creamy"}, img:IMG("photo-1541167760496-1628856ab772"),
    id:{n:"Café Crème Brûlée Latte",d:"Espresso + susu creamy, gula brûlée di atas."},
    en:{n:"Crème Brûlée Latte",d:"Espresso with creamy milk, torched sugar top."} },
  { cat:"coffee", badge:"", tag:{id:"Bold • Pekat",en:"Bold • Intense"}, img:IMG("photo-1510591509098-f4fdc6d0ff04"),
    id:{n:"Espresso Noisette",d:"Double shot pekat dengan sentuhan hazelnut."},
    en:{n:"Noisette Espresso",d:"Bold double shot with a hazelnut touch."} },
  { cat:"coffee", badge:"", tag:{id:"Segar • Ringan",en:"Fresh • Light"}, img:IMG("photo-1515823064-d35759e58259"),
    id:{n:"Iced Lumière Matcha",d:"Matcha premium, susu oat, es bening. Segar elegan."},
    en:{n:"Iced Lumière Matcha",d:"Premium matcha, oat milk over clear ice."} },
  // SAVOURY
  { cat:"savoury", badge:"Favorit", tag:{id:"Gurih • Renyah",en:"Savoury • Crisp"}, img:IMG("photo-1601050690597-df0568f70950"),
    id:{n:"Pastel Ayam Ragout",d:"Kulit renyah berlapis, isi ragout ayam creamy."},
    en:{n:"Chicken Ragout Pastel",d:"Flaky crust, creamy chicken ragout filling."} },
  { cat:"savoury", badge:"", tag:{id:"Gurih • Keemasan",en:"Savoury • Golden"}, img:IMG("photo-1630384060421-cb20d0e0649d"),
    id:{n:"Kroket Kentang Sapi",d:"Kentang halus, daging sapi, goreng keemasan."},
    en:{n:"Beef Potato Kroket",d:"Silky potato, beef, golden-fried."} },
  { cat:"savoury", badge:"Best Seller", tag:{id:"Gurih • Best Nongkrong",en:"Savoury • Crowd Favourite"}, img:IMG("photo-1606755962773-d324e0a13086"),
    id:{n:"Risol Mayo Smoked Beef",d:"Smoked beef + mayo + telur, kulit tipis renyah."},
    en:{n:"Smoked Beef Risol Mayo",d:"Smoked beef, mayo & egg in crisp thin skin."} },
];

const I18N = {
  id:{ "nav.home":"Beranda","nav.menu":"Menu","nav.visit":"Lokasi","nav.atmos":"Suasana","nav.reserve":"Reservasi",
    "hero.eyebrow":"N°01 — Patisserie Artisan, Cikarang","hero.title":'Dipanggang dengan <em>Kelembutan</em>',
    "hero.french":"croissants · cakery · pastel — depuis Cikarang",
    "hero.sub":"Croissant berlapis, cakery lembut, dan savoury favorit — pastel, kroket, risol — diseduh dengan espresso pilihan. Elegan, hangat, #MadeWithDelicacy.",
    "hero.cta1":"Lihat Menu","hero.cta2":"Reservasi via WhatsApp","hero.p1":"Ruko Neo Patio East NO.51","hero.p2":"Fresh dari oven tiap pagi","hero.p3":"Buka tiap hari",
    "hero.b1":"Fresh Daily","hero.b2":"Signature Croissant",
    "sig.eyebrow":"01 — Signature","sig.title":'Favorit yang paling <em>Dirindukan</em>',"sig.sub":"Tiga yang wajib dicoba saat pertama datang.",
    "quote.text":'"Kami tidak menjual kue. Kami menjual <em>momen lembut</em> yang bisa dimakan."',
    "menu.eyebrow":"02 — Menu","menu.title":'Pilih <em>Cita Rasamu</em>',"menu.t1":"Cakery","menu.t2":"Coffee & Drinks","menu.t3":"Savoury Patisserie",
    "menu.note":"Pastel • Kroket • Risol digoreng dadakan — mohon tunggu ±10 menit. Tanya ketersediaan harian via WhatsApp.",
    "atmos.eyebrow":"03 — Suasana","atmos.title":'Sudut yang <em>Fotogenik</em>',"atmos.c1":"Interior hangat — kayu & cahaya sore","atmos.c2":"Dibuat tangan, dengan teliti","atmos.c3":"Detail berlapis yang renyah",
    "visit.eyebrow":"04 — Kunjungi Kami","visit.title":'Mampir, <em>Duduk Manis</em>',"visit.hours":"Setiap Hari • 09.00 – 21.00",
    "visit.dir":"Buka di Google Maps","visit.copy":"Salin Alamat","visit.res":"Reservasi Meja",
    "wa.title":"Reservasi Meja","wa.sub":"Isi detail, kami teruskan ke WhatsApp.","wa.name":"Nama","wa.pax":"Tamu","wa.date":"Tanggal","wa.time":"Jam","wa.note":"Catatan","wa.send":"Kirim ke WhatsApp" },
  en:{ "nav.home":"Home","nav.menu":"Menu","nav.visit":"Visit","nav.atmos":"Atmosphere","nav.reserve":"Reserve",
    "hero.eyebrow":"N°01 — Artisan Patisserie, Cikarang","hero.title":'Baked with <em>Délicatesse</em>',
    "hero.french":"croissants · cakery · pastel — depuis Cikarang",
    "hero.sub":"Flaky croissants, delicate cakery and beloved savouries — pastel, kroket, risol — paired with fine espresso. Elegant, warm, #MadeWithDelicacy.",
    "hero.cta1":"Explore Menu","hero.cta2":"Reserve via WhatsApp","hero.p1":"Ruko Neo Patio East N°51","hero.p2":"Fresh from the oven daily","hero.p3":"Open daily",
    "hero.b1":"Fresh Daily","hero.b2":"Signature Croissant",
    "sig.eyebrow":"01 — Signature","sig.title":'Most <em>Missed</em> Favourites',"sig.sub":"Three must-tries on your first visit.",
    "quote.text":'"We don\'t sell cake. We sell <em>tender moments</em> you can taste."',
    "menu.eyebrow":"02 — Menu","menu.title":'Choose Your <em>Craving</em>',"menu.t1":"Cakery","menu.t2":"Coffee & Drinks","menu.t3":"Savoury Patisserie",
    "menu.note":"Pastel • Kroket • Risol are fried to order — approx. 10 min. Ask daily availability via WhatsApp.",
    "atmos.eyebrow":"03 — Atmosphere","atmos.title":'Most <em>Photogenic</em> Corners',"atmos.c1":"Warm interior — wood & evening light","atmos.c2":"Handcrafted with care","atmos.c3":"Crisp layered details",
    "visit.eyebrow":"04 — Visit Us","visit.title":'Drop by, <em>Sit Pretty</em>',"visit.hours":"Daily • 9 AM – 9 PM",
    "visit.dir":"Open in Google Maps","visit.copy":"Copy Address","visit.res":"Reserve a Table",
    "wa.title":"Table Reservation","wa.sub":"Fill in, we'll forward to WhatsApp.","wa.name":"Name","wa.pax":"Guests","wa.date":"Date","wa.time":"Time","wa.note":"Notes","wa.send":"Send to WhatsApp" }
};

let lang = localStorage.getItem("ruethies-lang") || new URLSearchParams(location.search).get("lang") || "en";
let activeCat = "cakery";

function t(k){ return (I18N[lang] && I18N[lang][k]) || I18N.id[k] || k; }

function applyLang(){
  document.documentElement.lang = lang;
  document.querySelectorAll("[data-i18n]").forEach(el=>{ el.textContent = t(el.dataset.i18n); });
  document.querySelectorAll("[data-i18n-html]").forEach(el=>{ el.innerHTML = t(el.dataset.i18nHtml); });
  document.getElementById("btn-id").classList.toggle("on", lang==="id");
  document.getElementById("btn-en").classList.toggle("on", lang==="en");
  renderMenus();
}

function cardHTML(m){
  const v = m[lang] || m.id;
  return `<article class="card rv"><div class="ph"><img loading="lazy" src="${m.img}" alt="${v.n}" /></div>
    <div class="bd">${m.badge?`<span class="chip">${m.badge}</span>`:""}<h3>${v.n}</h3><p>${v.d}</p></div></article>`;
}

function rowHTML(m, i){
  const v = m[lang] || m.id;
  const tag = m.tag ? (m.tag[lang] || m.tag.id) : "";
  const num = String(i+1).padStart(2,"0");
  return `<article class="mrow rv">
    <span class="mnum">${num}</span>
    <div class="mbody"><h3>${v.n}</h3><p>${v.d}</p>
      <div class="mtags">${m.badge?`<span class="chip">${m.badge}</span>`:""}${tag?`<span class="tagline">${tag}</span>`:""}</div></div>
    <span class="mdots" aria-hidden="true"></span>
    <span class="mpeek"><img loading="lazy" src="${m.img}" alt="" /></span>
  </article>`;
}

function renderMenus(){
  const sig = [MENU[0], MENU[3], MENU[8]];
  document.getElementById("signature").innerHTML = sig.map(cardHTML).join("");
  const items = MENU.filter(m=>m.cat===activeCat);
  document.getElementById("menugrid").innerHTML = items.map(rowHTML).join("");
  movePill();
  observeReveals();
}

const io = new IntersectionObserver(es=>es.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add("in"); io.unobserve(e.target); } }),{threshold:.12});
function observeReveals(){ document.querySelectorAll(".rv:not(.in)").forEach(el=>io.observe(el)); }

function movePill(){
  const btn = document.querySelector(`.tab[data-cat="${activeCat}"]`);
  const pill = document.getElementById("pill");
  if(!btn) return;
  pill.style.left = btn.offsetLeft+"px"; pill.style.width = btn.offsetWidth+"px";
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

// WA modal
const bd = document.getElementById("backdrop");
const stickybar = document.getElementById("stickybar");
const openWA = ()=>{ bd.hidden=false; stickybar.classList.add("hide"); document.getElementById("f-name").focus(); };
const closeWA = ()=>{ bd.hidden=true; stickybar.classList.remove("hide"); onScroll(); };
document.querySelectorAll("[data-open-wa]").forEach(b=>b.onclick=openWA);
document.getElementById("closewa").onclick=closeWA;
bd.addEventListener("click",e=>{ if(e.target===bd) closeWA(); });
document.addEventListener("keydown",e=>{ if(e.key==="Escape" && !bd.hidden) closeWA(); });
document.getElementById("f-date").valueAsDate = new Date();
document.getElementById("sendwa").onclick=()=>{
  const n=document.getElementById("f-name").value||"-", p=document.getElementById("f-pax").value,
        d=document.getElementById("f-date").value, tm=document.getElementById("f-time").value,
        no=document.getElementById("f-note").value||"-";
  const msg = lang==="id"
    ? `Halo Ruethies Patisserie! Saya ${n} — reservasi ${p} orang, ${d} pukul ${tm}. Catatan: ${no}. Terima kasih! #MadeWithDelicacy`
    : `Hello Ruethies Patisserie! I'm ${n} — reserving for ${p}, ${d} at ${tm}. Notes: ${no}. Thank you! #MadeWithDelicacy`;
  window.open(`https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(msg)}`,"_blank");
};
document.getElementById("copyaddr").onclick=(e)=>{
  navigator.clipboard?.writeText("Ruko The Neo Patio East NO. 51, Lippo, Cikarang Sel., Kabupaten Bekasi, Jawa Barat 17530");
  e.target.textContent = lang==="id" ? "Tersalin ✓" : "Copied ✓";
  setTimeout(applyLang,1500);
};
window.addEventListener("resize",movePill);
function onScroll(){
  document.getElementById("nav").style.boxShadow = scrollY>24?"0 12px 32px -18px rgba(61,0,0,.25)":"none";
  const hero = document.querySelector(".hero");
  const past = hero ? scrollY > hero.offsetTop + hero.offsetHeight - 120 : scrollY > 600;
  stickybar.classList.toggle("show", past);
}
window.addEventListener("scroll",onScroll,{passive:true});
applyLang();
onScroll();
