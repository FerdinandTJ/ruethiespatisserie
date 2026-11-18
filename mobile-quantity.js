// C1: enhance the existing quantity input; profile.js remains the WhatsApp composer.
(() => {
 'use strict';
 const input=document.getElementById('order-quantity'),motion=window.RuethiesMotion;
 if(!input||!motion)return;
 const minimum=Number(input.min)||1,maximum=Number(input.max)||999;
 const control=document.createElement('div');control.className='mc-quantity';
 const slot=document.createElement('div');slot.className='mc-number-slot';
 const minus=document.createElement('button'),plus=document.createElement('button');
 [minus,plus].forEach(button=>{button.type='button';button.className='mc-quantity-button';});
 minus.textContent='−';plus.textContent='+';
 const visual=document.createElement('span');visual.className='mc-number-visual';visual.setAttribute('aria-hidden','true');visual.hidden=true;
 const live=document.createElement('span');live.className='mc-quantity-live';live.setAttribute('aria-live','polite');live.setAttribute('aria-atomic','true');
 const label=input.closest('label');input.before(control);slot.append(input,visual);control.append(minus,slot,plus);
 if(label){const field=document.createElement('div');field.className='mc-quantity-field';label.before(field);field.append(label,control,live);}else control.after(live);
 let animations=[];
 function finish(){const old=animations;animations=[];old.forEach(animation=>animation.cancel());visual.hidden=true;slot.classList.remove('mc-number-moving');}
 function sync(){const count=Number(input.value),valid=Number.isInteger(count)&&count>=minimum&&count<=maximum;minus.disabled=valid&&count===minimum;plus.disabled=valid&&count===maximum;}
 const english=()=>document.documentElement.lang==='en';
 function announce(){live.textContent=(english()?'Quantity: ':'Jumlah: ')+input.value;}
 function change(direction){
  const old=Number(input.value);
  if(!Number.isInteger(old)||old<minimum||old>maximum||!input.validity.valid){input.reportValidity();return;}
  const next=Math.max(minimum,Math.min(maximum,old+direction));if(next===old)return;
  finish();input.value=String(next);input.dispatchEvent(new Event('input',{bubbles:true}));sync();announce();
  if(motion.reduced.matches||typeof visual.animate!=='function')return;
  const leaving=document.createElement('span'),entering=document.createElement('span');leaving.textContent=String(old);entering.textContent=String(next);visual.replaceChildren(leaving,entering);visual.hidden=false;slot.classList.add('mc-number-moving');
  const options={duration:180,easing:'ease-out',fill:'both'};
  animations=[leaving.animate([{transform:'translateY(0)',opacity:1},{transform:`translateY(${-direction*100}%)`,opacity:0}],options),entering.animate([{transform:`translateY(${direction*100}%)`,opacity:0},{transform:'translateY(0)',opacity:1}],options)];
  const current=animations;Promise.all(current.map(animation=>animation.finished)).then(()=>{if(animations===current)finish();}).catch(()=>{});
 }
 minus.addEventListener('click',()=>change(-1));plus.addEventListener('click',()=>change(1));input.addEventListener('input',()=>{finish();sync();});
 motion.localize(()=>{finish();minus.setAttribute('aria-label',english()?'Decrease quantity':'Kurangi jumlah');plus.setAttribute('aria-label',english()?'Increase quantity':'Tambah jumlah');if(live.textContent)announce();});
 motion.reduced.addEventListener('change',finish);sync();
})();
