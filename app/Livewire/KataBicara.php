<?php

namespace App\Livewire;

use App\Services\KataBicaraCatalog;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Kata bicara": look up one word (typed, or spoken and then confirmed by the
 * user) and show it in Indonesian, English and Mandarin. Speech recognition and
 * playback happen in the browser (Web Speech API); this component only searches
 * the local catalog.
 */
#[Layout('layouts.study')]
class KataBicara extends Component
{
    public string $query = '';

    /** The query of the last search, null before the first one. */
    public ?string $searched = null;

    public function cari(): void
    {
        $this->query = trim($this->query);
        $this->searched = $this->query === '' ? null : $this->query;
    }

    public function render()
    {
        $catalog = app(KataBicaraCatalog::class);
        $results = $this->searched !== null ? $catalog->search($this->searched) : collect();

        return view('livewire.kata-bicara', [
            'available' => $catalog->available(),
            'results' => $results,
            'examples' => $this->searched !== null && $results->isEmpty()
                ? $catalog->examples((int) config('katabicara.contoh_jumlah', 8))
                : [],
        ]);
    }
}
