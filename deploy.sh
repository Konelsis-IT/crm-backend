#!/usr/bin/env bash
#
# Konelsis canli kurulum betigi (D-166, 6 Ekim 2026 kullanici talimati:
# "Composer install'dan tut, git pull, optimize, migrate, db seed, --class
# seed, konelsis:release ... tum komutlar icin tek komut olsun; db yedegi
# sistemini de iceri al; her adimda ve is bitince neler cozuldu console'da
# acikla").
#
# Kullanim (sunucuda, proje klasorunde):
#   ./deploy.sh          kodu gunceller, surum yayinlamaz
#   ./deploy.sh 2.5      kodu gunceller ve 2.5 surumunu yayinlar
#
# Sira: on kontrol -> veritabani yedegi (son 14 saklanir) -> bakim modu ->
# git pull -> composer install -> onbellek temizligi -> migration -> seed
# (ozellik anahtarlari + bu surumun yeni seed dosyalari; var olan veriye
# dokunulmaz, D-165) -> varliklar ve onbellek -> surum yayini -> bakimdan
# cikis -> ozet ve surum notlari.
#
# Bir adim hata verirse betik durur, sistem bakimda kalir; hangi adimda
# durdugu ve yedek dosyasi ekrana yazilir. Butun cikti
# storage/logs/deploy-*.log dosyasina da yazilir.
#
# Bu betigi yalniz kullanici / DevOps canli sunucuda calistirir; yapay zeka
# ajanlari hicbir ortamda calistirmaz.

set -Eeuo pipefail

main() {
    local app_dir version started log_file backup_dir keep_backups
    app_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    version="${1:-}"
    started="$(date +%s)"
    keep_backups=14

    cd "$app_dir"
    mkdir -p storage/logs
    log_file="storage/logs/deploy-$(date +%Y%m%d-%H%M%S).log"
    exec > >(tee -a "$log_file") 2>&1

    STEP=0
    STEP_TOTAL=11
    STEP_NAME=""
    SUMMARY=()
    IN_MAINTENANCE=0
    BACKUP_FILE=""

    trap 'on_error $LINENO' ERR

    title "Konelsis kurulumu basliyor ($(date '+%d.%m.%Y %H:%M'))"
    say "Klasor: $app_dir"
    say "Kayit dosyasi: $log_file"

    if [[ -n "$version" && ! "$version" =~ ^[0-9]+\.[0-9]+$ ]]; then
        fail "Surum numarasi gecersiz: '$version' (ornek: 2.5)."
    fi

    # 1 -----------------------------------------------------------------
    step "On kontrol" "Gerekli programlar, .env dosyasi ve git durumu kontrol ediliyor."
    for tool in php composer git mysqldump gzip; do
        command -v "$tool" >/dev/null 2>&1 || fail "'$tool' bulunamadi; sunucuya kurulmali."
    done
    [[ -f .env ]] || fail ".env dosyasi yok."
    if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
        git status --short --untracked-files=no
        fail "Sunucudaki kodda commit'lenmemis degisiklik var (yukarida). git pull bunlari ezmesin diye durdum."
    fi
    local old_commit new_commit
    old_commit="$(git rev-parse --short HEAD)"
    done_step "Kontroller tamam; su anki kod: $old_commit."

    # 2 -----------------------------------------------------------------
    step "Veritabani yedegi" "Herhangi bir degisiklikten once veritabaninin tam yedegi aliniyor (son $keep_backups yedek saklanir)."
    backup_dir="storage/backups"
    mkdir -p "$backup_dir"
    chmod 700 "$backup_dir"
    local cnf db_name
    cnf="$(mktemp)"
    chmod 600 "$cnf"
    db_name="$(php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $c = config("database.connections.".config("database.default"));
        if (! in_array($c["driver"] ?? "", ["mysql", "mariadb"], true)) { fwrite(STDERR, "Veritabani MySQL degil.\n"); exit(1); }
        file_put_contents($argv[1], "[client]\nhost=".($c["host"] ?? "127.0.0.1")."\nport=".($c["port"] ?? 3306)."\nuser=".($c["username"] ?? "")."\npassword=\"".addcslashes((string) ($c["password"] ?? ""), "\\\"")."\"\n");
        echo $c["database"];
    ' "$cnf")"
    BACKUP_FILE="$backup_dir/konelsis-${db_name}-$(date +%Y%m%d-%H%M%S)-${old_commit}.sql.gz"
    mysqldump --defaults-extra-file="$cnf" --single-transaction --quick --routines --triggers --no-tablespaces "$db_name" | gzip > "$BACKUP_FILE"
    rm -f "$cnf"
    chmod 600 "$BACKUP_FILE"
    local removed
    removed="$(ls -1t "$backup_dir"/konelsis-*.sql.gz 2>/dev/null | tail -n +$((keep_backups + 1)) || true)"
    if [[ -n "$removed" ]]; then
        echo "$removed" | xargs -r rm -f --
    fi
    done_step "Yedek alindi: $BACKUP_FILE ($(du -h "$BACKUP_FILE" | cut -f1)); $(ls -1 "$backup_dir"/konelsis-*.sql.gz | wc -l) yedek saklaniyor."

    # 3 -----------------------------------------------------------------
    step "Bakim modu" "Kullanicilar kurulum bitene kadar kisa bir 'bakimda' sayfasi gorur."
    php artisan down --retry=60
    IN_MAINTENANCE=1
    done_step "Sistem bakim modunda."

    # 4 -----------------------------------------------------------------
    step "Kod guncelleme (git pull)" "Yeni kod depodan cekiliyor."
    git pull --ff-only
    new_commit="$(git rev-parse --short HEAD)"
    if [[ "$old_commit" == "$new_commit" ]]; then
        done_step "Kod zaten guncel ($new_commit)."
    else
        say "Gelen degisiklikler:"
        git log --oneline "$old_commit..$new_commit" | sed 's/^/    /'
        done_step "Kod $old_commit -> $new_commit guncellendi."
    fi

    # 5 -----------------------------------------------------------------
    step "Paketler (composer install)" "Kodun istedigi PHP paketleri kuruluyor (gelistirme paketleri haric)."
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    done_step "Paketler kuruldu."

    # 6 -----------------------------------------------------------------
    step "Onbellek temizligi" "Eski kodun onbellegi temizleniyor ki yeni kod eski ayarlarla calismasin."
    php artisan optimize:clear
    done_step "Onbellek temizlendi."

    # 7 -----------------------------------------------------------------
    step "Veritabani sema guncellemesi (migration)" "Bu surumle gelen tablo / sutun degisiklikleri uygulaniyor."
    local migrate_out
    if ! migrate_out="$(php artisan migrate --force 2>&1)"; then
        echo "$migrate_out" | sed 's/^/    /'
        false
    fi
    echo "$migrate_out" | sed 's/^/    /'
    if grep -qi "nothing to migrate" <<<"$migrate_out"; then
        done_step "Yeni sema degisikligi yok."
    else
        done_step "Sema degisiklikleri uygulandi ($(grep -ci 'done' <<<"$migrate_out" || true) adim)."
    fi

    # 8 -----------------------------------------------------------------
    step "Veri aktarimi (db:seed)" "Ozellik anahtarlari ve bu surumun yeni seed dosyalari calisiyor. Canlidaki veriye dokunulmaz, her satir bir kez islenir (D-165)."
    php artisan db:seed --force
    done_step "Seed tamam."

    # 9 -----------------------------------------------------------------
    step "Ekran dosyalari ve onbellek" "Filament dosyalari yayinlaniyor, uygulama onbellegi yeniden kuruluyor, kuyruk calisanlari yenileniyor."
    php artisan filament:assets
    php artisan optimize
    php artisan queue:restart
    done_step "Ekran dosyalari ve onbellek hazir."

    # 10 ----------------------------------------------------------------
    if [[ -n "$version" ]]; then
        step "Surum yayini ($version)" "$version surumu yayinlaniyor; bu surume kadar bekleyen ozellikler acilir."
        if php artisan konelsis:release "$version" --not="deploy.sh ($new_commit)"; then
            done_step "$version yayinlandi."
        else
            warn_step "$version yayinlanamadi (yukaridaki mesaja bakin; ornegin bu surum zaten yayinda). Kurulum devam ediyor."
        fi
    else
        step "Surum yayini" "Surum numarasi verilmedi; yayin yapilmiyor, mevcut yayin surumu gecerli."
        php artisan konelsis:release
        done_step "Yayin yapilmadi."
    fi

    # 11 ----------------------------------------------------------------
    step "Bakimdan cikis" "Sistem kullanicilara aciliyor."
    php artisan up
    IN_MAINTENANCE=0
    done_step "Sistem acik."

    title "Kurulum tamamlandi ($(( $(date +%s) - started )) sn)"
    for line in "${SUMMARY[@]}"; do
        echo "  $line"
    done
    echo
    say "Yedek: $BACKUP_FILE"
    say "Kod: $old_commit -> $new_commit"
    echo
    title "Bu surumde neler var"
    php artisan konelsis:notes ${version:+"$version"} || true
}

