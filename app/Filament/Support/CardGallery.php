<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Party\CommunicationChannelType;
use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyRoleCode;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Document\Document;
use App\Models\Party\Address;
use App\Models\Party\CommunicationPoint;
use App\Models\Party\Party;
use App\Models\Party\PartyActivityArea;
use App\Models\Party\PartyRole;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Report\Report;
use App\Query\Project\ProjectStepReadiness;
use App\Services\Platform\SchemaReadiness;
use App\Support\ContactLinks;
use App\Support\DisplayTime;
use App\Support\RoleLabels;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

/**
 * Yeniden kullanilabilir kayit karti (UI deneme, 10 Eylul 2026).
 *
 * Tek bir kart anatomisi vardir ve her kayit turu (proje, personel, belge,
 * ileride sunucu) ayni iskeleti ayni bolgelerle doldurur — bolgeler sabit
 * yuksekliktedir, boylece yan yana duran kartlarda her sey ayni hizadadir:
 *
 *   gorsel/simge (sabit kutu) · baslik + alt baslik (tek satir) · durum rozeti
 *   (sag ust) · rozet satiri (tek satir, en cok 3) · bilgi alanlari (2x2)
 *   · altta eylemler (footer, her zaman en altta)
 *
 * Iki yerlesim: "cover" (gorsel ustte, dikey) ve "row" (gorsel solda, yatay).
 * Her sey Filament'in yerlesik sema bilesenleridir; Blade/JS yok. Tablo
 * tabanli karsiligi (arama/suzgec/sayfalama tabloda kalir) icin
 * projectTableCardColumns() ayni anatomiyi tablo yerlesim bilesenleriyle kurar.
 */
final class CardGallery
{
    public const VARIANT_COVER = 'cover';

    public const VARIANT_ROW = 'row';

    /** Rozet satirinda en cok kac rozet (tek satir, hiza bozulmasin). */
    private const MAX_BADGES = 3;

    /** Bilgi alani sayisi: her kartta 2x2. */
    private const ENTRY_COUNT = 4;

    public function __construct(private readonly ProjectStepReadiness $readiness) {}

    /**
     * @param  list<Component>  $cards
     */
    public function grid(array $cards, string $variant): Component
    {
        $columns = $variant === self::VARIANT_ROW
            ? ['default' => 1, 'lg' => 2, '2xl' => 3]
            : ['default' => 1, 'md' => 2, 'xl' => 3, '2xl' => 4];

        return Grid::make($columns)
            ->components($cards)
            ->extraAttributes(['class' => 'konelsis-card-grid']);
    }

    public function projectCard(Project $project, string $variant): Component
    {
        $steps = $this->readiness->forProject($project);
        $ready = count(array_filter($steps, fn (array $step): bool => (bool) $step['is_ready']));
        $focus = $project->primaryFocusWorkstream?->group?->localizedName();
        $customer = $project->customerParty;
        $manager = $project->projectManager;

        return $this->card(
            variant: $variant,
            title: (string) $project->name,
            subtitle: trim(($project->businessCode?->formatted_code ?? '').' · '.($customer?->display_name ?? ''), ' ·'),
            mediaUrl: $project->coverPhoto?->previewUrl('thumbnail'),
            mediaIcon: Heroicon::OutlinedRocketLaunch,
            mediaColor: 'primary',
            status: Text::make($project->status->getLabel())->badge()->color($project->status->getColor()),
            badges: [
                Text::make($focus ?? __('ui_gallery.values.no_focus'))->badge()->color($focus !== null ? 'primary' : 'gray')->icon(Heroicon::OutlinedPlayCircle),
                Text::make($project->criticality_profile->getLabel())->badge()->color($project->criticality_profile->getColor()),
                Text::make(__('ui_gallery.values.steps_ready', ['ready' => $ready, 'total' => count($steps)]))->badge()->color($ready === count($steps) && $steps !== [] ? 'success' : 'gray')->icon(Heroicon::OutlinedCheckCircle),
            ],
            entries: [
                $this->entry('manager', __('ui_gallery.entries.manager'), $manager?->full_name ?? '-', Heroicon::OutlinedUserCircle, $manager !== null ? 'primary' : 'gray', $manager !== null ? RecordLinks::detailUrl($manager, checkRecord: false) : null),
                $this->entry('city', __('ui_gallery.entries.city'), $project->site_city ?: '-', Heroicon::OutlinedMapPin, 'danger'),
                $this->entry('dates', __('ui_gallery.entries.dates'), ($project->planned_start_on?->format('d.m.Y') ?? '-').' → '.($project->planned_finish_on?->format('d.m.Y') ?? '-'), Heroicon::OutlinedCalendarDays, 'warning'),
                $this->entry('value', __('ui_gallery.entries.value'), $this->money($project->contract_value_snapshot, $project->currency_code), Heroicon::OutlinedBanknotes, 'success'),
            ],
            actions: [
                $this->editAction(Gate::allows('update', $project) ? ProjectResource::getUrl('edit', ['record' => $project]) : null),
            ],
            clickUrl: ProjectResource::getUrl('view', ['record' => $project]),
        );
    }

