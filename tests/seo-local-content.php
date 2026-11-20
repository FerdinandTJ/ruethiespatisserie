<?php
// CLI-only additive draft seed fixture. No production paths, users or network.
if(PHP_SAPI!=='cli')exit;
define('RUETHIES_PUBLIC_RENDER',true);require dirname(__DIR__).'/public-lib/articles.php';
$source=dirname(__DIR__);$root=sys_get_temp_dir().'/ruethies-local-content-'.bin2hex(random_bytes(6));$public=$root.'/public_html';$private=$root.'/ruethies-data';
function lc_test(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label.PHP_EOL;}
function lc_remove(string $path):void{if(is_link($path)||!is_dir($path)){unlink($path);return;}foreach(scandir($path)as$n)if($n!=='.'&&$n!=='..')lc_remove($path.'/'.$n);rmdir($path);}
try{
 foreach(['admin','public-lib','content']as$dir)mkdir($public.'/'.$dir,0700,true);mkdir($private,0700);
 foreach(['admin/config.php','admin/seo-content-upgrade.php','public-lib/articles.php','admin/seo-local-article-seeds.php']as$file)copy($source.'/'.$file,$public.'/'.$file);
 $run=static function(string $mode)use($public):array{$p=proc_open([PHP_BINARY,$public.'/admin/seo-content-upgrade.php',$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$error];};
 [$code,$out]=$run('--check');lc_test($code===0&&!is_file($private.'/articles.json')&&count(json_decode($out,true)['skipped'])===2,'empty hosting has no writes and reports missing English sources');
 $state=article_empty();$state['enabled']=true;$now='2026-11-19T00:00:00Z';$seeds=require $public.'/admin/seo-local-article-seeds.php';
 foreach($seeds as$i=>$seed)$state['items'][]=['id'=>'english-'.$i,'title'=>'English hosting article '.$i,'slug'=>$seed['source_slug'],'description'=>'Original hosting description','category'=>'panduan','body'=>"## Original\n\nHosting text.",'status'=>'published','createdAt'=>$now,'updatedAt'=>$now,'publishedAt'=>$now,'deletedAt'=>null,'createdBy'=>'owner','updatedBy'=>'owner','cover'=>'','coverAlt'=>'','lang'=>'en','translationOf'=>'','related'=>[]];
 $initial=json_encode($state);file_put_contents($private.'/articles.json',$initial);file_put_contents($private.'/users.json','security sentinel');file_put_contents($public.'/menu.json','catalogue sentinel');
 [$code,$out]=$run('--check');lc_test($code===0&&count(json_decode($out,true)['drafts'])===2&&file_get_contents($private.'/articles.json')===$initial&&!is_dir($private.'/backups'),'check plans two drafts without backup or state mutation');
 // Backup failure must reject before modifying the original store.
 file_put_contents($private.'/backups','blocked');[$code]=$run('--apply');lc_test($code===1&&file_get_contents($private.'/articles.json')===$initial,'backup failure rejects migration without altering published content');unlink($private.'/backups');
 [$code,$out]=$run('--apply');$result=json_decode($out,true);$after=json_decode(file_get_contents($private.'/articles.json'),true);
 lc_test($code===0&&$result['applied']&&count($after['items'])===4&&$after['enabled']===true,'apply adds exactly two drafts without changing enabled flag');
 lc_test(array_slice($after['items'],0,2)===$state['items'],'original English publications retain every field');
 foreach(array_slice($after['items'],2)as$i=>$row){lc_test($row['status']==='draft'&&$row['publishedAt']===null&&$row['lang']==='id'&&$row['translationOf']==='english-'.$i,'new Indonesian translation remains draft and links to hosting source');article_validate($row,true,article_categories($after));}
 lc_test(is_file($result['backup'])&&file_get_contents($result['backup'])===$initial,'backup preserves matching original article store');
 $after['items'][2]['body'].="\n\nOwner edited draft.";$after['items'][3]['deletedAt']=$now;$after['revision']=str_repeat('b',64);file_put_contents($private.'/articles.json',json_encode($after));$custom=file_get_contents($private.'/articles.json');$backupCount=count(glob($private.'/backups/*'));
 [$code,$out]=$run('--apply');lc_test($code===0&&!json_decode($out,true)['applied']&&file_get_contents($private.'/articles.json')===$custom&&count(glob($private.'/backups/*'))===$backupCount,'repeated apply preserves edited and soft-deleted drafts without new backup');
 lc_test(file_get_contents($private.'/users.json')==='security sentinel'&&file_get_contents($public.'/menu.json')==='catalogue sentinel','seed does not touch accounts or catalogue');
 file_put_contents($private.'/articles.json','broken');[$code]=$run('--apply');lc_test($code===1&&file_get_contents($private.'/articles.json')==='broken','corrupt storage fails closed');
 [$code]=$run('--enable');lc_test($code===1,'CLI cannot enable public articles or publish content');
}finally{lc_remove($root);}
