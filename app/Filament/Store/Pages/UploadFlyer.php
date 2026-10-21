<?php

namespace App\Filament\Store\Pages;

use App\Actions\Flyers\ImportFlyerFromPost;
use App\Exceptions\PostImportException;
use App\Models\FlyerSource;
use App\Services\FlyerImageProcessor;
use App\Support\PostDates;
use Carbon\CarbonImmutable;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * The private upload link a store's own team uses to send its flyer. There is
 * no login: the link itself (a long random token tied to an approved flyer
 * source) is the key. What arrives becomes a draft that staff review before
 * anything is published, and the link stops working when the licence is
 * revoked or staff issue a new link.
 */
class UploadFlyer extends SimplePage
{
    use RestrictsFileUploadsToSchemaComponents;
    use WithRateLimiting;

    protected bool $hasTopbar = false;

    protected Width|string|null $maxWidth = Width::TwoExtraLarge;

    #[Locked]
    public string $token = '';

    /** "received" after a successful upload, "duplicate" when we already had it. */
    public ?string $outcome = null;

    public ?array $data = [];

    public static function url(string $token): string
    {
        return route('filament.store.upload', ['token' => $token]);
    }

    public function mount(string $token): void
    {
        $this->token = $token;

        abort_if($this->source === null, 404);

        $this->form->fill();
    }

    #[Computed]
    public function source(): ?FlyerSource
    {
        return FlyerSource::findByUploadToken($this->token);
    }

    public function getTitle(): string
    {
        return __('store.upload.title');
    }

    public function getHeading(): string
    {
        return (string) $this->source?->retailer?->name;
    }

    public function getSubheading(): string
    {
        return __('store.upload.subheading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                FileUpload::make('images')
                    ->label(__('store.upload.images'))
                    ->helperText(__('store.upload.images_help'))
                    ->disk('local')->directory('uploads/flyers')->visibility('private')
                    ->multiple()->reorderable()->appendFiles()->panelLayout('grid')
                    ->acceptedFileTypes(FlyerImageProcessor::canRasterizePdf()
                        ? ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']
                        : ['image/jpeg', 'image/png', 'image/webp'])
                    ->maxFiles(40)
                    ->maxSize(config('qdealme.max_upload_mb') * 1024)
                    ->required(),
                Textarea::make('text')
                    ->label(__('store.upload.text'))
                    ->helperText(__('store.upload.text_help'))
                    ->rows(3)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($dates = PostDates::find($state)) {
                            $set('valid_from', $dates['from']->toDateString());
                            $set('valid_until', $dates['until']->toDateString());
                        }
                    }),
                Grid::make(2)->schema([
                    DatePicker::make('valid_from')->label(__('store.upload.valid_from'))->required()->native(false)
                        ->default(fn () => today(config('qdealme.timezone'))->toDateString()),
                    DatePicker::make('valid_until')->label(__('store.upload.valid_until'))->required()->native(false)
                        ->default(fn () => today(config('qdealme.timezone'))->addDays(6)->toDateString())
                        ->afterOrEqual('valid_from'),
                ]),
                TextInput::make('title')
                    ->label(__('store.upload.flyer_title'))
                    ->placeholder(__('store.upload.flyer_title_placeholder'))
                    ->maxLength(160),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('send')
                ->footer([
                    Actions::make([
                        Action::make('send')
                            ->label(__('store.upload.send'))
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->submit('send'),
                    ])->fullWidth(),
                ])
                ->visible(fn (): bool => $this->outcome === null),
            EmptyState::make(fn (): string => __('store.upload.'.($this->outcome ?? 'received')))
                ->description(fn (): string => __('store.upload.'.($this->outcome ?? 'received').'_hint'))
                ->icon(fn (): Heroicon => $this->outcome === 'duplicate' ? Heroicon::OutlinedDocumentDuplicate : Heroicon::OutlinedCheckBadge)
                ->footer([
                    Action::make('another')
                        ->label(__('store.upload.another'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('gray')
                        ->action(function (): void {
                            $this->outcome = null;
                            $this->form->fill();
                        }),
                ])
                ->visible(fn (): bool => $this->outcome !== null),
        ]);
    }

    public function send(): void
    {
        try {
            $this->rateLimit(10, 3600);
        } catch (TooManyRequestsException $exception) {
            Notification::make()->danger()
                ->title(__('store.upload.too_many', ['minutes' => $exception->minutesUntilAvailable]))
                ->send();

            return;
        }

        $source = $this->source;
        abort_if($source === null, 404);

        $data = $this->form->getState();
        $paths = array_values(array_filter((array) ($data['images'] ?? [])));
        $title = trim((string) ($data['title'] ?? ''));
        $isArabic = preg_match('/\p{Arabic}/u', $title) === 1;
        $timezone = config('qdealme.timezone');

        try {
            app(ImportFlyerFromPost::class)->handle($source, $paths, null, [
                'title_ar' => $isArabic ? $title : null,
                'title_en' => $isArabic ? null : $title,
                'valid_from' => CarbonImmutable::parse($data['valid_from'], $timezone),
                'valid_until' => CarbonImmutable::parse($data['valid_until'], $timezone),
            ]);
        } catch (PostImportException $e) {
            // Nothing was created, so the uploaded copies are not needed.
            Storage::disk('local')->delete($paths);

            if ($e->existing === null) {
                Notification::make()->danger()->title($e->getMessage())->send();

                return;
            }

            $this->outcome = 'duplicate';

            return;
        }

        $source->forceFill(['last_uploaded_at' => now()])->save();
        $this->outcome = 'received';
    }
}
