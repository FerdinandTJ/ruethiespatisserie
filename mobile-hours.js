(() => {
 'use strict';const motion=window.RuethiesMotion;if(!motion)return;
 // TODO: Owner to confirm hosting FAQ hours; never rewrite CMS FAQ automatically.
 const HOURS=Object.freeze({open:7,close:21,timeZone:'Asia/Jakarta'});
 const nodes=[...document.querySelectorAll('[data-live-hours]')];
 const formatter=new Intl.DateTimeFormat('en-GB',{timeZone:HOURS.timeZone,hour:'2-digit',hourCycle:'h23'});
 function update(){const hour=Number(formatter.format(new Date())),open=hour>=HOURS.open&&hour<HOURS.close,en=document.documentElement.lang==='en';
  const text=en?(open?'Open · until 9 PM':'Closed · opens at 7 AM'):(open?'Buka · sampai 21.00':'Tutup · buka lagi 07.00');
  nodes.forEach(node=>{node.classList.toggle('ma-hours-open',open);node.textContent=text;});
 }
 motion.localize(update);let timer;
 function monitor(){clearInterval(timer);update();if(!document.hidden)timer=setInterval(update,60000);}
 document.addEventListener('visibilitychange',monitor);monitor();
 const location=document.querySelector('#visit');motion.watch(location,entry=>{if(entry.isIntersecting&&!motion.reduced.matches)location.classList.add('ma-pin-played');});
})();
