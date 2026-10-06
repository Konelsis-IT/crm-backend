#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Konelsis safe verification (docs/planning/01 §19, 16 §8, decision D-39)
|--------------------------------------------------------------------------
|
| Runs only checks that can neither execute application tests nor touch a
| database:
|   1. PHP syntax lint of project sources
|   2. composer.json script scan for prohibited commands
|   3. Filament layer boundary scan (raw SQL / DB facade / query chains)
|   4. Runtime schema-change scan outside migrations
|   5. Migration destructive-statement alarm
|
| Usage: php tools/safe-verify.php   (exit code 1 on failure)
*/

$root = dirname(__DIR__);
$failures = [];
$warnings = [];

/**
 * @return Generator<string>
 */
function phpFiles(string $directory): Generator
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            yield $file->getPathname();
        }
    }
}

function relative(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

// 1. Syntax lint -----------------------------------------------------------
$lintDirectories = ['app', 'bootstrap', 'config', 'database', 'routes', 'tools', 'lang'];
$linted = 0;

foreach ($lintDirectories as $directory) {
    foreach (phpFiles($root.DIRECTORY_SEPARATOR.$directory) as $file) {
        $output = [];
        $exitCode = 0;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $exitCode);
        $linted++;

        if ($exitCode !== 0) {
            $failures[] = sprintf('Syntax error in %s: %s', relative($root, $file), trim(implode(' ', $output)));
        }
    }
}

// 2. Composer script scan ---------------------------------------------------
$composerJson = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$prohibitedTokens = ['artisan migrate', 'db:wipe', 'artisan test', 'vendor/bin/pint', 'vendor\\bin\\pint', 'phpunit', 'pest', 'artisan db:seed'];

foreach ((array) ($composerJson['scripts'] ?? []) as $scriptName => $commands) {
    foreach ((array) $commands as $command) {
        foreach ($prohibitedTokens as $token) {
            if (is_string($command) && stripos($command, $token) !== false) {
                $failures[] = sprintf('composer.json script [%s] invokes prohibited command token [%s].', $scriptName, $token);
            }
        }
    }
}

// 3. Filament layer boundary ---------------------------------------------------
$filamentForbidden = [
    '/\bDB::/' => 'DB facade',
    '/->(whereRaw|selectRaw|orderByRaw|havingRaw|groupByRaw|fromRaw)\(/' => 'raw SQL',
    '/::query\(\)\s*->/' => 'explicit Eloquent query chain',
    '/->newQuery\(\)/' => 'explicit query builder',
    '/Illuminate\\\\Support\\\\Facades\\\\DB\b/' => 'DB facade import',
    '/Illuminate\\\\Support\\\\Facades\\\\Schema\b/' => 'Schema facade import',
];
$filamentWriteWarnings = ['/->save\(\)/' => 'model save()', '/::create\(\[/' => 'mass create()', '/->update\(\[/' => 'direct update()', '/->delete\(\)/' => 'direct delete()'];

foreach (phpFiles($root.'/app/Filament') as $file) {
    $source = (string) file_get_contents($file);

    foreach ($filamentForbidden as $pattern => $label) {
        if (preg_match($pattern, $source) === 1) {
            $failures[] = sprintf('Filament boundary: %s uses %s; move it to a Query Service or use case.', relative($root, $file), $label);
        }
    }

    foreach ($filamentWriteWarnings as $pattern => $label) {
        if (preg_match($pattern, $source) === 1) {
            $warnings[] = sprintf('Filament boundary: %s performs %s; writes belong to application use cases.', relative($root, $file), $label);
        }
    }
}

// 3b. "Olustur & yeni olustur" dugmesi yasagi (16 Eylul 2026 kullanici karari) ---
// Dugme AppServiceProvider'da sistem geneli kapalidir; hicbir kaynak, sayfa,
// iliski yoneticisi veya eylem onu yeniden acamaz.
$createAnotherPatterns = [
    '/->createAnother\(\s*(true\s*)?\)/' => '->createAnother() (re-enables the button)',
    '/\$canCreateAnother\s*=\s*true\b/' => '$canCreateAnother = true',
    '/function\s+canCreateAnother\s*\(/' => 'a canCreateAnother() override',
    '/function\s+getCreateAnotherFormAction\s*\(/' => 'a getCreateAnotherFormAction() override',
    '/->createAnotherAction\(/' => '->createAnotherAction()',
];

