@if(\App\Support\LocaleHelper::shouldShowSelectionPopup(request()))
    @php
        $supportedLocales = config('localization.supported', []);
        $fallbackLocale = config('localization.fallback', 'hi_Latn');
    @endphp
    <div id="crm-lang-modal" class="crm-lang-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="crm-lang-modal-title">
        <div class="crm-lang-modal-card">
            <div class="crm-lang-modal-header">
                <div>
                    <h2 id="crm-lang-modal-title" style="margin:0 0 0.35rem; font-size:1.25rem;">
                        {{ __('Choose your preferred language') }}
                    </h2>
                    <p class="crm-muted" style="margin:0; font-size:0.88rem;">
                        {{ __('Select a language to continue. If you skip, Hinglish will be used as the default fallback.') }}
                    </p>
                </div>
                <form method="POST" action="{{ route('locale.update') }}" style="margin:0;">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $fallbackLocale }}">
                    <button type="submit" class="crm-lang-modal-close" aria-label="{{ __('Close') }}" title="{{ __('Continue in Hinglish') }}">&times;</button>
                </form>
            </div>

            <div class="crm-lang-option-grid">
                @foreach($supportedLocales as $code => $meta)
                    <form method="POST" action="{{ route('locale.update') }}" style="margin:0;">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $code }}">
                        <button type="submit" class="crm-lang-option-btn {{ $code === $fallbackLocale ? 'is-recommended' : '' }}">
                            <span class="crm-lang-option-badge">{{ $meta['short'] ?? strtoupper($code) }}</span>
                            <span class="crm-lang-option-text">
                                <strong>{{ $meta['native'] ?? $meta['label'] }}</strong>
                                <small>{{ $meta['description'] ?? $meta['label'] }}</small>
                            </span>
                            @if($code === $fallbackLocale)
                                <span class="crm-lang-fallback-tag">Fallback</span>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>

            <div class="crm-lang-modal-footer">
                <form method="POST" action="{{ route('locale.update') }}" style="margin:0; width:100%;">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $fallbackLocale }}">
                    <button type="submit" class="crm-btn crm-btn-secondary crm-btn-block">
                        {{ __('Continue in Hinglish') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif
