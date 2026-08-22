<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected GeminiService $geminiService;
    protected WhatsAppService $whatsAppService;

    public function __construct(GeminiService $geminiService, WhatsAppService $whatsAppService)
    {
        $this->geminiService = $geminiService;
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Endpoint Webhook yang menerima chat masuk dari WhatsApp (Fonnte)
     */
    public function handle(Request $request): JsonResponse
    {
        // Tangkap parameter dari webhook Fonnte
        $sender = $request->input('sender') ?? $request->input('from');
        $message = $request->input('message') ?? $request->input('text');
        $name = $request->input('name') ?? '';

        Log::info("[WhatsApp Webhook Masuk] Dari: {$sender} ({$name}) | Pesan: {$message}");

        if (empty($sender) || empty($message)) {
            return response()->json([
                'status' => false,
                'message' => 'Missing sender or message',
            ]);
        }

        // Jangan proses jika pesan berasal dari bot sendiri atau broadcast status
        if (str_contains(strtolower($message), 'peringatan monitoring presensi cctv')) {
            return response()->json(['status' => true]);
        }

        // Tanyakan ke Gemini AI Assistant dengan konteks live CCTV
        $aiReply = $this->geminiService->askAssistant($message, $sender, $name);

        // Kirim balasan ke WhatsApp pengirim
        $this->whatsAppService->sendMessage(
            $sender,
            $aiReply,
            null,
            null,
            'GEMINI_AI_REPLY'
        );

        return response()->json([
            'status' => true,
            'reply' => $aiReply,
        ]);
    }
}
