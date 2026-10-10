<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Acquisition\ProposalStatus;
use App\Enums\Platform\Feature;
use App\Filament\Clusters\UiGallery;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Acquisition\Proposal;
use App\Models\Personnel\Personnel;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Ui\ChainGalleryQueries;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;
use App\Support\DisplayTime;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * UI Deneme > Adim denemeleri (D-141, 29 Eylul 2026 kullanici istegi): teklif
 * sayfasindaki "Potansiyel is -> Teklif -> Proje" cubugu yerine ne konacagi.
 * D-140'taki 10 zincir gosterimi kullanici istegiyle silindi ("hepsi kotu ...
 * daha anlasilir olmali"); yerine kayit turlerini cizmek yerine isin nerede
 * oldugunu duz Turkce ve tanidik kaliplarla (kargo takibi, durum karti,
 * yapilacaklar listesi) anlatan 8 gosterim geldi. Baska kayda gitmek hep
 * acikca yazan bir dugmeyle; sekme ya da tiklanir adim yok. Kayit degistiren
 * dugmeler pasif, baglantilar gercek kayitlari acar. Katalogun parcasidir.
 */
class UiChainGallery extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowLongRight;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 4;

    /** Gosterilen ornek teklif (?teklif=ID). */
    #[Url(as: 'teklif')]
    public ?int $teklif = null;

    public static function getNavigationLabel(): string
    {
        return __('ui_gallery.pages.chain');
    }

    public function getTitle(): string
    {
        return __('ui_gallery.chain.title');
    }

    public function getSubheading(): ?string
    {
        return __('ui_gallery.chain.subheading');
    }

    /**
     * Calisma arayuzu (D-91): yalniz gelistirme ortaminda, tam yetkili rol.
     */
    public static function canAccess(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        $user = auth()->user();

        return FeatureFlags::enabled(Feature::UiGallery) && $user instanceof Personnel && app(RoleResolver::class)->hasFullAccess($user);
    }

    /** Icerik semasi bir kez uretildigi icin secim sayfayi yeniden acar. */
    public function updatedTeklif(): void
    {
        $this->redirect(static::getUrl(['teklif' => $this->teklif]));
    }

    public function content(Schema $schema): Schema
    {
        $queries = app(ChainGalleryQueries::class);
        $proposal = $queries->proposal($this->teklif);

        if ($proposal === null) {
            return $schema->components([
                Callout::make(__('ui_gallery.chain.empty'))->warning()->icon(Heroicon::OutlinedExclamationTriangle),
            ]);
        }

        $this->teklif ??= (int) $proposal->getKey();
        $d = $this->deal($proposal);
        // 9-12 (dikey hattin diger durak kartlari) icin potansiyel is ve proje kunyeleri.
        $cards = $this->cards($proposal);

        $designs = [
            1 => ['tracker', $this->tracker($d)],
            2 => ['path', $this->path($d)],
            3 => ['status', $this->statusCard($d)],
            4 => ['story', $this->story($d)],
            5 => ['qa', $this->questions($d)],
            6 => ['todo', $this->todo($d)],
            7 => ['header', $this->header($d)],
            8 => ['metro', $this->metro($d)],
            9 => ['metro_all', $this->metroAll($d, $cards)],
            10 => ['metro_fold', $this->metroFold($d, $cards)],
            11 => ['metro_panel', $this->metroPanel($d, $cards)],
            12 => ['metro_chips', $this->metroChips($d, $cards)],
        ];

        $blocks = [];

        foreach ($designs as $number => [$key, $demo]) {
            $blocks[] = Group::make([
                Text::make($number.' · '.__('ui_gallery.chain.designs.'.$key.'.name'))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large),
                Text::make(__('ui_gallery.chain.designs.'.$key.'.description'))->color('gray'),
                $demo,
            ])->extraAttributes(['class' => 'kc-demo-block']);
        }

        return $schema->columns(1)->components([
            Callout::make(__('ui_gallery.chain.intro_heading'))
                ->description(__('ui_gallery.chain.intro'))
                ->info()
                ->icon(Heroicon::OutlinedLightBulb)
                ->footer(__('ui_gallery.chain.shown', [
                    'proposal' => $d['proposal']['code'].' · '.$d['proposal']['title'],
                    'state' => __('ui_gallery.chain.state.'.$d['state'].'.headline'),
                ])),
            Grid::make(['default' => 1, 'md' => 2])->components([
                Select::make('teklif')
                    ->label(__('ui_gallery.chain.pick'))
                    ->options(fn (): array => $queries->options())
                    ->searchable()
                    ->selectablePlaceholder(false)
                    ->live(),
            ]),
            ...$blocks,
        ]);
    }

    /**
     * Gosterimlerin ortak verisi. state: isin su anki hali — draft (teklif
     * hazirlaniyor), sent (musteride, cevap bekleniyor), won (kazanildi, proje
     * yok), lost (kaybedildi), project (proje acildi).
     *
     * @return array<string, mixed>
     */
    private function deal(Proposal $proposal): array
    {
        $case = $proposal->businessCase;
        $project = $case?->project;
        $version = $proposal->currentVersion;
        $zone = DisplayTime::zone();
        $date = static fn (mixed $value): ?string => $value === null ? null : Carbon::parse($value)->timezone($zone)->format('d.m.Y');
        $status = $proposal->status;
        $submittedAt = $version?->submitted_at !== null ? Carbon::parse($version->submitted_at)->timezone($zone) : null;
        $sent = $submittedAt !== null
            || in_array($status, [ProposalStatus::Submitted, ProposalStatus::Negotiation, ProposalStatus::Accepted, ProposalStatus::Rejected], true);
        $won = $case?->outcome === BusinessOutcome::Won || $status === ProposalStatus::Accepted;
        $lost = $proposal->offer_status === OfferStatus::Lost || $case?->outcome === BusinessOutcome::Lost || $status === ProposalStatus::Rejected;

        $siblings = $case?->proposals->sortBy('proposal_no')->values();
        $position = $siblings?->search(fn (Proposal $sibling): bool => $sibling->is($proposal));

        return [
            'state' => match (true) {
                $project !== null => 'project',
                $lost => 'lost',
                $won => 'won',
                $sent => 'sent',
                default => 'draft',
            },
            'case' => [
                'code' => $case?->caseCode()?->formatted_code ?? '-',
                'title' => (string) ($case?->title ?? '-'),
                'customer' => (string) ($case?->primaryParty?->display_name ?? '-'),
                'opened' => $date($case?->created_at) ?? '-',
                'url' => $case !== null ? BusinessCaseResource::getUrl('view', ['record' => $case]) : null,
                'count' => $siblings?->count() ?? 0,
                'position' => $position === false || $position === null ? 1 : $position + 1,
            ],
            'proposal' => [
                'code' => (string) $proposal->proposal_no,
                'title' => (string) $proposal->title,
                'status' => (string) ($status?->getLabel() ?? '-'),
                'status_color' => $status?->getColor() ?? 'gray',
                'offer' => $proposal->offer_status?->getLabel(),
                'offer_color' => $proposal->offer_status?->getColor() ?? 'gray',
                'version' => $version === null
                    ? __('proposal.steps.no_version')
                    : __('proposal.steps.version', ['no' => $version->version_no, 'status' => (string) ($version->status?->getLabel() ?? '-')]),
                'submitted' => $submittedAt?->format('d.m.Y'),
                'days' => $submittedAt === null ? null : (int) $submittedAt->copy()->startOfDay()->diffInDays(Carbon::now($zone)->startOfDay()),
                // D-180: tutar + para birimi simgesi.
                'price' => Money::format($version?->total_price, $version?->currency_code),
            ],
            'project' => $project === null ? null : [
                'code' => $project->businessCode?->formatted_code ?? '-',
                'name' => (string) $project->name,
                'opened' => $date($project->created_at) ?? '-',
                'url' => ProjectResource::getUrl('view', ['record' => $project]),
            ],
        ];
    }

    /** Durum cumleleri (dil dosyasi, ui_gallery.chain.state.*). */
    private function say(array $d, string $key): string
    {
        return __('ui_gallery.chain.state.'.$d['state'].'.'.$key, [
            'date' => $d['proposal']['submitted'] ?? '-',
            'wait' => $this->wait($d),
            'code' => $d['project']['code'] ?? '-',
        ]);
    }

    /** "bugun gonderildi" ya da "12 gundur cevap bekleniyor". */
    private function wait(array $d): string
    {
        $days = $d['proposal']['days'];

        return $days === null || $days <= 0
            ? __('ui_gallery.chain.wait_today')
            : __('ui_gallery.chain.wait_days', ['days' => $days]);
    }

    /** 1: kargo takibi — bagli daireler, dolan cizgi, "Buradasiniz" isareti. */
    private function tracker(array $d): Component
    {
        $project = $d['project'];
        $projectState = $project !== null ? 'done' : ($d['state'] === 'lost' ? 'off' : 'todo');

        $steps = [
            ['done', Heroicon::OutlinedCheck, __('ui_gallery.chain.case'), $d['case']['title'], __('ui_gallery.chain.opened_on', ['date' => $d['case']['opened']]), $this->caseButton('d1', $d)],
            ['current', Heroicon::OutlinedClipboardDocumentList, __('ui_gallery.chain.proposal'), $d['proposal']['code'], $this->say($d, 'short'), null],
            [
                $projectState,
                $project !== null ? Heroicon::OutlinedCheck : Heroicon::OutlinedRocketLaunch,
                __('ui_gallery.chain.project'),
                $project !== null ? $project['code'] : '—',
                $project !== null ? __('ui_gallery.chain.opened_on', ['date' => $project['opened']]) : ($d['state'] === 'lost' ? Str::ucfirst(__('ui_gallery.chain.project_off')) : __('ui_gallery.chain.project_when')),
                $project !== null ? $this->projectButton('d1', $d) : null,
            ],
        ];

        return Section::make(__('ui_gallery.chain.track_heading'))
            ->icon(Heroicon::OutlinedMapPin)
            ->schema([
                Grid::make(['default' => 3])
                    ->extraAttributes(['class' => 'kc-track'.($project !== null ? ' kc-track-full' : '')])
                    ->components(array_map(fn (array $step): Component => Group::make([
                        Icon::make($step[1])->extraAttributes(['class' => 'kc-track-dot kc-'.$step[0]]),
                        Text::make($step[2])->weight(FontWeight::Bold)->size(TextSize::Large)->color($step[0] === 'current' ? 'primary' : ($step[0] === 'done' ? null : 'gray')),
                        Text::make($step[3])->weight(FontWeight::Medium)->color($step[0] === 'todo' || $step[0] === 'off' ? 'gray' : null),
                        Text::make($step[4])->size(TextSize::Small)->color($step[0] === 'current' ? 'primary' : 'gray'),
                        ...($step[0] === 'current' ? [Text::make(__('ui_gallery.chain.here'))->badge()->color('primary')->icon(Heroicon::OutlinedMapPin)] : []),
                        ...($step[5] !== null ? [Actions::make([$step[5]])->alignCenter()] : []),
                    ])->extraAttributes(['class' => 'kc-track-step']), $steps)),
            ]);
    }

    /** 2: satis yolu — isin bes asamasi, bulunulan asama dolu; altinda bu asamada yapilacaklar. */
    private function path(array $d): Component
    {
        $state = $d['state'];
        $order = ['draft' => 1, 'sent' => 2, 'won' => 3, 'lost' => 3, 'project' => 4];
        $at = $order[$state];

        $stages = [
            [__('ui_gallery.chain.stages.opportunity'), $d['case']['code']],
            [__('ui_gallery.chain.stages.preparing'), $d['proposal']['code']],
            [__('ui_gallery.chain.stages.customer'), $d['proposal']['submitted'] ?? ''],
            [match ($state) {
                'lost' => __('ui_gallery.chain.stages.lost'),
                'won', 'project' => __('ui_gallery.chain.stages.won'),
                default => __('ui_gallery.chain.stages.decision'),
            }, ''],
            [__('ui_gallery.chain.stages.project'), $d['project']['code'] ?? ''],
        ];

        $cells = [];

        foreach ($stages as $index => [$label, $sub]) {
            $class = match (true) {
                $index === 3 && $state === 'lost' => 'kc-lost',
                $index === 4 && $state === 'lost' => 'kc-off',
                $index < $at => 'kc-done',
                $index === $at => 'kc-current',
                default => 'kc-todo',
            };

            $cells[] = Group::make([
                Text::make($label)->weight(FontWeight::Bold),
                Text::make($sub !== '' ? $sub : ' ')->size(TextSize::ExtraSmall),
            ])->extraAttributes(['class' => 'kc-path-step '.$class]);
        }

        $guide = (array) __('ui_gallery.chain.guide.'.$state);

        return Section::make()
            ->schema([
                Grid::make(['default' => 5])->extraAttributes(['class' => 'kc-path'])->components($cells),
                Group::make([
                    Text::make(__('ui_gallery.chain.now', ['stage' => $this->say($d, 'headline')]))->weight(FontWeight::Bold)->size(TextSize::Large),
                    Text::make(__('ui_gallery.chain.todo_here'))->size(TextSize::Small)->color('gray'),
                    UnorderedList::make(array_map(fn (string $line): Text => Text::make($line), $guide))->columns(['default' => 1, 'sm' => 1]),
                    $this->nextActions('d2', $d),
                ])->extraAttributes(['class' => 'kc-guide']),
            ]);
    }

    /** 3: durum karti — buyuk durum cumlesi, kac gundur, yapilacak is; en altta bagli is. */
    private function statusCard(array $d): Component
    {
        $icon = match ($d['state']) {
            'draft' => Heroicon::OutlinedPencilSquare,
            'sent' => Heroicon::OutlinedClock,
            'won' => Heroicon::OutlinedTrophy,
            'lost' => Heroicon::OutlinedXCircle,
            default => Heroicon::OutlinedRocketLaunch,
        };

        return Section::make()
            ->extraAttributes(['class' => 'kc-status'])
            ->schema([
                Flex::make([
                    Icon::make($icon)->extraAttributes(['class' => 'kc-status-icon kc-'.$d['state']])->grow(false),
                    Group::make([
                        Text::make(__('ui_gallery.chain.status_label'))->size(TextSize::ExtraSmall)->weight(FontWeight::SemiBold)->color('gray'),
                        Text::make($this->say($d, 'headline'))->extraAttributes(['class' => 'kc-status-headline']),
                        Text::make($this->say($d, 'detail'))->color('gray'),
                        $this->nextActions('d3', $d),
                    ])->extraAttributes(['class' => 'kc-tight']),
                ]),
                Flex::make([
                    Text::make(__('ui_gallery.chain.belongs_to'))->size(TextSize::Small)->color('gray')->grow(false),
                    $this->caseLink('d3', $d)->grow(false),
                    Text::make('·')->color('gray')->grow(false),
                    Text::make(__('ui_gallery.chain.project').': '.($d['project'] !== null ? $d['project']['code'] : ($d['state'] === 'lost' ? __('ui_gallery.chain.project_off') : __('ui_gallery.chain.no_project'))))
                        ->size(TextSize::Small)
                        ->color('gray'),
                ])->from('md')->extraAttributes(['class' => 'kc-status-foot']),
            ]);
    }

    /** 4: tek paragrafta anlatim. */
    private function story(array $d): Component
    {
        $sibling = $d['case']['count'] > 1
            ? ' '.__('ui_gallery.chain.story.siblings', ['count' => $d['case']['count'], 'position' => $d['case']['position']])
            : '';

        $text = __('ui_gallery.chain.story.belongs', [
            'customer' => $this->md($d['case']['customer']),
            'case' => $this->md($d['case']['title']),
        ]).$sibling.' '.$this->say($d, 'story');

        return Section::make(__('ui_gallery.chain.story.heading'))
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->schema([
                TextEntry::make('d4_story')
                    ->hiddenLabel()
                    ->state($text)
                    ->markdown()
                    ->extraAttributes(['class' => 'kc-story']),
                Actions::make([
                    $this->caseButton('d4', $d, withTitle: false),
                    ...$this->nextActionList('d4', $d),
                ]),
            ]);
    }

    /** 5: uc soru, uc cevap. */
    private function questions(array $d): Component
    {
        $rows = [
            [
                __('ui_gallery.chain.qa.where'),
                __('ui_gallery.chain.qa.where_answer', ['code' => $d['case']['code'], 'case' => $this->md($d['case']['title']), 'customer' => $this->md($d['case']['customer'])])
                    .($d['case']['count'] > 1 ? ' '.__('ui_gallery.chain.story.siblings', ['count' => $d['case']['count'], 'position' => $d['case']['position']]) : ''),
                [$this->caseButton('d5', $d, withTitle: false)],
            ],
            [__('ui_gallery.chain.qa.status'), '**'.$this->say($d, 'headline').'.** '.$this->say($d, 'detail'), []],
            [__('ui_gallery.chain.qa.next'), $this->say($d, 'next_help'), $this->nextActionList('d5', $d)],
        ];

        return Section::make()
            ->schema(array_map(fn (array $row, int $index): Component => Grid::make(['default' => 1, 'md' => 12])
                ->extraAttributes(['class' => 'kc-list-row'])
                ->components([
                    Text::make($row[0])
                        ->weight(FontWeight::Bold)
                        ->size(TextSize::Large)
                        ->icon(Heroicon::OutlinedQuestionMarkCircle)
                        ->columnSpan(['default' => 1, 'md' => 4]),
                    Group::make([
                        TextEntry::make('d5_answer_'.$index)->hiddenLabel()->state($row[1])->markdown()->extraAttributes(['class' => 'kc-story']),
                        ...($row[2] !== [] ? [Actions::make($row[2])] : []),
                    ])->extraAttributes(['class' => 'kc-tight'])->columnSpan(['default' => 1, 'md' => 8]),
                ]), $rows, array_keys($rows)));
    }

    /** 6: yapilacaklar listesi — biten adimlar isaretli, siradaki adim vurgulu ve dugmeli. */
    private function todo(array $d): Component
    {
        $state = $d['state'];
        $p = $d['proposal'];
        $project = $d['project'];

        $rows = [
            ['done', __('ui_gallery.chain.todo.case'), $d['case']['code'].' · '.$d['case']['title'].' · '.$d['case']['opened'], [$this->caseButton('d6', $d, withTitle: false)]],
            [$state === 'draft' ? 'current' : 'done', __($state === 'draft' ? 'ui_gallery.chain.todo.prepare_now' : 'ui_gallery.chain.todo.prepare'), $p['code'].' · '.$p['version'], $state === 'draft' ? $this->nextActionList('d6a', $d) : []],
            [$state === 'draft' ? 'todo' : 'done', __('ui_gallery.chain.todo.send'), $p['submitted'] ?? __('ui_gallery.chain.not_sent'), []],
            [
                match ($state) {
                    'sent' => 'current',
                    'won', 'project' => 'done',
                    'lost' => 'lost',
                    default => 'todo',
                },
                match ($state) {
                    'lost' => __('ui_gallery.chain.todo.decision_lost'),
                    'won', 'project' => __('ui_gallery.chain.todo.decision_won'),
                    default => __('ui_gallery.chain.todo.decision'),
                },
                $state === 'sent' ? $this->wait($d) : '',
                $state === 'sent' ? $this->nextActionList('d6b', $d) : [],
            ],
            [
                match ($state) {
                    'project' => 'done',
                    'won' => 'current',
                    'lost' => 'off',
                    default => 'todo',
                },
                __($state === 'won' ? 'ui_gallery.chain.todo.project_now' : 'ui_gallery.chain.todo.project'),
                $project !== null ? $project['code'].' · '.$project['name'] : ($state === 'lost' ? Str::ucfirst(__('ui_gallery.chain.project_off')) : __('ui_gallery.chain.project_when')),
                $state === 'won' ? $this->nextActionList('d6c', $d) : ($project !== null ? [$this->projectButton('d6', $d)] : []),
            ],
        ];

        return Section::make(__('ui_gallery.chain.todo.heading'))
            ->icon(Heroicon::OutlinedListBullet)
            ->extraAttributes(['class' => 'kc-list-section'])
            ->schema(array_map(fn (array $row): Component => Flex::make([
                Icon::make(match ($row[0]) {
                    'done' => Heroicon::OutlinedCheckCircle,
                    'current' => Heroicon::OutlinedArrowRightCircle,
                    'lost', 'off' => Heroicon::OutlinedXCircle,
                    default => Heroicon::OutlinedStopCircle,
                })->color(match ($row[0]) {
                    'done' => 'success',
                    'current' => 'primary',
                    'lost' => 'danger',
                    default => 'gray',
                })->extraAttributes(['class' => 'kc-todo-mark'])->grow(false),
                Group::make([
                    Text::make($row[1])->weight($row[0] === 'current' ? FontWeight::Bold : FontWeight::Medium)->color(in_array($row[0], ['todo', 'off'], true) ? 'gray' : null),
                    ...($row[2] !== '' ? [Text::make($row[2])->size(TextSize::Small)->color('gray')] : []),
                ]),
                ...($row[3] !== [] ? [Actions::make($row[3])->grow(false)] : []),
            ])->from('md')->extraAttributes(['class' => 'kc-todo-row kc-'.$row[0]]), $rows));
    }

    /** 7: "Is akisi" bolumu yok; bagli is basligin ustunde, proje durumu rozette. */
    private function header(array $d): Component
    {
        $p = $d['proposal'];

        return Section::make()
            ->extraAttributes(['class' => 'kc-mock'])
            ->schema([
                Flex::make([
                    Actions::make([
                        Action::make('d7_cases')
                            ->label(__('business_case.plural'))
                            ->link()
                            ->color('gray')
                            ->url(BusinessCaseResource::getUrl('index')),
                    ])->grow(false),
                    Icon::make(Heroicon::OutlinedChevronRight)->color('gray')->grow(false),
                    $this->caseLink('d7', $d)->grow(false),
                    Icon::make(Heroicon::OutlinedChevronRight)->color('gray')->grow(false),
                    Text::make(__('ui_gallery.chain.proposal'))->color('gray'),
                ])->from('md'),
                Text::make($p['code'].' · '.$p['title'])->extraAttributes(['class' => 'kc-mock-title']),
                Flex::make([
                    Text::make($p['status'])->badge()->color($p['status_color'])->grow(false),
                    ...($p['offer'] !== null ? [Text::make($p['offer'])->badge()->color($p['offer_color'])->icon(Heroicon::OutlinedFlag)->grow(false)] : []),
                    Text::make(__('ui_gallery.chain.project').': '.($d['project'] !== null ? $d['project']['code'] : ($d['state'] === 'lost' ? __('ui_gallery.chain.project_off') : __('ui_gallery.chain.no_project'))))
                        ->badge()
                        ->color($d['project'] !== null ? 'success' : 'gray')
                        ->icon(Heroicon::OutlinedRocketLaunch)
                        ->grow(false),
                ]),
                Callout::make(__('ui_gallery.chain.header_note'))->color('gray')->icon(Heroicon::OutlinedInformationCircle),
            ]);
    }

    /** 8: dikey hat — bulunulan durak acik ve ayrintili, digerleri tek satir. */
    private function metro(array $d): Component
    {
        $p = $d['proposal'];
        $project = $d['project'];
        $projectState = $project !== null ? 'done' : ($d['state'] === 'lost' ? 'off' : 'todo');

        return Section::make(__('ui_gallery.chain.track_heading'))
            ->icon(Heroicon::OutlinedMapPin)
            ->schema([
                Group::make([
                    Group::make([
                        Text::make(__('ui_gallery.chain.case'))->weight(FontWeight::Bold),
                        Text::make($d['case']['code'].' · '.$d['case']['title'].' · '.$d['case']['customer'])->color('gray'),
                        Actions::make([$this->caseButton('d8', $d, withTitle: false)->link()]),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-done']),
                    Group::make([
                        Flex::make([
                            Text::make(__('ui_gallery.chain.proposal').' · '.$p['code'])->weight(FontWeight::Bold)->size(TextSize::Large)->color('primary'),
                            Text::make(__('ui_gallery.chain.here'))->badge()->color('primary')->icon(Heroicon::OutlinedMapPin)->grow(false),
                        ]),
                        Text::make($this->say($d, 'headline').' — '.$this->say($d, 'detail')),
                        Grid::make(['default' => 2, 'md' => 4])->components([
                            TextEntry::make('d8_status')->label(__('ui_gallery.chain.fields.status'))->state($p['status'])->badge()->color($p['status_color']),
                            TextEntry::make('d8_version')->label(__('ui_gallery.chain.fields.version'))->state($p['version']),
                            TextEntry::make('d8_sent')->label(__('ui_gallery.chain.fields.sent'))->state($p['submitted'] ?? __('ui_gallery.chain.not_sent')),
                            TextEntry::make('d8_price')->label(__('ui_gallery.chain.fields.price'))->state($p['price']),
                        ]),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-current']),
                    Group::make([
                        Text::make(__('ui_gallery.chain.project'))->weight(FontWeight::Bold)->color($projectState === 'done' ? null : 'gray'),
                        Text::make($project !== null ? $project['code'].' · '.$project['name'] : ($d['state'] === 'lost' ? Str::ucfirst(__('ui_gallery.chain.project_off')) : __('ui_gallery.chain.project_when')))->color('gray'),
                        ...($project !== null ? [Actions::make([$this->projectButton('d8', $d)->link()])] : []),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-metro-last kc-'.$projectState]),
                ])->extraAttributes(['class' => 'kc-metro']),
            ]);
    }

    /**
     * 9-12 icin durak kartlarinin kunyeleri: [etiket, deger, simge, rozet rengi|null].
     *
     * @return array{case: list<array{0: string, 1: string, 2: Heroicon, 3: string|null}>, project: list<array{0: string, 1: string, 2: Heroicon, 3: string|null}>}
     */
    private function cards(Proposal $proposal): array
    {
        $case = $proposal->businessCase;
        $project = $case?->project;
        $types = app(ProjectCatalogQueries::class)->componentDefinitionOptions();
        $label = static fn (string $key): string => __('ui_gallery.chain.card.'.$key);
        $scopes = $case?->scopes
            ->map(static fn ($scope): string => $scope->scope_type instanceof ProjectScopeType ? (string) $scope->scope_type->getLabel() : (string) $scope->scope_type)
            ->filter()
            ->implode(' + ');

        $caseFacts = $case === null ? [] : [
            [$label('customer'), (string) ($case->primaryParty?->display_name ?? '-'), Heroicon::OutlinedBuildingOffice2, null],
            [$label('stage'), (string) $case->acquisition_stage->getLabel(), Heroicon::OutlinedFlag, $case->acquisition_stage->getColor()],
            [$label('type'), (string) ($types[$case->project_type_code] ?? ($case->project_type_code ?: '-')), Heroicon::OutlinedCube, null],
            [$label('scopes'), $scopes !== '' ? $scopes : '-', Heroicon::OutlinedSquares2x2, null],
            [$label('offer_type'), (string) ($case->offer_type?->getLabel() ?? '-'), Heroicon::OutlinedTag, null],
            [$label('value'), Money::format($case->estimated_value, $case->currency_code), Heroicon::OutlinedBanknotes, null],
            [$label('owner'), (string) ($case->owner?->full_name ?? '-'), Heroicon::OutlinedUserCircle, null],
            [$label('proposals'), __('ui_gallery.chain.card.proposal_count', ['count' => $case->proposals->count()]), Heroicon::OutlinedClipboardDocumentList, null],
        ];

        $site = trim(implode(' / ', array_filter([$project?->site_district, $project?->site_city])));
        $planned = $project?->planned_start_on === null && $project?->planned_finish_on === null
            ? '-'
            : ($project?->planned_start_on?->format('d.m.Y') ?? '-').' – '.($project?->planned_finish_on?->format('d.m.Y') ?? '-');

        $projectFacts = $project === null ? [] : [
            [$label('project'), trim(($project->businessCode?->formatted_code ?? '').' · '.$project->name, ' ·'), Heroicon::OutlinedRocketLaunch, null],
            [$label('stage'), (string) $project->status->getLabel(), Heroicon::OutlinedFlag, $project->status->getColor()],
            [$label('manager'), (string) ($project->projectManager?->full_name ?? '-'), Heroicon::OutlinedUserCircle, null],
            [$label('planned'), $planned, Heroicon::OutlinedCalendarDays, null],
            [$label('site'), $site !== '' ? $site : '-', Heroicon::OutlinedMapPin, null],
        ];

        return ['case' => $caseFacts, 'project' => $projectFacts];
    }

    /**
     * Kunye izgarasi (etiket + deger).
     *
     * @param  list<array{0: string, 1: string, 2: Heroicon, 3: string|null}>  $facts
     */
    private function factsGrid(string $prefix, array $facts, int $columns = 4): Component
    {
        return Grid::make(['default' => 1, 'sm' => 2, 'lg' => $columns])->components(array_map(
            fn (array $fact, int $index): Component => $fact[3] !== null
                ? TextEntry::make($prefix.'_fact_'.$index)->label($fact[0])->state($fact[1])->badge()->color($fact[3])
                : TextEntry::make($prefix.'_fact_'.$index)->label($fact[0])->state($fact[1])->icon($fact[2])->iconColor('gray'),
            $facts,
            array_keys($facts),
        ));
    }

    /** Bu teklifin karti (8'deki acik durakla ayni icerik). */
    private function proposalCard(string $prefix, array $d): array
    {
        $p = $d['proposal'];

        return [
            Flex::make([
                Text::make(__('ui_gallery.chain.proposal').' · '.$p['code'])->weight(FontWeight::Bold)->size(TextSize::Large)->color('primary'),
                Text::make(__('ui_gallery.chain.here'))->badge()->color('primary')->icon(Heroicon::OutlinedMapPin)->grow(false),
            ]),
            Text::make($this->say($d, 'headline').' — '.$this->say($d, 'detail')),
            $this->factsGrid($prefix.'_p', [
                [__('ui_gallery.chain.fields.status'), $p['status'], Heroicon::OutlinedFlag, $p['status_color']],
                [__('ui_gallery.chain.fields.version'), $p['version'], Heroicon::OutlinedDocumentDuplicate, null],
                [__('ui_gallery.chain.fields.sent'), $p['submitted'] ?? __('ui_gallery.chain.not_sent'), Heroicon::OutlinedPaperAirplane, null],
                [__('ui_gallery.chain.fields.price'), $p['price'], Heroicon::OutlinedBanknotes, null],
            ]),
        ];
    }

    /**
     * Proje karti: proje varsa kunyesi; yoksa ne zaman acilacagi ve ne gerektigi
     * (kaybedilen teklifte "acilmayacak").
     *
     * @return list<Component>
     */
    private function projectCardBody(string $prefix, array $d, array $cards): array
    {
        if ($d['project'] !== null) {
            return [$this->factsGrid($prefix.'_prj', $cards['project'], 3)];
        }

        if ($d['state'] === 'lost') {
            return [Text::make(__('ui_gallery.chain.card.off_help'))->color('gray')];
        }

        // Filament rozet olmayan metne simge cizmez; simge ayri bilesen.
        $check = static fn (bool $done, string $key): Flex => Flex::make([
            Icon::make($done ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedMinusCircle)
                ->color($done ? 'success' : 'gray')
                ->grow(false),
            Text::make(__('ui_gallery.chain.card.'.$key))->color($done ? 'success' : 'gray'),
        ])->extraAttributes(['class' => 'kc-check']);

        return [
            Text::make(__('ui_gallery.chain.card.none_help'))->color('gray'),
            $check($d['state'] !== 'draft', 'check_sent'),
            $check($d['state'] === 'won', 'check_won'),
            ...($d['state'] === 'won' ? [$this->nextActions($prefix.'_prj', $d)] : []),
        ];
    }

    private function projectHeading(array $d): string
    {
        return match (true) {
            $d['project'] !== null => __('ui_gallery.chain.project').' · '.$d['project']['code'],
            $d['state'] === 'lost' => __('ui_gallery.chain.card.off_heading'),
            default => __('ui_gallery.chain.card.none_heading'),
        };
    }

    private function projectStop(array $d): string
    {
        return $d['project'] !== null ? 'done' : ($d['state'] === 'lost' ? 'off' : 'todo');
    }

    /** 9: dikey hat, uc duragin karti da hep acik. */
    private function metroAll(array $d, array $cards): Component
    {
        return Section::make(__('ui_gallery.chain.track_heading'))
            ->icon(Heroicon::OutlinedMapPin)
            ->schema([
                Group::make([
                    Group::make([
                        Flex::make([
                            Text::make(__('ui_gallery.chain.case').' · '.$d['case']['code'].' · '.$d['case']['title'])->weight(FontWeight::Bold),
                            Actions::make([$this->caseButton('d9', $d)->link()])->grow(false),
                        ]),
                        $this->factsGrid('d9_case', $cards['case']),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-done kc-card']),
                    Group::make($this->proposalCard('d9', $d))->extraAttributes(['class' => 'kc-metro-stop kc-current']),
                    Group::make([
                        Flex::make([
                            Text::make($this->projectHeading($d))->weight(FontWeight::Bold)->color($d['project'] !== null ? null : 'gray'),
                            ...($d['project'] !== null ? [Actions::make([$this->projectButton('d9', $d)->link()])->grow(false)] : []),
                        ]),
                        ...$this->projectCardBody('d9', $d, $cards),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-metro-last kc-card kc-'.$this->projectStop($d)]),
                ])->extraAttributes(['class' => 'kc-metro']),
            ]);
    }

    /** 10: dikey hat, diger duraklarin karti basliga tiklayinca ayni yerde acilir. */
    private function metroFold(array $d, array $cards): Component
    {
        $case = $d['case'];

        return Section::make(__('ui_gallery.chain.track_heading'))
            ->icon(Heroicon::OutlinedMapPin)
            ->schema([
                Group::make([
                    Group::make([
                        Section::make(__('ui_gallery.chain.case').' · '.$case['code'].' · '.$case['title'])
                            ->description($case['customer'].' · '.($cards['case'][1][1] ?? ''))
                            ->icon(Heroicon::OutlinedBriefcase)
                            ->compact()
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                $this->factsGrid('d10_case', $cards['case']),
                                Actions::make([$this->caseButton('d10', $d)]),
                            ]),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-done kc-metro-section']),
                    Group::make([
                        Section::make(__('ui_gallery.chain.proposal').' · '.$d['proposal']['code'].' · '.__('ui_gallery.chain.here'))
                            ->description($this->say($d, 'headline').' — '.$this->say($d, 'detail'))
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->iconColor('primary')
                            ->compact()
                            ->collapsible()
                            ->extraAttributes(['class' => 'kc-stop-now'])
                            ->schema(array_slice($this->proposalCard('d10', $d), 2)),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-current kc-plain kc-metro-section']),
                    Group::make([
                        Section::make($this->projectHeading($d))
                            ->description($d['project'] !== null ? $d['project']['name'] : ($d['state'] === 'lost' ? __('ui_gallery.chain.card.off_short') : __('ui_gallery.chain.project_when')))
                            ->icon(Heroicon::OutlinedRocketLaunch)
                            ->compact()
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                ...$this->projectCardBody('d10', $d, $cards),
                                ...($d['project'] !== null ? [Actions::make([$this->projectButton('d10', $d)])] : []),
                            ]),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-metro-last kc-metro-section kc-'.$this->projectStop($d)]),
                ])->extraAttributes(['class' => 'kc-metro']),
            ]);
    }

    /** 11: dikey hat, diger duraklarin karti "Karti gor" ile sagdan acilan panelde. */
    private function metroPanel(array $d, array $cards): Component
    {
        $case = $d['case'];

        $caseCard = Action::make('d11_case_card')
            ->label(__('ui_gallery.chain.card.show'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->slideOver()
            ->modalHeading(__('ui_gallery.chain.case').' · '.$case['code'])
            ->modalDescription($case['title'])
            ->modalIcon(Heroicon::OutlinedBriefcase)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('ui_gallery.chain.card.close'))
            ->extraModalFooterActions([$this->caseButton('d11_modal', $d)])
            ->schema([$this->factsGrid('d11_case', $cards['case'], 2)]);

        $projectCard = Action::make('d11_project_card')
            ->label(__('ui_gallery.chain.card.show'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->slideOver()
            ->modalHeading($this->projectHeading($d))
            ->modalDescription($d['project']['name'] ?? ($d['state'] === 'lost' ? __('ui_gallery.chain.card.off_short') : __('ui_gallery.chain.project_when')))
            ->modalIcon(Heroicon::OutlinedRocketLaunch)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('ui_gallery.chain.card.close'))
            ->extraModalFooterActions($d['project'] !== null ? [$this->projectButton('d11_modal', $d)] : [])
            ->schema($d['project'] !== null
                ? [$this->factsGrid('d11_prj', $cards['project'], 2)]
                : array_values(array_filter($this->projectCardBody('d11_modal', $d, $cards), static fn (Component $component): bool => ! $component instanceof Actions)));

        return Section::make(__('ui_gallery.chain.track_heading'))
            ->icon(Heroicon::OutlinedMapPin)
            ->schema([
                Group::make([
                    Group::make([
                        Text::make(__('ui_gallery.chain.case'))->weight(FontWeight::Bold),
                        Text::make($case['code'].' · '.$case['title'].' · '.$case['customer'])->color('gray'),
                        Actions::make([$caseCard, $this->caseButton('d11', $d)->link()]),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-done']),
                    Group::make($this->proposalCard('d11', $d))->extraAttributes(['class' => 'kc-metro-stop kc-current']),
                    Group::make([
                        Text::make($this->projectHeading($d))->weight(FontWeight::Bold)->color($d['project'] !== null ? null : 'gray'),
                        Text::make($d['project']['name'] ?? ($d['state'] === 'lost' ? __('ui_gallery.chain.card.off_short') : __('ui_gallery.chain.project_when')))->color('gray'),
                        Actions::make([$projectCard]),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-metro-last kc-'.$this->projectStop($d)]),
                ])->extraAttributes(['class' => 'kc-metro']),
            ]);
    }

    /** 12: dikey hat, diger duraklarin temel bilgileri kucuk etiketlerle hep acik. */
    private function metroChips(array $d, array $cards): Component
    {
        $case = $d['case'];
        // Bos degerler ("-") etiket olarak gosterilmez.
        $chips = fn (array $facts, array $keep): Flex => Flex::make(array_values(array_map(
            static fn (array $fact): Text => Text::make($fact[1])
                ->badge()
                ->color($fact[3] ?? 'gray')
                ->icon($fact[2])
                ->tooltip($fact[0])
                ->grow(false),
            array_values(array_filter(
                array_intersect_key($facts, array_flip($keep)),
                static fn (array $fact): bool => ! in_array(trim($fact[1]), ['', '-', '- – -'], true),
            )),
        )))->extraAttributes(['class' => 'kc-chips']);

        return Section::make(__('ui_gallery.chain.track_heading'))
            ->icon(Heroicon::OutlinedMapPin)
            ->schema([
                Group::make([
                    Group::make([
                        Flex::make([
                            Text::make(__('ui_gallery.chain.case').' · '.$case['code'].' · '.$case['title'])->weight(FontWeight::Bold),
                            Actions::make([$this->caseButton('d12', $d)->link()])->grow(false),
                        ]),
                        // Musteri, durum, proje tipi, tahmini deger, teklif sayisi.
                        $chips($cards['case'], [0, 1, 2, 5, 7]),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-done']),
                    Group::make($this->proposalCard('d12', $d))->extraAttributes(['class' => 'kc-metro-stop kc-current']),
                    Group::make([
                        Flex::make([
                            Text::make($this->projectHeading($d))->weight(FontWeight::Bold)->color($d['project'] !== null ? null : 'gray'),
                            ...($d['project'] !== null ? [Actions::make([$this->projectButton('d12', $d)->link()])->grow(false)] : []),
                        ]),
                        $d['project'] !== null
                            ? $chips($cards['project'], [1, 2, 3, 4])
                            : Text::make($d['state'] === 'lost' ? __('ui_gallery.chain.card.off_short') : __('ui_gallery.chain.card.none_short'))->color('gray'),
                    ])->extraAttributes(['class' => 'kc-metro-stop kc-metro-last kc-'.$this->projectStop($d)]),
                ])->extraAttributes(['class' => 'kc-metro']),
            ]);
    }

    /** Siradaki isin dugmeleri (pasif; gercek dugmeler teklif sayfasinda). */
    private function nextActions(string $prefix, array $d): Component
    {
        return Actions::make($this->nextActionList($prefix, $d));
    }

    /** @return list<Action> */
    private function nextActionList(string $prefix, array $d): array
    {
        $demo = fn (string $name, string $label, Heroicon $icon, string $color): Action => Action::make($prefix.'_'.$name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            // Denemede kayit degismez: dugme gercek gorunur, tiklaninca yalniz uyari verir.
            ->action(fn () => Notification::make()->title(__('ui_gallery.chain.demo_disabled'))->info()->send());

        return match ($d['state']) {
            'draft' => [$demo('send', __('ui_gallery.chain.actions.send'), Heroicon::OutlinedPaperAirplane, 'primary')],
            'sent' => [
                $demo('won', __('ui_gallery.chain.actions.won'), Heroicon::OutlinedTrophy, 'success'),
                $demo('lost', __('ui_gallery.chain.actions.lost'), Heroicon::OutlinedXMark, 'gray'),
            ],
            'won' => [$demo('convert', __('ui_gallery.chain.actions.convert'), Heroicon::OutlinedRocketLaunch, 'success')],
            'project' => [$this->projectButton($prefix, $d)],
            default => [],
        };
    }

    /** Potansiyel ise giden bag (baslik baglanti gorunumunde). */
    private function caseLink(string $prefix, array $d): Actions
    {
        return Actions::make([
            Action::make($prefix.'_case_link')
                ->label($d['case']['code'].' · '.$d['case']['title'])
                ->icon(Heroicon::OutlinedBriefcase)
                ->link()
                ->url($d['case']['url']),
        ]);
    }

    private function caseButton(string $prefix, array $d, bool $withTitle = true): Action
    {
        return Action::make($prefix.'_open_case')
            ->label($withTitle ? __('ui_gallery.chain.go_case') : __('ui_gallery.chain.go_case'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url($d['case']['url']);
    }

    private function projectButton(string $prefix, array $d): Action
    {
        return Action::make($prefix.'_open_project')
            ->label(__('ui_gallery.chain.go_project'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('success')
            ->url($d['project']['url'] ?? null);
    }

    /** Markdown icine giden serbest metin: _ * ` [ ] kacis. */
    private function md(string $value): string
    {
        return addcslashes($value, '\\_*`[]');
    }
}
