<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $chatId,
        public string $message
    ) {}

    public function handle(): void
    {
        $token = env('TELEGRAM_TOKEN');
        $baseUrl = "https://api.telegram.org/bot{$token}";

        // Enviar texto
        Http::post("$baseUrl/sendMessage", [
            'chat_id' => $this->chatId,
            'text'    => $this->message,
        ]);

        // Enviar o áudio
        Http::post("$baseUrl/sendAudio", [
            'chat_id' => $this->chatId,
            'audio'   => "https://files.catbox.moe/l0z2vs.mp3",
        ]);
    }
}
