#!/usr/bin/env bash
# Run from public_html/staging. Preserve hosting JSON and optional hPanel Basic Auth.
set -euo pipefail
if [ "$(basename "$PWD")" != staging ] || [ "$(basename "$(dirname "$PWD")")" != public_html ]; then
  echo 'STOP: jalankan dari public_html/staging.' >&2; exit 1
fi
if [ "$(git branch --show-current)" != staging ]; then echo 'STOP: branch harus staging.' >&2; exit 1; fi
if ! git diff --cached --quiet; then echo 'STOP: ada perubahan staged; tinjau dahulu.' >&2; exit 1; fi
git diff --name-only | while IFS= read -r changed; do
  case "$changed" in
    .htaccess|menu.json|faq.json|atmosphere.json|categories.json|index.html|llms.txt) ;;
    images/menu/*.jpg|images/menu/*.webp)
      if [ "$(git diff --diff-filter=D --name-only -- "$changed")" != "$changed" ] || ! php -r 'exit(preg_match("~\\Aimages/menu/[a-z0-9]+(?:-[a-z0-9]+)*\\.(?:jpg|webp)\\z~D",$argv[1])?0:1);' "$changed"; then
        echo "STOP: perubahan foto perlu ditinjau: $changed" >&2; exit 1
      fi ;;
    *) echo "STOP: perubahan lain perlu ditinjau: $changed" >&2; exit 1;;
  esac
done
umask 077
STAGING_BACKUP_DIR="../../deploy-backups/staging-$(date +%Y%m%d-%H%M%S)-$$"
mkdir -p "$STAGING_BACKUP_DIR"
for file in .htaccess menu.json faq.json atmosphere.json categories.json; do if [ -f "$file" ]; then cp "$file" "$STAGING_BACKUP_DIR/$file"; fi; done
git rev-parse HEAD > "$STAGING_BACKUP_DIR/commit.txt"
# Keep the hosting deletion state, not just the surviving photo files.
git diff --diff-filter=D --name-only -- images/menu > "$STAGING_BACKUP_DIR/deleted-menu-images.txt"
tar --exclude=.git --exclude=.catalog.lock -czf "$STAGING_BACKUP_DIR/staging.tgz" -C .. staging
if [ -d ../../ruethies-data-staging ]; then tar -czf "$STAGING_BACKUP_DIR/private.tgz" -C ../.. ruethies-data-staging; fi
php -r '
$s=file_get_contents(".htaccess");$i=strpos($s,"# DO NOT REMOVE THIS LINE AND THE LINES BELOW PWPROTECTID:");
if($i===false){
  if(preg_match("/^\\s*(?:AuthType|AuthName|AuthUserFile|Require\\s+valid-user)\\b/im",$s)){fwrite(STDERR,"STOP: aturan autentikasi tanpa penanda hPanel perlu ditinjau.\n");exit(1);}
  if(file_put_contents($argv[1],"")===false)exit(1);
  echo "Staging tanpa password protection; pengaturan ini dipertahankan.\n";
  exit(0);
}
$b=substr($s,$i);
if(!preg_match("/AuthType\\s+Basic/i",$b)||!preg_match("/AuthUserFile\\s+\\S+/",$b)||!preg_match("/Require\\s+valid-user/i",$b)){fwrite(STDERR,"STOP: blok password protection belum lengkap.\n");exit(1);}
if(file_put_contents($argv[1],$b)===false)exit(1);
' "$STAGING_BACKUP_DIR/auth-block.txt"
# Stash temporarily restores tracked deletions. Reapply only the reviewed missing
# JPG/WebP paths; untracked CMS uploads and other images are never removed.
restore_deleted_menu_images() {
  if [ -s "$STAGING_BACKUP_DIR/deleted-menu-images.txt" ]; then
    php -r '
$paths=file($argv[1],FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
if($paths===false||realpath("images/menu")!==getcwd()."/images/menu"){fwrite(STDERR,"STOP: direktori foto/manifest tidak valid.\n");exit(1);}
foreach($paths as $path){
  if(!preg_match("~\\Aimages/menu/[a-z0-9]+(?:-[a-z0-9]+)*\\.(?:jpg|webp)\\z~D",$path)||is_link($path)||(file_exists($path)&&!is_file($path))){fwrite(STDERR,"STOP: path foto tidak valid.\n");exit(1);}
  if(is_file($path)&&!unlink($path)){fwrite(STDERR,"STOP: status penghapusan foto gagal dipulihkan.\n");exit(1);}
}
' "$STAGING_BACKUP_DIR/deleted-menu-images.txt"
  fi
}
# Restore protection/data if a fetch, stash or checkout fails. Never stash-pop over
# freshly upgraded templates; the hosting data is explicitly restored instead.
restore_on_error() {
  cp "$STAGING_BACKUP_DIR/.htaccess" .htaccess
  chmod 644 .htaccess
  for file in menu.json faq.json atmosphere.json categories.json; do if [ -f "$STAGING_BACKUP_DIR/$file" ]; then cp "$STAGING_BACKUP_DIR/$file" "$file"; fi; done
  restore_deleted_menu_images
  echo "Update terhenti. Proteksi/konten dipulihkan. Backup: $STAGING_BACKUP_DIR" >&2
}
trap restore_on_error EXIT
umask 022
git stash push -m 'Hosting staging before update' -- .htaccess menu.json faq.json atmosphere.json index.html llms.txt images/menu
git pull --ff-only origin staging
for file in menu.json faq.json atmosphere.json categories.json; do if [ -f "$STAGING_BACKUP_DIR/$file" ]; then cp "$STAGING_BACKUP_DIR/$file" "$file"; fi; done
cat "$STAGING_BACKUP_DIR/auth-block.txt" >> .htaccess
chmod 644 .htaccess menu.json faq.json atmosphere.json
touch .htaccess
restore_deleted_menu_images
trap - EXIT
echo "Kode staging diperbarui. Konten dan proteksi hPanel dipertahankan. Backup: $STAGING_BACKUP_DIR"
echo 'Jangan stash pop. Jalankan categories-upgrade.php --check/--apply, lalu pemeriksaan sesuai docs/STAGING-SYNC.md.'
