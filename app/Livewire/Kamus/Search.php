<?php

namespace App\Livewire\Kamus;

use App\Services\KamusSearchService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.study')]
class Search extends Component
{
    public string $query = '';

    public string $filterLang = 'all';

    public string $filterLevel = 'all';

    public int $limit = 50;

    public function updatedQuery(): void
    {
        $this->limit = 50;
    }

    public function updatedFilterLang(): void
    {
        $this->filterLevel = 'all';
        $this->limit = 50;
    }

    public function updatedFilterLevel(): void
    {
        $this->limit = 50;
    }

    public function loadMore(): void
    {
        $this->limit += 50;
    }

    #[Computed]
    public function results()
    {
        return app(KamusSearchService::class)->results($this->filterLang, $this->filterLevel, $this->query, $this->limit);
    }

    #[Computed]
    public function totalCount(): int
    {
        return app(KamusSearchService::class)->count($this->filterLang, $this->filterLevel, $this->query);
    }

    public function render()
    {
        return view('livewire.kamus.search', [
            'mandarinLevels' => KamusSearchService::MANDARIN_LEVELS,
            'englishLevels' => KamusSearchService::ENGLISH_LEVELS,
        ]);
    }
}
