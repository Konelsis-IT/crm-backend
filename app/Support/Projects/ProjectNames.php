<?php

declare(strict_types=1);

namespace App\Support\Projects;

use App\Enums\Platform\Feature;
use App\Models\Project\Project;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;

/**
 * Projenin gorunen adi ve D-174 anahtarlari (8 Ekim 2026 kullanici talimati:
 * "Listelerde: Kisa isim, altina Lisans ismi seklinde tek kolonda gorulecek.
 * Kart bicimindeki proje alaninda ise kisa isim gorulecek").
 *
 * - Kisa ad (projects.short_name, B48) doluysa proje her yerde kisa adiyla,
 *   bossa lisans adiyla (projects.name) gorunur.
 * - B48 uygulanmadiysa ya da `projects.short_name` ozelligi kapaliysa ekranlar
 *   eskisi gibi tek "Ad" gosterir; kayitli kisa ad silinmez.
 * - Proje tipleri (project_scopes) `projects.scope_types` ozelligine baglidir.
 * - Proje tipi koordinatorleri (B49, D-175) `projects.type_coordinators`
 *   ozelligine baglidir.
 */
final class ProjectNames
{
    /** B48 (kisa ad kolonu + project_scopes) bu ortamda uygulanmis mi. */
    public static function schemaReady(): bool
    {
        return SchemaReadiness::hasBatch('B48');
    }

    /** Kisa ad alani ve "Lisans adi" etiketi ekranda mi. */
    public static function shortNameEnabled(): bool
    {
        return self::schemaReady() && FeatureFlags::enabled(Feature::ProjectShortName);
    }

    /** Proje tipi secimi, sutunu ve kapsam karti ekranda mi. */
    public static function scopeTypesEnabled(): bool
    {
        return self::schemaReady() && FeatureFlags::enabled(Feature::ProjectScopeTypes);
    }

    /**
     * Proje tipi koordinatorleri (B49, D-175) ekranda mi: yonetim ekrani,
     * personel rozetleri, proje sayfasindaki koordinatorler, liste sutunu.
     */
    public static function coordinatorsEnabled(): bool
    {
        return SchemaReadiness::hasBatch('B49') && FeatureFlags::enabled(Feature::ProjectTypeCoordinators);
    }

    /** Gorunen ad: kisa ad, yoksa lisans adi. */
    public static function display(?Project $project): string
    {
        if ($project === null) {
            return '';
        }

        $short = $project->getAttributes()['short_name'] ?? null;

        if (is_string($short) && trim($short) !== '' && self::shortNameEnabled()) {
            return trim($short);
        }

        return (string) ($project->getAttributes()['name'] ?? '');
    }

    /** Kisa ad gosterildiyse ve lisans adindan farkliysa lisans adi (liste aciklamasi). */
    public static function licenseNameIfDifferent(?Project $project): ?string
    {
        if ($project === null || ! self::shortNameEnabled()) {
            return null;
        }

        $name = (string) ($project->getAttributes()['name'] ?? '');

        return self::display($project) !== $name && $name !== '' ? $name : null;
    }

    /**
     * Ad icin okunacak kolonlar (sinirli select'lerde): kisa ad yalniz B48
     * uygulandiysa istenir.
     *
     * @return list<string>
     */
    public static function columns(): array
    {
        return self::schemaReady() ? ['id', 'name', 'short_name'] : ['id', 'name'];
    }

    /**
     * Eager load kolon listesi: `'project:'.ProjectNames::select('business_case_id')`
     * => "id,business_case_id,name,short_name" (kisa ad yalniz B48 ile).
     */
    public static function select(string ...$extra): string
    {
        return implode(',', ['id', ...$extra, ...array_slice(self::columns(), 1)]);
    }

    /**
     * Proje secim kutularinda aranan kolonlar.
     *
     * @return list<string>
     */
    public static function searchColumns(): array
    {
        return self::schemaReady() ? ['name', 'short_name'] : ['name'];
    }

    /** Secim kutusu etiketi (relationship('project', 'name') ile). */
    public static function optionLabel(Project $project): string
    {
        return self::display($project);
    }

    /** Ad etiketi: ozellik aciksa "Lisans adi", degilse eski "Ad". */
    public static function nameLabel(): string
    {
        return self::shortNameEnabled() ? __('project.fields.license_name') : __('project.fields.name');
    }
}
