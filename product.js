// Progressive enhancement only: product content and links are already in HTML.
(() => {
  'use strict';
  const fonts=document.getElementById('site-fonts');if(fonts)fonts.media='all';
  const snapshot=JSON.parse(document.getElementById('product-data').textContent), product=snapshot.product;
  const words={
    id:{'nav.home':'Beranda','nav.menu':'Menu','nav.visit':'Lokasi','nav.atmos':'Suasana','nav.info':'Info','nav.contact':'WhatsApp','nav.skip':'Langsung ke menu','hero.cta1':'Lihat Menu','foot.visit':'Kunjungi','foot.contact':'Kontak','visit.hours':'Setiap Hari • 09.00 – 21.00'},
    en:{'nav.home':'Home','nav.menu':'Menu','nav.visit':'Visit','nav.atmos':'Atmosphere','nav.info':'Info','nav.contact':'WhatsApp','nav.skip':'Skip to menu','hero.cta1':'Explore Menu','foot.visit':'Visit','foot.contact':'Contact','visit.hours':'Daily • 9 AM – 9 PM'}
  };
  let language='id';try{if(localStorage.getItem('ruethies-lang')==='en')language='en';}catch{}
  const text=(id,value)=>{document.getElementById(id).textContent=value;};
  function render(){
    const en=language==='en', item=product[language] || product.id, snack=product.slug==='snackbox';
    document.documentElement.lang=language;
    document.querySelectorAll('[data-i18n]').forEach(el=>{if(words[language][el.dataset.i18n])el.textContent=words[language][el.dataset.i18n];});
    for(const key of ['id','en'])document.getElementById('btn-'+key).classList.toggle('on',key===language);
    text('product-title',snack?(en?'Snack Boxes in Cikarang':'Snack Box di Cikarang'):item.n+(en?' in Cikarang':' di Cikarang'));
    text('product-summary',item.d);
    const detail=document.getElementById('product-detail');detail.replaceChildren();
    (item.detail || '').split(/\n\s*\n/).filter(Boolean).forEach(paragraph=>{const p=document.createElement('p');p.textContent=paragraph;detail.appendChild(p);});
    const content={context:en?(snack?'Explore snack boxes at Ruethies Patisserie in Lippo Cikarang. Contact us to discuss your needs.':'Discover '+item.n+' at Ruethies Patisserie in Lippo Cikarang. Ask us about selections and availability.'):(snack?'Snack box merupakan pilihan paket kudapan dalam kotak. Hubungi Ruethies Patisserie di Lippo Cikarang untuk membicarakan kebutuhan Anda.':'Temukan '+item.n+' dalam katalog Ruethies Patisserie di Lippo Cikarang. Hubungi kami untuk menanyakan pilihan dan ketersediaannya.'),terms:en?'Confirm selections, sizes, prices, minimum quantities, lead times, pickup or delivery on WhatsApp.':'Pilihan isi, ukuran, harga, minimum pesanan, tenggat pemesanan, serta pengambilan atau pengantaran dikonfirmasi melalui WhatsApp.',order:en?'How to order':'Cara memesan',location:en?'Find Ruethies Patisserie':'Lokasi Ruethies Patisserie',directions:en?'Get directions →':'Dapatkan rute →',faq:en?'Questions about '+item.n:'Pertanyaan tentang '+item.n,related:en?'More to explore':'Pilihan lainnya'};
    document.querySelectorAll('[data-copy]').forEach(el=>el.textContent=content[el.dataset.copy]);
    const steps=en?['Tell us which product interests you.','Share the quantity and date you need.','Confirm selections, prices and pickup or delivery on WhatsApp.']:['Sebutkan produk yang Anda minati.','Sampaikan jumlah serta tanggal kebutuhan.','Konfirmasikan pilihan, harga, dan cara menerima pesanan melalui WhatsApp.'];
    const list=document.getElementById('order-steps');list.replaceChildren();steps.forEach(step=>{const li=document.createElement('li');li.textContent=step;list.appendChild(li);});
    const wa=document.getElementById('product-wa');wa.textContent=en?'Ask about '+item.n+' on WhatsApp':'Tanyakan '+item.n+' via WhatsApp';wa.href='https://wa.me/6285139281257?text='+encodeURIComponent(en?'Hello Ruethies Patisserie! I would like to ask about '+item.n+'. Please share selections, prices, quantities and dates.':'Halo Ruethies Patisserie! Saya ingin bertanya tentang '+item.n+'. Mohon informasi pilihan, harga, jumlah pesanan, dan tanggal kebutuhan.');
    document.querySelectorAll('[data-contact-wa]').forEach(a=>a.href='https://wa.me/6285139281257?text='+encodeURIComponent(en?'Hello Ruethies Patisserie! I would like to ask about your menu and placing an order.':'Halo Ruethies Patisserie! Saya ingin bertanya tentang menu dan pemesanan.'));
    const english=[['How can I order '+item.n+'?','Contact us on WhatsApp at 0851-3928-1257. Mention the product, quantity and date to confirm selections and prices.'],['Are prices and ordering terms listed on the website?','Please confirm prices, selections, minimum quantities, lead times, pickup or delivery on WhatsApp.'],['Where is Ruethies Patisserie?','Ruko The Neo Patio East No. 51, Lippo, Cikarang Selatan, Bekasi Regency, West Java 17530.']];
    document.querySelectorAll('#product-faq details').forEach((row,i)=>{row.querySelector('summary').textContent=en?english[i][0]:snapshot.faq[i].q;row.querySelector('p').textContent=en?english[i][1]:snapshot.faq[i].a;});
    document.querySelectorAll('.product-photo-frame').forEach((figure,i)=>{figure.querySelector('img').alt=item.n;figure.querySelector('figcaption').textContent=item.n+(i?' · Detail '+i:'');});
  }
  for(const key of ['id','en'])document.getElementById('btn-'+key).onclick=()=>{language=key;try{localStorage.setItem('ruethies-lang',key);}catch{}render();};
  const burger=document.getElementById('burger'),menu=document.getElementById('mmenu');burger.onclick=()=>{menu.hidden=!menu.hidden;burger.setAttribute('aria-expanded',String(!menu.hidden));};
  menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{menu.hidden=true;burger.setAttribute('aria-expanded','false');}));
  document.getElementById('stickybar').classList.add('show');
  render();
})();
