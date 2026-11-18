(() => {
 'use strict';const title=document.getElementById('article-title'),slug=document.getElementById('article-slug'),description=document.getElementById('article-description');if(!title||!slug||!description)return;
 let automatic=slug.value==='';slug.addEventListener('input',()=>automatic=slug.value==='');title.addEventListener('input',()=>{if(automatic)slug.value=title.value.normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').slice(0,100).replace(/^-|-$/g,'');});
 const count=()=>document.getElementById('article-description-count').textContent=Array.from(description.value).length+' dari 155 karakter';description.addEventListener('input',count);count();

 const form=title.closest('form'),file=form.querySelector('input[type=file]');let sending=false;
 form.addEventListener('submit',async event=>{
  if(event.submitter?.getAttribute('formaction')==='article-preview.php'||!file.files[0])return;
  event.preventDefault();if(sending)return;const image=file.files[0];
  if(!['image/jpeg','image/png','image/webp'].includes(image.type)||image.size>2*1024*1024){alert('Sampul harus JPG/PNG/WebP, maksimal 2 MB.');return;}
  sending=true;const buttons=[...form.querySelectorAll('button')];buttons.forEach(button=>button.disabled=true);
  try{const data=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=reader.onabort=reject;reader.readAsDataURL(image);});let hidden=form.querySelector('[name=article_cover_b64]');if(!hidden){hidden=document.createElement('input');hidden.type='hidden';hidden.name='article_cover_b64';form.append(hidden);}hidden.value=data;file.disabled=true;form.enctype='application/x-www-form-urlencoded';form.submit();}
  catch(error){sending=false;buttons.forEach(button=>button.disabled=false);alert('Foto belum dapat dibaca. Pilih ulang foto lalu coba lagi.');}
 });
})();
