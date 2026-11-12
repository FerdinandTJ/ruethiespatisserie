// Public progressive enhancement. Order drafts stay in the current DOM only.
(() => {
  'use strict';
  const dataElement=document.getElementById('profile-data');if(!dataElement)return;
  const data=JSON.parse(dataElement.textContent),product=data.product,carousel=window.RuethiesCarousel?.(document.getElementById('product-carousel'));
  const fonts=document.getElementById('site-fonts');if(fonts)fonts.media='all';
  let language='id';try{if(localStorage.getItem('ruethies-lang')==='en')language='en';}catch{}
  const phrase=(id,en)=>language==='en'?en:id;
  function answer(node,raw){
    node.replaceChildren();const pattern=/\[([^\[\]]{1,80})\]\((https?:\/\/[^\s()<>\"]{1,300})\)/g;let last=0;
    for(const match of raw.matchAll(pattern)){node.appendChild(document.createTextNode(raw.slice(last,match.index)));const link=document.createElement('a');link.textContent=match[1];link.href=match[2];link.target='_blank';link.rel='noopener';node.appendChild(link);last=match.index+match[0].length;}
    node.appendChild(document.createTextNode(raw.slice(last)));
  }
  function todayWIB(){const parts=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());const values=Object.fromEntries(parts.map(p=>[p.type,p.value]));return values.year+'-'+values.month+'-'+values.day;}
  const quantity=document.getElementById('order-quantity'),date=document.getElementById('order-date'),note=document.getElementById('order-note');
  const orderLinks=product?[document.getElementById('order-whatsapp'),...document.querySelectorAll('[data-order-link]')].filter(Boolean):[];
  function updateOrder(report=false){
    if(!product)return true;
    date.min=todayWIB();const count=Number(quantity.value);
    quantity.setCustomValidity(Number.isSafeInteger(count)&&count>0?'':phrase('Masukkan jumlah berupa bilangan bulat positif.','Enter a positive whole number.'));
    date.setCustomValidity(date.value&&date.value<date.min?phrase('Tanggal kebutuhan tidak boleh sebelum hari ini (WIB).','The date cannot be before today in Jakarta time.'):'');
    note.setCustomValidity(note.value.length>500?phrase('Catatan maksimal 500 karakter.','Notes must be 500 characters or fewer.'):'');
    const fields=[quantity,date,note],invalid=fields.find(el=>!el.validity.valid);
    if(invalid){if(report)invalid.reportValidity();return false;}
    const name=(product[language]||product.id).n;
    const message=language==='en'?['Hello Ruethies Patisserie! I would like to order '+name+'.','Product: '+data.canonical,'Quantity: '+count]:['Halo Ruethies Patisserie! Saya ingin memesan '+name+'.','Produk: '+data.canonical,'Jumlah: '+count];
    if(date.value)message.push(phrase('Tanggal kebutuhan: ','Date needed: ')+date.value);
    if(note.value.trim())message.push(phrase('Catatan: ','Notes: ')+note.value.trim());
    message.push(phrase('Mohon konfirmasi ketersediaan, harga, dan cara menerima pesanan.','Please confirm availability, prices and how to receive the order.'));
    const url='https://wa.me/6285139281257?text='+encodeURIComponent(message.join('\n'));orderLinks.forEach(link=>link.href=url);return true;
  }
  if(product){document.querySelector('.rp-order-fields').hidden=false;[quantity,date,note].forEach(el=>el.addEventListener('input',()=>updateOrder()));orderLinks.forEach(link=>link.addEventListener('click',event=>{if(!updateOrder(true))event.preventDefault();}));}
  function render(){
    document.documentElement.lang=language;
    document.querySelectorAll('[data-id][data-en]').forEach(el=>el.textContent=el.dataset[language]);
    document.querySelectorAll('[data-alt-id]').forEach(el=>el.alt=language==='en'?el.dataset.altEn:el.dataset.altId);
    document.querySelectorAll('[data-language]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.language===language)));
    const general='https://wa.me/6285139281257?text='+encodeURIComponent(phrase('Halo Ruethies Patisserie! Saya ingin bertanya tentang menu dan pemesanan.','Hello Ruethies Patisserie! I would like to ask about your menu and placing an order.'));
    document.querySelectorAll('[data-general-wa]').forEach(link=>link.href=general);
    if(!product)document.querySelectorAll('[data-order-link]').forEach(link=>link.href=general);
    document.querySelectorAll('[data-faq]').forEach(node=>{const row=data.faq?.[Number(node.dataset.faq)];if(row)answer(node,language==='en'?(row.ae||row.a):row.a);});
    if(product){
      const item=product[language]||product.id,detail=document.getElementById('rp-product-detail');detail.replaceChildren();
      (item.detail||item.d).split(/\n\s*\n/).filter(Boolean).forEach(text=>{const p=document.createElement('p');p.textContent=text;detail.appendChild(p);});
      document.querySelectorAll('.rp-product-gallery .product-photo-frame').forEach((figure,i)=>{figure.querySelector('img').alt=item.n;figure.querySelector('figcaption').textContent=item.n+(i?' · Detail '+i:'');});
      updateOrder();
    }
    document.querySelectorAll('.rp-gallery-photo img').forEach((img,i)=>{const row=data.gallery?.[i];if(row)img.alt=(row[language]||row.id).cap;});
    carousel?.localize(language==='en');
    if(dialog){dialog.setAttribute('aria-label',phrase('Galeri Ruethies','Ruethies Gallery'));document.getElementById('rp-gallery-close').setAttribute('aria-label',phrase('Tutup foto','Close photo'));dialog.querySelector('[data-gallery-prev]').setAttribute('aria-label',phrase('Foto sebelumnya','Previous photo'));dialog.querySelector('[data-gallery-next]').setAttribute('aria-label',phrase('Foto berikutnya','Next photo'));}
    burger?.setAttribute('aria-label',phrase('Menu navigasi','Navigation menu'));
    const search=document.getElementById('rp-search');if(search)search.placeholder=phrase('Brownies, pastel, snackbox…','Brownies, savoury bites, snack boxes…');
  }
  document.querySelectorAll('[data-language]').forEach(button=>{button.disabled=false;button.addEventListener('click',()=>{language=button.dataset.language;try{localStorage.setItem('ruethies-lang',language);}catch{}render();});});
  const burger=document.getElementById('rp-burger'),menu=document.getElementById('rp-mobile-menu');
  if(burger&&menu){burger.hidden=false;menu.hidden=true;burger.addEventListener('click',()=>{menu.hidden=!menu.hidden;burger.setAttribute('aria-expanded',String(!menu.hidden));});menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{menu.hidden=true;burger.setAttribute('aria-expanded','false');}));document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!menu.hidden){menu.hidden=true;burger.setAttribute('aria-expanded','false');burger.focus();}});}
  const loadMap=document.getElementById('rp-map-load');if(loadMap){loadMap.hidden=false;loadMap.addEventListener('click',()=>{const frame=document.getElementById('rp-map-frame');frame.src='https://www.google.com/maps?q=-6.3443722,107.1490519&z=16&output=embed';frame.hidden=false;document.getElementById('rp-map-placeholder').hidden=true;});}
  const dialog=document.getElementById('rp-gallery-dialog');let galleryIndex=0,returnFocus=null;
  function showGallery(index){
    const items=data.gallery||[];if(!items.length)return;
    galleryIndex=(index+items.length)%items.length;const row=items[galleryIndex],image=document.getElementById('rp-gallery-image');image.src='/'+(row.imgFull||row.img);image.alt=(row[language]||row.id).cap;document.getElementById('rp-gallery-caption').textContent=image.alt;document.getElementById('rp-gallery-count').textContent=(galleryIndex+1)+' / '+items.length;
    dialog.querySelectorAll('[data-gallery-prev],[data-gallery-next]').forEach(button=>button.hidden=items.length<2);
    if(!dialog.open){returnFocus=document.activeElement;document.body.classList.add('rp-dialog-open');dialog.showModal();}
  }
  if(dialog&&typeof dialog.showModal==='function'){
    document.querySelectorAll('[data-gallery-index]').forEach(link=>link.addEventListener('click',event=>{if(event.button||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;event.preventDefault();showGallery(Number(link.dataset.galleryIndex));}));
    document.getElementById('rp-gallery-close').addEventListener('click',()=>dialog.close());dialog.querySelector('[data-gallery-prev]').addEventListener('click',()=>showGallery(galleryIndex-1));dialog.querySelector('[data-gallery-next]').addEventListener('click',()=>showGallery(galleryIndex+1));
    dialog.addEventListener('keydown',event=>{if(event.key==='ArrowLeft'||event.key==='ArrowRight'){event.preventDefault();showGallery(galleryIndex+(event.key==='ArrowRight'?1:-1));}});
    dialog.addEventListener('click',event=>{const rect=dialog.getBoundingClientRect();if(event.target===dialog&&(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom))dialog.close();});
    dialog.addEventListener('close',()=>{document.getElementById('rp-gallery-image').removeAttribute('src');document.body.classList.remove('rp-dialog-open');returnFocus?.focus();});
  }
  render();
})();
