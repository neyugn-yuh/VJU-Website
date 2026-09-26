<?php

namespace App\Filament\Resources\Contents\Schemas;

use App\Domain\Content\ContentStatus;
use App\Domain\Content\ContentType;
use App\Domain\Content\ContentWorkflow;
use App\Filament\Forms\CmsRichEditor;
use App\Filament\Forms\MediaPicker;
use App\Filament\Forms\PageBlocks;
use App\Models\Category;
use App\Models\Content;
use App\Models\ContentTranslation;
use App\Models\Tag;
use App\Models\User;
use App\Support\Locales;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class ContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make([
                Tabs::make('Translations')
                    ->tabs(collect(Locales::codes())->map(fn (string $locale) => self::localeTab($locale))->all())
                    ->persistTabInQueryString('lang'),
            ])->columnSpan(['lg' => 2]),

            Group::make([
                self::publishSection(),
                self::organizationSection(),
                Section::make('Featured image')->schema([
                    MediaPicker::make('featured_media_id')->hiddenLabel(),
                ]),
                self::structuredFieldsSection(),
            ])->columnSpan(['lg' => 1]),
        ]);
    }

    public static function type(mixed $livewire, ?Content $record): ContentType
    {
        return $record?->type ?? ContentType::tryFrom((string) ($livewire->type ?? '')) ?? ContentType::Post;
    }

    private static function localeTab(string $locale): Tab
    {
        $p = "translations.$locale";

        return Tab::make(Locales::name($locale))
            ->badge(fn (Get $get) => filled($get("$p.title")) ? '✓' : null)
            ->schema([
                TextInput::make("$p.title")->label('Title')->maxLength(500)
                    ->required(fn () => $locale === Locales::default())
                    ->helperText($locale === Locales::default() ? null : 'Leave empty if this language version does not exist. Clearing the title removes the translation.'),
                TextInput::make("$p.slug")->label('Slug')->maxLength(190)
                    ->helperText(fn (?Content $record) => ($url = $record?->url($locale)) ? 'Current URL: '.$url : 'Generated from the title when empty.'),
                Textarea::make("$p.excerpt")->label('Excerpt')->rows(3),
                CmsRichEditor::make("$p.body")->label('Body'),
                PageBlocks::make("$p.blocks")
                    ->visible(fn ($livewire, ?Content $record) => self::type($livewire, $record) === ContentType::Page),
                Section::make('SEO')->collapsed()->schema([
                    TextInput::make("$p.seo.meta_title")->label('Meta title')->maxLength(500)->helperText('Defaults to the title.'),
                    Textarea::make("$p.seo.meta_description")->label('Meta description')->rows(2)->maxLength(1000),
                    TextInput::make("$p.seo.meta_keywords")->label('Keywords')->maxLength(500),
                    TextInput::make("$p.seo.canonical_url")->label('Canonical URL')->url()->maxLength(1000)->helperText('Only set when the canonical differs from this page.'),
                    TextInput::make("$p.seo.og_title")->label('Open Graph title')->maxLength(500),
                    Textarea::make("$p.seo.og_description")->label('Open Graph description')->rows(2),
                    MediaPicker::make("$p.seo.og_image_id")->label('Open Graph image')->helperText('Defaults to the featured image.'),
                    Toggle::make("$p.seo.robots_index")->label('Allow indexing')->default(true),
                    Toggle::make("$p.seo.robots_follow")->label('Follow links')->default(true),
                ]),
            ]);
    }

    private static function publishSection(): Section
    {
        return Section::make('Publish')->schema([
            Select::make('status')
                ->options(function ($livewire, ?Content $record) {
                    $content = $record ?? new Content(['type' => self::type($livewire, null)]);

                    return app(ContentWorkflow::class)->allowedTargets(auth()->user(), $content);
                })
                ->default(ContentStatus::Draft->value)
                ->selectablePlaceholder(false)
                ->live()
                ->required(),
            DateTimePicker::make('scheduled_at')->label('Publish at')->seconds(false)
                ->visible(fn (Get $get) => $get('status') === ContentStatus::Scheduled->value)
                ->required(fn (Get $get) => $get('status') === ContentStatus::Scheduled->value)
                ->minDate(now()),
            DateTimePicker::make('published_at')->label('Publication date')->seconds(false)
                ->hidden(fn (Get $get) => $get('status') === ContentStatus::Scheduled->value)
                ->helperText('Set automatically on first publish.'),
            Select::make('author_id')->label('Author')
                ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->visible(fn () => Gate::allows('assignAuthor', Content::class)),
            Toggle::make('is_commentable')->label('Allow comments'),
            Toggle::make('is_featured')->label('Featured'),
        ]);
    }

    private static function organizationSection(): Section
    {
        return Section::make('Organization')->schema([
            Select::make('parent_id')->label('Parent page')->searchable()
                ->options(fn (?Content $record) => ContentTranslation::query()
                    ->whereHas('content', fn ($q) => $q->where('type', ContentType::Page->value))
                    ->where('locale', Locales::default())
                    ->when($record, fn ($q) => $q->where('content_id', '!=', $record->id))
                    ->orderBy('path')->limit(500)->pluck('path', 'content_id'))
                ->visible(fn ($livewire, ?Content $record) => self::type($livewire, $record)->isHierarchical()),
            Select::make('template')
                ->options(['default' => 'Default', 'landing' => 'Landing (modules only)', 'admissions' => 'Admissions', 'education' => 'Education / program', 'research' => 'Research', 'contact' => 'Contact'])
                ->default('default')
                ->visible(fn ($livewire, ?Content $record) => self::type($livewire, $record) === ContentType::Page),
            Select::make('categories')->multiple()->searchable()->preload()
                ->options(fn () => Category::with('translations')->get()->mapWithKeys(fn (Category $c) => [$c->id => $c->name]))
                ->visible(fn ($livewire, ?Content $record) => self::type($livewire, $record)->hasTaxonomy()),
            Select::make('tags')->multiple()->searchable()
                ->options(fn () => Tag::with('translations')->limit(1000)->get()->mapWithKeys(fn (Tag $t) => [$t->id => $t->name]))
                ->visible(fn ($livewire, ?Content $record) => self::type($livewire, $record)->hasTaxonomy()),
            TextInput::make('menu_order')->label('Order')->numeric()->default(0),
        ]);
    }

    private static function structuredFieldsSection(): Section
    {
        $is = fn (ContentType ...$types) => fn ($livewire, ?Content $record) => in_array(self::type($livewire, $record), $types, true);

        return Section::make('Details')
            ->visible($is(ContentType::Document, ContentType::Notification, ContentType::TuitionFee, ContentType::Opportunity))
            ->schema([
                MediaPicker::make('fields.file_id', imagesOnly: false)->label('Attached file'),
                TextInput::make('fields.file_url')->label('…or external file URL')->url(),
                TextInput::make('fields.number')->label('Document number')->visible($is(ContentType::Notification, ContentType::Document)),
                DatePicker::make('fields.issued_at')->label('Issued on')->visible($is(ContentType::Notification, ContentType::Document, ContentType::TuitionFee)),
                TextInput::make('fields.program')->label('Program')->visible($is(ContentType::TuitionFee)),
                TextInput::make('fields.academic_year')->label('Academic year')->visible($is(ContentType::TuitionFee)),
                TextInput::make('fields.location')->label('Location')->visible($is(ContentType::Opportunity)),
                TextInput::make('fields.work_type')->label('Work type')->visible($is(ContentType::Opportunity)),
                TextInput::make('fields.apply_url')->label('Application URL')->url()->visible($is(ContentType::Opportunity)),
                DatePicker::make('fields.deadline')->label('Deadline')->visible($is(ContentType::TuitionFee, ContentType::Opportunity)),
            ]);
    }
}
