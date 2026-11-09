const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../app.js'),'utf8');
function element(){return {hidden:true,textContent:'',isConnected:true,classList:{add(){},remove(){}},focus(){},removeAttribute(key){delete this[key]},setAttribute(key,value){this[key]=value},addEventListener(){},querySelector(){return this}};}
const elements=Object.fromEntries(['lightbox','lb-img','lb-cap','lb-count','lb-wa','lb-close','lb-prev','lb-next'].map(id=>[id,element()]));
const context=vm.createContext({document:{getElementById:id=>elements[id],activeElement:element(),addEventListener(){}},ATMOS:[{img:'images/a.jpg',imgFull:'images/a-xl.jpg',id:{cap:'Suasana'}}],MENU:[],modalOpen:false,stickybar:element(),onScroll(){},atmosCap:a=>a.id.cap,productPhotoLabel:n=>'Lihat foto: '+n,whatsappURL:m=>'https://wa.me/6285139281257?text='+encodeURIComponent(m),productMessage:n=>'Tanya '+n,t:()=> 'Tanya via WA',lang:'id'});
vm.runInContext(source.slice(source.indexOf('// Satu lightbox'),source.indexOf('let tx = 0;')),context);
const run=code=>vm.runInContext(code,context);
context.product={img:'images/menu/main.jpg',detail_images:['images/menu/first.jpg','images/menu/second.jpg','images/menu/third.jpg'],id:{n:'Kue "A&B" <spesial>'},en:{n:'Cake'}};
assert.equal(run('productPhotos(product).length'),3);
context.unsafe={img:'images/menu/main.jpg',detail_images:['javascript:alert(1)','images/menu/../../secret.jpg']};assert.equal(run('productPhotos(unsafe).length'),1);
assert.equal(run('productPhotos({img:"images/menu/main.jpg"}).length'),1);
run('lbProduct=product;showLB(0)');assert.equal(elements['lb-count'].textContent,'01 / 03');assert.equal(elements['lb-img'].src,context.product.img);assert.equal(elements['lb-cap'].textContent,context.product.id.n);assert.equal(elements['lb-wa'].hidden,false);assert(new URL(elements['lb-wa'].href).searchParams.get('text').includes(context.product.id.n));
run('showLB(2)');assert.equal(elements['lb-img'].src,context.product.detail_images[1]);run('showLB(3)');assert.equal(elements['lb-img'].src,context.product.img);run('showLB(-1)');assert.equal(elements['lb-count'].textContent,'03 / 03');
run('lang="en";showLB(0)');assert.equal(elements['lb-cap'].textContent,'Cake');
elements['lb-close'].onclick();assert.equal(elements.lightbox.hidden,true);assert.equal(elements['lb-img'].src,undefined);
run('lbProduct=null;showLB(0)');assert.equal(elements['lb-img'].src,'images/a-xl.jpg');assert.equal(elements['lb-wa'].hidden,true);assert.equal(elements['lb-next'].hidden,true);assert.equal(elements['lb-count'].textContent,'01 / 01');
console.log('PASS: product galleries limit photos, reject unsafe paths, retain legacy images, navigate, localize WhatsApp and preserve atmosphere gallery');
