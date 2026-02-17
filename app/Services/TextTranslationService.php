<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TextTranslationService
{
    public function translate(?string $text, ?string $source = null, ?string $target = null): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }

        $sourceLang = $source ?? (string) config('translation.source_lang', 'en');
        $targetLang = $target ?? (string) config('translation.target_lang', 'es');
        $baseUrl = trim((string) config('translation.base_url', ''));

        if ($baseUrl === '' || $sourceLang === $targetLang) {
            return $text;
        }

        $cacheKey = 'translation.'.sha1($sourceLang.'|'.$targetLang.'|'.$text);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && trim($cached) !== '') {
            return $cached;
        }

        try {
            $translated = $this->performTranslate($baseUrl, $text, $sourceLang, $targetLang);
            if ($sourceLang === 'en' && $targetLang === 'es' && trim(mb_strtolower($translated)) === trim(mb_strtolower($text))) {
                $translated = $this->translateWithGooglePublic($text, $sourceLang, $targetLang)
                    ?? $this->fallbackDictionaryTranslate($text);
            }
            $ttl = now()->addSeconds((int) config('translation.cache_ttl_seconds', 86400));
            Cache::put($cacheKey, $translated, $ttl);

            return $translated;
        } catch (Throwable $exception) {
            Log::warning('Translation API failed, using fallback translation', [
                'message' => $exception->getMessage(),
            ]);

            $googleFallback = $this->translateWithGooglePublic($text, $sourceLang, $targetLang);
            if (is_string($googleFallback) && trim($googleFallback) !== '') {
                return $googleFallback;
            }

            return $sourceLang === 'en' && $targetLang === 'es'
                ? $this->fallbackDictionaryTranslate($text)
                : $text;
        }
    }

    public function translateMany(array $texts, ?string $source = null, ?string $target = null): array
    {
        $uniqueTexts = array_values(array_unique(array_filter(array_map(function ($value) {
            return is_string($value) && trim($value) !== '' ? $value : null;
        }, $texts))));

        $translated = [];
        foreach ($uniqueTexts as $text) {
            $translated[$text] = $this->translate($text, $source, $target) ?? $text;
        }

        return $translated;
    }

    private function performTranslate(string $baseUrl, string $text, string $sourceLang, string $targetLang): string
    {
        $librePayload = [
            'q' => $text,
            'source' => $sourceLang,
            'target' => $targetLang,
            'format' => 'text',
        ];

        $paths = ['/translate', '/api/translate'];

        foreach ($paths as $path) {
            $url = rtrim($baseUrl, '/').$path;

            $postResponse = Http::timeout((int) config('translation.timeout', 12))
                ->connectTimeout((int) config('translation.connect_timeout', 6))
                ->asForm()
                ->acceptJson()
                ->post($url, $librePayload);

            if ($postResponse->successful()) {
                $translated = $this->extractFromResponse($postResponse);
                if ($translated !== null && trim($translated) !== '') {
                    return $translated;
                }
            }

            $getResponse = Http::timeout((int) config('translation.timeout', 12))
                ->connectTimeout((int) config('translation.connect_timeout', 6))
                ->acceptJson()
                ->get($url, $librePayload);

            if ($getResponse->successful()) {
                $translated = $this->extractFromResponse($getResponse);
                if ($translated !== null && trim($translated) !== '') {
                    return $translated;
                }
            }

            $simplyPayload = [
                'engine' => 'google',
                'from' => $sourceLang,
                'to' => $targetLang,
                'text' => $text,
            ];

            $simplyResponse = Http::timeout((int) config('translation.timeout', 12))
                ->connectTimeout((int) config('translation.connect_timeout', 6))
                ->acceptJson()
                ->get($url, $simplyPayload);

            if ($simplyResponse->successful()) {
                $translated = $this->extractFromResponse($simplyResponse);
                if ($translated !== null && trim($translated) !== '') {
                    return $translated;
                }
            }
        }

        throw new RuntimeException('No se pudo traducir el texto con la API de traduccion.');
    }

    private function extractTranslatedText(mixed $payload): ?string
    {
        if (is_string($payload)) {
            return $payload;
        }

        if (!is_array($payload)) {
            return null;
        }

        // Some SimplyTranslate responses return service status metadata, not translated text.
        if (isset($payload['service']) && isset($payload['status']) && isset($payload['supportedLanguages'])) {
            return null;
        }

        foreach (['translatedText', 'translated_text', 'translated-text', 'translation', 'text'] as $key) {
            $candidate = $payload[$key] ?? null;
            if (is_string($candidate)) {
                return $candidate;
            }
        }

        $translations = $payload['translations'] ?? null;
        if (is_array($translations) && isset($translations[0]) && is_array($translations[0])) {
            $candidate = $translations[0]['text'] ?? ($translations[0]['translation'] ?? null);
            if (is_string($candidate)) {
                return $candidate;
            }
        }

        $data = $payload['data'] ?? null;
        if (is_array($data)) {
            return $this->extractTranslatedText($data);
        }

        return null;
    }

    private function extractFromResponse(Response $response): ?string
    {
        $fromJson = $this->extractTranslatedText($response->json());
        if (is_string($fromJson) && trim($fromJson) !== '') {
            return $fromJson;
        }

        $body = trim((string) $response->body());
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $fromDecoded = $this->extractTranslatedText($decoded);
            if (is_string($fromDecoded) && trim($fromDecoded) !== '') {
                return $fromDecoded;
            }
            return null;
        }

        if (!str_starts_with($body, '<')) {
            return $body;
        }

        return null;
    }

    private function fallbackDictionaryTranslate(string $text): string
    {
        $map = [
            'beginner' => 'principiante',
            'intermediate' => 'intermedio',
            'expert' => 'avanzado',
            'strength' => 'fuerza',
            'stretching' => 'estiramiento',
            'cardio' => 'cardio',
            'powerlifting' => 'powerlifting',
            'olympic weightlifting' => 'halterofilia olimpica',
            'olympic_weightlifting' => 'halterofilia olimpica',
            'biceps' => 'biceps',
            'triceps' => 'triceps',
            'chest' => 'pecho',
            'shoulders' => 'hombros',
            'quadriceps' => 'cuadriceps',
            'hamstrings' => 'isquiotibiales',
            'abdominals' => 'abdominales',
            'middle_back' => 'espalda media',
            'lower_back' => 'espalda baja',
            'lats' => 'dorsales',
            'traps' => 'trapecio',
            'equipment' => 'equipo',
            'instructions' => 'instrucciones',
            'safety' => 'seguridad',
            'calories' => 'calorias',
            'protein' => 'proteina',
            'carbohydrates' => 'carbohidratos',
            'fat' => 'grasa',
            'chicken breast' => 'pechuga de pollo',
            'broccoli' => 'brocoli',
            'apple' => 'manzana',
        ];

        $translated = $text;
        foreach ($map as $en => $es) {
            $translated = str_ireplace($en, $es, $translated);
        }

        return $translated;
    }

    private function translateWithGooglePublic(string $text, string $sourceLang, string $targetLang): ?string
    {
        try {
            $response = Http::timeout((int) config('translation.timeout', 12))
                ->connectTimeout((int) config('translation.connect_timeout', 6))
                ->get('https://translate.googleapis.com/translate_a/single', [
                    'client' => 'gtx',
                    'sl' => $sourceLang,
                    'tl' => $targetLang,
                    'dt' => 't',
                    'q' => $text,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $payload = $response->json();
            if (!is_array($payload) || !isset($payload[0]) || !is_array($payload[0])) {
                return null;
            }

            $chunks = [];
            foreach ($payload[0] as $segment) {
                if (is_array($segment) && isset($segment[0]) && is_string($segment[0])) {
                    $chunks[] = $segment[0];
                }
            }

            $result = trim(implode('', $chunks));

            return $result !== '' ? $result : null;
        } catch (Throwable) {
            return null;
        }
    }
}
