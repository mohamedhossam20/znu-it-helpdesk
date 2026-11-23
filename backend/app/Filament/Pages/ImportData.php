<?php

namespace App\Filament\Pages;

use App\Filament\NavigationGroup;
use App\Imports\ImportType;
use App\Imports\RowImportException;
use App\Imports\SpreadsheetReader;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ImportData extends Page
{
    protected string $view = 'filament.pages.import-data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Settings;

    protected static ?int $navigationSort = 9;

    private const DISK = 'local';

    /**
     * @var array{type?: string, file?: string|array<string, string>|null}
     */
    public ?array $data = [];

    /**
     * @var array{type: string, dry_run: bool, total: int, created: int, updated: int, saved: bool, errors: array<int, list<string>>}|null
     */
    public ?array $report = null;

    public static function getNavigationLabel(): string
    {
        return __('Import from Excel');
    }

    public function getTitle(): string
    {
        return __('Import from Excel');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && collect(ImportType::cases())->contains(fn (ImportType $type) => $type->allows($user));
    }

    public function mount(): void
    {
        $this->form->fill(['type' => $this->allowedTypes()[0]->value ?? null]);
    }

    /**
     * @return list<ImportType>
     */
    private function allowedTypes(): array
    {
        return array_values(array_filter(ImportType::cases(), fn (ImportType $type) => $type->allows(auth()->user())));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Radio::make('type')
                    ->label(__('What are you importing?'))
                    ->options(collect($this->allowedTypes())->mapWithKeys(fn (ImportType $t) => [$t->value => $t->getLabel()]))
                    ->descriptions(collect($this->allowedTypes())->mapWithKeys(fn (ImportType $t) => [$t->value => $t->getDescription()]))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->report = null),
                FileUpload::make('file')
                    ->label(__('Spreadsheet (.xlsx or .csv)'))
                    ->disk(self::DISK)
                    ->directory('imports')
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                    ])
                    ->maxSize(10240)
                    ->required()
                    ->afterStateUpdated(fn () => $this->report = null),
            ]);
    }

    public function downloadTemplateAction(): Action
    {
        return Action::make('downloadTemplate')
            ->label(__('Download template'))
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->url(fn () => filled($this->data['type'] ?? null) ? route('admin.import-templates', $this->data['type']) : null)
            ->disabled(fn () => blank($this->data['type'] ?? null));
    }

    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('Check file'))
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->color('gray')
            ->action(fn () => $this->process(dryRun: true));
    }

    public function importAction(): Action
    {
        return Action::make('import')
            ->label(__('Import'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->requiresConfirmation()
            ->modalDescription(__('Rows are matched by code, email or asset tag: existing records will be updated, new ones created. If any row has an error, nothing is saved.'))
            ->action(fn () => $this->process(dryRun: false));
    }

    private function process(bool $dryRun): void
    {
        $state = $this->form->getState();
        $type = ImportType::from($state['type']);
        abort_unless($type->allows(auth()->user()), 403);

        $path = is_array($state['file']) ? reset($state['file']) : $state['file'];

        try {
            $rows = app(SpreadsheetReader::class)->read($path, self::DISK);
            $missing = array_diff($type->importer()->headings(), array_keys($rows[0] ?? []));

            if ($rows === [] || $missing !== []) {
                Notification::make()
                    ->danger()
                    ->title(__('The file does not match the template'))
                    ->body($rows === [] ? __('No data rows were found.') : __('Missing columns: :columns', ['columns' => implode(', ', $missing)]))
                    ->send();

                return;
            }

            $report = $type->importer()->run($rows, $dryRun);
        } catch (RowImportException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $this->report = ['type' => $type->value, 'dry_run' => $dryRun, ...$report->toArray()];

        if ($report->saved) {
            Storage::disk(self::DISK)->delete($path);
            $this->form->fill(['type' => $type->value]);

            Notification::make()
                ->success()
                ->title(__('Import finished'))
                ->body(__(':created created, :updated updated.', ['created' => $report->created, 'updated' => $report->updated]))
                ->send();
        }
    }
}