foreach (phpFiles($root.'/app') as $file) {
    $source = (string) file_get_contents($file);

    foreach ($createAnotherPatterns as $pattern => $label) {
        if (preg_match($pattern, $source) === 1) {
            $failures[] = sprintf('Create-another guard: %s contains %s; the "create & create another" button is removed system-wide and must not be used.', relative($root, $file), $label);
        }
    }
}

$providerSource = (string) file_get_contents($root.'/app/Providers/AppServiceProvider.php');

if (! str_contains($providerSource, 'CreateRecord::disableCreateAnother()') || ! str_contains($providerSource, '->createAnother(false)')) {
    $failures[] = 'Create-another guard: app/Providers/AppServiceProvider.php must keep CreateRecord::disableCreateAnother() and CreateAction ->createAnother(false).';
}

// 3c. Tarih girdisi standardi (16 Eylul 2026 kullanici karari) -------------------
// Tek tarih girdisi vardir: DatePicker, tarayici yerel girisi, gun.ay.yil, kisa
// genislik (FieldGrid). DateTimePicker yasaktir; baska bicim/boyut/tip yoktur.
// Denetim zincir bazlidir: `DatePicker::make(` ile baslayip ayni derinlikteki
// ilk virgul/noktali virgul/parantezde biten metot zinciri incelenir.
function dateInputChains(string $source, string $needle = 'DatePicker::make('): array
{
    $chains = [];
    $offset = 0;

    while (($pos = strpos($source, $needle, $offset)) !== false) {
        // Ad siniri: "Select::make(" "XSelect::make(" icinde eslesmesin (ad alani "\" serbest).
        $before = $pos > 0 ? $source[$pos - 1] : ' ';

        if (ctype_alnum($before) || $before === '_') {
            $offset = $pos + strlen($needle);

            continue;
        }

        $i = $pos;
        $depth = 0;
        $length = strlen($source);
        $quote = null;

        for (; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($char === chr(92)) {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                if ($depth === 0) {
                    break;
                }

                $depth--;
            } elseif (($char === ',' || $char === ';') && $depth === 0) {
                break;
            }
        }

        $chains[] = substr($source, $pos, $i - $pos);
        $offset = $i;
    }

    return $chains;
}

foreach (phpFiles($root.'/app/Filament') as $file) {
    $source = (string) file_get_contents($file);

    if (preg_match('/\bDateTimePicker\b/', $source) === 1) {
        $failures[] = sprintf('Date input standard: %s uses DateTimePicker; every date input is a native DatePicker, d.m.Y, short width.', relative($root, $file));
    }

    foreach (dateInputChains($source) as $chain) {
        $field = preg_match("/DatePicker::make\('([^']*)'\)/", $chain, $m) === 1 ? $m[1] : '?';

        if (preg_match('/->native\(\s*false\s*\)/', $chain) === 1) {
            $failures[] = sprintf('Date input standard: %s field [%s] uses ->native(false); date inputs are native.', relative($root, $file), $field);
        }

        if (preg_match("/->displayFormat\('(?!d\.m\.Y')[^']*'\)/", $chain) === 1) {
            $failures[] = sprintf('Date input standard: %s field [%s] uses a display format other than d.m.Y.', relative($root, $file), $field);
        }

        if (preg_match('/->seconds\(/', $chain) === 1) {
            $failures[] = sprintf('Date input standard: %s field [%s] uses ->seconds(); date inputs carry no time.', relative($root, $file), $field);
        }
    }
}

// 3d. Yukleme sinirlari (25 Eylul 2026 kullanici karari, D-127) ----------------
// Genel gecici yukleme tavani belge siniridir (1 GB). Her FileUpload kendi
// sinirini acikca yazar: belge alanlari UploadLimits::documentMaxKb(),
// digerleri gereken kucuk boyut (fotograf 4-8 MB, ek 20 MB...).
foreach ([$root.'/app/Filament', $root.'/app/Livewire'] as $uploadDirectory) {
    if (! is_dir($uploadDirectory)) {
        continue;
    }

    foreach (phpFiles($uploadDirectory) as $file) {
        $source = (string) file_get_contents($file);

        foreach (dateInputChains($source, 'FileUpload::make(') as $chain) {
            if (! str_contains($chain, '->maxSize(')) {
                $field = preg_match('/FileUpload::make\(([^)]*)\)/', $chain, $m) === 1 ? trim($m[1]) : '?';
                $failures[] = sprintf('Upload limits: %s FileUpload [%s] has no ->maxSize(); the global cap is the 1 GB document limit, so every field states its own (documents: UploadLimits::documentMaxKb()).', relative($root, $file), $field);
            }
        }
    }
}

