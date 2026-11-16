(() => {
 'use strict';const motion=window.RuethiesMotion,bar=document.querySelector('.rp-sticky');if(!motion||!bar)return;
 const hero=document.querySelector('.rp-hero .rp-actions'),order=document.querySelector('.rp-order'),stops=[document.querySelector('.rp-footer'),document.querySelector('#visit'),document.querySelector('.rp-contact-layout')].filter(Boolean);
 let blocked=false,orderVisible=false;const visible=new Set();
 function update(){const passed=!hero||hero.getBoundingClientRect().bottom<=0;const show=motion.mobile.matches&&passed&&!blocked&&!orderVisible&&!document.body.classList.contains('rp-dialog-open');bar.classList.toggle('ma-wa-visible',show);bar.inert=!show;bar.setAttribute('aria-hidden',String(!show));}
 stops.forEach(node=>motion.watch(node,e=>{if(e.isIntersecting)visible.add(node);else visible.delete(node);blocked=visible.size>0;motion.schedule();}));
 motion.watch(order,e=>{orderVisible=e.isIntersecting;motion.schedule();});
 motion.onFrame(update);document.body.classList.add('ma-wa-enhanced');
 new MutationObserver(()=>motion.schedule()).observe(document.body,{attributes:true,attributeFilter:['class']});
})();
