<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=sys_get_temp_dir().'/ruethies-upgrade-'.bin2hex(random_bytes(5));$public=$root.'/public_html';$private=$root.'/ruethies-data';mkdir($public.'/admin',0700,true);mkdir($private,0700);
function upgrade_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function upgrade_remove(string $p):void{if(!is_dir($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')upgrade_remove($p.'/'.$n);rmdir($p);}
function upgrade_run(array $args,string $stdin=''):array{global $public;$p=proc_open(array_merge([PHP_BINARY,$public.'/admin/security-upgrade.php'],$args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],$stdin);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$err];}
function upgrade_snapshot(string $p):array{$out=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p,FilesystemIterator::SKIP_DOTS))as$f)if($f->isFile())$out[$f->getPathname()]=hash_file('sha256',$f->getPathname());ksort($out);return $out;}
try{
 foreach(['admin/config.php','admin/lib.php','admin/catalog.php','admin/security.php','admin/security-resources.php','admin/security-upgrade.php','index.html','llms.txt']as$f)copy(dirname(__DIR__).'/'.$f,$public.'/'.$f);
 $users=[['name'=>'owner','role'=>'owner','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'totp'=>'JBSWY3DPEHPK3PXP'],['name'=>'staff','role'=>'staff','hash'=>password_hash('Demo-test-password',PASSWORD_DEFAULT),'totp'=>'']];file_put_contents($private.'/users.json',json_encode($users));
 $faq=[['q'=>'Jam buka?','a'=>'Host original </script><div id="unexpected">x</div> [Peta](https://example.com).','qe'=>'Hours?','ae'=>'Hosting translation.']];file_put_contents($public.'/faq.json',json_encode($faq));file_put_contents($private.'/log.jsonl',"{\"legacy\":true}\n");
 $before=upgrade_snapshot($root);[$exit,$out,$err]=upgrade_run(['--check']);upgrade_check($exit===0&&$err===''&&$before===upgrade_snapshot($root),'CLI check does not create, delete or change any file');
 [$exit,$out,$err]=upgrade_run(['--apply']);if($exit)fwrite(STDERR,$err);upgrade_check($exit===0,'CLI apply succeeds on legacy hosting fixture');$new=json_decode(file_get_contents($private.'/users.json'),true);
 upgrade_check($new[0]['hash']===$users[0]['hash']&&$new[0]['totp']===$users[0]['totp']&&$new[1]['hash']===$users[1]['hash'],'upgrade retains accounts, hashes and authenticator secret');
 upgrade_check(strlen($new[0]['auth_version'])===64&&$new[0]['last_totp_counter']===-1,'upgrade adds security metadata');
 $doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML(file_get_contents($public.'/index.html'));$xp=new DOMXPath($doc);$schemas=$xp->query('//script[@type="application/ld+json"]');$data=json_decode($schemas->item(3)->textContent,true);
 upgrade_check($xp->query('//*[@id="unexpected"]')->length===0&&str_contains($data['mainEntity'][0]['acceptedAnswer']['text'],'Host original'),'upgrade regenerates safe FAQ from hosting content');
 upgrade_check(json_decode(file_get_contents($public.'/faq.json'),true)===$faq,'FAQ contents and translations preserved');
 $archives=glob($private.'/log-before-security-*.jsonl');upgrade_check(count($archives)===1&&file_get_contents($archives[0])==="{\"legacy\":true}\n",'legacy log archived once before retention');
 [$exit]=upgrade_run(['--apply']);upgrade_check($exit===0&&json_decode(file_get_contents($private.'/users.json'),true)===$new&&count(glob($private.'/log-before-security-*.jsonl'))===1,'repeated apply preserves versions/counters and does not rearchive logs');
 $latest=$new;$latest[0]['last_totp_counter']=123456;file_put_contents($private.'/users.json',json_encode($latest));
 [$exit]=upgrade_run(['--recover-owner','owner'],"A-new-safe-password!\n");$recovered=json_decode(file_get_contents($private.'/users.json'),true);
 upgrade_check($exit===0&&password_verify('A-new-safe-password!',$recovered[0]['hash'])&&$recovered[0]['auth_version']!==$latest[0]['auth_version']&&$recovered[0]['last_totp_counter']===123456&&$recovered[0]['totp']===$latest[0]['totp']&&$recovered[1]===$latest[1],'SSH recovery rotates owner version, keeps latest counters, 2FA and other accounts');
 [$exit]=upgrade_run(['--revoke-sessions']);$revoked=json_decode(file_get_contents($private.'/users.json'),true);upgrade_check($exit===0&&$revoked[0]['auth_version']!==$recovered[0]['auth_version']&&$revoked[1]['auth_version']!==$recovered[1]['auth_version']&&$revoked[0]['last_totp_counter']===123456,'SSH session revocation preserves security counter');
 $before=upgrade_snapshot($root);file_put_contents($public.'/faq.json','{broken');$bad=upgrade_snapshot($root);[$exit]=upgrade_run(['--apply']);upgrade_check($exit===1&&$bad===upgrade_snapshot($root),'corrupt FAQ fails preflight without mutating files');
 file_put_contents($public.'/faq.json',json_encode($faq));rename($private,$public.'/admin/data');$private=$public.'/admin/data';$before=upgrade_snapshot($root);
 [$exit]=upgrade_run(['--check']);upgrade_check($exit===0&&$before===upgrade_snapshot($root)&&!is_dir($root.'/ruethies-data'),'check respects existing legacy fallback without creating a competing store');
 [$exit]=upgrade_run(['--apply']);upgrade_check($exit===0&&json_decode(file_get_contents($private.'/users.json'),true)===$revoked&&!is_dir($root.'/ruethies-data'),'apply preserves legacy fallback and latest security metadata');
}finally{upgrade_remove($root);}
