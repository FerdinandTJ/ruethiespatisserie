// B2: choose a theme at the viewport reading line; the three-step scene is isolated.
(() => {
 'use strict';
 const motion=window.RuethiesMotion,home=document.querySelector('main#home');if(!motion||!home)return;
 const sections=[...home.children].filter(node=>node.matches('section[data-theme]'));
 const visible=new Set();
 sections.forEach(node=>motion.watch(node,entry=>{if(entry.isIntersecting)visible.add(node);else visible.delete(node);motion.schedule();}));
 motion.onFrame(()=>{
  let theme='cream';
  if(!motion.reduced.matches){
   const line=innerHeight*.45;
   for(const node of visible){const rect=node.getBoundingClientRect();if(rect.top<=line&&rect.bottom>line){theme=node.dataset.theme==='wine'?'wine':'cream';break;}}
  }
  if(document.body.dataset.sectionTheme!==theme)document.body.dataset.sectionTheme=theme;
 });
})();
