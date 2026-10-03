<?php

namespace App\Livewire;

use App\Models\UserChecklist;
use App\Models\UserNote;
use App\Services\ConversationService;
use App\Services\ProgressStats;
use App\Services\Scheduler;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.study')]
class Dashboard extends Component
{
    public int $day;

    public ?string $activeItemId = null;

    public bool $showDayPicker = false;

    public array $noteDrafts = [];

    public function mount(?int $day = null): void
    {
        $user = Auth::user();
        $this->day = $day ?? max(1, $user->dayNumber() ?? 1);
    }

    #[Computed]
    public function items(): array
    {
        return app(Scheduler::class)->planForDay($this->day, Auth::user()->track);
    }

    #[Computed]
    public function checklistState()
    {
        return UserChecklist::where('user_id', Auth::id())
            ->where('day_number', $this->day)
            ->get()
            ->keyBy('item_id');
    }

    #[Computed]
    public function activeItem(): ?array
    {
        if (! $this->activeItemId) {
            return null;
        }

        return collect($this->items)->firstWhere('id', $this->activeItemId);
    }

    #[Computed]
    public function mandarinStatus(): array
    {
        return app(Scheduler::class)->mandarinStatus($this->day);
    }

    #[Computed]
    public function englishStatus(): array
    {
        return app(Scheduler::class)->englishStatus($this->day);
    }

    #[Computed]
    public function progress(): ProgressStats
    {
        return app(ProgressStats::class);
    }

    #[Computed]
    public function weekRanges(): array
    {
        $ranges = [];
        for ($w = 1; $w <= 9; $w++) {
            $start = ($w - 1) * 7 + 1;
            $end = min($w * 7, Scheduler::ENGLISH_TOTAL_DAYS);
            $ranges[] = ['week' => $w, 'start' => $start, 'end' => $end];
        }

        return $ranges;
    }

    public function openItem(string $id): void
    {
        $this->activeItemId = $id;
        $item = $this->activeItem;
        if ($item) {
            $note = UserNote::where('user_id', Auth::id())
                ->where('day_number', $this->day)
                ->where('item_index', $id)
                ->value('note_text');
            $this->noteDrafts[$id] = $note ?? '';
        }
    }

    public function closeItem(): void
    {
        $this->activeItemId = null;
    }

    /**
     * The detail modal's "Latihan Ngobrol" button on a "speaking" item —
     * starts a real session (scripted scenario if the topic genuinely
     * matches one, otherwise "Ngobrol Bebas" with that topic assigned — see
     * ConversationService::startSessionForTopic()) and jumps straight into
     * it, instead of dropping the user on the picker page to start from
     * scratch. closeItem() first, then a real (non-SPA) redirect — so the
     * modal is gone before the browser ever navigates, not still open
     * mid-transition.
     */
    public function startConversationFromItem(string $itemId): mixed
    {
        $item = collect($this->items)->firstWhere('id', $itemId);

        if (! $item || $item['type'] !== 'speaking') {
            return null;
        }

        $session = app(ConversationService::class)->startSessionForTopic(
            Auth::user(),
            $item['lang'],
            $item['payload']['topic'] ?? null,
        );

        $this->closeItem();

        return $this->redirect(route('conversation.chat', $session));
    }

    public function toggleChecklist(string $itemId): void
    {
        $existing = UserChecklist::firstOrNew([
            'user_id' => Auth::id(),
            'day_number' => $this->day,
            'item_id' => $itemId,
        ]);
        $existing->is_checked = ! $existing->is_checked;
        $existing->checked_at = $existing->is_checked ? now() : null;
        $existing->save();

        unset($this->checklistState);
    }

    public function goToDay(int $day): void
    {
        $this->day = max(1, $day);
        $this->activeItemId = null;
        $this->showDayPicker = false;
        unset($this->items, $this->checklistState, $this->mandarinStatus, $this->englishStatus);
    }

    public function saveNote(string $itemId, string $lang): void
    {
        $text = $this->noteDrafts[$itemId] ?? '';

        UserNote::updateOrCreate(
            ['user_id' => Auth::id(), 'day_number' => $this->day, 'lang' => $lang, 'item_index' => $itemId],
            ['note_text' => $text]
        );
    }

    /** Livewire magic hook: fires ~700ms after typing stops in a note-box textarea (wire:model.live.debounce). */
    public function updatedNoteDrafts($value, $key): void
    {
        $item = collect($this->items)->firstWhere('id', $key);
        if ($item) {
            $this->saveNote($key, $item['lang']);
        }
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
