(() => {
  'use strict';
  const root=document.getElementById('variant-editor');if(!root)return;
  const toggle=root.querySelector('#variants-enabled'),list=root.querySelector('#variant-rows'),add=root.querySelector('#variant-add');
  const all=[...list.querySelectorAll('.variant-row')];
  all.sort((a,b)=>Number(a.querySelector('[data-variant-order]').value)-Number(b.querySelector('[data-variant-order]').value)).forEach(row=>list.append(row));
  for(const row of all){
    row.hidden=!row.querySelector('[data-variant-name]').value&&!row.querySelector('input[name$="_uid"]').value;
    row.querySelector('[data-variant-name]').addEventListener('input',()=>row.querySelector('[data-variant-title]').textContent=row.querySelector('[data-variant-name]').value||'Varian baru');
    row.querySelector('[data-variant-delete]').addEventListener('change',event=>{
      if(event.target.checked&&!confirm('Hapus varian ini saat menu disimpan?'))event.target.checked=false;
    });
    row.querySelectorAll('[data-variant-move]').forEach(button=>{
      button.hidden=false;button.addEventListener('click',()=>{
        const rows=[...list.children].filter(el=>!el.hidden),i=rows.indexOf(row),up=button.dataset.variantMove==='up',other=rows[i+(up?-1:1)];
        if(other){if(up)list.insertBefore(row,other);else list.insertBefore(other,row);refresh();button.focus();}
      });
    });
  }
  function refresh(){
    const rows=[...list.children].filter(row=>!row.hidden);rows.forEach((row,i)=>{
      row.querySelector('[data-variant-order]').value=i;
      row.querySelector('[data-variant-move="up"]').disabled=i===0;
      row.querySelector('[data-variant-move="down"]').disabled=i===rows.length-1;
    });
    list.hidden=!toggle.checked;add.hidden=!toggle.checked;add.disabled=rows.length>=all.length;
  }
  add.addEventListener('click',()=>{const row=all.find(row=>row.hidden);if(!row)return;row.hidden=false;row.open=true;list.append(row);refresh();row.querySelector('[data-variant-name]').focus();});
  toggle.addEventListener('change',refresh);refresh();
})();
