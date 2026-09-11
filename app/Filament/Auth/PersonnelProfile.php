<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Exceptions\Personnel\EmailAlreadyInUseException;
use App\Exceptions\StaleRecordException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\PersonnelFormData;
use App\Models\Personnel\Personnel;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Personnel\PersonnelService;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Personelin kendi profili.
 *
 * Kisi kendi kartindaki bilgileri gorur ve duzenler. Gorev, departman ve
 * durum gibi alanlar burada salt okunurdur; onlari yalniz yetkili personel
 * Personel ekranindan degistirir. Yazma islemi UpdatePersonnel use-case'inden
 * gecer.
 */
class PersonnelProfile extends EditProfile
{
    public static function getLabel(): string
    {
        return __('personnel.profile.title');
    }

    public function getTitle(): string
    {
        return __('personnel.profile.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('personnel.sections.identity'))
                ->icon(Heroicon::OutlinedIdentification)
                ->description(__('personnel.help.profile_intro'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    FileUpload::make('photo_path')
                        ->label(__('personnel.fields.photo'))
                        ->image()
                        ->avatar()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('personel-fotograflari')
                        ->maxSize(4096)
                        ->columnSpanFull(),
                    TextInput::make('full_name')
                        ->label(__('personnel.fields.full_name'))
                        ->prefixIcon(Heroicon::OutlinedUser)
                        ->required()
                        ->maxLength(255),
                    TextInput::make('national_id')
                        ->label(__('personnel.fields.national_id'))
                        ->prefixIcon(Heroicon::OutlinedFingerPrint)
                        ->numeric()
                        ->length(11)
                        ->rule('digits:11')
                        ->helperText(__('personnel.help.national_id')),
                    TextInput::make('phone')
                        ->label(__('personnel.fields.phone'))
                        ->prefixIcon(Heroicon::OutlinedPhone)
                        ->tel()
                        ->maxLength(32)
                        ->helperText(__('personnel.help.phone')),
                    TextInput::make('email')
                        ->label(__('personnel.fields.email'))
                        ->prefixIcon(Heroicon::OutlinedEnvelope)
                        ->email()
                        ->required()
                        ->maxLength(320),
                ])),

            Section::make(__('personnel.sections.assignment'))
                ->icon(Heroicon::OutlinedBriefcase)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextEntry::make('orgUnit.name')
                        ->label(__('personnel.fields.department'))
                        ->badge()
                        ->color('gray')
                        ->placeholder('-'),
                    TextEntry::make('job_title')
                        ->label(__('personnel.fields.job_title'))
                        ->placeholder('-'),
                    DatePicker::make('hired_on')
                        ->label(__('personnel.fields.hired_on'))
                        ->prefixIcon(Heroicon::OutlinedCalendarDays)
                        ->displayFormat('d.m.Y')
                        ->disabled()
                        ->dehydrated(false),
                    TextEntry::make('status')
                        ->label(__('personnel.fields.status'))
                        ->badge(),
                ])),

            Section::make(__('personnel.sections.competencies'))
                ->icon(Heroicon::OutlinedSparkles)
                ->components([
                    TextEntry::make('competencies.name')
                        ->hiddenLabel()
                        ->badge()
                        ->placeholder(__('personnel.messages.no_competency')),
                ]),

            Section::make(__('personnel.sections.account'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->description(__('personnel.help.profile_password'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    $this->getPasswordFormComponent()
                        ->label(__('personnel.fields.password')),
                    $this->getPasswordConfirmationFormComponent()
                        ->label(__('personnel.fields.password_confirmation')),
                    $this->getCurrentPasswordFormComponent()
                        ->label(__('personnel.fields.current_password')),
                    TextEntry::make('last_login_at')
                        ->label(__('personnel.fields.last_login_at'))
                        ->icon(Heroicon::OutlinedClock)
                        ->dateTime('d.m.Y H:i')
                        ->placeholder(__('personnel.messages.never_logged_in')),
                    Hidden::make('row_version'),
                ])),
        ]);
    }

    /**
     * Kaydi use-case uzerinden gunceller; dogrudan yazma yapilmaz.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var Personnel $record */
        $data = PersonnelFormData::forProfileScreen($data, $record);
        $data['competencyRecords'] = app(PersonnelQueries::class)->competencyRows($record);

        try {
            return app(PersonnelService::class)->update($record, $data);
        } catch (EmailAlreadyInUseException) {
            throw ValidationException::withMessages([
                'data.email' => __('personnel.validation.email_taken'),
            ]);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('personnel.messages.profile_saved');
    }
}