// 3d-2. Tam satir alan yok (5 Ekim 2026 kullanici kurali, D-157) ------------------
// "Hicbir duzenleme/olusturma ekraninda ... tum satir alan olarak secilmemeli ...
// Compact olmali, gerektigi kadar bir alan olmalidir." Girdi alanlari
// columnSpanFull() / FieldGrid::FULL / 'full' almaz; uzun icerik FieldGrid::LONG,
// HALF_LONG ya da MODAL_LONG alir. Secenekleri sutunlara dizilen liste
// (CheckboxList / Radio / ToggleButtons + ->columns()) ve yerlesim bilesenleri
// (Section, Grid, Group, Repeater, Text...) bu kuralin disindadir.
function withoutPhpComments(string $source): string
{
    $out = '';

    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $out .= str_repeat("\n", substr_count($token[1], "\n"));

            continue;
        }

        $out .= is_array($token) ? $token[1] : $token;
    }

    return $out;
}

/**
 * Zincirin kendi (derinlik 0) metot cagrilari: [[ad, argumanlar], ...]. Ic ice
 * bilesenlerin (or. createOptionForm([...]) icindeki alanlar) cagrilari dahil degildir.
 *
 * @return list<array{0: string, 1: string}>
 */
function topLevelCalls(string $chain): array
{
    $calls = [];
    $length = strlen($chain);
    $depth = 0;
    $quote = null;
    $i = 0;

    while ($i < $length) {
        $char = $chain[$i];

        if ($quote !== null) {
            if ($char === chr(92)) {
                $i += 2;

                continue;
            }

            if ($char === $quote) {
                $quote = null;
            }

            $i++;

            continue;
        }

        if ($char === "'" || $char === '"') {
            $quote = $char;
        } elseif ($char === '(' || $char === '[') {
            $depth++;
        } elseif ($char === ')' || $char === ']') {
            $depth--;
        } elseif ($depth === 0 && $char === '-' && ($chain[$i + 1] ?? '') === '>' && preg_match('/\G->([A-Za-z_][A-Za-z0-9_]*)\(/', $chain, $m, 0, $i) === 1) {
            $start = $i + strlen($m[0]);
            $inner = 0;
            $innerQuote = null;

            for ($j = $start; $j < $length; $j++) {
                $c = $chain[$j];

                if ($innerQuote !== null) {
                    if ($c === chr(92)) {
                        $j++;
                    } elseif ($c === $innerQuote) {
                        $innerQuote = null;
                    }

                    continue;
                }

                if ($c === "'" || $c === '"') {
                    $innerQuote = $c;
                } elseif ($c === '(' || $c === '[') {
                    $inner++;
                } elseif ($c === ')' || $c === ']') {
                    if ($inner === 0) {
                        break;
                    }

                    $inner--;
                }
            }

            $calls[] = [$m[1], substr($chain, $start, $j - $start)];
            $i = $j + 1;

            continue;
        }

        $i++;
    }

    return $calls;
}

$compactFields = ['TextInput', 'Textarea', 'Select', 'FileUpload', 'RichEditor', 'MarkdownEditor', 'TagsInput', 'KeyValue', 'DatePicker', 'TimePicker', 'Toggle', 'Checkbox', 'ColorPicker', 'CheckboxList', 'Radio', 'ToggleButtons'];
$optionLists = ['CheckboxList', 'Radio', 'ToggleButtons'];

