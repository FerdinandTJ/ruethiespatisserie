(() => {
 'use strict';
 const source=document.getElementById('article-body'),editor=document.getElementById('article-visual'),shell=document.getElementById('article-visual-shell');
 if(!source||!editor||!shell||typeof document.execCommand!=='function'||!document.queryCommandSupported('insertText'))return;
 const form=source.closest('form'),mode=document.getElementById('article-body-mode'),html=document.getElementById('article-body-html'),status=document.getElementById('article-editor-status'),toggle=document.getElementById('article-source-toggle'),format=document.getElementById('article-block-format');
 let visual=true,dirty=false,savedRange=null,busy=false,submitting=false,approved=false;
 shell.hidden=false;source.hidden=true;
 const tell=text=>{status.textContent=text;};
 function selection(){const s=getSelection();return s?.rangeCount&&editor.contains(s.anchorNode)&&editor.contains(s.focusNode)?s:null;}
 function remember(){const s=selection();if(s)savedRange=s.getRangeAt(0).cloneRange();}
 function restore(){editor.focus();if(savedRange&&editor.contains(savedRange.commonAncestorContainer)){const s=getSelection();s.removeAllRanges();s.addRange(savedRange);}}
 function sync(){if(visual&&dirty){mode.value='visual';html.value=editor.innerHTML;}else{mode.value='markdown';} }
 function changed(){dirty=true;sync();tell('Ada perubahan isi yang belum disimpan.');remember();}
 editor.addEventListener('input',changed);editor.addEventListener('keyup',remember);editor.addEventListener('mouseup',remember);editor.addEventListener('touchend',remember);
 document.addEventListener('selectionchange',()=>{const s=selection();if(!s)return;remember();for(const b of shell.querySelectorAll('[data-editor-command]'))if(['bold','italic','insertOrderedList','insertUnorderedList'].includes(b.dataset.editorCommand))b.setAttribute('aria-pressed',String(document.queryCommandState(b.dataset.editorCommand)));let n=s.anchorNode.nodeType===1?s.anchorNode:s.anchorNode.parentElement;while(n&&n!==editor&&!['P','H2','H3','BLOCKQUOTE'].includes(n.tagName))n=n.parentElement;format.value=n&&n!==editor?n.tagName.toLowerCase():'p';});
 function command(name,value=null){restore();try{document.execCommand('styleWithCSS',false,false);if(!document.execCommand(name,false,value))tell('Format belum dapat diterapkan. Pilih teks lalu coba lagi.');changed();}catch{tell('Format belum tersedia di browser ini. Gunakan mode teks atau browser terbaru.');}}
 shell.querySelectorAll('[data-editor-command]').forEach(button=>{
  button.addEventListener('mousedown',event=>event.preventDefault());
  button.addEventListener('click',()=>{
   const name=button.dataset.editorCommand;
   if(name==='createLink'){
    restore();const s=selection();if(!s||s.isCollapsed){tell('Blok teks yang akan diberi tautan terlebih dahulu.');return;}
    const raw=prompt('Alamat tautan, misalnya /menu/ atau https://contoh.com');if(raw===null)return;const url=raw.trim();
    if(/[\x00-\x20\x7f\\]/.test(url)||!((url.startsWith('/')&&!url.startsWith('//'))||/^https?:\/\//i.test(url))){tell('Gunakan alamat /halaman/ atau https://website. Tautan ini tidak diterapkan.');return;}
    command(name,url);return;
   }
   command(name);
  });
 });
 format.addEventListener('change',()=>command('formatBlock',format.value));
 // Plain-text paste prevents Word styling, remote images and executable markup entering the editor.
 editor.addEventListener('paste',event=>{event.preventDefault();command('insertText',event.clipboardData?.getData('text/plain')||'');});
 editor.addEventListener('dragover',event=>event.preventDefault());editor.addEventListener('drop',event=>{event.preventDefault();tell('Untuk menambahkan tulisan, salin lalu tempel teks. Foto sampul memakai kolom unggah di bawah.');});
 source.addEventListener('input',()=>{dirty=true;tell('Ada perubahan isi yang belum disimpan.');});
 toggle.addEventListener('click',async()=>{
  if(busy)return;busy=true;toggle.disabled=true;tell('Menyiapkan tampilan isi…');
  try{
   sync();const data=new URLSearchParams({csrf:form.elements.csrf.value,body_mode:visual&&dirty?'visual':'markdown',body:source.value,body_html:editor.innerHTML});
   const response=await fetch('article-editor.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data});const result=await response.json();if(!response.ok||typeof result.body!=='string'||typeof result.html!=='string')throw Error(result.error||'Isi belum dapat diproses.');
   if(visual){source.value=result.body;source.hidden=false;editor.hidden=true;shell.querySelector('.article-visual-toolbar').hidden=true;mode.value='markdown';toggle.textContent='Kembali ke editor visual';source.focus();}
   else{editor.innerHTML=result.html;source.hidden=true;editor.hidden=false;shell.querySelector('.article-visual-toolbar').hidden=false;savedRange=null;toggle.textContent='Buka mode teks';editor.focus();}
   visual=!visual;sync();tell(visual?'Editor visual aktif.':'Mode teks aktif. Perubahan tetap perlu disimpan.');
  }catch(error){tell(error.message||'Belum dapat mengganti mode. Isi Anda tetap tersedia; coba lagi.');}
  finally{busy=false;toggle.disabled=false;}
 });
 form.addEventListener('submit',async event=>{
  if(busy){event.preventDefault();tell('Tunggu pemeriksaan isi selesai sebelum menyimpan.');return;}
  sync();
  if(visual&&dirty&&!approved){
   event.preventDefault();busy=true;const submitter=event.submitter;tell('Memeriksa format isi…');
   // Validate before navigation: an unsupported format leaves the entire visual text editable.
   try{
    const response=await fetch('article-editor.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:form.elements.csrf.value,body_mode:'visual',body_html:editor.innerHTML})});
    const result=await response.json();if(!response.ok)throw Error(result.error||'Isi belum dapat diperiksa.');
    approved=true;busy=false;form.requestSubmit(submitter);
   }catch(error){tell(error.message||'Isi belum dapat diperiksa. Tulisan Anda tetap ada; coba lagi.');}
   finally{busy=false;approved=false;}
   return;
  }
  submitting=event.submitter?.getAttribute('formaction')!=='article-preview.php';
 },true);
 form.addEventListener('formdata',event=>{sync();event.formData.set('body_mode',mode.value);event.formData.set('body_html',html.value);});
 addEventListener('beforeunload',event=>{if(dirty&&!submitting){event.preventDefault();event.returnValue='';}});
 tell('Editor visual aktif. Tulisan disimpan setelah Anda memilih Simpan.');
})();
