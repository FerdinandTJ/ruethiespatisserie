// Execute the actual multipart form handler with CSP-compatible image loading.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../admin/admin.js'),'utf8');
const handler=source.slice(source.indexOf('  document.querySelectorAll(\'form[enctype="multipart/form-data"]\')'),source.indexOf('  // Penghitung karakter'));
async function scenario(failure){
 let submit, submitted=0;const alerts=[],sources=[],hidden=[];
 const inputs=['foto','detail1','detail2'].map(name=>({name,value:'selected.webp',files:[{name:name+'.webp',type:'image/webp',size:200000}]}));
 const button={disabled:false,innerHTML:'Simpan menu'};
 const form={dataset:{},querySelectorAll:()=>inputs,querySelector:()=>button,appendChild:el=>hidden.push(el),addEventListener:(event,fn)=>{submit=fn;},submit(){submitted++;}};
 class Reader{readAsDataURL(file){queueMicrotask(()=>{if(file.name==='detail2.webp'&&failure==='read'){this.onerror();return;}if(file.name==='detail2.webp'&&failure==='abort'){this.onabort();return;}this.result='data:image/webp;base64,fixture';this.onload();});}}
 class Image{constructor(){this.width=1920;this.height=1080;}set src(value){sources.push(value);queueMicrotask(()=>{if(!value.startsWith('data:')||failure==='decode')this.onerror();else this.onload();});}}
 const context=vm.createContext({document:{querySelectorAll:()=>[form],createElement:tag=>tag==='canvas'?{getContext:()=>({drawImage(){}}),toDataURL:()=> 'data:image/jpeg;base64,encoded'}:{}},FileReader:Reader,Image,alert:msg=>alerts.push(msg)});
 vm.runInContext(handler,context);let prevented=false;await submit({preventDefault(){prevented=true;}});
 assert(prevented);assert(sources.every(src=>src.startsWith('data:')));
 if(!failure){assert.equal(submitted,1);assert.equal(hidden.length,3);assert.deepEqual(hidden.map(el=>el.name),['foto_b64','detail1_b64','detail2_b64']);assert(inputs.every(input=>input.value===''));assert.equal(form.enctype,'application/x-www-form-urlencoded');assert.equal(alerts.length,0);}
 else{assert.equal(submitted,0);assert.equal(hidden.length,0);assert(inputs.every(input=>input.value==='selected.webp'));assert.equal(button.disabled,false);assert.equal(form.dataset.sending,undefined);assert.equal(alerts.length,1);}
 console.log('PASS: '+(failure?'failed '+failure+' preserves selection and allows retry':'three WebP files submit through data URLs allowed by hosting CSP'));
}
(async()=>{await scenario();for(const failure of ['read','abort','decode'])await scenario(failure);})().catch(error=>{console.error(error);process.exitCode=1;});