    public function personnelCard(Personnel $personnel, string $variant): Component
    {
        $competencies = $personnel->competencies->take(self::MAX_BADGES - 1)->pluck('name')->all();

        return $this->card(
            variant: $variant,
            title: (string) $personnel->full_name,
            subtitle: (string) ($personnel->job_title ?: __('ui_gallery.values.no_title')),
            mediaUrl: PersonnelAvatar::url($personnel),
            mediaIcon: Heroicon::OutlinedUserCircle,
            mediaColor: 'gray',
            status: Text::make($personnel->status->getLabel())->badge()->color($personnel->status->getColor()),
            badges: array_values(array_filter([
                $personnel->orgUnit !== null ? Text::make((string) $personnel->orgUnit->name)->badge()->color('gray')->icon(Heroicon::OutlinedBuildingOffice2) : null,
                ...array_map(fn (string $name): Text => Text::make($name)->badge()->color('info'), $competencies),
            ])),
            entries: [
                $this->entry('phone', __('ui_gallery.entries.phone'), $personnel->phone ?: '-', Heroicon::OutlinedPhone, 'success', ContactLinks::tel($personnel->phone)),
                $this->entry('email', __('ui_gallery.entries.email'), $personnel->email ?: '-', Heroicon::OutlinedEnvelope, 'primary', ContactLinks::mailto($personnel->email)),
                $this->entry('hired', __('ui_gallery.entries.hired_on'), $personnel->hired_on?->format('d.m.Y') ?? '-', Heroicon::OutlinedCalendarDays, 'warning'),
                $this->entry('login', __('ui_gallery.entries.last_login'), DisplayTime::format($personnel->last_login_at), Heroicon::OutlinedClock, 'gray'),
            ],
            actions: $this->personnelActions($personnel, PersonnelResource::getUrl('index', ['sohbet_kisi' => $personnel->getKey()])),
            mediaRounded: true,
            clickUrl: PersonnelResource::getUrl('view', ['record' => $personnel]),
        );
    }

    /**
     * Personel ayrinti sayfasinin ust karti (11 Eylul 2026, kullanici istegi:
     * "Kimlik ve iletisim" bolumu yerine yatay kart). Liste kartiyla ayni
     * anatomi; farklari: rozetler kesilmez (departman, tum yetkinlikler,
     * roller satir satir sarar), yukseklik serbest, fotograf yalniz cember,
     * bilgi alanlarinda TC kimlik no ve gorev; karta tiklama yok (zaten
     * ayrinti sayfasindayiz). Alt eylemler liste kartiyla ayni.
     */
    public function personnelDetailCard(Personnel $personnel): Component
    {
        $roles = $personnel->roles
            ->map(fn ($role): Text => Text::make(RoleLabels::name((string) $role->name))->badge()->color('warning')->icon(Heroicon::OutlinedShieldCheck))
            ->all();

        return $this->card(
            variant: self::VARIANT_ROW,
            title: (string) $personnel->full_name,
            subtitle: filled($personnel->personnel_no)
                ? __('personnel.fields.personnel_no').': '.$personnel->personnel_no
                : (string) ($personnel->job_title ?: __('ui_gallery.values.no_title')),
            mediaUrl: PersonnelAvatar::url($personnel),
            mediaIcon: Heroicon::OutlinedUserCircle,
            mediaColor: 'gray',
            status: Text::make($personnel->status->getLabel())->badge()->color($personnel->status->getColor()),
            badges: array_values(array_filter([
                $personnel->orgUnit !== null ? Text::make((string) $personnel->orgUnit->name)->badge()->color('gray')->icon(Heroicon::OutlinedBuildingOffice2) : null,
                ...$personnel->competencies
                    ->map(fn ($competency): Text => Text::make((string) $competency->name)->badge()->color($competency->category?->getColor() ?? 'gray'))
                    ->all(),
                ...$roles,
            ])),
            entries: [
                $this->entry('phone', __('ui_gallery.entries.phone'), $personnel->phone ?: '-', Heroicon::OutlinedPhone, 'success', ContactLinks::tel($personnel->phone)),
                $this->entry('email', __('ui_gallery.entries.email'), $personnel->email ?: '-', Heroicon::OutlinedEnvelope, 'primary', ContactLinks::mailto($personnel->email)),
                $this->entry('national_id', __('personnel.fields.national_id'), $personnel->national_id ?: '-', Heroicon::OutlinedFingerPrint, 'gray')
                    ->copyable(filled($personnel->national_id)),
                $this->entry('job_title', __('personnel.fields.job_title'), $personnel->job_title ?: '-', Heroicon::OutlinedBriefcase, 'warning'),
            ],
            actions: $this->personnelActions($personnel, PersonnelResource::getUrl('view', ['record' => $personnel, 'sohbet_kisi' => $personnel->getKey()])),
            mediaRounded: true,
            detail: true,
        );
    }

