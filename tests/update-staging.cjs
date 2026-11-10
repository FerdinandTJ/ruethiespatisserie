const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),cp=require('node:child_process'),assert=require('node:assert/strict');
const root=fs.mkdtempSync(path.join(os.tmpdir(),'ruethies-deploy-test-')),remote=path.join(root,'remote'),site=path.join(root,'site/public_html/staging');
const script=path.resolve(__dirname,'../tools/update-staging.sh');
function run(command,args,cwd){const r=cp.spawnSync(command,args,{cwd,encoding:'utf8'});assert.equal(r.status,0,r.stdout+r.stderr);return r.stdout;}
function git(args,cwd=remote){return run('git',args,cwd);}
function commit(message){git(['add','.']);git(['-c','user.name=Fixture','-c','user.email=fixture@example.test','commit','-m',message]);}
try{
 fs.mkdirSync(remote);git(['init','--initial-branch=staging']);
 for(const[file,body]of Object.entries({'.htaccess':'# old rules\n','menu.json':'[]','faq.json':'[]','atmosphere.json':'[]','index.html':'old template','llms.txt':'old llms'}))fs.writeFileSync(path.join(remote,file),body);
 commit('old release');fs.mkdirSync(path.dirname(site),{recursive:true});git(['clone',remote,site],root);
 const original='[{"uid":"permanent-hosting-id","cat":"coffee"}]';fs.writeFileSync(path.join(site,'menu.json'),original);
 const auth='# DO NOT REMOVE THIS LINE AND THE LINES BELOW PWPROTECTID:test\nAuthType Basic\nAuthName "Test"\nAuthUserFile /private/test-passwords\nRequire valid-user\n';fs.appendFileSync(path.join(site,'.htaccess'),auth);
 fs.writeFileSync(path.join(site,'index.html'),'hosting FAQ generated template');
 fs.writeFileSync(path.join(remote,'.htaccess'),'# new rules\n<Files "midtrans-notify.php">\nRequire all granted\n</Files>\n');fs.writeFileSync(path.join(remote,'index.html'),'new SEO template');fs.writeFileSync(path.join(remote,'menu.json'),'[{"name":"repo default"}]');commit('new release');
 run('bash',[script],site);
 assert.equal(fs.readFileSync(path.join(site,'menu.json'),'utf8'),original);
 assert.equal(fs.readFileSync(path.join(site,'index.html'),'utf8'),'new SEO template');
 const access=fs.readFileSync(path.join(site,'.htaccess'),'utf8');assert(access.includes('# new rules')&&access.includes(auth)&&access.includes('<Files "midtrans-notify.php">'));
 assert(git(['stash','list'],site).includes('Hosting staging before update'));
 assert.equal(git(['rev-parse','HEAD'],site),git(['rev-parse','HEAD']));
 assert(fs.readdirSync(path.join(root,'site/deploy-backups')).length===1);
 console.log('PASS: staging update preserves hosting UID/JSON and Basic Auth, updates templates/rules, retains stash and backup');
 fs.writeFileSync(path.join(site,'unrelated.php'),'local code');git(['add','unrelated.php'],site);
 const rejected=cp.spawnSync('bash',[script],{cwd:site,encoding:'utf8'});assert.notEqual(rejected.status,0);assert.equal(fs.readFileSync(path.join(site,'menu.json'),'utf8'),original);
 console.log('PASS: deployment refuses staged changes without changing hosting content');
}finally{fs.rmSync(root,{recursive:true,force:true});}
