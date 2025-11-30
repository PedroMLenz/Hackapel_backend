<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $chatId,
        public string $message
    ) {}

    public function handle(): void
    {
        /** -------------------------------------
         * 1. Enviar mensagem de texto para Telegram
         * --------------------------------------*/
        $token = env('TELEGRAM_TOKEN');
        $telegramApi = "https://api.telegram.org/bot$token";

        Http::post("$telegramApi/sendMessage", [
            'chat_id' => $this->chatId,
            'text'    => $this->message,
        ]);

        /** -------------------------------------
         * 2. Gerar áudio com ElevenLabs API
         * --------------------------------------*/
        $elevenApiKey = env("ELEVEN_API_KEY");
        $voiceId = "EXAVITQu4vr4xnSDxMaL"; // Voz "Rachel" - oficial

        Log::info("Gerando áudio ElevenLabs...");

        $ttsResponse = Http::withHeaders([
            "xi-api-key" => $elevenApiKey,
            "Content-Type" => "application/json"
        ])->post("https://api.elevenlabs.io/v1/text-to-speech/$voiceId", [
            "text"   => $this->message,
            "model_id" => "eleven_multilingual_v2"
        ]);

        if (!$ttsResponse->successful()) {
            Log::error("Erro ElevenLabs", $ttsResponse->json());
            return;
        }

        $audioBinary = $ttsResponse->body();

        /** -------------------------------------
         * 3. Salvar MP3 local temporário
         * --------------------------------------*/
        $fileName = "audio_" . Str::uuid() . ".mp3";
        $localPath = storage_path("app/$fileName");

        file_put_contents($localPath, $audioBinary);

        /** -------------------------------------
         * 4. Upload ao Supabase via API
         * --------------------------------------*/
        Log::info("Enviando MP3 para Supabase...");

        $supabaseUrl  = env("SUPABASE_URL");
        $supabaseKey  = env("SUPABASE_KEY");
        $bucket       = env("SUPABASE_BUCKET");

        $remoteName = "audios/$fileName";

        $uploadUrl = "$supabaseUrl/storage/v1/object/$bucket/$remoteName";

        $upload = Http::withHeaders([
            "apikey"        => $supabaseKey,
            "Authorization" => "Bearer $supabaseKey",
            "Content-Type"  => "audio/mpeg"
        ])
        ->withBody($audioBinary, 'audio/mpeg')
        ->put($uploadUrl);

        if (!$upload->successful()) {
            Log::error("Erro upload Supabase", [
                'status' => $upload->status(),
                'body'   => $upload->body()
            ]);
            return;
        }

        // URL pública final
        $publicUrl = "$supabaseUrl/storage/v1/object/public/$bucket/$remoteName";

        Log::info("MP3 salvo no Supabase", ['url' => $publicUrl]);

        /** -------------------------------------
         * 5. Enviar áudio para Telegram
         * --------------------------------------*/
        Log::info("Enviando áudio via Telegram...");

        $telegramAudio = Http::attach(
            'audio', $audioBinary, $fileName
        )->post("$telegramApi/sendAudio", [
            'chat_id' => $this->chatId,
            'caption' => "Aqui está seu áudio 😉"
        ]);

        if (!$telegramAudio->successful()) {
            Log::error("Erro ao enviar áudio Telegram", $telegramAudio->json());
        }

        /** -------------------------------------
         * 6. Limpar arquivo local temporário
         * --------------------------------------*/
        @unlink($localPath);
    }
}
