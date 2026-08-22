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
     * Mendukung GET (verifikasi Fonnte) dan POST (incoming messages)
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Tangani verifikasi awal dari Fonnte via GET
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => true,
                'message' => 'Fonnte Webhook URL is Active & Ready',
            ]);
        }

        // 2. Tangani pesan chat masuk via POST
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

        // Jangan proses jika pesan berasal dari template peringatan sistem sendiri
        if (str_contains(strtolower($message), 'peringatan monitoring presensi cctv')) {
            return response()->json(['status' => true]);
        }

        // Tanyakan ke Gemini AI Assistant dengan konteks live CCTV
        $aiReply = $this->geminiService->askAssistant($message, $sender, $name);

        // Kirim balasan langsung ke WhatsApp pengirim via Fonnte
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
