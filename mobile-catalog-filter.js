// C2: server-rendered filter results, enhanced with reversible FLIP transitions.
(() => {
 'use strict';
 const grid=document.getElementById('rp-catalog'),nav=document.querySelector('.rp-filter'),status=document.getElementById('catalog-filter-status'),motion=window.RuethiesMotion;
 if(!grid||!nav||!status||!motion||!window.fetch||!window.AbortController)return;
 let controller,requestId=0,animations=[],exits=[],displayed=location.pathname+location.search,loading=false;
 let count=grid.querySelectorAll('.rp-card').length;
 const english=()=>document.documentElement.lang==='en';
 function localize(root){root.querySelectorAll('[data-id][data-en]').forEach(node=>node.textContent=node.dataset[english()?'en':'id']);root.querySelectorAll('[data-alt-id]').forEach(node=>node.alt=english()?node.dataset.altEn:node.dataset.altId);}
 function announce(){status.textContent=loading?(english()?'Loading treats…':'Memuat sajian…'):(english()?`${count} ${count===1?'treat':'treats'} shown.`:`${count} sajian ditampilkan.`);}
 function cleanup(){animations.forEach(animation=>animation.cancel());animations=[];exits.forEach(node=>node.remove());exits=[];}
 function animate(node,frames,duration){if(motion.reduced.matches||typeof node.animate!=='function')return null;const animation=node.animate(frames,{duration,easing:'cubic-bezier(.22,1,.36,1)'});animations.push(animation);animation.finished.catch(()=>{});return animation;}
 async function load(url,replace=true){
  controller?.abort();controller=new AbortController();const signal=controller.signal,id=++requestId;loading=true;announce();grid.setAttribute('aria-busy','true');
  try{
   const response=await fetch(url,{signal,credentials:'same-origin',headers:{Accept:'text/html'}});
   if(!response.ok||!response.headers.get('content-type')?.includes('text/html'))throw Error('Filter response unavailable');
   const html=await response.text();if(signal.aborted||id!==requestId)return;
   const page=new DOMParser().parseFromString(html,'text/html'),incoming=page.getElementById('rp-catalog'),filters=page.querySelector('.rp-filter');
   if(!incoming||!filters)throw Error('Incomplete catalogue response');
   cleanup();const old=[...grid.querySelectorAll(':scope>.rp-card')],before=new Map(old.map(node=>[node,node.getBoundingClientRect()])),existing=new Map(old.map(node=>[node.dataset.productUid,node]));
   const focusedCard=old.find(node=>node.contains(document.activeElement)),focusedHref=focusedCard?document.activeElement.closest('a')?.getAttribute('href'):null;
   const next=[...incoming.querySelectorAll(':scope>.rp-card')].map(fresh=>{
    const node=existing.get(fresh.dataset.productUid);if(!node)return fresh;
    existing.delete(fresh.dataset.productUid);node.replaceChildren(...fresh.childNodes);return node;
   });
   const bounds=grid.getBoundingClientRect();
   // Retiring cards leave layout immediately and are no longer focusable/readable.
   exits=[...existing.values()];exits.forEach(node=>{if(node.contains(document.activeElement))nav.querySelector('[aria-current]')?.focus({preventScroll:true});const rect=before.get(node);node.inert=true;node.setAttribute('aria-hidden','true');node.classList.add('mc-filter-exit');Object.assign(node.style,{top:(rect.top-bounds.top)+'px',left:(rect.left-bounds.left)+'px',width:rect.width+'px',height:rect.height+'px'});});
   next.forEach(node=>{localize(node);grid.append(node);});
   if(focusedCard&&next.includes(focusedCard)){const target=[...focusedCard.querySelectorAll('a')].find(link=>link.getAttribute('href')===focusedHref);target?.focus({preventScroll:true});}
   const after=new Map(next.map(node=>[node,node.getBoundingClientRect()]));
   exits.forEach(node=>{const animation=next.length?animate(node,[{opacity:1,transform:'scale(1)'},{opacity:0,transform:'scale(.96)'}],140):null;if(animation)animation.finished.then(()=>{node.hidden=true;node.remove();}).catch(()=>{});else node.remove();});
   next.forEach(node=>{const start=before.get(node),end=after.get(node);animate(node,start?[{transform:`translate(${start.left-end.left}px,${start.top-end.top}px)`,opacity:1},{transform:'translate(0,0)',opacity:1}]:[{transform:'translateY(8px) scale(.98)',opacity:0},{transform:'translateY(0) scale(1)',opacity:1}],240);});
   const focused=nav.contains(document.activeElement);nav.replaceChildren(...filters.childNodes);localize(nav);if(focused)nav.querySelector('[aria-current]')?.focus({preventScroll:true});
   const empty=grid.parentElement.querySelector('.rp-empty');empty?.remove();const nextEmpty=page.querySelector('.rp-empty');if(nextEmpty){localize(nextEmpty);grid.after(nextEmpty);}
   const query=new URLSearchParams(url.search),form=document.querySelector('.rp-search');if(form){form.elements.namedItem('kategori').value=query.get('kategori')||'';form.elements.namedItem('q').value=page.getElementById('rp-search')?.value||'';}
   // Keep the existing server indexing policy; canonical/title/heading stay unchanged.
   const robots=document.querySelector('meta[name="robots"]'),serverRobots=page.querySelector('meta[name="robots"]');if(robots&&serverRobots)robots.content=serverRobots.content;
   if(replace)history.replaceState(history.state,'',url.pathname+url.search);
   displayed=url.pathname+url.search;count=next.length;loading=false;grid.setAttribute('aria-busy','false');announce();
  }catch(error){if(signal.aborted||id!==requestId)return;loading=false;grid.setAttribute('aria-busy','false');announce();location.assign(url.href);}
 }
 nav.addEventListener('click',event=>{const link=event.target.closest('a');if(!link||!nav.contains(link)||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;const url=new URL(link.href,location.href);if(url.origin!==location.origin||url.pathname!=='/menu/'||link.target==='_blank')return;event.preventDefault();if(url.pathname+url.search!==displayed||loading)load(url);});
 addEventListener('popstate',()=>{if(location.pathname==='/menu/'&&location.pathname+location.search!==displayed)load(new URL(location.href),false);});
 addEventListener('pagehide',()=>{requestId++;controller?.abort();cleanup();loading=false;grid.setAttribute('aria-busy','false');announce();});
 addEventListener('pageshow',event=>{if(event.persisted&&location.pathname==='/menu/'&&location.pathname+location.search!==displayed)load(new URL(location.href),false);});
 motion.reduced.addEventListener('change',cleanup);motion.localize(()=>{localize(grid);announce();});
})();
