<?php
// CLI build only. Public-only base + current profile; never modifies admin/legacy styles.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$css='';
foreach(['public-fonts.css','public-base.css','profile.css']as$file){$part=file_get_contents($root.'/'.$file);if($part===false)throw new RuntimeException($file);$css.="\n".$part;}
$css=str_replace(['"Cormorant Garamond",Georgia','"Plus Jakarta Sans",sans-serif'],['"Cormorant Garamond","Ruethies Serif Fallback",Georgia','"Plus Jakarta Sans","Ruethies Sans Fallback",Arial,sans-serif'],$css);
$css=preg_replace('~/\*.*?\*/~s','',$css);$css=preg_replace('/\s+/',' ',$css);$css=trim($css)."\n";
if(in_array('--check',$argv,true)){if(file_get_contents($root.'/public.css')!==$css){fwrite(STDERR,"public.css is stale; run php tools/build-public-css.php\n");exit(1);}echo "PASS: public.css matches its sources\n";exit;}
file_put_contents($root.'/public.css',$css);echo strlen($css)." bytes public.css\n";
