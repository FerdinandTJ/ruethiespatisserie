// Homepage ordering illustration. All state and observers stay local to this section.
(() => {
 'use strict';
 const root=document.querySelector('[data-order-story]');
 if(!root || !('IntersectionObserver' in window))return;
 const steps=Array.from(root.querySelectorAll('[data-order-step]'));
 const desktop=matchMedia('(min-width:900px) and (min-height:700px)');
 const motion=matchMedia('(prefers-reduced-motion:reduce)');
 const active=new Set(),played=new WeakSet();
 const desktopText=root.querySelector('.ow-sticky .ow-chat-text');
 const mobileText=root.querySelector('[data-card-step="2"] .ow-chat-text');
 let current=1;
 function finishTyping(){root.querySelectorAll('.ow-typing').forEach(node=>{node.classList.remove('ow-typing');node.textContent=node.dataset[document.documentElement.lang==='en'?'en':'id'];});}
 function typeOnce(node){
  if(!node || played.has(node) || motion.matches)return;
  played.add(node);
  const text=node.dataset[document.documentElement.lang==='en'?'en':'id'];
  const fragment=document.createDocumentFragment();
  let index=0;
  // Keep normal word spacing and wrapping while revealing individual letters.
  text.split(/(\s+)/).forEach(token=>{
   if(/^\s+$/.test(token)){fragment.appendChild(document.createTextNode(token));index+=token.length;return;}
   const word=document.createElement('span');word.className='ow-word';
   Array.from(token).forEach(char=>{const letter=document.createElement('span');letter.className='ow-letter';letter.textContent=char;letter.style.setProperty('--ow-letter',String(index++));word.appendChild(letter);});
   fragment.appendChild(word);
  });
  node.replaceChildren(fragment);node.classList.add('ow-typing');
 }
 function select(step){
  current=step;root.dataset.activeStep=String(step);
  if(step===2 && desktop.matches)typeOnce(desktopText);
 }
 const observer=new IntersectionObserver(entries=>{
  entries.forEach(entry=>entry.isIntersecting?active.add(entry.target):active.delete(entry.target));
  if(!desktop.matches || !active.size)return;
  const center=innerHeight*.5;
  const nearest=Array.from(active).sort((a,b)=>{const ra=a.getBoundingClientRect(),rb=b.getBoundingClientRect();return Math.abs(ra.top+ra.height/2-center)-Math.abs(rb.top+rb.height/2-center);})[0];
  select(Number(nearest.dataset.orderStep));
 },{rootMargin:'-40% 0px -40% 0px',threshold:0});
 const reveal=new IntersectionObserver(entries=>{
  entries.forEach(entry=>{if(!entry.isIntersecting)return;entry.target.classList.add('ow-entered');entry.target.querySelector('.ow-card').classList.add('ow-visible');if(!desktop.matches && entry.target.dataset.orderStep==='2')typeOnce(mobileText);});
 },{threshold:.25});
 const visibility=new IntersectionObserver(entries=>{
  root.classList.toggle('ow-in-view',entries[0].isIntersecting);
  if(!entries[0].isIntersecting)finishTyping();
 },{threshold:0});
 function resize(){
  const header=document.querySelector('.rp-header');
  root.style.setProperty('--ow-header',(header?.getBoundingClientRect().height||96)+'px');
  finishTyping();if(desktop.matches)select(current);
 }
 // The existing language renderer updates all data-id/data-en nodes synchronously.
 // Stop any partial typing on a language change; never leave mixed-language letters.
 new MutationObserver(finishTyping).observe(document.documentElement,{attributes:true,attributeFilter:['lang']});
 motion.addEventListener('change',finishTyping);desktop.addEventListener('change',resize);window.addEventListener('resize',resize,{passive:true});
 root.classList.add('ow-enhanced');steps.forEach(step=>{observer.observe(step);reveal.observe(step);});visibility.observe(root);resize();
})();
