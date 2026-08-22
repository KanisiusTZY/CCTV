const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeInMemoryStore,
    delay
} = require("@whiskeysockets/baileys");
const pino = require("pino");
const qrcode = require("qrcode-terminal");
const express = require("express");
const cors = require("cors");
const http = require("http");
const path = require("path");

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cors());

const PORT = process.env.PORT || 3000;
const LARAVEL_WEBHOOK = process.env.LARAVEL_WEBHOOK || "http://127.0.0.1:8000/api/whatsapp/webhook";

let sock = null;
let connectionState = "DISCONNECTED"; // DISCONNECTED | QR_READY | CONNECTED
let lastQr = null;

// Format nomor Indonesia ke JID WhatsApp (e.g. 081234 -> 6281234@s.whatsapp.net)
function formatJid(number) {
    let clean = number.toString().replace(/[^0-9]/g, "");
    if (clean.startsWith("0")) {
        clean = "62" + clean.substring(1);
    } else if (clean.startsWith("8")) {
        clean = "62" + clean;
    }
    if (!clean.includes("@s.whatsapp.net")) {
        clean = clean + "@s.whatsapp.net";
    }
    return clean;
}

async function connectToWhatsApp() {
    const authPath = path.join(__dirname, "auth_info_baileys");
    const { state, saveCreds } = await useMultiFileAuthState(authPath);
    const { version, isLatest } = await fetchLatestBaileysVersion();

    console.log(`[Baileys] Inisialisasi WhatsApp Gateway (v${version.join(".")}, latest: ${isLatest})...`);

    sock = makeWASocket({
        version,
        logger: pino({ level: "silent" }),
        printQRInTerminal: false,
        auth: state,
        browser: ["Pratama AI CCTV", "Desktop", "1.0.0"],
        syncFullHistory: false,
    });

    sock.ev.on("creds.update", saveCreds);

    sock.ev.on("connection.update", async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            lastQr = qr;
            connectionState = "QR_READY";
            console.log("\n========================================================");
            console.log("?? SILAKAN SCAN QR CODE INI DENGAN WHATSAPP ANDA:");
            console.log("========================================================\n");
            qrcode.generate(qr, { small: true });
            console.log("\nBuka WhatsApp -> Perangkat Tertaut -> Tautkan Perangkat\n");
        }

        if (connection === "close") {
            const shouldReconnect = (lastDisconnect?.error)?.output?.statusCode !== DisconnectReason.loggedOut;
            console.log(`[Baileys] Koneksi terputus. Alasan: ${lastDisconnect?.error?.message}. Reconnect: ${shouldReconnect}`);
            connectionState = "DISCONNECTED";
            if (shouldReconnect) {
                setTimeout(connectToWhatsApp, 3000);
            }
        } else if (connection === "open") {
            connectionState = "CONNECTED";
            lastQr = null;
            const userJid = sock.user?.id || "";
            const phone = userJid.split(":")[0] || userJid.split("@")[0];
            console.log("\n========================================================");
            console.log(`? [Baileys] WHATSAPP TERHUBUNG BERHASIL! (Nomor: ${phone})`);
            console.log("========================================================\n");
        }
    });

    // Handle Incoming Messages (Chat Masuk -> Teruskan ke Gemini AI Laravel)
    sock.ev.on("messages.upsert", async ({ messages, type }) => {
        if (type !== "notify") return;

        for (const msg of messages) {
            if (!msg.message || msg.key.fromMe) continue;

            const remoteJid = msg.key.remoteJid;
            if (!remoteJid || remoteJid.includes("@g.us") || remoteJid === "status@broadcast") {
                continue; // Hanya tangani chat personal (bukan grup/status)
            }

            const sender = remoteJid.replace("@s.whatsapp.net", "");
            const name = msg.pushName || "";
            const text = msg.message.conversation ||
                         msg.message.extendedTextMessage?.text ||
                         msg.message.imageMessage?.caption ||
                         "";

            if (!text || text.trim() === "") continue;

            console.log(`[WA Masuk] Dari: ${sender} (${name}) | Pesan: "${text}"`);

            try {
                // Tampilkan status mengetik di WhatsApp
                await sock.sendPresenceUpdate("composing", remoteJid);

                // Kirim pesan ke Webhook Laravel
                const fetchRes = await fetch(LARAVEL_WEBHOOK, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        sender: sender,
                        from: sender,
                        message: text,
                        text: text,
                        name: name
                    })
                });

                if (fetchRes.ok) {
                    const resJson = await fetchRes.json();
                    const replyText = resJson.reply || resJson.message;

                    if (replyText && typeof replyText === "string") {
                        await delay(500);
                        await sock.sendMessage(remoteJid, { text: replyText }, { quoted: msg });
                        console.log(`[WA Balas] Terkirim ke ${sender}: "${replyText.substring(0, 60)}..."`);
                    }
                }
            } catch (err) {
                console.error("[Baileys Webhook Error]", err.message);
            } finally {
                await sock.sendPresenceUpdate("paused", remoteJid);
            }
        }
    });
}

// REST API Endpoints untuk Laravel
app.get("/status", (req, res) => {
    res.json({
        status: connectionState,
        connected: connectionState === "CONNECTED",
        user: sock?.user || null,
        qr: connectionState === "QR_READY" ? lastQr : null
    });
});

app.post("/send", async (req, res) => {
    try {
        const { target, message } = req.body;

        if (!target || !message) {
            return res.status(400).json({ status: false, message: "Target dan message wajib diisi" });
        }

        if (connectionState !== "CONNECTED" || !sock) {
            return res.status(503).json({ status: false, message: "WhatsApp belum terhubung (Scan QR terlebih dahulu)" });
        }

        const jid = formatJid(target);
        await sock.sendMessage(jid, { text: message });

        console.log(`[WA Keluar] Pesan notifikasi terkirim ke ${target}`);
        return res.json({
            status: true,
            message: "Pesan berhasil terkirim tanpa watermark!",
            target: target
        });
    } catch (err) {
        console.error("[Baileys Send Error]", err);
        return res.status(500).json({ status: false, error: err.message });
    }
});

app.listen(PORT, () => {
    console.log(`[Baileys Server] REST API berjalan di http://localhost:${PORT}`);
    connectToWhatsApp();
});
