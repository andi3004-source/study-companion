<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Raba lalu dengar" page. The photo never touches Livewire (no temporary
 * uploads): the browser compresses it and posts it to ScanBendaController,
 * and the result is rendered client-side by the scanBenda() Alpine component.
 */
#[Layout('layouts.study')]
class ScanBenda extends Component
{
    public function mount(): void
    {
        abort_unless(config('scan.enabled'), 404);
    }

    public function render()
    {
        return view('livewire.scan-benda')->title('Raba lalu dengar');
    }
}
