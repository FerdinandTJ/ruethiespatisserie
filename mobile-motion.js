// Shared scheduler: one passive scroll listener, one observer for Phase A.
(() => {
 'use strict';
 const callbacks=new Set(),watched=new Map();let pending=false;
 const mobile=matchMedia('(max-width:760px)'), reduced=matchMedia('(prefers-reduced-motion:reduce)');
 function tick(){pending=false;callbacks.forEach(fn=>fn());}
 function schedule(){if(!pending){pending=true;requestAnimationFrame(tick);}}
 const observer='IntersectionObserver' in window?new IntersectionObserver(entries=>{entries.forEach(entry=>watched.get(entry.target)?.(entry));},{threshold:[0,.01,.5,1]}):null;
 window.RuethiesMotion={mobile,reduced,schedule,onFrame(fn){callbacks.add(fn);schedule();},watch(node,fn){if(!node)return;if(observer){watched.set(node,fn);observer.observe(node);}else fn({isIntersecting:true,target:node});},localize(fn){new MutationObserver(fn).observe(document.documentElement,{attributes:true,attributeFilter:['lang']});fn();}};
 addEventListener('scroll',schedule,{passive:true});addEventListener('resize',schedule,{passive:true});mobile.addEventListener('change',schedule);reduced.addEventListener('change',schedule);
})();
