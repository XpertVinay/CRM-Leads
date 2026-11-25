<?php

namespace Tests\Feature\Localization;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Tracking\Models\TrackingLink;
use App\Support\LocaleHelper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_visit_shows_language_selection_popup_and_uses_en_in_default(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('id="crm-lang-modal"', false)
            ->assertSee('Choose your preferred language')
            ->assertSee('Continue in Hinglish')
            ->assertSee('<html lang="en-IN">', false);
    }

    public function test_guest_can_switch_to_hinglish_and_hindi_and_popup_hides(): void
    {
        $this->post('/locale', ['locale' => 'hi_Latn'])
            ->assertRedirect()
            ->assertCookie('crm_locale', 'hi_Latn');

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('id="crm-lang-modal"', false)
            ->assertSee('<html lang="hi-Latn">', false)
            ->assertSee('Wapas swagat hai')
            ->assertSee('Sign In karein');

        $this->post('/locale', ['locale' => 'hi'])
            ->assertRedirect()
            ->assertCookie('crm_locale', 'hi');

        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="hi">', false)
            ->assertSee('वापसी पर स्वागत है')
            ->assertSee('साइन इन करें');
    }

    public function test_authenticated_user_locale_persists_in_database(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->postJson('/locale', ['locale' => 'hi'])
            ->assertOk()
            ->assertJson(['locale' => 'hi']);

        $this->assertSame('hi', $user->fresh()->locale);

        $this->actingAs($user->fresh())
            ->get('/organizations')
            ->assertOk()
            ->assertDontSee('id="crm-lang-modal"', false)
            ->assertSee('<html lang="hi">', false)
            ->assertSee('साइन आउट');
    }

    public function test_invalid_locale_is_rejected_with_422_for_json(): void
    {
        $this->postJson('/locale', ['locale' => 'fr_FR'])
            ->assertStatus(422);
    }

    public function test_public_lead_capture_supports_lang_query_parameter(): void
    {
        $org = Organization::create([
            'name' => 'Test India Org',
            'slug' => 'test-india-org',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);

        $link = TrackingLink::create([
            'organization_id' => $org->id,
            'name' => 'Test India Link',
            'channel' => 'whatsapp',
            'code' => 'TEST123',
            'slug' => 'test-india-link',
            'destination_url' => 'https://wa.me/919876543210',
            'is_active' => true,
        ]);

        $this->get('/go/'.$link->slug.'?lang=hi')
            ->assertOk()
            ->assertSee('<html lang="hi">', false)
            ->assertSee('पूरा नाम')
            ->assertSee('व्हाट्सएप पर जारी रखें');

        $this->get('/go/'.$link->slug.'?lang=hi_Latn')
            ->assertOk()
            ->assertSee('<html lang="hi-Latn">', false)
            ->assertSee('Poora Naam')
            ->assertSee('WhatsApp par Jaari Rakhein');
    }

    public function test_translation_json_files_have_identical_keys_and_non_empty_values(): void
    {
        $locales = ['en_IN', 'hi_Latn', 'hi'];
        $dictionaries = [];

        foreach ($locales as $locale) {
            $path = lang_path($locale.'.json');
            $this->assertFileExists($path);
            $decoded = json_decode((string) file_get_contents($path), true);
            $this->assertIsArray($decoded);
            $dictionaries[$locale] = $decoded;
        }

        $baseKeys = array_keys($dictionaries['en_IN']);
        sort($baseKeys);

        foreach ($locales as $locale) {
            $keys = array_keys($dictionaries[$locale]);
            sort($keys);
            $this->assertSame($baseKeys, $keys, "Key mismatch in {$locale}.json");

            foreach ($dictionaries[$locale] as $key => $value) {
                $this->assertNotSame('', trim((string) $value), "Empty translation for '{$key}' in {$locale}.json");
            }
        }
    }

    public function test_indian_formatting_helpers(): void
    {
        $this->assertSame('₹1,25,000.00', LocaleHelper::formatInr(125000));
        $this->assertSame('₹12,34,567.50', LocaleHelper::formatInr(1234567.5));
        $this->assertSame('25/11/2026', LocaleHelper::formatDate(Carbon::create(2026, 11, 25)));
    }
}
