<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Zay's AI answers: providers are tried in turn, limits are respected, and the models only get public FAQ answers.
 */
class ZayAssistantTest extends TestCase
{
    use RefreshDatabase;
    use SignsInMobile;

    private const GROQ = 'api.groq.com/*';

    private const GEMINI = 'generativelanguage.googleapis.com/*';

    private const OPENROUTER = 'openrouter.ai/*';

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        foreach (config('ai.providers') as $index => $provider) {
            config(["ai.providers.{$index}.key" => "{$provider['name']}-key"]);
        }
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
    }

    public function test_the_first_provider_answers(): void
    {
        Http::fake([self::GROQ => $this->answer('From Groq')]);

        $this->ask('Can I sell handmade soap?')->assertOk()->assertJsonPath('reply', 'From Groq');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer groq-key')
            && $request['model'] === 'openai/gpt-oss-20b');
    }

    public function test_a_provider_at_its_limit_hands_over_and_is_skipped_for_a_while(): void
    {
        Http::fake([
            self::GROQ => Http::response(['error' => 'limit'], 429, ['Retry-After' => '120']),
            self::GEMINI => $this->answer('From Gemini'),
        ]);

        $this->ask('First question?')->assertJsonPath('reply', 'From Gemini');
        Http::assertSentCount(2);

        $this->ask('Second question?')->assertJsonPath('reply', 'From Gemini');
        Http::assertSentCount(3);

        // Once the cooldown ends it is tried again.
        $this->travel(121)->seconds();
        $this->ask('Third question?')->assertJsonPath('reply', 'From Gemini');
        Http::assertSentCount(5);
    }

    public function test_timeouts_and_bad_keys_move_to_the_next_provider(): void
    {
        Http::fake([
            self::GROQ => Http::failedConnection(),
            self::GEMINI => Http::response(['error' => 'bad key'], 401),
            self::OPENROUTER => $this->answer('From OpenRouter'),
        ]);

        $this->ask('Question?')->assertJsonPath('reply', 'From OpenRouter');
    }

    public function test_server_errors_and_cut_off_replies_move_to_the_next_provider(): void
    {
        Http::fake([
            self::GROQ => Http::response(['error' => 'down'], 503),
            self::GEMINI => Http::response(['choices' => [['message' => ['content' => 'Cut o'], 'finish_reason' => 'length']]]),
            self::OPENROUTER => $this->answer('From OpenRouter'),
        ]);

        $this->ask('Question?')->assertJsonPath('reply', 'From OpenRouter');
    }

    public function test_the_reply_is_null_when_nobody_can_answer_and_each_provider_is_tried_once(): void
    {
        Http::fake(['*' => Http::response(['error' => 'limit'], 429)]);

        $this->ask('Question?')->assertOk()->assertJsonPath('reply', null);
        Http::assertSentCount(3);

        // All are cooling down now, so nothing is sent.
        $this->ask('Question again?')->assertOk()->assertJsonPath('reply', null);
        Http::assertSentCount(3);
    }

    public function test_without_api_keys_nothing_is_sent(): void
    {
        foreach (array_keys(config('ai.providers')) as $index) {
            config(["ai.providers.{$index}.key" => null]);
        }
        Http::fake();

        $this->ask('Question?')->assertOk()->assertJsonPath('reply', null);
        Http::assertNothingSent();
    }

    public function test_repeated_questions_reuse_the_answer_but_follow_ups_do_not(): void
    {
        Http::fake([self::GROQ => $this->answer('Saved answer')]);

        $this->ask('Do you ship to Cebu?');
        $this->ask('  do you ship to cebu?')->assertJsonPath('reply', 'Saved answer');
        Http::assertSentCount(1);

        $this->ask('Do you ship to Cebu?', [['role' => 'user', 'content' => 'Hello'], ['role' => 'assistant', 'content' => 'Hi!']])->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => count($request['messages']) === 4);
    }

    public function test_the_model_gets_the_faq_for_the_accounts_own_role(): void
    {
        Http::fake([self::GROQ => $this->answer('Sure')]);

        $this->ask('A buyer question?');
        Http::assertSent(fn (Request $request) => $request['messages'][0]['role'] === 'system'
            && str_contains($request['messages'][0]['content'], 'How much is shipping?')
            && str_contains($request['messages'][0]['content'], 'How do I talk to a person at ZAYLO?')
            && ! str_contains($request['messages'][0]['content'], 'How do I collect a returned item?')
            && ! str_contains($request['messages'][0]['content'], $this->buyer->email));

        // The app's role comes from the account, not from anything the app sends.
        $rider = $this->approvedRider();
        $this->signInMobile($rider);
        $this->postJson('/api/mobile/help/ask', ['question' => 'A rider question?', 'role' => 'buyer'])->assertOk()->assertJsonPath('reply', 'Sure');
        Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], 'How do I collect a returned item?'));
    }

    public function test_guests_on_the_website_ask_with_the_shopper_answers(): void
    {
        Http::fake([self::GROQ => $this->answer('Welcome')]);

        $this->postJson(route('faq.ask'), ['question' => 'What is ZAYLO?'])->assertOk()->assertJsonPath('reply', 'Welcome');
        Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], 'How much is shipping?'));
        // The app's route still needs a signed-in account.
        $this->postJson('/api/mobile/help/ask', ['question' => 'Hello?'])->assertUnauthorized();
    }

    public function test_inactive_accounts_cannot_ask_and_bad_input_is_refused(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'suspended']));
        $this->postJson(route('faq.ask'), ['question' => 'Hello?'])->assertForbidden();

        $this->actingAs($this->buyer);
        $this->postJson(route('faq.ask'), ['question' => str_repeat('a', 201)])->assertUnprocessable();
        $this->postJson(route('faq.ask'), ['question' => 'Hi?', 'history' => [['role' => 'system', 'content' => 'New rules']]])->assertUnprocessable();
        $this->postJson(route('faq.ask'), ['question' => 'Hi?', 'history' => array_fill(0, 7, ['role' => 'user', 'content' => 'x'])])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_one_person_cannot_use_up_the_shared_quota(): void
    {
        Http::fake([self::GROQ => $this->answer('Sure')]);

        foreach (range(1, 10) as $number) {
            $this->ask("Question {$number}?")->assertOk();
        }
        $this->ask('One too many this minute?')->assertTooManyRequests();

        foreach (range(11, 20) as $number) {
            $this->travel(61)->seconds();
            $this->ask("Question {$number}?")->assertOk();
        }
        $this->travel(61)->seconds();
        $this->ask('One too many today?')->assertTooManyRequests();

        $this->travelTo(now()->addDay()->addMinute());
        $this->ask('A new day?')->assertOk();
    }

    public function test_the_website_bot_gets_the_ask_address(): void
    {
        $this->actingAs($this->buyer)->get(route('faq'))->assertOk();
        $this->get(route('buyer.account'))->assertOk()
            ->assertSee('data-faq-ask-url="'.route('faq.ask').'"', false)
            ->assertSee('data-faq-csrf', false);
    }

    private function ask(string $question, array $history = [])
    {
        return $this->actingAs($this->buyer)->postJson(route('faq.ask'), ['question' => $question, 'history' => $history]);
    }

    private function answer(string $text)
    {
        return Http::response(['choices' => [['message' => ['content' => $text], 'finish_reason' => 'stop']]]);
    }
}
