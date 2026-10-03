<?php

namespace Tests\Feature;

use App\Livewire\Cards\Deck;
use App\Livewire\Dashboard;
use App\Livewire\Ujian\Runner;
use App\Models\User;
use App\Models\UserChecklist;
use App\Models\UserExamScore;
use App\Models\UserWordProgress;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function onboardedUser(array $overrides = []): User
    {
        $this->seed(ReferenceDataSeeder::class);

        return User::factory()->create(array_merge([
            'start_date' => now()->subDays(2)->toDateString(), // day_number = 3 (vocab day, batch 1)
            'track' => 'both',
            'onboarded' => true,
        ], $overrides));
    }

    public function test_toggling_checklist_item_persists(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)
            ->test(Dashboard::class, ['day' => 3])
            ->call('toggleChecklist', 'mandarin-vocab')
            ->assertSet('activeItemId', null);

        $this->assertDatabaseHas('user_checklist', [
            'user_id' => $user->id,
            'day_number' => 3,
            'item_id' => 'mandarin-vocab',
            'is_checked' => true,
        ]);

        // toggling again flips it back off
        Livewire::actingAs($user)
            ->test(Dashboard::class, ['day' => 3])
            ->call('toggleChecklist', 'mandarin-vocab');

        $this->assertSame(0, UserChecklist::where('is_checked', true)->count());
    }

    public function test_opening_item_shows_detail_payload(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)
            ->test(Dashboard::class, ['day' => 3])
            ->call('openItem', 'mandarin-vocab')
            ->assertSet('activeItemId', 'mandarin-vocab')
            ->assertSee('Vocab Baru (Batch 1)');
    }

    public function test_exam_does_not_expose_correct_answers_before_submit(): void
    {
        $user = $this->onboardedUser();

        $component = Livewire::actingAs($user)->test(Runner::class, ['mode' => 'mandarin']);

        // Public component data must never include an "answer" or "correct" key pre-submission.
        $questions = $component->get('questions');
        $this->assertNotEmpty($questions);
        foreach ($questions as $q) {
            $this->assertArrayNotHasKey('answer', $q);
            $this->assertArrayNotHasKey('correct', $q);
            $this->assertArrayNotHasKey('correctIndex', $q);
        }

        $component->assertSet('submitted', false);
    }

    public function test_exam_submit_scores_correctly_and_persists(): void
    {
        $user = $this->onboardedUser();

        $component = Livewire::actingAs($user)->test(Runner::class, ['mode' => 'mandarin']);
        $questionCount = count($component->get('questions'));

        $component->call('submit')->assertSet('submitted', true);

        $this->assertDatabaseHas('user_exam_scores', [
            'user_id' => $user->id,
            'exam_mode' => 'mandarin',
            'total' => $questionCount,
        ]);

        // With no answers selected, score must be 0.
        $score = UserExamScore::where('user_id', $user->id)->first();
        $this->assertSame(0, $score->correct);
    }

    public function test_opening_writing_and_conversation_items_renders_without_error(): void
    {
        $user = $this->onboardedUser(['start_date' => now()->subDays(4)->toDateString()]); // day_number = 5

        $component = Livewire::actingAs($user)->test(Dashboard::class, ['day' => 5]);

        $component->call('openItem', 'english-writing')
            ->assertSet('activeItemId', 'english-writing')
            ->assertSee('kata'); // word-count label in note-box.blade.php

        $component->call('openItem', 'mandarin-conversation')
            ->assertSet('activeItemId', 'mandarin-conversation')
            ->assertSee('Mulai'); // stopwatch control

        $component->set('noteDrafts.english-writing', 'This is my essay draft with several words in it.')
            ->call('saveNote', 'english-writing', 'english');

        $this->assertDatabaseHas('user_notes', [
            'user_id' => $user->id,
            'day_number' => 5,
            'item_index' => 'english-writing',
            'note_text' => 'This is my essay draft with several words in it.',
        ]);
    }

    public function test_flashcard_mark_known_persists_and_advances(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)
            ->test(Deck::class, ['deck' => 'mandarin'])
            ->assertSet('index', 0)
            ->call('markKnown', true)
            ->assertSet('index', 1);

        $this->assertSame(1, UserWordProgress::where('user_id', $user->id)
            ->where('word_type', 'mandarin')
            ->where('is_known', true)
            ->count());
    }
}
