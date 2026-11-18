(() => {
 'use strict';const motion=window.RuethiesMotion,node=document.querySelector('#atmosphere>div:last-child>p[data-id]:not(.rp-kicker)');if(!motion||!node)return;
 let words=[],active=false,last=-1;
 function build(){const language=document.documentElement.lang==='en'?'en':'id',text=node.dataset[language];if(!text)return;
  const accessible=document.createElement('span');accessible.className='ma-sr-only';accessible.textContent=text;
  const visual=document.createElement('span');visual.setAttribute('aria-hidden','true');
  words=[];text.split(/(\s+)/).forEach(token=>{if(/^\s+$/.test(token)){visual.append(document.createTextNode(token));return;}const word=document.createElement('span');word.className='ma-read-word';word.append(document.createTextNode(token));const ink=document.createElement('span');ink.className='ma-read-ink';ink.textContent=token;word.append(ink);words.push(ink);visual.append(word);});
  node.replaceChildren(accessible,visual);last=-1;motion.schedule();
 }
 motion.watch(node,e=>{active=e.isIntersecting;motion.schedule();});
 motion.onFrame(()=>{if(!active&&last>=0&&!motion.reduced.matches)return;const rect=node.getBoundingClientRect(),progress=motion.reduced.matches?1:Math.max(0,Math.min(1,(innerHeight*.85-rect.top)/(innerHeight*.5+rect.height)));const count=Math.round(progress*words.length);if(count===last)return;last=count;words.forEach((word,i)=>word.style.opacity=i<count?'1':'0');});
 motion.localize(build);
})();
