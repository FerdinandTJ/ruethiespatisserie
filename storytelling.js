/* One sticky scene, native reversible scroll. No animation loop while offscreen. */
(() => {
 'use strict';
 const section=document.getElementById('snack-story');
 if(!section || !('IntersectionObserver' in window))return;
 const chapters=Array.from(section.querySelectorAll('[data-story-step]'));
 const scene=section.querySelector('.rst-desktop-visual .rst-scene');
 const desktop=matchMedia('(min-width:901px) and (min-height:651px)');
 let visible=false, frame=0;
 const header=document.querySelector('.rp-header');
 function select(){
  frame=0;if(!visible || !desktop.matches)return;
  const focus=(innerHeight+(header?.getBoundingClientRect().height||96))*.5;
  let active=chapters[0],distance=Infinity;
  for(const chapter of chapters){const r=chapter.getBoundingClientRect(),d=Math.abs(r.top+r.height*.5-focus);if(d<distance){active=chapter;distance=d;}}
  scene.dataset.phase=active.dataset.storyStep;
  chapters.forEach(chapter=>chapter.classList.toggle('is-active',chapter===active));
 }
 function schedule(){if(visible && desktop.matches && !frame)frame=requestAnimationFrame(select);}
 function resize(){section.style.setProperty('--rst-header',(header?.getBoundingClientRect().height||96)+'px');schedule();}
 const visibility=new IntersectionObserver(entries=>{
  visible=entries[0].isIntersecting;
  if(visible){section.classList.add('rst-started');window.addEventListener('scroll',schedule,{passive:true});schedule();}
  else{window.removeEventListener('scroll',schedule);cancelAnimationFrame(frame);frame=0;}
 });
 const entrance=new IntersectionObserver(entries=>{
  entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('has-entered');entrance.unobserve(entry.target);}});
 },{threshold:.12});
 section.classList.add('rst-enhanced');
 chapters.forEach(chapter=>entrance.observe(chapter));
 visibility.observe(section);window.addEventListener('resize',resize,{passive:true});desktop.addEventListener('change',resize);resize();
})();
