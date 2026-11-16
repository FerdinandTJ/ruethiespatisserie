// B1: native sticky cards, following the hosting-managed category order.
(() => {
 'use strict';
 const motion=window.RuethiesMotion,root=document.querySelector('#menu .rp-categories');
 if(!motion||!root)return;
 const cards=[...root.querySelectorAll('.rp-category')];
 let visible=false;
 motion.watch(root,entry=>{visible=entry.isIntersecting;motion.schedule();});
 motion.onFrame(()=>{
  const enabled=motion.mobile.matches&&!motion.reduced.matches&&innerHeight>=600;
  if(!enabled){cards.forEach(card=>card.style.removeProperty('--mb-scale'));return;}
  if(!visible)return;
  const rectangles=cards.map(card=>card.getBoundingClientRect());
  const scales=rectangles.map((rect,i)=>{
   const next=rectangles[i+1];if(!next||cards[i]===document.activeElement)return 1;
   const overlap=Math.max(0,Math.min(1,(rect.bottom-next.top)/Math.max(1,rect.height)));
   return 1-.06*overlap;
  });
  // Cards further underneath get at most .90 scale. Only transforms are written.
  scales.forEach((scale,i)=>{const second=rectangles[i+2];if(second){const overlap=Math.max(0,Math.min(1,(rectangles[i].bottom-second.top)/Math.max(1,rectangles[i].height)));scale=Math.min(scale,1-.1*overlap);}cards[i].style.setProperty('--mb-scale',String(Math.round(scale*1000)/1000));});
 });
 root.addEventListener('focusin',motion.schedule);root.addEventListener('focusout',motion.schedule);
})();