title() {
    echo
    echo "=============================================================="
    echo "  $1"
    echo "=============================================================="
}

say() {
    echo "  $1"
}

step() {
    STEP=$((STEP + 1))
    STEP_NAME="$1"
    echo
    echo "[$STEP/$STEP_TOTAL] $1"
    echo "      $2"
}

done_step() {
    echo "      OK: $1"
    SUMMARY+=("[OK] $STEP_NAME: $1")
}

warn_step() {
    echo "      UYARI: $1"
    SUMMARY+=("[UYARI] $STEP_NAME: $1")
}

fail() {
    echo
    echo "HATA: $1"
    exit 1
}

on_error() {
    local line="$1"
    trap - ERR
    echo
    echo "=============================================================="
    echo "  KURULUM DURDU: [$STEP/$STEP_TOTAL] $STEP_NAME (satir $line)"
    echo "=============================================================="
    for item in "${SUMMARY[@]}"; do
        echo "  $item"
    done
    if [[ -n "$BACKUP_FILE" ]]; then
        echo "  Yedek: $BACKUP_FILE"
        echo "  Geri donmek gerekirse: gunzip -c $BACKUP_FILE | mysql <veritabani>"
    fi
    if [[ "$IN_MAINTENANCE" == "1" ]]; then
        echo "  Sistem BAKIM MODUNDA kaldi. Sorunu giderip ./deploy.sh komutunu yeniden"
        echo "  calistirin ya da sistemi oldugu gibi acmak icin: php artisan up"
    fi
    exit 1
}

main "$@"
exit
