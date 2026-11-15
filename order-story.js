// Homepage ordering illustration. All state and observers stay local to this section.
(() => {
 'use strict';
 const root=document.querySelector('[data-order-story]');
 if(!root || !('IntersectionObserver' in window))return;
 const steps=Array.from(root.querySelectorAll('[data-order-step]'));
 const desktop=matchMedia('(min-width:900px) and (min-height:700px)');
 const compact=matchMedia('(max-width:899px) and (min-height:740px)');
 const shared=()=>desktop.matches||compact.matches;
 const motion=matchMedia('(prefers-reduced-motion:reduce)');
 const active=new Set(),played=new WeakSet();
 const desktopText=root.querySelector('.ow-sticky .ow-chat-text');
 const mobileText=root.querySelector('[data-card-step="2"] .ow-chat-text');
 let current=1,observer;
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
  if(step===2 && shared())typeOnce(desktopText);
 }
 function observeSteps(){
  observer?.disconnect();active.clear();
  const header=document.querySelector('.rp-header')?.getBoundingClientRect().height||96;
  const sticky=root.querySelector('.ow-sticky').getBoundingClientRect().height;
  const bottom=innerHeight-(document.querySelector('.rp-sticky')?.getBoundingClientRect().height||0)-24;
  const center=compact.matches?Math.min(innerHeight-48,(header+12+sticky+18+bottom)/2):innerHeight*.5;
  const rootMargin=compact.matches?`-${Math.round(center-24)}px 0px -${Math.round(innerHeight-center-24)}px 0px`:'-40% 0px -40% 0px';
  observer=new IntersectionObserver(entries=>{
   entries.forEach(entry=>entry.isIntersecting?active.add(entry.target):active.delete(entry.target));
   if(!shared() || !active.size)return;
   const nearest=Array.from(active).sort((a,b)=>{const ra=a.getBoundingClientRect(),rb=b.getBoundingClientRect();return Math.abs(ra.top+ra.height/2-center)-Math.abs(rb.top+rb.height/2-center);})[0];
   select(Number(nearest.dataset.orderStep));
  },{rootMargin,threshold:0});
  steps.forEach(step=>observer.observe(step));
 }
 const reveal=new IntersectionObserver(entries=>{
  entries.forEach(entry=>{if(!entry.isIntersecting)return;entry.target.classList.add('ow-entered');entry.target.querySelector('.ow-card').classList.add('ow-visible');if(!shared() && entry.target.dataset.orderStep==='2')typeOnce(mobileText);});
 },{threshold:.25});
 const visibility=new IntersectionObserver(entries=>{
  root.classList.toggle('ow-in-view',entries[0].isIntersecting);
  if(!entries[0].isIntersecting)finishTyping();
 },{threshold:0});
 function resize(){
  const header=document.querySelector('.rp-header');
  root.style.setProperty('--ow-header',(header?.getBoundingClientRect().height||96)+'px');
  root.classList.toggle('ow-mobile-sticky',compact.matches);
  finishTyping();observeSteps();if(shared())select(current);
 }
 // The existing language renderer updates all data-id/data-en nodes synchronously.
 // Stop any partial typing on a language change; never leave mixed-language letters.
 new MutationObserver(finishTyping).observe(document.documentElement,{attributes:true,attributeFilter:['lang']});
 motion.addEventListener('change',finishTyping);desktop.addEventListener('change',resize);compact.addEventListener('change',resize);window.addEventListener('resize',resize,{passive:true});
 root.classList.add('ow-enhanced');steps.forEach(step=>reveal.observe(step));visibility.observe(root);resize();
})();
