(() => {
 'use strict';
 const source=document.getElementById('article-body'),editor=document.getElementById('article-visual'),shell=document.getElementById('article-visual-shell');
 if(!source||!editor||!shell||typeof document.execCommand!=='function'||!document.queryCommandSupported('insertText'))return;
 const form=source.closest('form'),mode=document.getElementById('article-body-mode'),html=document.getElementById('article-body-html'),status=document.getElementById('article-editor-status'),toggle=document.getElementById('article-source-toggle'),format=document.getElementById('article-block-format');
 let visual=true,dirty=false,savedRange=null,busy=false,submitting=false,approved=false;
 shell.hidden=false;source.hidden=true;toggle.hidden=false;
 const tell=text=>{status.textContent=text;};
 function selection(){const s=getSelection();return s?.rangeCount&&editor.contains(s.anchorNode)&&editor.contains(s.focusNode)?s:null;}
 function remember(){const s=selection();if(s)savedRange=s.getRangeAt(0).cloneRange();}
 function restore(){editor.focus();if(savedRange&&editor.contains(savedRange.commonAncestorContainer)){const s=getSelection();s.removeAllRanges();s.addRange(savedRange);}}
 function sync(){if(visual&&dirty){mode.value='visual';html.value=editor.innerHTML;}else{mode.value='markdown';} }
 function changed(){dirty=true;sync();tell('Ada perubahan. Jangan lupa klik Simpan.');remember();}
 editor.addEventListener('input',changed);editor.addEventListener('keyup',remember);editor.addEventListener('mouseup',remember);editor.addEventListener('touchend',remember);
 document.addEventListener('selectionchange',()=>{const s=selection();if(!s)return;remember();for(const b of shell.querySelectorAll('[data-editor-command]'))if(['bold','italic','insertOrderedList','insertUnorderedList'].includes(b.dataset.editorCommand))b.setAttribute('aria-pressed',String(document.queryCommandState(b.dataset.editorCommand)));let n=s.anchorNode.nodeType===1?s.anchorNode:s.anchorNode.parentElement;while(n&&n!==editor&&!['P','H2','H3','BLOCKQUOTE'].includes(n.tagName))n=n.parentElement;format.value=n&&n!==editor?n.tagName.toLowerCase():'p';});
 function command(name,value=null){restore();try{document.execCommand('styleWithCSS',false,false);if(!document.execCommand(name,false,value))tell('Format belum bisa dipakai. Blok teksnya, lalu coba lagi.');changed();}catch{tell('Browser ini belum mendukung formatnya. Coba mode teks atau update browser.');}}
 shell.querySelectorAll('[data-editor-command]').forEach(button=>{
  button.addEventListener('mousedown',event=>event.preventDefault());
  button.addEventListener('click',()=>{
   const name=button.dataset.editorCommand;
   if(name==='createLink'){
    restore();const s=selection();if(!s||s.isCollapsed){tell('Blok dulu teks yang mau diberi link.');return;}
    const raw=prompt('Masukkan link, misalnya /menu/ atau https://contoh.com');if(raw===null)return;const url=raw.trim();
    if(/[\x00-\x20\x7f\\]/.test(url)||!((url.startsWith('/')&&!url.startsWith('//'))||/^https?:\/\//i.test(url))){tell('Link belum valid. Pakai /halaman/ atau https://website.');return;}
    command(name,url);return;
   }
   command(name);
  });
 });
 // Simple tables share the existing Markdown table format, with native undo history.
 const tableActions=document.getElementById('article-table-actions'),tablePicker=document.getElementById('article-table-picker');let tableSerial=0;
 function tableContext(){
  const range=selection()?.getRangeAt(0)||savedRange;if(!range||!editor.contains(range.commonAncestorContainer))return null;
  const node=range.startContainer.nodeType===1?range.startContainer:range.startContainer.parentElement,cell=node.closest?.('td,th'),table=cell?.closest('table');
  if(!table||!editor.contains(table))return null;return {table,row:cell.parentElement.rowIndex,col:cell.cellIndex};
 }
 function tableState(){tableActions.hidden=!visual||!tableContext();}
 document.addEventListener('selectionchange',tableState);
 function focusCell(table,row=1,col=0){const cell=table.rows[Math.min(row,table.rows.length-1)]?.cells[col]||table.rows[0]?.cells[0];if(!cell)return;editor.focus();const r=document.createRange();r.selectNodeContents(cell);r.collapse(true);const s=getSelection();s.removeAllRanges();s.addRange(r);remember();tableState();}
 function insertTableHTML(table,old=null,row=1,col=0){
  const key='table-'+(++tableSerial);table.dataset.editorTable=key;
  if(old){const r=document.createRange();r.selectNode(old);savedRange=r;}
  command('insertHTML',table.outerHTML+(old?'':'<p><br></p>'));
  const inserted=editor.querySelector('[data-editor-table="'+key+'"]');if(inserted){inserted.removeAttribute('data-editor-table');focusCell(inserted,row,col);}
 }
 document.getElementById('article-table-insert').addEventListener('click',()=>{
  const cols=Number(document.getElementById('article-table-columns').value),rows=Number(document.getElementById('article-table-rows').value);
  if(!Number.isInteger(cols)||cols<1||cols>6||!Number.isInteger(rows)||rows<1||rows>20){tell('Pilih 1–6 kolom dan 1–20 baris isi.');return;}
  if(tableContext()){tell('Klik paragraf di luar tabel dulu untuk membuat tabel baru.');return;}
  const table=document.createElement('table'),head=table.createTHead().insertRow(),body=table.createTBody();
  for(let c=0;c<cols;c++){const th=document.createElement('th');th.scope='col';th.textContent='Kolom '+(c+1);head.append(th);}
  for(let r=0;r<rows;r++){const tr=body.insertRow();for(let c=0;c<cols;c++)tr.insertCell().append(document.createElement('br'));}
  insertTableHTML(table);tablePicker.open=false;tell('Tabel siap. Klik sel untuk mengisi, atau pilih kontrol baris dan kolom.');
 });
 tableActions.querySelectorAll('[data-table-action]').forEach(button=>{
  button.addEventListener('mousedown',event=>event.preventDefault());button.addEventListener('click',()=>{
   const ctx=tableContext();if(!ctx){tell('Klik sel tabel yang mau diubah dulu.');return;}
   const {table,row,col}=ctx,copy=table.cloneNode(true),action=button.dataset.tableAction,width=table.rows[0]?.cells.length;
   if(!width||Array.from(table.rows).some(r=>r.cells.length!==width||Array.from(r.cells).some(c=>c.colSpan!==1||c.rowSpan!==1))){tell('Kontrol ini hanya untuk tabel sederhana tanpa gabungan sel.');return;}
   if(action==='table-delete'){
    if(!confirm('Hapus tabel ini beserta isinya?'))return;const r=document.createRange();r.selectNode(table);savedRange=r;command('delete');savedRange=null;tableState();tell('Tabel dihapus. Klik Undo kalau ingin mengembalikannya.');return;
   }
   let targetRow=row,targetCol=col;
   if(action==='row-add'){
    if(copy.rows.length>=21){tell('Maksimal 20 baris isi per tabel.');return;}
    const tr=document.createElement('tr');for(let c=0;c<width;c++){const td=document.createElement('td');td.append(document.createElement('br'));tr.append(td);}
    if(row===0){let body=copy.tBodies[0]||copy.createTBody();body.insertBefore(tr,body.firstChild);}else copy.rows[row].after(tr);targetRow=row+1;
   }
   if(action==='column-add'){
    if(width>=6){tell('Maksimal 6 kolom per tabel supaya tetap nyaman dibaca.');return;}
    Array.from(copy.rows).forEach((tr,i)=>{const cell=document.createElement(i===0?'th':'td');if(i===0){cell.scope='col';cell.textContent='Kolom baru';}else cell.append(document.createElement('br'));tr.cells[col].after(cell);});targetCol=col+1;
   }
   if(action==='row-delete'){
    if(row===0){tell('Baris atas adalah judul kolom. Klik selnya untuk mengganti judul.');return;}
    if(copy.rows.length<=2){tell('Sisakan satu baris isi, atau pilih Hapus tabel.');return;}
    if(table.rows[row].textContent.trim()&&!confirm('Hapus baris ini beserta isinya?'))return;copy.deleteRow(row);targetRow=Math.min(row,copy.rows.length-1);
   }
   if(action==='column-delete'){
    if(width<=1){tell('Sisakan satu kolom, atau pilih Hapus tabel.');return;}
    if(Array.from(table.rows).some(tr=>tr.cells[col].textContent.trim())&&!confirm('Hapus kolom ini beserta isinya?'))return;Array.from(copy.rows).forEach(tr=>tr.deleteCell(col));targetCol=Math.min(col,width-2);
   }
   insertTableHTML(copy,table,targetRow,targetCol);tell('Tabel diubah. Jangan lupa klik Simpan.');
  });
 });
 editor.addEventListener('keydown',event=>{
  if(event.key!=='Tab')return;const ctx=tableContext();if(!ctx)return;const cells=Array.from(ctx.table.rows).flatMap(row=>Array.from(row.cells)),current=ctx.table.rows[ctx.row].cells[ctx.col],next=cells[cells.indexOf(current)+(event.shiftKey?-1:1)];
  if(next){event.preventDefault();focusCell(ctx.table,next.parentElement.rowIndex,next.cellIndex);}
 });

 format.addEventListener('change',()=>command('formatBlock',format.value));
 // Plain-text paste prevents Word styling, remote images and executable markup entering the editor.
 editor.addEventListener('paste',event=>{event.preventDefault();command('insertText',event.clipboardData?.getData('text/plain')||'');});
 editor.addEventListener('dragover',event=>event.preventDefault());editor.addEventListener('drop',event=>{event.preventDefault();tell('Untuk menambah tulisan, copy lalu paste teksnya. Foto sampul bisa diunggah di bawah.');});
 source.addEventListener('input',()=>{dirty=true;tell('Ada perubahan. Jangan lupa klik Simpan.');});
 toggle.addEventListener('click',async()=>{
  if(busy)return;busy=true;toggle.disabled=true;tell('Sebentar, menyiapkan editor…');
  try{
   sync();const data=new URLSearchParams({csrf:form.elements.csrf.value,body_mode:visual&&dirty?'visual':'markdown',body:source.value,body_html:editor.innerHTML});
   const response=await fetch('article-editor.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data});const result=await response.json();if(!response.ok||typeof result.body!=='string'||typeof result.html!=='string')throw Error(result.error||'Tulisan belum bisa diproses.');
   if(visual){source.value=result.body;source.hidden=false;editor.hidden=true;shell.querySelector('.article-visual-toolbar').hidden=true;mode.value='markdown';toggle.textContent='Kembali ke editor visual';source.focus();}
   else{editor.innerHTML=result.html;source.hidden=true;editor.hidden=false;shell.querySelector('.article-visual-toolbar').hidden=false;savedRange=null;toggle.textContent='Buka mode teks';editor.focus();}
   visual=!visual;tableState();sync();tell(visual?'Editor visual siap dipakai.':'Mode teks aktif. Jangan lupa simpan perubahannya.');
  }catch(error){tell(error.message||'Belum bisa ganti mode. Tulisan tetap ada, coba lagi.');}
  finally{busy=false;toggle.disabled=false;}
 });
 form.addEventListener('submit',async event=>{
  if(busy){event.preventDefault();tell('Tunggu sebentar, tulisan sedang dicek.');return;}
  sync();
  if(visual&&dirty&&!approved){
   event.preventDefault();busy=true;const submitter=event.submitter;tell('Mengecek tulisan…');
   // Validate before navigation: an unsupported format leaves the entire visual text editable.
   try{
    const response=await fetch('article-editor.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:form.elements.csrf.value,body_mode:'visual',body_html:editor.innerHTML})});
    const result=await response.json();if(!response.ok)throw Error(result.error||'Tulisan belum bisa dicek.');
    approved=true;busy=false;form.requestSubmit(submitter);
   }catch(error){tell(error.message||'Tulisan belum bisa dicek. Tulisan tetap ada, coba lagi.');}
   finally{busy=false;approved=false;}
   return;
  }
  submitting=event.submitter?.getAttribute('formaction')!=='article-preview.php';
 },true);
 form.addEventListener('formdata',event=>{sync();event.formData.set('body_mode',mode.value);event.formData.set('body_html',html.value);});
 addEventListener('beforeunload',event=>{if(dirty&&!submitting){event.preventDefault();event.returnValue='';}});
 tell('Editor visual siap dipakai. Klik Simpan kalau sudah selesai.');
})();