foreach (phpFiles($root.'/app/Filament') as $file) {
    $source = withoutPhpComments((string) file_get_contents($file));

    foreach ($compactFields as $fieldClass) {
        foreach (dateInputChains($source, $fieldClass.'::make(') as $chain) {
            $calls = topLevelCalls($chain);
            $full = false;
            $hasColumns = false;

            foreach ($calls as [$method, $arguments]) {
                $arguments = trim($arguments);

                // Kosullu genislik de sayilir: ->columnSpan($x ? FieldGrid::FULL : FieldGrid::HALF).
                if ($method === 'columnSpanFull'
                    || ($method === 'columnSpan' && preg_match('/FieldGrid::FULL\b|[\'"]full[\'"]/', $arguments) === 1)) {
                    $full = true;
                }

                if ($method === 'columns') {
                    $hasColumns = true;
                }
            }

            if ($full && ! (in_array($fieldClass, $optionLists, true) && $hasColumns)) {
                $field = preg_match('/::make\(([^)]*)\)/', $chain, $m) === 1 ? trim($m[1]) : '?';
                $failures[] = sprintf('Compact fields (D-157): %s %s [%s] spans the full row; use FieldGrid::LONG / HALF_LONG / MODAL_LONG or a narrower width.', relative($root, $file), $fieldClass, $field);
            }
        }
    }
}

if (! str_contains((string) file_get_contents($root.'/app/Providers/AppServiceProvider.php'), 'livewire.temporary_file_upload.rules')) {
    $failures[] = 'Upload limits: app/Providers/AppServiceProvider.php must set livewire.temporary_file_upload.rules from UploadLimits (Livewire default caps every upload at 12 MB).';
}

// 3e. Ozellik anahtarlari (25 Eylul 2026 kullanici karari, D-128) ---------------
// Her ozellik yalniz veritabanindan (features.is_active) acilip kapanir; durum
// FeatureFlags::enabled(Feature::...) ile okunur. Dizge kod, .env bayragi ya da
// config/features.php geri gelmez. Her kaynak, sayfa ve widget bir ozellige
// baglidir ya da erisimini bagli bir siniftan alir (return X::canAccess();).
if (is_file($root.'/config/features.php')) {
    $failures[] = 'Feature switches: config/features.php must not exist; feature state lives only in the features table (FeatureFlags + App\Enums\Platform\Feature).';
}

foreach (phpFiles($root.'/app') as $file) {
    if (preg_match('/FeatureFlags::enabled\(\s*[\'"]/', (string) file_get_contents($file)) === 1) {
        $failures[] = sprintf('Feature switches: %s passes a string to FeatureFlags::enabled(); use a App\Enums\Platform\Feature case.', relative($root, $file));
    }
}

$featureScoped = array_merge(
    glob($root.'/app/Filament/Resources/*/*Resource.php') ?: [],
    glob($root.'/app/Filament/Widgets/*.php') ?: [],
    iterator_to_array(phpFiles($root.'/app/Filament/Pages'), false),
);

// Bilincli istisnalar: rolleri kapatmak yoneticiyi kilitler (D-130, Roller = Shield genisletmesi).
$featureExempt = ['app/Filament/Resources/Roles/RoleResource.php'];

foreach ($featureScoped as $file) {
    $source = (string) file_get_contents($file);

    if (in_array(str_replace('\\', '/', relative($root, $file)), $featureExempt, true)) {
        continue;
    }

    if (! str_contains($source, 'Feature::') && preg_match('/return\s+[A-Z][A-Za-z]+::canAccess\(\);/', $source) !== 1) {
        $failures[] = sprintf('Feature switches: %s is not tied to a feature; register it in App\Enums\Platform\Feature and check FeatureFlags::enabled(Feature::...) in canAccess()/canView().', relative($root, $file));
    }
}

$appProvider = (string) file_get_contents($root.'/app/Providers/AppServiceProvider.php');

if (! str_contains($appProvider, 'Gate::before(') || ! str_contains($appProvider, 'FeatureRegistry')) {
    $failures[] = 'Feature switches: app/Providers/AppServiceProvider.php must register the FeatureRegistry Gate::before hook (disabled features deny their record types).';
}

// 4. Runtime schema change scan ---------------------------------------------
$schemaPatterns = [
    '/Artisan::call\(\s*[\'"](migrate|db:wipe|db:seed)/' => 'programmatic migration/seed call',
    '/\bSchema::(create|table|drop|dropIfExists|rename)\(/' => 'runtime schema change',
];

foreach (phpFiles($root.'/app') as $file) {
    if (str_contains(str_replace('\\', '/', $file), '/app/Infrastructure/Database/Migrations/')) {
        continue;
    }

    $source = (string) file_get_contents($file);

    foreach ($schemaPatterns as $pattern => $label) {
        if (preg_match($pattern, $source) === 1) {
            $failures[] = sprintf('Schema guard: %s contains a %s.', relative($root, $file), $label);
        }
    }
}

