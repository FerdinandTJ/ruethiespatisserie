(() => {
 'use strict';const title=document.getElementById('article-title'),slug=document.getElementById('article-slug'),description=document.getElementById('article-description');if(!title||!slug||!description)return;
 let automatic=slug.value==='';slug.addEventListener('input',()=>automatic=slug.value==='');title.addEventListener('input',()=>{if(automatic)slug.value=title.value.normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').slice(0,100).replace(/^-|-$/g,'');});
 const count=()=>document.getElementById('article-description-count').textContent=Array.from(description.value).length+' / 155';description.addEventListener('input',count);count();
})();
