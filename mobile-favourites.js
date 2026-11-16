(() => {
 'use strict';const motion=window.RuethiesMotion,track=document.querySelector('[data-mobile-favourites]');if(!motion||!track)return;
 const cards=[...track.children];if(cards.length<2)return;
 const dots=document.createElement('div');dots.className='ma-favourite-dots';dots.setAttribute('role','group');
 const controls=cards.map((card,i)=>{const button=document.createElement('button');button.type='button';button.addEventListener('click',()=>track.scrollTo({left:card.offsetLeft-track.offsetLeft-(track.clientWidth-card.clientWidth)/2,behavior:motion.reduced.matches?'instant':'smooth'}));dots.append(button);return button;});
 track.after(dots);track.classList.add('ma-favourites');let current=-1;
 function select(){if(!motion.mobile.matches){cards.forEach(card=>card.classList.remove('ma-card-neighbour'));return;}
  const bounds=track.getBoundingClientRect(),center=bounds.left+bounds.width/2;let best=0,distance=Infinity;
  cards.forEach((card,i)=>{const rect=card.getBoundingClientRect(),d=Math.abs(rect.left+rect.width/2-center);if(d<distance){distance=d;best=i;}});
  if(current===best&&cards.some(card=>card.classList.contains('ma-card-neighbour')))return;current=best;
  cards.forEach((card,i)=>card.classList.toggle('ma-card-neighbour',i!==best));controls.forEach((button,i)=>button.setAttribute('aria-pressed',String(i===best)));
 }
 track.addEventListener('scroll',motion.schedule,{passive:true});track.addEventListener('focusin',event=>{if(!motion.mobile.matches)return;const card=event.target.closest('.rp-card');if(card)track.scrollTo({left:card.offsetLeft-track.offsetLeft-(track.clientWidth-card.clientWidth)/2,behavior:'instant'});});
 motion.localize(()=>{const en=document.documentElement.lang==='en';dots.setAttribute('aria-label',en?'Featured products':'Sajian pilihan');controls.forEach((button,i)=>button.setAttribute('aria-label',(en?'Show product ':'Lihat produk ')+(i+1)));});motion.onFrame(select);
})();
