<?php

namespace Tests\E2E\Support;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\LivewireManager;
use ReflectionProperty;
use Tests\TestCase;

abstract class E2ETestCase extends TestCase
{
    use RefreshDatabase;

    protected ?int $activeOrganizationId = null;

    /**
     * Force shared-server database drivers for queue, cache, and session mid-test.
     */
    public function enableDatabaseDrivers(): void
    {
        $previousLimiters = [];
        $rateLimiterWasResolved = $this->app->resolved(RateLimiter::class);

        if ($rateLimiterWasResolved) {
            $existingLimiter = $this->app->make(RateLimiter::class);
            $limitersProp = new ReflectionProperty(RateLimiter::class, 'limiters');
            $rawLimiters = $limitersProp->getValue($existingLimiter);

            if (is_array($rawLimiters)) {
                $previousLimiters = $rawLimiters;
            }
        }

        if ($this->app->resolved('cache')) {
            Cache::forgetDriver(['array', 'database']);
        }

        config([
            'queue.default' => 'database',
            'cache.default' => 'database',
            'cache.limiter' => 'database',
            'session.driver' => 'database',
        ]);

        foreach (['cache', 'cache.store', 'cache.psr6'] as $abstract) {
            if ($this->app->resolved($abstract)) {
                $this->app->forgetInstance($abstract);
            }
        }
        Cache::clearResolvedInstance('cache');

        if ($rateLimiterWasResolved) {
            $this->app->forgetInstance(RateLimiter::class);
            RateLimiterFacade::clearResolvedInstance(RateLimiter::class);

            if ($previousLimiters !== []) {
                $freshLimiter = $this->app->make(RateLimiter::class);
                foreach ($previousLimiters as $name => $callback) {
                    if ($callback instanceof Closure) {
                        $freshLimiter->for($name, $callback);
                    }
                }
            }
        }

        if ($this->app->resolved('queue') || $this->app->resolved('queue.connection')) {
            $this->app->forgetInstance('queue');
            $this->app->forgetInstance('queue.connection');
            Queue::clearResolvedInstance('queue');
        }

        if ($this->app->resolved('session') || $this->app->resolved('session.store')) {
            $this->app->forgetInstance('session');
            $this->app->forgetInstance('session.store');
            Session::clearResolvedInstance('session');
        }
    }

