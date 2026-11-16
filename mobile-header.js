(() => {
 'use strict';const motion=window.RuethiesMotion,header=document.querySelector('.rp-header');if(!motion||!header)return;
 let previous=scrollY,travel=0;
 motion.onFrame(()=>{const y=Math.max(0,scrollY),delta=y-previous;previous=y;
  if(!motion.mobile.matches||motion.reduced.matches||y<100||header.contains(document.activeElement)||header.classList.contains('rp-menu-open')){header.classList.remove('ma-header-away');travel=0;return;}
  travel=delta*travel<0?delta:travel+delta;if(Math.abs(travel)>=8){header.classList.toggle('ma-header-away',travel>0);travel=0;}
 });
 header.addEventListener('focusin',()=>{header.classList.remove('ma-header-away');motion.schedule();});
 new MutationObserver(()=>motion.schedule()).observe(header,{attributes:true,attributeFilter:['class']});
})();
