/**
 * Pure Essence Vita — Assistant chatbot (Wave, OM, suivi, WhatsApp)
 */
(function () {
  const CHATBOT_API = "api/chatbot.php";
  let config = null;
  let open = false;
  let busy = false;
  let started = false;

  const quickReplyMap = {};

  function el(tag, cls, html) {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (html != null) node.innerHTML = html;
    return node;
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatMessage(text) {
    return escapeHtml(text).replace(/\n/g, "<br>");
  }

  async function loadConfig() {
    if (typeof PEV !== "undefined" && PEV.settings?.chatbot) {
      config = PEV.settings.chatbot;
      indexQuickReplies(config.quickReplies);
      return config;
    }
    try {
      const res = await fetch(`${CHATBOT_API}?action=config`, { headers: { Accept: "application/json" } });
      const data = await res.json();
      if (data.ok && data.chatbot) {
        config = data.chatbot;
        indexQuickReplies(config.quickReplies);
      }
    } catch (err) {
      console.warn("Chatbot config indisponible", err);
    }
    if (!config) {
      config = {
        enabled: true,
        brandName: "Pure Essence Vita",
        agentName: "Essence",
        welcome: "Bonjour ! Comment puis-je vous aider ?",
        fallback: "Je n'ai pas bien compris. Choisissez une option ci-dessous.",
        whatsapp: "221771234567",
        quickReplies: [
          { id: "products", label: "Voir les produits" },
          { id: "payment_wave", label: "Payer avec Wave" },
          { id: "payment_orange", label: "Payer avec Orange Money" },
          { id: "track", label: "Suivre ma commande" },
          { id: "whatsapp", label: "Parler sur WhatsApp" },
        ],
      };
      indexQuickReplies(config.quickReplies);
    }
    return config;
  }

  function indexQuickReplies(list) {
    (list || []).forEach((item) => {
      quickReplyMap[item.id] = item.label;
    });
  }

  function whatsappUrl() {
    const num = String(config?.whatsapp || "221771234567").replace(/\D/g, "");
    const msg = encodeURIComponent("Bonjour Pure Essence Vita, j'aimerais des informations.");
    return `https://wa.me/${num}?text=${msg}`;
  }

  function buildWidget() {
    const root = el("div", "pev-chat");
    root.innerHTML = `
      <button type="button" class="pev-chat__toggle" id="pevChatToggle" aria-label="Ouvrir le chatbot Essence" aria-expanded="false" aria-controls="pevChatPanel">
        <span class="pev-chat__toggle-icon" aria-hidden="true">💬</span>
      </button>
      <div class="pev-chat__panel" id="pevChatPanel" role="dialog" aria-label="Assistant Essence" aria-hidden="true">
        <header class="pev-chat__header">
          <div>
            <strong id="pevChatAgent">${escapeHtml(config?.agentName || "Essence")}</strong>
            <span id="pevChatBrand">${escapeHtml(config?.brandName || "Pure Essence Vita")}</span>
          </div>
          <button type="button" class="pev-chat__close" id="pevChatClose" aria-label="Fermer">×</button>
        </header>
        <div class="pev-chat__messages" id="pevChatMessages" role="log" aria-live="polite"></div>
        <div class="pev-chat__quick" id="pevChatQuick"></div>
        <form class="pev-chat__form" id="pevChatForm">
          <input type="text" id="pevChatInput" placeholder="Votre message…" autocomplete="off" maxlength="500" />
          <button type="submit" class="pev-chat__send" aria-label="Envoyer">➤</button>
        </form>
      </div>`;
    document.body.appendChild(root);
    return root;
  }

  function appendMessage(text, role) {
    const box = document.getElementById("pevChatMessages");
    if (!box) return;
    const bubble = el("div", `pev-chat__msg pev-chat__msg--${role}`);
    bubble.innerHTML = formatMessage(text);
    box.appendChild(bubble);
    box.scrollTop = box.scrollHeight;
  }

  function showTyping() {
    const box = document.getElementById("pevChatMessages");
    if (!box || document.getElementById("pevChatTyping")) return;
    const typing = el("div", "pev-chat__typing", '<span></span><span></span><span></span>');
    typing.id = "pevChatTyping";
    box.appendChild(typing);
    box.scrollTop = box.scrollHeight;
  }

  function hideTyping() {
    document.getElementById("pevChatTyping")?.remove();
  }

  function renderQuickReplies(ids) {
    const wrap = document.getElementById("pevChatQuick");
    if (!wrap) return;
    wrap.innerHTML = "";
    (ids || []).forEach((id) => {
      const label = quickReplyMap[id] || id;
      const btn = el("button", "pev-chat__chip");
      btn.type = "button";
      btn.textContent = label;
      btn.dataset.intent = id;
      btn.addEventListener("click", () => sendMessage(label, id));
      wrap.appendChild(btn);
    });
  }

  async function sendMessage(message, intent) {
    if (busy) return;
    busy = true;
    appendMessage(message, "user");
    renderQuickReplies([]);
    showTyping();

    try {
      const res = await fetch(`${CHATBOT_API}?action=message`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ message, intent: intent || "" }),
      });
      const data = await res.json();
      hideTyping();
      if (!data.ok || !data.reply) {
        appendMessage(config?.fallback || "Service momentanément indisponible.", "bot");
        renderQuickReplies(["products", "whatsapp"]);
        return;
      }
      const reply = data.reply;
      appendMessage(reply.text, "bot");
      if (reply.action === "whatsapp") {
        setTimeout(() => window.open(whatsappUrl(), "_blank", "noopener"), 600);
      }
      renderQuickReplies(reply.quickReplies || []);
    } catch (_) {
      hideTyping();
      appendMessage(config?.fallback || "Connexion impossible. Réessayez ou contactez-nous sur WhatsApp.", "bot");
      renderQuickReplies(["whatsapp", "products"]);
    } finally {
      busy = false;
    }
  }

  function startConversation() {
    if (started) return;
    started = true;
    appendMessage(config.welcome, "bot");
    renderQuickReplies(["products", "payment_wave", "payment_orange", "track", "whatsapp"]);
  }

  function toggle(force) {
    open = typeof force === "boolean" ? force : !open;
    const panel = document.getElementById("pevChatPanel");
    const toggleBtn = document.getElementById("pevChatToggle");
    if (!panel) return;

    panel.classList.toggle("is-open", open);
    panel.setAttribute("aria-hidden", open ? "false" : "true");
    toggleBtn?.classList.toggle("is-open", open);
    toggleBtn?.setAttribute("aria-expanded", open ? "true" : "false");

    if (open) {
      startConversation();
      document.getElementById("pevChatInput")?.focus();
    }
  }

  async function init() {
    await loadConfig();
    if (config && config.enabled === false) return;

    buildWidget();

    document.getElementById("pevChatToggle")?.addEventListener("click", () => toggle());
    document.getElementById("pevChatClose")?.addEventListener("click", () => toggle(false));

    document.getElementById("pevChatForm")?.addEventListener("submit", (e) => {
      e.preventDefault();
      const input = document.getElementById("pevChatInput");
      const text = input?.value.trim();
      if (!text) return;
      input.value = "";
      sendMessage(text);
    });
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (typeof PEV !== "undefined" && PEV.loadSettings) {
      try {
        await PEV.loadSettings();
      } catch (_) {}
    }
    init();
  });
})();