    /**
     * Personel kartinin alt eylemleri: WhatsApp, kurum ici sohbet, duzenle —
     * yalniz simge, yazi ipucunda (11 Eylul 2026, kullanici istegi). Liste
     * ve ayrinti karti ayni seti kullanir; yalniz sohbet baglantisinin
     * donecegi sayfa degisir.
     *
     * @return list<Action|null>
     */
    private function personnelActions(Personnel $personnel, string $chatUrl): array
    {
        return [
            Action::make('whatsapp_'.$personnel->getKey())
                ->label(__('personnel.actions.whatsapp_short'))
                ->tooltip(__('personnel.actions.whatsapp_short'))
                ->icon(BrandIcons::whatsapp())
                ->color('success')
                ->link()
                ->iconButton()
                ->visible(ContactLinks::whatsapp($personnel->phone) !== null)
                ->url((string) ContactLinks::whatsapp($personnel->phone), shouldOpenInNewTab: true),
            Action::make('chat_'.$personnel->getKey())
                ->label(__('personnel.actions.start_chat'))
                ->tooltip(__('personnel.actions.start_chat'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('primary')
                ->link()
                ->iconButton()
                ->visible(fn (): bool => ! $personnel->is(auth()->user()))
                ->url($chatUrl),
            $this->editAction(Gate::allows('update', $personnel) ? PersonnelResource::getUrl('edit', ['record' => $personnel]) : null, iconOnly: true),
        ];
    }

    /**
     * Taraf ayrinti karti (16 Eylul 2026, kullanici istegi: "personel
     * detayindaki gibi kart"). Yatay ayrinti karti: baslikta ad, alt baslikta
     * taraf no / tur / sehir; rozetlerde acik taraf tipleri, ziyaret onceligi
     * ve arsiv durumu; bilgi alanlarinda adres, kuruma ait BUTUN iletisim
     * bilgileri (her kanal ayri, tiklanabilir satir), network ve varsayilan
     * yetkili kisi. Alt eylemler: WhatsApp (ilk cep/telefon), duzenle.
     */
    public function partyDetailCard(Party $party, ?string $editUrl = null): Component
    {
        $address = $party->addresses->sortByDesc('is_primary')->first();
        $channels = $party->ownCommunicationPoints
            ->sortBy(fn (CommunicationPoint $point): string => self::channelOrder($point->channel_type).((int) ! $point->is_primary).$point->getKey())
            ->values();
        $contact = $party->contacts->sortByDesc('is_primary')->first();
        $phone = $channels->first(fn (CommunicationPoint $point): bool => $point->channel_type === CommunicationChannelType::Mobile)
            ?? $channels->first(fn (CommunicationPoint $point): bool => $point->channel_type === CommunicationChannelType::Phone);

        $entries = [
            $this->entry('address', __('ui_gallery.entries.address'), $address instanceof Address ? self::addressLine($address) : '-', Heroicon::OutlinedMapPin, 'danger'),
        ];

        foreach ($channels as $point) {
            $entries[] = $this->channelEntry($point);
        }

        if ($channels->isEmpty()) {
            $entries[] = $this->entry('no_channels', __('ui_gallery.entries.channels'), __('ui_gallery.values.no_channels'), Heroicon::OutlinedPhone, 'gray');
        }

        $entries[] = $this->entry('network', __('party.fields.network_note'), filled($party->network_note) ? (string) $party->network_note : '-', Heroicon::OutlinedShare, 'info');
        $entries[] = $this->entry(
            'contact',
            __('ui_gallery.entries.contact'),
            $contact !== null ? $contact->displayName().' · '.($contact->relationship_role?->getLabel() ?? '-') : '-',
            Heroicon::OutlinedUserCircle,
            'primary',
        );

        // Koken ve faaliyet alanlari (B33) kartta durur; yan ozet kisa kalir ve kart
        // ozete gore uzamaz (21 Eylul 2026 kullanici istegi). Derneklerde gosterilmez.
        if (SchemaReadiness::hasBatch('B33') && ! $this->isAssociation($party)) {
            $entries[] = $this->entry('origin', __('party.fields.origin'), $party->origin?->getLabel() ?? '-', Heroicon::OutlinedGlobeEuropeAfrica, 'info');
            $entries[] = TextEntry::make('activity_areas_card')
                ->label(__('party.sections.activity_areas'))
                ->state($party->activityAreas->map(fn (PartyActivityArea $row): string => $row->summary())->all())
                ->listWithLineBreaks()
                ->placeholder('-')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->iconColor('primary')
                ->size(TextSize::Small)
                ->weight(FontWeight::Medium)
                ->columnSpanFull();
        }

        return $this->card(
            variant: self::VARIANT_ROW,
            title: (string) $party->display_name,
            subtitle: implode(' · ', array_filter([
                (string) $party->party_no,
                $party->party_kind?->getLabel(),
                $address?->city ?: $party->country?->name_tr,
            ])),
            mediaUrl: null,
            mediaIcon: $party->party_kind === PartyKind::Person ? Heroicon::OutlinedUserCircle : Heroicon::OutlinedBuildingOffice2,
            mediaColor: $party->archived_at !== null ? 'gray' : 'primary',
            status: Text::make($party->status->getLabel())->badge()->color($party->status->getColor()),
            badges: array_values(array_filter([
                ...$party->roles
                    ->filter(fn (PartyRole $role): bool => $role->valid_until === null)
                    ->map(fn (PartyRole $role): Text => Text::make($role->role_code->getLabel())->badge()->color($role->role_code->getColor())->icon(Heroicon::OutlinedTag))
                    ->all(),
                $party->visit_priority !== null
                    ? Text::make($party->visit_priority->getLabel())->badge()->color($party->visit_priority->getColor())->icon(Heroicon::OutlinedFlag)
                    : null,
                $party->archived_at !== null
                    ? Text::make(__('party.values.archived'))->badge()->color('danger')->icon(Heroicon::OutlinedArchiveBox)
                    : null,
            ])),
            entries: $entries,
            actions: $this->partyActions($party, $phone?->value, $editUrl),
            detail: true,
        );
    }

    /**
     * Taraf kartinin alt eylemleri: WhatsApp (varsa) ve duzenle — yalniz simge.
     *
     * @return list<Action|null>
     */
    private function partyActions(Party $party, ?string $phone, ?string $editUrl = null): array
    {
        return [
            Action::make('whatsapp_party_'.$party->getKey())
                ->label(__('personnel.actions.whatsapp_short'))
                ->tooltip(__('personnel.actions.whatsapp_short'))
                ->icon(BrandIcons::whatsapp())
                ->color('success')
                ->link()
                ->iconButton()
                ->visible(ContactLinks::whatsapp($phone) !== null)
                ->url((string) ContactLinks::whatsapp($phone), shouldOpenInNewTab: true),
            $this->editAction(Gate::allows('update', $party) ? ($editUrl ?? PartyResource::getUrl('edit', ['record' => $party])) : null, iconOnly: true),
        ];
    }

    /** Iletisim kanali satiri: etiket kanal turu (+ amac), deger tiklanabilir. */
    private function channelEntry(CommunicationPoint $point): TextEntry
    {
        $type = $point->channel_type;
        $label = $type?->getLabel() ?? '-';

        if (filled($point->purpose)) {
            $label .= ' · '.$point->purpose;
        }

        $color = match ($type) {
            CommunicationChannelType::Email => 'primary',
            CommunicationChannelType::Mobile, CommunicationChannelType::Phone => 'success',
            CommunicationChannelType::Website, CommunicationChannelType::Linkedin => 'info',
            default => 'gray',
        };
        $newTab = $type === CommunicationChannelType::Website || $type === CommunicationChannelType::Linkedin;

        // Her satirin sonunda tek kucuk kopyala dugmesi (satirlar ayni hizada
        // kalsin diye deger yaninda yalniz bir dugme durur); telefonlarda
        // WhatsApp etiketin yanindadir (16 Eylul 2026, kullanici istegi).
        $key = (string) $point->getKey();
        $entry = $this->entry('channel_'.$key, $label, (string) $point->value, ChannelActions::icon($type), $color, ChannelActions::url($type, $point->value), $newTab)
            ->suffixActions(array_values(array_filter([ChannelActions::copy($key, $point->value)])));

        $whatsapp = ChannelActions::isPhone($type) ? ChannelActions::whatsapp($key, $point->value) : null;

        return $whatsapp !== null ? $entry->hintAction($whatsapp) : $entry;
    }

    /** Acik "Dernek / oda" tipi olan taraf (Dernekler menusunun kaydi). */
    private function isAssociation(Party $party): bool
    {
        return $party->roles->contains(fn (PartyRole $role): bool => $role->role_code === PartyRoleCode::Association && $role->valid_until === null);
    }

    /** Kanal siralamasi: cep, telefon, e-posta, web, faks, LinkedIn, diger. */
    private static function channelOrder(?CommunicationChannelType $type): string
    {
        $order = array_search($type?->value, ['mobile', 'phone', 'email', 'website', 'fax', 'linkedin', 'other'], true);

        return (string) ($order === false ? 9 : $order);
    }

    /**
     * Adres tek satir: satir 1, satir 2, ilce / sehir. Satirlar sehri zaten
     * iceriyorsa (ice aktarilan adreslerde oldugu gibi) sehir tekrar yazilmaz.
     */
    private static function addressLine(Address $address): string
    {
        $lines = trim(implode(', ', array_filter([(string) $address->line1, (string) $address->line2])));
        $place = trim((filled($address->district) ? $address->district.' / ' : '').(string) $address->city);
        $lower = fn (string $text): string => mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $text), 'UTF-8');

        if ($place === '' || ($address->city !== null && str_contains($lower($lines), $lower((string) $address->city)))) {
            return $lines !== '' ? $lines : ($place !== '' ? $place : '-');
        }

        return implode(', ', array_filter([$lines, $place]));
    }