// 5. Migration destructive-statement alarm -------------------------------------
foreach (phpFiles($root.'/database/migrations') as $file) {
    $source = (string) file_get_contents($file);
    $relativePath = relative($root, $file);

    if (preg_match('/\btruncate\b/i', $source) === 1) {
        $failures[] = sprintf('Migration %s contains TRUNCATE.', $relativePath);
    }

    if (preg_match('/->delete\(|DELETE\s+FROM/i', $source) === 1) {
        $failures[] = sprintf('Migration %s deletes data; data corrections belong to the DBA script package.', $relativePath);
    }

    if (preg_match('/dropIfExists\(|->drop\(|dropColumn\(|dropForeign\(/', $source) === 1
        && str_contains($source, 'assertDestructiveAllowed') === false) {
        $failures[] = sprintf('Migration %s has destructive steps without assertDestructiveAllowed().', $relativePath);
    }
}

// 6. Table interaction standard (D-125, user decision 2026-09-25) ---------------
// Satira tiklamak detayi acar, Goruntule / Ac dugmesi yok, satir eylemleri
// simge + ipucu, bagli kayit tiklanabilir ve simgeli, personel adinda kisi
// simgesi. Kural App\Filament\Support\TableConventions ile tek noktadan uygulanir.
$provider = (string) @file_get_contents($root.'/app/Providers/AppServiceProvider.php');

if (! str_contains($provider, 'TableConventions::register()')) {
    $failures[] = 'Table standard: app/Providers/AppServiceProvider.php must call TableConventions::register() (row click opens detail, icon-only row actions, linked related records).';
}

foreach (phpFiles($root.'/app/Filament') as $file) {
    $relativePath = relative($root, $file);

    if (str_ends_with(str_replace('\\', '/', $relativePath), 'app/Filament/Support/TableConventions.php')) {
        continue;
    }

    $source = (string) file_get_contents($file);

    if (str_contains($source, '->modifyUngroupedRecordActionsUsing(')) {
        $failures[] = sprintf('Table standard: %s overrides modifyUngroupedRecordActionsUsing(); row actions must stay icon-only with tooltips (TableConventions).', $relativePath);
    }

    if (preg_match('/->recordUrl\(\s*null\s*\)/', $source) === 1 && ! str_contains($source, '->recordAction(')) {
        $failures[] = sprintf('Table standard: %s disables the row link without a row action; every row opens its detail (use RowDetail::action() when there is no detail page).', $relativePath);
    }

    // Enum secenekli alanda $get() enum nesnesi verir; ->value ile ham
    // karsilastirma hic tutmaz ve bagli alan gizli kalir (25 Eylul 2026,
    // dokumanda "Belgenin asli" alani). Deger FormState::value() ile indirilir.
    if (preg_match('/(?<![A-Za-z_:(])\$get\(\'[A-Za-z_.]+\'\)\s*(===|!==)\s*[A-Z][A-Za-z]+::[A-Za-z]+->value|in_array\(\s*\$get\(|match\s*\(\s*\$get\(/', $source) === 1) {
        $failures[] = sprintf('Form state: %s compares a raw $get() value with an enum ->value; wrap it with FormState::value($get(...)) (enum-option fields return enum instances).', $relativePath);
    }
}

// 7. Button colour standard (D-148, user decision 2026-09-30) -------------------
// Kaydet / onay yesil, Iptal / Kapat gul kirmizisi, Duzenle turuncu, Yeni
// zumrut yesili, Goruntule mavi. Kural App\Filament\Support\ActionColors ile
// tek noktadan uygulanir; Olustur / Duzenle sayfalarinin alt dugmeleri
// HasColoredFormActions ile boyanir (Filament Iptal'i sayfada gri kurar).
if (! str_contains($provider, 'ActionColors::register()')) {
    $failures[] = 'Button colours: app/Providers/AppServiceProvider.php must call ActionColors::register() (save green, cancel rose, edit orange, create emerald).';
}

