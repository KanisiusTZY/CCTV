const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    delay
} = require("@whiskeysockets/baileys");
const pino = require("pino");
const qrcode = require("qrcode-terminal");
const express = require("express");
const cors = require("cors");
const path = require("path");

// Cegah crash akibat EPIPE saat stdout tertutup di Windows background process
if (process.stdout) {
    process.stdout.on("error", (err) => { if (err.code === "EPIPE") return; });
}
if (process.stderr) {
    process.stderr.on("error", (err) => { if (err.code === "EPIPE") return; });
}
process.on("uncaughtException", (err) => {
    if (err.code === "EPIPE") return;
    try {
        console.error("[Uncaught Exception]", err);
    } catch (e) {}
});

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cors());

const PORT = process.env.PORT || 3000;
const LARAVEL_WEBHOOK = process.env.LARAVEL_WEBHOOK || "http://127.0.0.1:8000/api/whatsapp/webhook";

let sock = null;
let connectionState = "DISCONNECTED"; // DISCONNECTED | QR_READY | CONNECTED
let lastQr = null;

function safeLog(...args) {
    try {
        console.log(...args);
    } catch (e) {
        // Ignored if pipe is closed
    }
}

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

    safeLog(`[Baileys] Inisialisasi WhatsApp Gateway (v${version.join(".")}, latest: ${isLatest})...`);

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
            safeLog("\n========================================================");
            safeLog("SILAKAN SCAN QR CODE INI DENGAN WHATSAPP ANDA:");
            safeLog("========================================================\n");
            try {
                qrcode.generate(qr, { small: true });
            } catch (e) {}
            safeLog("\nBuka WhatsApp -> Perangkat Tertaut -> Tautkan Perangkat\n");
        }

        if (connection === "close") {
            const shouldReconnect = (lastDisconnect?.error)?.output?.statusCode !== DisconnectReason.loggedOut;
            safeLog(`[Baileys] Koneksi terputus. Reconnect: ${shouldReconnect}`);
            connectionState = "DISCONNECTED";
            if (shouldReconnect) {
                setTimeout(connectToWhatsApp, 3000);
            }
        } else if (connection === "open") {
            connectionState = "CONNECTED";
            lastQr = null;
            const userJid = sock.user?.id || "";
            const phone = userJid.split(":")[0] || userJid.split("@")[0];
            safeLog("\n========================================================");
            safeLog(`[Baileys] WHATSAPP TERHUBUNG BERHASIL! (Nomor: ${phone})`);
            safeLog("========================================================\n");
        }
    });

    // Handle Incoming Messages (Chat Masuk -> Teruskan ke Gemini AI Laravel)
    sock.ev.on("messages.upsert", async ({ messages, type }) => {
        if (type !== "notify") return;

        for (const msg of messages) {
            if (!msg.message || msg.key.fromMe) continue;

            const remoteJid = msg.key.remoteJid;
            if (!remoteJid || remoteJid.includes("@g.us") || remoteJid === "status@broadcast") {
                continue;
            }

            const sender = remoteJid.replace("@s.whatsapp.net", "");
            const name = msg.pushName || "";
            const text = msg.message.conversation ||
                         msg.message.extendedTextMessage?.text ||
                         msg.message.imageMessage?.caption ||
                         "";

            if (!text || text.trim() === "") continue;

            safeLog(`[WA Masuk] Dari: ${sender} (${name}) | Pesan: "${text}"`);

            try {
                await sock.sendPresenceUpdate("composing", remoteJid);

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
                        safeLog(`[WA Balas] Terkirim ke ${sender}: "${replyText.substring(0, 60)}..."`);
                    }
                }
            } catch (err) {
                // Ignore error if webhook is offline
            } finally {
                try {
                    await sock.sendPresenceUpdate("paused", remoteJid);
                } catch (e) {}
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
            return res.status(503).json({ status: false, message: "WhatsApp belum terhubung" });
        }

        const jid = formatJid(target);
        await sock.sendMessage(jid, { text: message });

        safeLog(`[WA Keluar] Pesan notifikasi terkirim ke ${target}`);
        return res.json({
            status: true,
            message: "Pesan berhasil terkirim!",
            target: target
        });
    } catch (err) {
        return res.status(500).json({ status: false, error: err.message });
    }
});

app.listen(PORT, () => {
    safeLog(`[Baileys Server] REST API berjalan di http://localhost:${PORT}`);
    connectToWhatsApp();
});