    /**
     * Drain all pending jobs on the database queue in-process.
     */
    public function drainDatabaseQueue(): void
    {
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => 1,
        ])->assertExitCode(0);
    }

    /**
     * Register a new organization and its initial org_admin via the public POST /register endpoint.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{user: mixed, organization: mixed, session_cookies: array<string, string>}
     */
    public function registerOrganizationAndAdmin(array $overrides = []): array
    {
        $suffix = Str::lower(Str::random(8));
        $password = (string) ($overrides['password'] ?? 'Password123!');

        $payload = array_merge([
            'name' => "Admin {$suffix}",
            'email' => "admin+{$suffix}@example.test",
            'password' => $password,
            'password_confirmation' => $overrides['password_confirmation'] ?? $password,
            'organization_name' => "Org {$suffix}",
            'organization_slug' => "org-{$suffix}",
            'timezone' => 'UTC',
            'currency' => 'USD',
            'registration_identifier' => 'REG-'.strtoupper($suffix),
            'primary_contact_email' => "contact+{$suffix}@example.test",
        ], $overrides);

        if (isset($overrides['password']) && ! array_key_exists('password_confirmation', $overrides)) {
            $payload['password_confirmation'] = (string) $overrides['password'];
        }

        // Ensure guest state so calling registerOrganizationAndAdmin() multiple times
        // within a single test is not intercepted by Laravel's `guest` middleware.
        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->flushSession();

        $response = $this->post('/register', $payload);

        $this->assertStatusIn(
            $response,
            [200, 201, 302],
            'Expected POST /register to succeed with 200, 201, or 302.'
        );

        $userModel = 'App\\Models\\User';
        $orgModel = 'App\\Models\\Organization';

        $this->assertTrue(class_exists($userModel), 'App\\Models\\User class must exist.');
        $this->assertTrue(class_exists($orgModel), 'App\\Models\\Organization class must exist.');

        $user = $userModel::query()->where('email', $payload['email'])->firstOrFail();

        $organization = $user->organizations()
            ->where('slug', $payload['organization_slug'])
            ->first()
            ?? $user->organizations()->latest('organizations.id')->firstOrFail();

        $this->activeOrganizationId = (int) $organization->id;

        $sessionCookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $sessionCookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return [
            'user' => $user,
            'organization' => $organization,
            'session_cookies' => $sessionCookies,
        ];
    }

    /**
     * Create or attach a member in an organization using opaque-box HTTP endpoints first,
     * falling back to invitation or direct pivot attachment if needed, while preserving caller auth/session state.
     *
     * @param  array<string, mixed>  $userOverrides
     * @return array{user: mixed, role: string, organization_id: int}
     */
    public function createMemberInOrganization(int|string $orgId, string $role = 'sales_rep', array $userOverrides = []): array
    {
        $suffix = Str::lower(Str::random(8));
        $name = (string) ($userOverrides['name'] ?? "Member {$suffix}");
        $email = Str::lower((string) ($userOverrides['email'] ?? "member+{$suffix}@example.test"));
        $password = (string) ($userOverrides['password'] ?? 'Password123!');

        $userModel = 'App\\Models\\User';
        $orgModel = 'App\\Models\\Organization';

        $previousUser = Auth::user();
        $previousHeaders = $this->defaultHeaders;
        $previousActiveOrgId = $this->activeOrganizationId;
        $previousSession = $this->app->bound('session.store') ? $this->app['session.store']->all() : [];

        try {
            $adminUser = $this->findOrganizationAdmin($orgId, $previousUser);
            $memberUser = null;
            $allowDbFallback = ! ($adminUser instanceof Authenticatable);

            if ($adminUser instanceof Authenticatable) {
                $this->actingAsInOrganization($adminUser, $orgId);

                $response = $this->postJson('/ajax/team/members', array_merge([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'password_confirmation' => $password,
                    'role' => $role,
                ], $userOverrides));

                if (in_array($response->getStatusCode(), [200, 201], true)) {
                    $memberUser = $userModel::query()->whereRaw('LOWER(email) = ?', [$email])->first();
                    if ($memberUser === null) {
                        $this->fail(sprintf(
                            'createMemberInOrganization(%s, %s) returned HTTP %d but user %s was not created.',
                            $orgId,
                            $role,
                            $response->getStatusCode(),
                            $email
                        ));
                    }
                } elseif (in_array($response->getStatusCode(), [404, 405], true)) {
                    $inviteResponse = $this->postJson('/ajax/organizations/invite', [
                        'email' => $email,
                        'role' => $role,
                    ]);

                    if (in_array($inviteResponse->getStatusCode(), [200, 201], true) && Schema::hasTable('organization_invitations')) {
                        $inviteRow = DB::table('organization_invitations')
                            ->where('organization_id', (int) $orgId)
                            ->whereRaw('LOWER(email) = ?', [$email])
                            ->orderByDesc('id')
                            ->first();

                        if ($inviteRow !== null && ! empty($inviteRow->token)) {
                            Auth::logout();
                            $this->clearTenantHeader();
                            $this->flushSession();

                            $acceptResponse = $this->post("/invitations/{$inviteRow->token}/accept", [
                                'name' => $name,
                                'password' => $password,
                                'password_confirmation' => $password,
                            ]);

                            if (in_array($acceptResponse->getStatusCode(), [200, 201, 302], true)) {
                                $memberUser = $userModel::query()->whereRaw('LOWER(email) = ?', [$email])->first();
                            } elseif (in_array($acceptResponse->getStatusCode(), [404, 405], true)) {
                                $allowDbFallback = true;
                            } else {
                                $this->fail(sprintf(
                                    'createMemberInOrganization(%s, %s) invitation accept failed with HTTP %d: %s',
                                    $orgId,
                                    $role,
                                    $acceptResponse->getStatusCode(),
                                    (string) $acceptResponse->getContent()
                                ));
                            }
                        } else {
                            $allowDbFallback = true;
                        }
                    } elseif (in_array($inviteResponse->getStatusCode(), [404, 405], true)) {
                        $allowDbFallback = true;
                    } else {
                        $this->fail(sprintf(
                            'createMemberInOrganization(%s, %s) failed via POST /ajax/organizations/invite with HTTP %d: %s',
                            $orgId,
                            $role,
                            $inviteResponse->getStatusCode(),
                            (string) $inviteResponse->getContent()
                        ));
                    }
                } else {
                    $this->fail(sprintf(
                        'createMemberInOrganization(%s, %s) failed via POST /ajax/team/members with HTTP %d: %s',
                        $orgId,
                        $role,
                        $response->getStatusCode(),
                        (string) $response->getContent()
                    ));
                }
            }

            if ($memberUser === null) {
                if (! $allowDbFallback) {
                    $this->fail(sprintf(
                        'createMemberInOrganization(%s, %s) could not provision %s and DB fallback is disallowed.',
                        $orgId,
                        $role,
                        $email
                    ));
                }

                $memberUser = $userModel::query()->whereRaw('LOWER(email) = ?', [$email])->first();

                if ($memberUser === null) {
                    $memberUser = $userModel::create(array_merge([
                        'name' => $name,
                        'email' => $email,
                        'password' => $password,
                    ], $userOverrides));
                }

                $organization = $orgModel::query()->findOrFail((int) $orgId);
                if ($memberUser->roleIn($organization) !== null) {
                    $this->fail(sprintf(
                        'User %s is already attached to organization %s; refusing silent pivot mutation.',
                        $email,
                        $orgId
                    ));
                }

                $organization->users()->attach($memberUser->id, ['role' => $role]);
            }

            return [
                'user' => $memberUser->fresh(),
                'role' => $role,
                'organization_id' => (int) $orgId,
            ];
        } finally {
            $this->clearTenantContextIfBound();
            $this->defaultHeaders = $previousHeaders;
            $this->activeOrganizationId = $previousActiveOrgId;

            if ($previousUser instanceof Authenticatable) {
                $this->actingAs($previousUser);
            } else {
                Auth::logout();
            }

            if ($previousSession !== []) {
                $this->flushSession();
                $this->withSession($previousSession);
            } elseif ($previousActiveOrgId !== null) {
                $this->flushSession();
                $this->withSession(['active_organization_id' => $previousActiveOrgId]);
            } else {
                $this->flushSession();
            }
        }
    }

    /**
     * Authenticate as the given user and configure session context for the target organization
     * without setting a persistent X-Organization-ID header (preventing silent header bleed),
     * while clearing TenantContext so EnsureTenantContext middleware executes genuinely per request.
     */
    public function actingAsInOrganization(mixed $user, int|string $orgId): static
    {
        return $this->actingAsSessionOnly($user, $orgId);
    }

    /**
     * Authenticate as the given user in the target organization using session state only
     * and clearing any persistent X-Organization-ID default header.
     */
    public function actingAsSessionOnly(mixed $user, int|string $orgId): static
    {
        $authenticatable = $this->resolveAuthenticatable($user);

        $this->activeOrganizationId = (int) $orgId;
        $this->clearTenantHeader()->clearTenantContextIfBound();

        return $this->actingAs($authenticatable)
            ->withSession(['active_organization_id' => (int) $orgId]);
    }

    /**
     * Remove X-Organization-ID from default test headers (useful for session-only or fallback tests).
     */
    public function clearTenantHeader(): static
    {
        unset($this->defaultHeaders['X-Organization-ID']);

        return $this;
    }

    /**
     * Alias for clearTenantHeader().
     */
    public function withoutTenantHeader(): static
    {
        return $this->clearTenantHeader();
    }

    /**
     * Explicitly set a persistent X-Organization-ID default header on subsequent test requests.
     */
    public function withTenantHeader(int|string $orgId): static
    {
        return $this->withHeader('X-Organization-ID', (string) $orgId);
    }

    /**
     * Normalize wrapped (`{"data": ...}`), paginated, single-resource, or direct JSON responses to an array.
     *
     * @return array<mixed>
     */
    public function extractJsonData(TestResponse $response): array
    {
        $content = (string) $response->getContent();
        if (trim($content) === '') {
            return [];
        }

        $payload = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        if (array_key_exists('data', $payload)) {
            if (! is_array($payload['data'])) {
                return [];
            }

            if (
                array_key_exists('current_page', $payload['data'])
                && array_key_exists('data', $payload['data'])
                && is_array($payload['data']['data'])
            ) {
                return $payload['data']['data'];
            }

            return $payload['data'];
        }

        $metadataKeys = ['status', 'ok', 'success', 'meta', 'links'];
        $remainingKeys = array_values(array_filter(
            array_keys($payload),
            static function (int|string $key) use ($payload, $metadataKeys): bool {
                if (! is_string($key)) {
                    return true;
                }

                if (in_array($key, $metadataKeys, true)) {
                    return false;
                }

                if ($key === 'message' && ! is_array($payload['message'])) {
                    return false;
                }

                return true;
            }
        ));

        $knownResourceKeys = [
            'organization', 'organizations',
            'member', 'members',
            'lead', 'leads',
            'contact', 'contacts',
            'account', 'accounts',
            'deal', 'deals',
            'pipeline', 'pipelines',
            'stage', 'stages',
            'custom_field', 'custom_fields',
            'definition', 'definitions',
            'tracking_link', 'tracking_links',
            'invitation', 'user', 'activity',
            'conversation', 'template', 'consent', 'audit_log',
        ];

        if (
            count($remainingKeys) === 1
            && in_array($remainingKeys[0], $knownResourceKeys, true)
            && is_array($payload[$remainingKeys[0]])
        ) {
            return $payload[$remainingKeys[0]];
        }

        return $payload;
    }

    /**
     * Dynamically discover and invoke the stale-deal SLA Artisan command.
     */
    public function invokeSlaCommand(): int
    {
        $commands = Artisan::all();
        $candidates = [
            'crm:deals:check-sla',
            'deals:check-sla',
            'crm:check-stale-deals',
            'sla:check-stale-deals',
            'deals:sla-check',
        ];

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $commands)) {
                return Artisan::call($candidate);
            }
        }

        foreach (array_keys($commands) as $name) {
            $lower = Str::lower((string) $name);
            if (
                (str_contains($lower, 'stale') && (str_contains($lower, 'deal') || str_contains($lower, 'sla')))
                || (str_contains($lower, 'sla') && str_contains($lower, 'deal'))
            ) {
                return Artisan::call($name);
            }
        }

        return 1;
    }

    /**
     * Check whether Livewire 3 is installed via Composer.
     */
    public function hasLivewireInstalled(): bool
    {
        return class_exists(Livewire::class)
            && class_exists(LivewireManager::class);
    }

    /**
     * Extract and HTML-decode the wire:snapshot JSON for a named Livewire component.
     */
    protected function extractLivewireSnapshot(TestResponse|string $responseOrHtml, string $componentName): ?string
    {
        $html = $responseOrHtml instanceof TestResponse
            ? (string) $responseOrHtml->getContent()
            : $responseOrHtml;

        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $decoded = html_entity_decode($encoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $parsed = json_decode($decoded, true);

            if (is_array($parsed) && (($parsed['memo']['name'] ?? null) === $componentName)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Extract the CSRF token from a rendered HTML page's meta tag.
     */
    protected function extractCsrfToken(TestResponse|string $responseOrHtml): ?string
    {
        $html = $responseOrHtml instanceof TestResponse
            ? (string) $responseOrHtml->getContent()
            : $responseOrHtml;

        if (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', $html, $match) === 1) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return null;
    }

    /**
     * Build standard tenant headers for AJAX/JSON requests.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public function tenantHeaders(int|string $orgId, array $extra = []): array
    {
        return array_merge([
            'X-Organization-ID' => (string) $orgId,
            'Accept' => 'application/json',
        ], $extra);
    }

    /**
     * Return the first existing Livewire component class name from candidates, or null.
     */
    public function livewireClassIfExists(string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Assert that an HTTP response status code is within an allowed set.
     *
     * @param  array<int, int>  $statuses
     */
    public function assertStatusIn(TestResponse $response, array $statuses, string $message = ''): void
    {
        $actual = $response->getStatusCode();
        $allowed = implode(', ', $statuses);
        $prefix = $message !== '' ? "{$message} " : '';

        $this->assertTrue(
            in_array($actual, $statuses, true),
            "{$prefix}Expected HTTP status in [{$allowed}], got {$actual}."
        );
    }

    /**
     * Assert that a rendered HTML page does not depend on Vite/NPM build artifacts.
     */
    public function assertNoViteAssets(TestResponse $response): void
    {
        $content = (string) $response->getContent();

        $this->assertStringNotContainsString('/build/manifest.json', $content);
        $this->assertStringNotContainsString('Vite manifest not found', $content);
        $this->assertStringNotContainsString('@vite', $content);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function validB2BLeadPayload(array $overrides = []): array
    {
        $rand = Str::lower(Str::random(6));

        return array_merge([
            'type' => 'B2B',
            'first_name' => 'Elena',
            'last_name' => 'Vance',
            'email' => "elena-{$rand}@acme.test",
            'phone' => '+15550101',
            'company_name' => "Acme Corp {$rand}",
            'job_title' => 'VP Engineering',
            'employee_count' => 150,
            'source' => 'web',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function validB2CLeadPayload(array $overrides = []): array
    {
        $rand = Str::lower(Str::random(6));

        return array_merge([
            'type' => 'B2C',
            'first_name' => 'Liam',
            'last_name' => 'Carter',
            'email' => "liam-{$rand}@consumer.test",
            'phone' => '+15550102',
            'location' => 'Austin, TX',
            'source' => 'direct',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function validAccountPayload(array $overrides = []): array
    {
        $rand = Str::lower(Str::random(5));

        return array_merge([
            'name' => "Globex {$rand}",
            'domain' => "globex-{$rand}.test",
            'industry' => 'SaaS',
            'annual_revenue' => 2500000,
            'employee_count' => 120,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function validContactPayload(array $overrides = []): array
    {
        $rand = Str::lower(Str::random(6));

        return array_merge([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => "alice-{$rand}@example.test",
            'phone' => '+15550103',
            'preferred_channel' => 'email',
        ], $overrides);
    }

    protected function clearTenantContextIfBound(): void
    {
        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';

        if (class_exists($tenantContextClass) && $this->app->bound($tenantContextClass)) {
            $this->app->make($tenantContextClass)->clear();
        }
    }

    protected function resolveAuthenticatable(mixed $user): Authenticatable
    {
        if ($user instanceof Authenticatable) {
            return $user;
        }

        $userModel = 'App\\Models\\User';
        $userId = is_array($user) ? ($user['id'] ?? null) : (is_object($user) ? ($user->id ?? null) : $user);

        return $userModel::query()->findOrFail((int) $userId);
    }

    protected function findOrganizationAdmin(int|string $orgId, ?Authenticatable $preferredUser = null): ?Authenticatable
    {
        $userModel = 'App\\Models\\User';

        if (
            $preferredUser instanceof Authenticatable
            && method_exists($preferredUser, 'roleIn')
            && $preferredUser->roleIn((int) $orgId) === 'org_admin'
        ) {
            return $preferredUser;
        }

        $adminId = DB::table('organization_user')
            ->where('organization_id', (int) $orgId)
            ->where('role', 'org_admin')
            ->value('user_id');

        return $adminId ? $userModel::query()->find((int) $adminId) : null;
    }
}
