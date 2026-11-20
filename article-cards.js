// One visible card per explicit translation family. Both article URLs stay independent.
(() => {
 'use strict';const cards=new WeakMap();
 function localize(root=document){
  const language=document.documentElement.lang==='en'?'en':'id';
  root.querySelectorAll('[data-article-group]').forEach(card=>{
   let versions=cards.get(card);
   if(!versions){versions=new Map();const initial=document.createDocumentFragment();for(const node of card.childNodes){if(node.nodeType===1&&node.matches('template[data-article-version]'))versions.set(node.dataset.articleVersion,node.content.cloneNode(true));else initial.appendChild(node.cloneNode(true));}versions.set(card.dataset.articleActive,initial);cards.set(card,versions);}
   const next=versions.has(language)?language:(versions.has('id')?'id':(versions.has('en')?'en':card.dataset.articleActive));
   if(next!==card.dataset.articleActive&&versions.has(next)){
    const focused=card.contains(document.activeElement);card.replaceChildren(versions.get(next).cloneNode(true));card.lang=next;card.dataset.articleActive=next;
    if(focused)card.querySelector('h2 a,h3 a')?.focus({preventScroll:true});
   }
   card.querySelectorAll('[data-id][data-en]').forEach(node=>node.textContent=node.dataset[language]);
  });
 }
 window.RuethiesArticleCards={localize};new MutationObserver(()=>localize()).observe(document.documentElement,{attributes:true,attributeFilter:['lang']});localize();
})();