    public function documentCard(Document $document, string $variant): Component
    {
        $revision = $document->displayRevision();
        $thumbnail = $revision !== null ? FileLinks::revisionThumbnail($revision) : null;
        $file = $revision?->originalFile();

        return $this->card(
            variant: $variant,
            title: (string) $document->title,
            subtitle: (string) $document->document_no.' · '.($document->documentType?->name ?? '-'),
            mediaUrl: $thumbnail,
            mediaIcon: ($revision?->isAuthored() ?? false) ? Heroicon::OutlinedPencilSquare : Heroicon::OutlinedDocumentText,
            mediaColor: 'info',
            status: Text::make($document->status->getLabel())->badge()->color($document->status->getColor()),
            badges: array_values(array_filter([
                Text::make($document->classification?->name_tr ?? '-')->badge()->color('warning')->icon(Heroicon::OutlinedLockClosed),
                $revision !== null ? Text::make('Rev '.$revision->revision_code.' · '.$revision->status->getLabel())->badge()->color($revision->status->getColor()) : null,
                $document->is_controlled ? Text::make(__('document.fields.is_controlled'))->badge()->color('success')->icon(Heroicon::OutlinedShieldCheck) : null,
            ])),
            entries: [
                $this->entry('owner', __('ui_gallery.entries.owner'), $document->owner?->full_name ?? '-', Heroicon::OutlinedUserCircle, 'primary', $document->owner !== null ? RecordLinks::detailUrl($document->owner, checkRecord: false) : null),
                $this->entry('project', __('ui_gallery.entries.project'), $document->project?->name ?? '-', Heroicon::OutlinedRocketLaunch, $document->project !== null ? 'primary' : 'gray', $document->project !== null ? RecordLinks::detailUrl($document->project, checkRecord: false) : null),
                $this->entry('file', __('ui_gallery.entries.file'), $file !== null ? $file->original_name.' · '.$file->humanSize() : (($revision?->isAuthored() ?? false) ? __('document_revision.values.authored') : '-'), Heroicon::OutlinedPaperClip, 'gray'),
                $this->entry('updated', __('ui_gallery.entries.updated'), DisplayTime::format($document->updated_at), Heroicon::OutlinedClock, 'gray'),
            ],
            actions: [
                Action::make('download_'.$document->getKey())
                    ->label(__('document_revision.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->link()
                    ->visible($revision !== null && FileLinks::revisionOriginal($revision) !== null)
                    ->url($revision !== null ? FileLinks::revisionOriginal($revision, 'download') : null),
            ],
            clickUrl: DocumentResource::getUrl('view', ['record' => $document]),
        );
    }

    /**
     * Sunucu karti (ornek veri): ileride sunucu modulu icin ayni anatomi —
     * durum rozeti, olcumler (CPU/RAM/disk) rozet olarak, ortam ve konum
     * bilgi alaninda.
     *
     * @param  array{name: string, environment: string, status: string, ip: string, cpu: int, ram: int, disk: int, uptime: string, location: string, checked_at: string}  $server
     */
    public function serverCard(array $server, string $variant): Component
    {
        $statusColor = match ($server['status']) {
            'running' => 'success',
            'warning' => 'warning',
            default => 'danger',
        };

        return $this->card(
            variant: $variant,
            title: $server['name'],
            subtitle: $server['ip'],
            mediaUrl: null,
            mediaIcon: Heroicon::OutlinedServerStack,
            mediaColor: $statusColor,
            status: Text::make(__('ui_gallery.values.server_status.'.$server['status']))->badge()->color($statusColor)->icon(Heroicon::OutlinedSignal),
            badges: [
                $this->metricBadge('CPU', $server['cpu']),
                $this->metricBadge('RAM', $server['ram']),
                $this->metricBadge(__('ui_gallery.entries.disk'), $server['disk']),
            ],
            entries: [
                $this->entry('environment', __('ui_gallery.entries.environment'), __('ui_gallery.values.environment.'.$server['environment']), Heroicon::OutlinedCube, $server['environment'] === 'production' ? 'primary' : 'gray'),
                $this->entry('location', __('ui_gallery.entries.location'), $server['location'], Heroicon::OutlinedMapPin, 'danger'),
                $this->entry('uptime', __('ui_gallery.entries.uptime'), $server['uptime'], Heroicon::OutlinedClock, 'success'),
                $this->entry('checked', __('ui_gallery.entries.last_check'), $server['checked_at'], Heroicon::OutlinedArrowPath, 'gray'),
            ],
            actions: [
                Action::make('server_'.md5($server['name']))
                    ->label(__('ui_gallery.actions.details'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->link()
                    ->disabled(),
            ],
        );
    }

    // ------------------------------------------------------------------ tablo kartlari

    /**
     * Tablo tabanli kart: ayni anatomi Filament tablo yerlesim bilesenleriyle.
     * Arama, siralama, suzgec ve sayfalama tabloda kalir; satir eylemleri
     * Filament tarafindan kartin altina cizilir.
     *
     * @return list<mixed>
     */
    public function projectTableCardColumns(): array
    {
        return [
            Stack::make([
                ImageColumn::make('cover')
                    ->getStateUsing(fn (Project $record): ?string => $record->coverPhoto?->previewUrl('thumbnail'))
                    ->imageHeight('9rem')
                    ->imageWidth('100%')
                    ->extraImgAttributes(['class' => 'konelsis-tcard-cover'])
                    ->visible(fn (?Project $record): bool => $record?->coverPhoto !== null),
                TextColumn::make('no_cover')
                    ->state(__('ui_gallery.values.no_cover'))
                    ->icon(Heroicon::OutlinedPhoto)
                    ->color('gray')
                    ->size(TextSize::ExtraSmall)
                    ->extraAttributes(['class' => 'konelsis-tcard-cover-empty'])
                    ->visible(fn (?Project $record): bool => $record === null || $record->coverPhoto === null),
                Split::make([
                    TextColumn::make('name')
                        ->weight(FontWeight::Bold)
                        ->size(TextSize::Medium)
                        ->searchable()
                        ->sortable()
                        ->description(fn (Project $record): string => trim(($record->businessCode?->formatted_code ?? '').' · '.($record->customerParty?->display_name ?? ''), ' ·')),
                    TextColumn::make('status')
                        ->badge()
                        ->grow(false),
                ]),
                Split::make([
                    TextColumn::make('primaryFocusWorkstream.group.name_tr')
                        ->badge()
                        ->color('primary')
                        ->icon(Heroicon::OutlinedPlayCircle)
                        ->placeholder(__('ui_gallery.values.no_focus'))
                        ->grow(false),
                    TextColumn::make('criticality_profile')
                        ->badge()
                        ->grow(false),
                ]),
                Split::make([
                    TextColumn::make('projectManager.full_name')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->iconColor('primary')
                        ->size(TextSize::Small)
                        ->placeholder('-'),
                    TextColumn::make('site_city')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->iconColor('danger')
                        ->size(TextSize::Small)
                        ->placeholder('-'),
                ])->from('sm'),
                Split::make([
                    TextColumn::make('planned_start_on')
                        ->date('d.m.Y')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->iconColor('warning')
                        ->size(TextSize::Small)
                        ->sortable()
                        ->placeholder('-'),
                    TextColumn::make('contract_value_snapshot')
                        ->getStateUsing(fn (Project $record): string => $this->money($record->contract_value_snapshot, $record->currency_code))
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->iconColor('success')
                        ->size(TextSize::Small),
                ])->from('sm'),
            ])->space(3)->extraAttributes(['class' => 'konelsis-tcard']),
        ];
    }

    // ------------------------------------------------------------------ iskelet

    /**
     * Sabit bolgeli kart: gorsel kutusu, baslik satiri, rozet satiri (en cok
     * MAX_BADGES), 2x2 bilgi alani (eksikse "-" ile doldurulur) ve footer.
     *
     * @param  list<Text>  $badges
     * @param  list<TextEntry>  $entries
     * @param  list<Action|null>  $actions
     */
    /**
     * Rapor ayrinti karti (kullanici istegi, 23 Eylul 2026): personel
     * ayrintisiyla ayni satir kart bicimi - rapor no ve basligi, durum
     * rozeti, taslak / tur / gizlilik rozetleri, yazan, konu, donem ve
     * gonderim bilgileri.
     */
    public function reportDetailCard(Report $report, ?string $subjectUrl = null): Component
    {
        $template = $report->template();

        return $this->card(
            variant: self::VARIANT_ROW,
            title: (string) ($report->title ?: $report->templateName()),
            subtitle: (string) $report->report_no,
            mediaUrl: null,
            mediaIcon: $template?->icon() ?? Heroicon::OutlinedDocumentChartBar,
            mediaColor: 'primary',
            status: Text::make($report->status->getLabel())->badge()->color($report->status->getColor()),
            badges: array_values(array_filter([
                Text::make($report->templateName())->badge()->color('gray')->icon(Heroicon::OutlinedDocumentText),
                Text::make($report->kind->getLabel())->badge()->color('gray'),
                $report->is_confidential
                    ? Text::make(__('report.values.confidential'))->badge()->color('danger')->icon(Heroicon::OutlinedLockClosed)
                    : null,
            ])),
            entries: [
                $this->entry('author', __('report.fields.author'), (string) ($report->author?->full_name ?: '-'), Heroicon::OutlinedUserCircle, 'primary', $report->author !== null ? RecordLinks::detailUrl($report->author, checkRecord: false) : null),
                $this->entry('subject', __('report.fields.subject'), (string) ($report->subjectLabel() ?: '-'), $report->subject_kind->getIcon(), 'warning', $subjectUrl),
                $this->entry('period', __('report.fields.period'), (string) ($report->periodLabel() ?: '-'), Heroicon::OutlinedCalendarDays, 'success'),
                $this->entry('unit', __('report.fields.author_org_unit'), (string) ($report->authorOrgUnit?->name ?: '-'), Heroicon::OutlinedBuildingOffice2, 'gray'),
            ],
            actions: [],
            detail: true,
        );
    }
    private function card(
        string $variant,
        string $title,
        string $subtitle,
        ?string $mediaUrl,
        Heroicon $mediaIcon,
        string $mediaColor,
        Text $status,
        array $badges,
        array $entries,
        array $actions,
        bool $mediaRounded = false,
        ?string $clickUrl = null,
        bool $detail = false,
    ): Component {
        // Yuvarlak fotograf: liste kartinda 6rem, ayrinti kartinda 7rem (satir
        // ici olcu; CSS'teki kutu boyutundan bagimsiz).
        $roundSize = $detail ? '7rem' : '6rem';

        $media = Group::make([
            $mediaUrl !== null
                ? Image::make($mediaUrl, $title)->imageHeight($mediaRounded ? $roundSize : '8rem')->imageWidth($mediaRounded ? $roundSize : '100%')
                : Icon::make($mediaIcon)->color($mediaColor)->size(IconSize::TwoExtraLarge),
        ])->extraAttributes(['class' => 'konelsis-card-media'.($mediaRounded ? ' konelsis-card-media-round' : '').($mediaUrl === null ? ' konelsis-card-media-empty' : '')]);

        // Ayrinti kartinda rozetler kesilmez, satir satir sarar (CSS: .konelsis-card-detail).
        $badges = $detail ? array_values($badges) : array_slice(array_values($badges), 0, self::MAX_BADGES);

        while (count($entries) < self::ENTRY_COUNT) {
            $entries[] = $this->entry('filler_'.count($entries), '\u{200B}', '-', Heroicon::OutlinedMinus, 'gray');
        }

        $body = [
            Flex::make($badges)
                ->extraAttributes(['class' => 'konelsis-card-badges']),
            // Ayrinti kartinda dar ekranda bilgi alanlari tek sutun (telefon /
            // e-posta kesilmesin); liste kartinda 2x2 sabit hiza korunur.
            // Ayrinti kartinda bilgi alani sayisi serbesttir (taraf kartinda her
            // iletisim kanali ayri satir); liste kartinda 2x2 ile sinirlidir.
            Grid::make($detail ? ['default' => 1, 'sm' => 2] : ['default' => 2])
                ->components($detail ? $entries : array_slice($entries, 0, self::ENTRY_COUNT))
                ->extraAttributes(['class' => 'konelsis-card-entries']),
        ];

        // Yatay yerlesim duyarlidir (11 Eylul 2026, kullanici bildirimi: mobilde
        // ayrinti karti bozuk): kucuk ekranda gorsel uste, govde alta; sm ve
        // ustunde gorsel solda 1/4, govde sagda 3/4.
        $schema = $variant === self::VARIANT_ROW
            ? [
                Grid::make(['default' => 1, 'sm' => 4])->components([
                    Group::make([$media])->columnSpan(['default' => 1, 'sm' => 1]),
                    Group::make($body)->columnSpan(['default' => 1, 'sm' => 3])->extraAttributes(['class' => 'konelsis-card-body']),
                ]),
            ]
            : [$media, ...$body];

        $attributes = ['class' => 'konelsis-card konelsis-card-'.$variant.($clickUrl !== null ? ' konelsis-card-clickable' : '').($detail ? ' konelsis-card-detail' : '')];

        if ($clickUrl !== null) {
            // Kartin herhangi bir bos noktasina tiklaninca ayrinti sayfasina
            // gider (11 Eylul 2026, kullanici istegi: ayri "Ac" dugmesine
            // gerek yok). Tiklama bir baglanti/dugmenin (telefon, e-posta,
            // WhatsApp, Duzenle...) icinden geldiyse dokunulmaz; o eleman
            // kendi isini yapar. Filament'in kart semasinda boyle bir
            // "tum kart tiklanabilir" secenegi yok; tek satirlik bu kontrol
            // disinda JS eklenmedi. Adres 'data-href'te tasinir (onclick
            // icinde tirnakli bir metin dizesi olarak gecmez) cunku Filament'in
            // ozellik torbasi ic ice ciftli tirnaklari bozuyordu.
            $attributes['data-href'] = $clickUrl;
            $attributes['onclick'] = "if(!event.target.closest('a,button')){window.location=this.dataset.href}";
        }

        return Section::make($title)
            ->description($subtitle)
            ->afterHeader([$status])
            ->schema($schema)
            ->footerActions(array_values(array_filter($actions)))
            ->footerActionsAlignment(Alignment::End)
            ->compact()
            ->extraAttributes($attributes);
    }

    private function entry(string $key, string $label, string $value, Heroicon $icon, string $iconColor, ?string $url = null, bool $newTab = false): TextEntry
    {
        $entry = TextEntry::make($key.'_'.md5($label.$value))
            ->label($label)
            ->state($value)
            ->icon($icon)
            ->iconColor($iconColor)
            ->size(TextSize::Small)
            ->weight(FontWeight::Medium);

        if ($url !== null && $value !== '-') {
            $entry->url($url, shouldOpenInNewTab: $newTab)->color('primary');
        }

        return $entry;
    }

    private function metricBadge(string $label, int $percent): Text
    {
        $color = match (true) {
            $percent >= 85 => 'danger',
            $percent >= 65 => 'warning',
            default => 'success',
        };

        return Text::make($label.' '.$percent.'%')->badge()->color($color);
    }

    private function editAction(?string $url, bool $iconOnly = false): ?Action
    {
        if ($url === null) {
            return null;
        }

        $action = Action::make('edit_'.md5($url))
            ->label(__('ui_gallery.actions.edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->link()
            ->url($url);

        return $iconOnly ? $action->iconButton()->tooltip(__('ui_gallery.actions.edit')) : $action;
    }

    private function money(mixed $amount, ?string $currency): string
    {
        if ($amount === null) {
            return '-';
        }

        return Number::format((float) $amount, precision: 0, locale: 'tr').' '.($currency ?? '');
    }
}