// Dolu dugmelerde yazi beyaz (D-149): Filament'in dugme rengi bileseni
// WhiteTextButtonComponent'e baglanir (acik tonlu renkte zemin koyulasir).
if (! str_contains($provider, 'WhiteTextButtonComponent::class')) {
    $failures[] = 'Button colours: app/Providers/AppServiceProvider.php must bind ButtonComponent to WhiteTextButtonComponent (filled buttons always carry white text).';
}

foreach (phpFiles($root.'/app/Providers/Filament') as $file) {
    $source = (string) file_get_contents($file);

    if (str_contains($source, '->colors([') && ! str_contains($source, 'ActionColors::panelColors()')) {
        $failures[] = sprintf('Button colours: %s registers panel colours without ActionColors::panelColors(); the orange / emerald / rose button tones must exist on every panel.', relative($root, $file));
    }
}

foreach (phpFiles($root.'/app/Filament') as $file) {
    $source = (string) file_get_contents($file);

    if (preg_match('/^(?:final\s+|abstract\s+)?class\s+\w+\s+extends\s+(?:EditRecord|CreateRecord)\b/m', $source) === 1 && ! str_contains($source, 'use HasColoredFormActions;')) {
        $failures[] = sprintf('Button colours: %s is a create/edit page without HasColoredFormActions; its Kaydet / Oluştur button must be green and İptal rose.', relative($root, $file));
    }
}

// 8. Feature versions (D-151, user decision 2026-10-02) ---------------------------
// Kod canliya dogrudan gider; yeni ozellik surumu yayinlanana kadar gorunmez.
// Bu yuzden katalogdaki her ozelligin tanim satiri bir surum tasimalidir
// ([ad, aciklama, karar, surum]); surumsuz ozellik hemen canliya acilirdi.
$featureSource = (string) @file_get_contents($root.'/app/Enums/Platform/Feature.php');
preg_match_all('/^\s*case\s+([A-Za-z]+)\s*=/m', $featureSource, $featureCases);
$definitionPart = (string) substr($featureSource, (int) strpos($featureSource, 'private function definition(): array'));
preg_match_all("/^\\s*self::([A-Za-z]+) => \\[.*, '(\\d+\\.\\d+(?:\\.\\d+)?)'\\],\\r?$/m", $definitionPart, $versionedFeatures);
$unversioned = array_diff($featureCases[1], $versionedFeatures[1]);

if ($featureCases[1] === [] || $unversioned !== []) {
    $failures[] = sprintf('Feature versions: App\Enums\Platform\Feature cases without a release version in definition() [name, description, decision, version]: %s.', $unversioned === [] ? '(catalog not found)' : implode(', ', $unversioned));
}

// Sira gelen guncelleme (D-152): hicbir ozellik Feature::NEXT_RELEASE'ten buyuk surum tasiyamaz.
if (preg_match("/const NEXT_RELEASE = '(\\d+\\.\\d+(?:\\.\\d+)?)';/", $featureSource, $nextRelease) !== 1) {
    $failures[] = 'Feature versions: App\Enums\Platform\Feature must declare NEXT_RELEASE (the upcoming release every new holdable feature uses).';
} else {
    foreach ($versionedFeatures[1] as $index => $case) {
        if (version_compare($versionedFeatures[2][$index], $nextRelease[1], '>')) {
            $failures[] = sprintf('Feature versions: Feature::%s has version %s, above NEXT_RELEASE %s.', $case, $versionedFeatures[2][$index], $nextRelease[1]);
        }
    }
}

if (! str_contains((string) @file_get_contents($root.'/app/Services/Platform/SchemaReadiness.php'), "'B42'")) {
    $failures[] = 'Feature versions: SchemaReadiness must keep the B42 signature (features.version + feature_releases).';
}

// Report ------------------------------------------------------------------------
fwrite(STDOUT, sprintf("Konelsis safe verification: %d PHP files linted.%s", $linted, PHP_EOL));

foreach ($warnings as $warning) {
    fwrite(STDOUT, 'WARN  '.$warning.PHP_EOL);
}

foreach ($failures as $failure) {
    fwrite(STDERR, 'FAIL  '.$failure.PHP_EOL);
}

if ($failures !== []) {
    fwrite(STDERR, sprintf('%d failure(s).%s', count($failures), PHP_EOL));
    exit(1);
}

fwrite(STDOUT, 'OK    No policy violations found. (Pint, test suites and migrations are intentionally not part of this check.)'.PHP_EOL);
exit(0);
