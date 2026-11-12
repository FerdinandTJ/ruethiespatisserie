// Shared progressive image gallery. No network requests until a slide is selected.
(() => {
  'use strict';
  function initCarousel(root){
    if(!root)return null;
    const slides=[...root.querySelectorAll('[data-slide]')];
    if(slides.length<2)return null;
    const controls=root.querySelector('.carousel-controls'),pause=root.querySelector('[data-carousel="pause"]'),prev=root.querySelector('[data-carousel="prev"]'),next=root.querySelector('[data-carousel="next"]'),position=root.querySelector('.carousel-position');
    const dots=[...root.querySelectorAll('[data-carousel-slide]')];
    const motion=matchMedia('(prefers-reduced-motion: reduce)');
    let index=0,timer=null,running=!motion.matches,hovered=false,visible=!('IntersectionObserver' in window),english=false,pointerState=null;
    controls.hidden=false;
    function labels(){
      root.setAttribute('aria-label',english?'Product photos':'Foto produk');
      root.setAttribute('aria-roledescription',english?'carousel':'karusel');
      pause.textContent=running?(english?'Pause':'Jeda'):(english?'Play':'Putar');
      pause.setAttribute('aria-label',running?(english?'Pause automatic photos':'Jeda pergantian foto otomatis'):(english?'Start automatic photos':'Mulai pergantian foto otomatis'));
      prev.setAttribute('aria-label',english?'Previous photo':'Foto sebelumnya');next.setAttribute('aria-label',english?'Next photo':'Foto berikutnya');
      position.textContent=String(index+1).padStart(2,'0')+' / '+String(slides.length).padStart(2,'0');
      dots.forEach((dot,i)=>{dot.setAttribute('aria-current',String(i===index));dot.setAttribute('aria-label',(english?'Show photo ':'Lihat foto ')+(i+1));});
      root.querySelector('.carousel-dots')?.setAttribute('aria-label',english?'Choose photo':'Pilih foto');
      position.setAttribute('aria-live',running?'off':'polite');
    }
    function schedule(){clearTimeout(timer);timer=null;if(running&&visible&&!hovered&&!document.hidden)timer=setTimeout(()=>show(index+1),6000);labels();}
    function show(value){
      index=(value+slides.length)%slides.length;
      slides[index].querySelectorAll('[data-srcset]').forEach(el=>{el.srcset=el.dataset.srcset;el.removeAttribute('data-srcset');});
      slides[index].querySelectorAll('[data-src]').forEach(el=>{el.src=el.dataset.src;el.removeAttribute('data-src');});
      slides.forEach((slide,i)=>{slide.hidden=i!==index;});schedule();
    }
    pause.addEventListener('pointerdown',()=>{pointerState=running;});
    pause.addEventListener('click',()=>{running=!(pointerState??running);pointerState=null;schedule();});
    prev.addEventListener('click',()=>{running=false;show(index-1);});next.addEventListener('click',()=>{running=false;show(index+1);});
    dots.forEach((dot,i)=>dot.addEventListener('click',()=>{running=false;show(i);}));
    let touchStart=null;
    root.addEventListener('pointerdown',event=>{if(event.pointerType==='touch'&&!event.target.closest('button'))touchStart={x:event.clientX,y:event.clientY};});
    root.addEventListener('pointercancel',()=>{touchStart=null;});
    root.addEventListener('pointerup',event=>{if(!touchStart)return;const dx=event.clientX-touchStart.x,dy=event.clientY-touchStart.y;touchStart=null;if(Math.abs(dx)>45&&Math.abs(dx)>Math.abs(dy)){running=false;show(index+(dx<0?1:-1));}});
    root.addEventListener('mouseenter',()=>{hovered=true;schedule();});root.addEventListener('mouseleave',()=>{hovered=false;schedule();});
    root.addEventListener('focusin',()=>{running=false;schedule();});
    root.addEventListener('keydown',event=>{if(event.key==='ArrowLeft'||event.key==='ArrowRight'){event.preventDefault();running=false;show(index+(event.key==='ArrowRight'?1:-1));}});
    document.addEventListener('visibilitychange',schedule);
    motion.addEventListener('change',()=>{if(motion.matches)running=false;schedule();});
    if('IntersectionObserver' in window)new IntersectionObserver(entries=>{visible=entries.some(e=>e.isIntersecting);schedule();},{threshold:.15}).observe(root);
    schedule();return{localize(en){english=en;labels();}};
  }
  window.RuethiesCarousel=initCarousel;
})();
