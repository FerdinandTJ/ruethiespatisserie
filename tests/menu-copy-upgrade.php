<?php
// Isolated catalogue-copy migration fixtures. No real accounts, hosting data or external APIs.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('RUETHIES_PUBLIC_RENDER',true);require dirname(__DIR__).'/admin/catalog.php';
$tmp=sys_get_temp_dir().'/ruethies-menu-copy-'.bin2hex(random_bytes(6));$public=$tmp.'/public_html';$private=$tmp.'/ruethies-data';$source=dirname(__DIR__);
function mc_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function mc_remove(string $p):void{if(!is_dir($p)||is_link($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')mc_remove($p.'/'.$n);rmdir($p);}
try{
 mkdir($public.'/admin',0700,true);mkdir($public.'/content',0700);mkdir($private,0700);
 foreach(['menu-copy-upgrade.php','config.php','lib.php','catalog.php','security.php','security-resources.php']as$f)copy($source.'/admin/'.$f,$public.'/admin/'.$f);
 copy($source.'/content/menu-copy-20261113.json',$public.'/content/menu-copy-20261113.json');
 $items=catalog_metadata(catalog_read_json($source.'/menu.json'),946684800);$patch=catalog_read_json($source.'/content/menu-copy-20261113.json');
 foreach($items as&$m){foreach($patch as$c)if($m['slug']===$c['slug']){$m['id']['d']='Hosting ID sebelumnya';$m['id']['detail']='Cerita lama dari hosting';$m['en']['d']='Previous English hosting copy';$m['en']['detail']='Previous hosting detail';}if($m['slug']==='lapis-marble'){$m['slug']='lapis-surabaya';$m['id']['n']='Lapis Surabaya';$m['en']['n']='Lapis Surabaya';}$m['detail_images']=['images/menu/foto-detail-hosting.jpg'];$m['owner_note']='Keep hosting metadata';}unset($m);
 $items[0]['available']=false;$items[0]['signature']=true;unset($items[0]['en']);
 $extra=$items[4];unset($extra['uid'],$extra['slug']);$extra['id']['n']='Menu tambahan pemilik';$extra['en']['n']='Owner extra item';$items[]=$extra;$items=catalog_metadata($items,946684800);
 $menu=$public.'/menu.json';catalog_atomic_write($menu,$items);$original=file_get_contents($menu);file_put_contents($private.'/users.json','{"auth_version":"keep"}');$users=file_get_contents($private.'/users.json');
 $run=function($mode)use($public):array{$p=proc_open([PHP_BINARY,$public.'/admin/menu-copy-upgrade.php',$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return[proc_close($p),json_decode($out,true),$err];};
 $dirs=scandir($private);[$code,$r]=$run('--check');mc_check($code===0&&$r['changes_needed']===7&&file_get_contents($menu)===$original&&scandir($private)===$dirs,'check reports seven changes without writing hosting data');
 [$code,$r,$err]=$run('--apply');mc_check($code===0&&$r['applied']===7&&is_file($r['backup'])&&file_get_contents($r['backup'])===$original,'apply backs up actual hosting catalogue before atomic update');
 $new=catalog_read_json($menu);catalog_validate($new);mc_check(count($new)===11&&$new[10]===$items[10],'extra hosting product is preserved and CSV-only products are not inserted');
 foreach($items as$i=>$m){$a=$m;$b=$new[$i];unset($a['id'],$a['en'],$a['updated_at'],$b['id'],$b['en'],$b['updated_at']);if($m['slug']==='lapis-surabaya'){mc_check($b['slug']==='lapis-marble','only Lapis gets the explicitly approved new slug');unset($a['slug'],$b['slug']);}mc_check($a===$b,'identity, URLs, photos, visibility and extra metadata retained: '.$m['id']['n']);}
 foreach($patch as$c){$n=array_values(array_filter($new,fn($m)=>$m['slug']===$c['slug']))[0];mc_check($n['id']['d']===$c['id']['d']&&$n['en']['detail']===$c['en']['detail']&&catalog_detail($n,'id')===$c['id']['detail'],'short description and bilingual philosophy updated: '.$c['slug']);}
 $lapis=array_values(array_filter($new,fn($m)=>$m['slug']==='lapis-marble'))[0];mc_check($lapis['id']['n']==='Lapis Marble'&&catalog_path($lapis)==='/menu/lapis-marble/','Lapis Marble uses only its new product URL while retaining identity');
 $after=file_get_contents($menu);$backups=glob($private.'/backups/menu-before-copy-*');[$code,$r]=$run('--apply');mc_check($code===0&&$r['applied']===0&&file_get_contents($menu)===$after&&count(glob($private.'/backups/menu-before-copy-*'))===count($backups),'repeated apply is idempotent');
 mc_check(file_get_contents($private.'/users.json')===$users,'account/security metadata is untouched');
 $renamed=$new;$renamed[5]['id']['n']='Nama khusus pemilik';catalog_atomic_write($menu,$renamed);[$code,$r]=$run('--apply');mc_check($code===0&&in_array('pastel',$r['unmatched'],true)&&catalog_read_json($menu)[5]['id']['n']==='Nama khusus pemilik','unmatched renamed product is reported and left unchanged');
 $collision=$items;$collision[10]['slug']='lapis-marble';catalog_atomic_write($menu,$collision);$before=file_get_contents($menu);[$code]=$run('--apply');mc_check($code!==0&&file_get_contents($menu)===$before,'new slug collision with another hosting product is rejected atomically');
 $duplicate=$items;$duplicate[]=$items[0];unset($duplicate[11]['uid'],$duplicate[11]['slug']);$duplicate=catalog_metadata($duplicate,946684800);catalog_atomic_write($menu,$duplicate);$before=file_get_contents($menu);[$code]=$run('--apply');mc_check($code!==0&&file_get_contents($menu)===$before,'ambiguous product names fail without partially applying copy');
 file_put_contents($menu,'{bad json');[$code]=$run('--apply');mc_check($code!==0&&file_get_contents($menu)==='{bad json','corrupt input is rejected without replacement');
}finally{mc_remove($tmp);}
