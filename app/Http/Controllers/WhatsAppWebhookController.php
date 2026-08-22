<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\PresenceNotificationLog;

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
     * Endpoint Webhook yang menerima chat masuk dari WhatsApp Gateway Lokal (Baileys)
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Verifikasi GET endpoint
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => true,
                'message' => 'WhatsApp Webhook Active & Ready',
            ]);
        }

        // 2. Tangkap pesan masuk
        $sender = $request->input('sender') ?? $request->input('from');
        $message = $request->input('message') ?? $request->input('text');
        $name = $request->input('name') ?? '';

        Log::info("[WhatsApp Masuk] Dari: {$sender} ({$name}) | Pesan: {$message}");

        if (empty($sender) || empty($message)) {
            return response()->json([
                'status' => false,
                'message' => 'Missing sender or message',
            ]);
        }

        // Jangan proses jika pesan dari sistem sendiri
        if (str_contains(strtolower($message), 'peringatan monitoring presensi cctv')) {
            return response()->json(['status' => true]);
        }

        // Generate balasan cerdas dari Gemini AI dengan konteks live CCTV
        $aiReply = $this->geminiService->askAssistant($message, $sender, $name);

        // Catat ke log database
        PresenceNotificationLog::create([
            'employee_id' => null,
            'zone_id' => null,
            'phone_number' => $sender,
            'notification_type' => 'GEMINI_AI_REPLY',
            'message' => $aiReply,
            'status' => 'SENT',
            'away_duration_minutes' => null,
        ]);

        return response()->json([
            'status' => true,
            'reply' => $aiReply,
            'message' => $aiReply,
        ]);
    }
}
