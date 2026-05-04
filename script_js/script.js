const PDFJS_VERSION = "5.6.205";
let pdfJsReadyPromise = null;

async function ensurePdfJs() {
  if (window.pdfjsLib) {
    window.pdfjsLib.GlobalWorkerOptions.workerSrc =
      `https://cdn.jsdelivr.net/npm/pdfjs-dist@${PDFJS_VERSION}/build/pdf.worker.min.mjs`;
    return window.pdfjsLib;
  }

  if (!pdfJsReadyPromise) {
    pdfJsReadyPromise = import(
      `https://cdn.jsdelivr.net/npm/pdfjs-dist@${PDFJS_VERSION}/build/pdf.min.mjs`
    ).then((module) => {
      window.pdfjsLib = module;
      window.pdfjsLib.GlobalWorkerOptions.workerSrc =
        `https://cdn.jsdelivr.net/npm/pdfjs-dist@${PDFJS_VERSION}/build/pdf.worker.min.mjs`;
      return window.pdfjsLib;
    });
  }

  return pdfJsReadyPromise;
}

/* ================= GLOBAL ELEMEK ================= */

const output = document.getElementById("output-content");
const tags = document.getElementById("output-tags");
const newsList = document.getElementById("news-list");
const historyList = document.getElementById("history-list");
const chatMessages = document.getElementById("chat-messages");
const tabs = document.querySelectorAll(".tool-tab");
const themeToggleButton = document.getElementById("theme-toggle");
const panelGrid = document.querySelector(".panel-grid");
const outputBox = document.getElementById("output-box");
const clearHistoryButton = document.getElementById("clear-history-btn");
const confirmOverlay = document.getElementById("confirm-overlay");
const confirmCancel = document.getElementById("confirm-cancel");
const confirmDelete = document.getElementById("confirm-delete");
const chatConfirmOverlay = document.getElementById("chat-confirm-overlay");
const chatConfirmCancel = document.getElementById("chat-confirm-cancel");
const chatConfirmDelete = document.getElementById("chat-confirm-delete");
const pdfFileInput = document.getElementById("pdf-file");
const pdfFileList = document.getElementById("pdf-file-list");
const chatInput = document.getElementById("chat-input");
const toastStack = document.getElementById("toast-stack");
const creditDisplay = document.getElementById("credit-count");

const HISTORY_KEY = "aiWrappedHistory";
const THEME_KEY = "aiWrappedTheme";
const API_ENDPOINT = "/api_php/api.php";
const HISTORY_ENDPOINT = "/api_php/history.php";

let currentCredits = Number(window.ASTRALUS_SERVER_CREDITS ?? 0);
const IS_LOGGED_IN = Boolean(window.ASTRALUS_IS_LOGGED_IN ?? false);

let activeChatHistoryId = null;
let pendingHistoryDeleteIndex = null;
let uploadedPdfDocuments = [];
let historyCache = [];
let historyReady = false;

const panels = {
  news: document.getElementById("panel-news"),
  notes: document.getElementById("panel-notes"),
  study: document.getElementById("panel-study"),
  chat: document.getElementById("panel-chat"),
  history: document.getElementById("panel-history"),
};

/* ================= SEGÉDFÜGGVÉNYEK ================= */

function safeJsonParse(value, fallback = []) {
  try {
    const parsed = JSON.parse(value);
    return parsed ?? fallback;
  } catch (error) {
    return fallback;
  }
}

function autoResizeTextarea(el) {
  if (!el) return;

  el.style.height = "auto";
  el.style.height = `${el.scrollHeight}px`;
}

function getHistoryItems() {
  return Array.isArray(historyCache) ? historyCache : [];
}

function setHistoryItems(items) {
  historyCache = Array.isArray(items) ? items : [];
  historyReady = true;
}

async function loadHistoryFromServer(force = false) {
  if (!IS_LOGGED_IN) {
    setHistoryItems([]);
    return [];
  }

  if (historyReady && !force) {
    return getHistoryItems();
  }

  const data = await apiCall("list_history", {}, HISTORY_ENDPOINT);
  setHistoryItems(Array.isArray(data.items) ? data.items : []);
  return getHistoryItems();
}

async function saveHistoryToServer(item) {
  if (!IS_LOGGED_IN) return null;

  const data = await apiCall(
    "upsert_history",
    { item },
    HISTORY_ENDPOINT
  );

  setHistoryItems(Array.isArray(data.items) ? data.items : []);
  return data.item || item;
}

async function deleteHistoryFromServer(id) {
  if (!IS_LOGGED_IN) return;

  const data = await apiCall(
    "delete_history",
    { id },
    HISTORY_ENDPOINT
  );

  setHistoryItems(Array.isArray(data.items) ? data.items : []);
}

async function clearHistoryOnServer() {
  if (!IS_LOGGED_IN) return;

  const data = await apiCall(
    "clear_history",
    {},
    HISTORY_ENDPOINT
  );

  setHistoryItems(Array.isArray(data.items) ? data.items : []);
}

async function apiCall(action, payload, endpoint = API_ENDPOINT) {
  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      action,
      payload,
    }),
  });

  const rawText = await response.text();

  let data;

  try {
    data = JSON.parse(rawText);
  } catch (error) {
    console.error("Nem JSON válasz érkezett az API-tól:", rawText);
    throw new Error("A szerver nem JSON választ adott vissza. Nézd meg a Console-t.");
  }

  if (!response.ok || !data.ok) {
    if (data.debug) {
      console.error("API DEBUG:", data.debug);
    }
    throw new Error(data.error || "Szerver hiba.");
  }

  return data;
}

function updateCreditsUI() {
  if (creditDisplay) {
    creditDisplay.textContent = String(currentCredits);
  }
}

function useCredits() {
  return true;
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function setOutputContent(text, tagList = []) {
  if (output) {
    output.textContent = text || "";
  }

  if (tags) {
    tags.innerHTML = "";

    if (Array.isArray(tagList) && tagList.length) {
      tags.innerHTML = tagList
        .map((tag) => `<span class="tag">${escapeHtml(tag)}</span>`)
        .join("");
    }
  }
}

function collectChatConversationFromDOM() {
  if (!chatMessages) return [];

  return Array.from(chatMessages.querySelectorAll(".chat-bubble")).map((el) => ({
    role: el.classList.contains("user") ? "user" : "assistant",
    text: (el.textContent || "").trim(),
  }));
}

/* ================= ELŐZMÉNYEK ================= */

async function saveHistory(item) {
  if (!IS_LOGGED_IN) {
    return null;
  }

  const historyItem = {
    at: new Date().toLocaleString("hu-HU"),
    id: item.id || `history-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    ...item,
    fullOutput: item.fullOutput || item.preview || "",
    savedTags: Array.isArray(item.savedTags) ? item.savedTags : [],
    conversation: Array.isArray(item.conversation) ? item.conversation : [],
    formData: item.formData && typeof item.formData === "object" ? item.formData : {},
  };

  const items = getHistoryItems().filter((entry) => entry.id !== historyItem.id);
  items.unshift(historyItem);
  setHistoryItems(items.slice(0, 50));

  await saveHistoryToServer(historyItem);
  renderHistory();

  return historyItem.id;
}

function renderHistory() {
  if (!historyList) return;

  const items = getHistoryItems();
  historyList.innerHTML = "";

  const clearBtn = document.getElementById("clear-history-btn");

  if (clearBtn) {
    if (!items.length) {
      clearBtn.style.opacity = "0";
      clearBtn.style.transform = "translateY(6px)";
      clearBtn.style.pointerEvents = "none";

      setTimeout(() => {
        clearBtn.style.display = "none";
      }, 200);
    } else {
      clearBtn.style.display = "inline-flex";

      requestAnimationFrame(() => {
        clearBtn.style.opacity = "1";
        clearBtn.style.transform = "translateY(0)";
        clearBtn.style.pointerEvents = "auto";
      });
    }
  }

  if (!items.length) {
    historyList.innerHTML = `
      <div class="history-empty-state">
        <div class="history-empty-icon">✦</div>
        <h4>Még nincs mentett előzmény</h4>
        <p>${
  IS_LOGGED_IN
    ? "Amint használsz egy modult vagy beszélgetsz az AI-jal, itt szépen rendezve megjelennek a mentett elemek."
    : "Az előzmények mentéséhez jelentkezz be. Vendég módban a rendszer nem tárol beszélgetési előzményt."
}</p>
      </div>
    `;
    return;
  }

  function getHistoryIcon(type = "", title = "") {
    const text = `${type} ${title}`.toLowerCase();

    if (text.includes("chat")) return "💬";
    if (text.includes("hír")) return "📰";
    if (text.includes("jegyzet")) return "📝";
    if (text.includes("tanul")) return "🎓";

    return "✨";
  }

  function getHistoryBadge(type = "", module = "") {
    if (module === "chat") return "AI Chat";
    if (module === "news") return "Hírkereső";
    if (module === "notes") return "Jegyzet";
    if (module === "study") return "Astralus Study";
    return type || "AI művelet";
  }

  items.forEach((item, index) => {
    const card = document.createElement("article");
    card.className = "history-item";

    const icon = getHistoryIcon(item.type || "", item.title || "");
    const badge = getHistoryBadge(item.type || "", item.module || "");
    const title = escapeHtml(item.title || "Mentett elem");

    const metaParts = [
      item.at ? escapeHtml(item.at) : "",
      item.creditDelta ? escapeHtml(item.creditDelta) : "",
    ].filter(Boolean);

    const preview = escapeHtml((item.preview || "Mentett tartalom.").slice(0, 240));

    card.innerHTML = `
      <div class="history-card-top">
        <div class="history-type-badge">${badge}</div>
        <div class="history-preview-icon">${icon}</div>
      </div>

      <div class="history-card-copy">
        <h4 class="history-item-title">${title}</h4>
        <div class="history-item-meta">${metaParts.join(" • ")}</div>
      </div>

      <div class="history-item-preview">${preview}</div>

      <div class="history-item-actions">
        <button class="history-open-btn" type="button" data-history-index="${index}">
          Megnyitás
        </button>
        <button class="history-delete-btn" type="button" data-history-index="${index}">
          Törlés
        </button>
      </div>
    `;

    historyList.appendChild(card);
  });
}

function inferHistoryModule(item) {
  if (item.module) return item.module;

  const type = (item.type || "").toLowerCase();
  const title = (item.title || "").toLowerCase();

  if (type.includes("hír") || title.includes("hír")) return "news";
  if (type.includes("jegyzet") || title.includes("pdf")) return "notes";
  if (type.includes("tanulási") || title.includes("házi")) return "study";
  if (type.includes("chat")) return "chat";

  return "";
}

function restoreHistoryItem(index) {
  const items = getHistoryItems();
  const item = items[index];
  if (!item) return;

  const moduleKey = inferHistoryModule(item);
  if (!moduleKey) return;

  const restoredOutput = item.fullOutput || item.preview || "";
  const restoredTags = Array.isArray(item.savedTags) ? item.savedTags : [];

  if (moduleKey === "news") {
    activeChatHistoryId = null;
    switchTab("news");
    setOutputContent(
      restoredOutput || "Mentett hírösszefoglaló.",
      restoredTags.length ? restoredTags : ["Hírkereső", "Előzmény", "Betöltve"]
    );

    if (newsList) {
      newsList.innerHTML = "";
    }
    return;
  }

  if (moduleKey === "notes") {
    activeChatHistoryId = null;
    switchTab("notes");
    setOutputContent(
      restoredOutput || "Mentett jegyzet vagy PDF összefoglaló.",
      restoredTags.length ? restoredTags : ["PDF", "Jegyzet összefoglaló", "Előzmény"]
    );
    return;
  }

  if (moduleKey === "study") {
    activeChatHistoryId = null;
    switchTab("study");
    setOutputContent(
      restoredOutput || "Mentett tanulási anyag.",
      restoredTags.length ? restoredTags : ["Tanulási jegyzet", "Előzmény"]
    );
    return;
  }

  if (moduleKey === "chat") {
  switchTab("chat");
  activeChatHistoryId = item.id || null;

  if (chatMessages) {
    chatMessages.innerHTML = "";
  }

  if (item.conversation && Array.isArray(item.conversation) && item.conversation.length) {
    item.conversation.forEach((msg) => appendChatBubble(msg.role, msg.text));
  } else {
    appendChatBubble("assistant", restoredOutput || "Mentett AI válasz.");
  }

  if (chatInput) {
    chatInput.value = "";
    autoResizeTextarea(chatInput);
  }

  updateClearChatButtonVisibility();
}
}

function openDeleteConfirm(index = null) {
  pendingHistoryDeleteIndex = Number.isInteger(index) ? index : null;

  const titleEl = confirmOverlay?.querySelector(".confirm-title");
  const textEl = confirmOverlay?.querySelector(".confirm-text");

  if (titleEl) {
    titleEl.textContent =
      pendingHistoryDeleteIndex !== null
        ? "Előzmény törlése"
        : "Előzmények törlése";
  }

  if (textEl) {
    textEl.textContent =
      pendingHistoryDeleteIndex !== null
        ? "Biztosan törölni szeretnéd ezt a mentett előzményt?"
        : "Biztosan törölni szeretnéd az összes mentett előzményt?";
  }

  if (confirmOverlay) {
    confirmOverlay.hidden = false;
  }
}

function closeDeleteConfirm() {
  pendingHistoryDeleteIndex = null;

  if (confirmOverlay) {
    confirmOverlay.hidden = true;
  }
}

async function clearHistory() {
  if (!IS_LOGGED_IN) {
    setHistoryItems([]);
    activeChatHistoryId = null;
    renderHistory();
    closeDeleteConfirm();
    return;
  }

  await clearHistoryOnServer();
  activeChatHistoryId = null;
  renderHistory();
  closeDeleteConfirm();
  showToast("Előzmények törölve", "Minden mentett előzmény törölve lett.");
}

async function deleteHistoryItem(index) {
  const items = getHistoryItems();

  if (!items[index]) return;

  const deletedItem = items[index];

  if (IS_LOGGED_IN && deletedItem.id) {
    await deleteHistoryFromServer(deletedItem.id);
  } else {
    const nextItems = [...items];
    nextItems.splice(index, 1);
    setHistoryItems(nextItems);
  }

  renderHistory();

  if (deletedItem.id === activeChatHistoryId) {
    activeChatHistoryId = null;
    clearChatDisplay();
  }

  showToast("Előzmény törölve", "A kiválasztott elem törölve lett.");
}

/* ================= CHAT ================= */

function openChatDeleteConfirm() {
  if (chatConfirmOverlay) {
    chatConfirmOverlay.hidden = false;
  }
}

function closeChatDeleteConfirm() {
  if (chatConfirmOverlay) {
    chatConfirmOverlay.hidden = true;
  }
}

function updateClearChatButtonVisibility() {
  const clearChatBtn = document.getElementById("clear-chat-btn");
  if (!clearChatBtn || !chatMessages) return;

  const assistantBubbles = Array.from(
    chatMessages.querySelectorAll(".chat-bubble.assistant")
  );

  const hasRealAiReply = assistantBubbles.some((bubble) => {
    const text = (bubble.textContent || "").trim();
    return text && text !== "Szia! Írj ide kérdést vagy tanulási témát.";
  });

  clearChatBtn.style.display = hasRealAiReply ? "inline-flex" : "none";
}

function clearChatDisplay() {
  activeChatHistoryId = null;

  if (!chatMessages) return;

  chatMessages.innerHTML =
    '<div class="chat-bubble assistant">Szia! Írj ide kérdést vagy tanulási témát.</div>';

  if (chatInput) {
    chatInput.value = "";
    autoResizeTextarea(chatInput);
  }

  updateClearChatButtonVisibility();
}

function clearChatImmediately() {
  activeChatHistoryId = null;
  clearChatDisplay();
  closeChatDeleteConfirm();
}

function appendChatBubble(role, text) {
  if (!chatMessages) return;

  const div = document.createElement("div");
  div.className = `chat-bubble ${role}`;
  div.textContent = text;
  chatMessages.appendChild(div);
  chatMessages.scrollTop = chatMessages.scrollHeight;

  updateClearChatButtonVisibility();
}

async function sendChatMessage() {
  const message = chatInput ? chatInput.value.trim() : "";
  if (!message) return;
  if (!useCredits()) return;

  appendChatBubble("user", message);

  if (chatInput) {
    chatInput.value = "";
    autoResizeTextarea(chatInput);
  }

  let historyId = activeChatHistoryId;

  if (!historyId) {
    historyId = `history-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    activeChatHistoryId = historyId;

    await saveHistory({
      id: historyId,
      module: "chat",
      type: "AI Chat",
      title: message.slice(0, 80),
      preview: "Üzenet elküldve...",
      fullOutput: "Üzenet elküldve...",
      conversation: collectChatConversationFromDOM(),
      savedTags: ["AI Chat"],
    });
  } else {
    const items = getHistoryItems();
    const currentItem = items.find((entry) => entry.id === historyId);

    if (currentItem) {
      currentItem.conversation = collectChatConversationFromDOM();
      currentItem.preview = "Üzenet elküldve...";
      currentItem.fullOutput = "Üzenet elküldve...";
      currentItem.savedTags = ["AI Chat"];
setHistoryItems(items);
await saveHistoryToServer(currentItem);
renderHistory();
    }
  }

  try {
    const itemsBeforeRequest = getHistoryItems();
    const currentItemBeforeRequest = itemsBeforeRequest.find((entry) => entry.id === historyId);

    const conversationForApi =
      currentItemBeforeRequest && Array.isArray(currentItemBeforeRequest.conversation)
        ? currentItemBeforeRequest.conversation
        : [];

    const data = await apiCall("chat", {
      message,
      conversation: conversationForApi,
    });

    const aiText = data.text || "Nem érkezett AI válasz.";
    appendChatBubble("assistant", aiText);

    currentCredits = Number(data.new_credits ?? currentCredits);
    updateCreditsUI();

    notifyCompletion("Új AI válasz", "Megérkezett a válasz a chatben.");

    const fullConversation = collectChatConversationFromDOM();
    const items = getHistoryItems();
    const currentItem = items.find((entry) => entry.id === historyId);

    if (currentItem) {
      if (!currentItem.title || currentItem.title === "Üzenet elküldve...") {
        currentItem.title = message.slice(0, 80);
      }

      currentItem.preview = aiText;
      currentItem.fullOutput = aiText;
      currentItem.conversation = fullConversation;
      currentItem.savedTags = ["AI Chat"];
setHistoryItems(items);
await saveHistoryToServer(currentItem);
renderHistory();
    }
  } catch (error) {
    console.error(error);
    appendChatBubble("assistant", "Hiba: " + error.message);

    const fullConversationWithError = collectChatConversationFromDOM();
    const items = getHistoryItems();
    const currentItem = items.find((entry) => entry.id === historyId);

    if (currentItem) {
      currentItem.preview = "Hiba: " + error.message;
      currentItem.fullOutput = "Hiba: " + error.message;
      currentItem.conversation = fullConversationWithError;
      currentItem.savedTags = ["AI Chat"];
setHistoryItems(items);
await saveHistoryToServer(currentItem);
renderHistory();
    }

    showToast("Hiba", error.message || "A chat válasz nem sikerült.");
  }
}

/* ================= TOAST / ÉRTESÍTÉS ================= */

function showToast(title = "Sikeres művelet", text = "A művelet befejeződött.") {
  if (!toastStack) return;

  const toast = document.createElement("div");
  toast.className = "toast";
  toast.innerHTML = `
    <div class="toast-title">${escapeHtml(title)}</div>
    <div class="toast-text">${escapeHtml(text)}</div>
    <div class="toast-progress"></div>
  `;

  toastStack.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transform = "translateY(10px) scale(.98)";
    toast.style.transition = "opacity .22s ease, transform .22s ease";
  }, 4700);

  setTimeout(() => {
    toast.remove();
  }, 5000);
}

function requestNotificationPermissionIfNeeded() {
  if (!("Notification" in window)) return;

  if (Notification.permission === "default") {
    Notification.requestPermission().catch(() => {});
  }
}

function showSystemNotification(title, body) {
  if (!("Notification" in window)) return;
  if (document.hasFocus()) return;
  if (Notification.permission !== "granted") return;

  try {
    new Notification(title, {
      body,
      icon: "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✨</text></svg>",
    });
  } catch (error) {
    console.warn("Rendszerértesítés hiba:", error);
  }
}

function notifyCompletion(title, text) {
  showToast(title, text);
  showSystemNotification(title, text);
}

/* ================= PDF / NOTES ================= */

function getCombinedNotesText() {
  const manualText = document.getElementById("notes-input")?.value.trim() || "";

  const pdfTexts = uploadedPdfDocuments
    .map((doc) => {
      const cleanText = (doc.text || "").trim();
      if (!cleanText) return "";
      return `PDF fájl: ${doc.name}\n${cleanText}`;
    })
    .filter(Boolean)
    .join("\n\n--------------------\n\n");

  if (manualText && pdfTexts) {
    return `${manualText}\n\n====================\n\n${pdfTexts}`.trim();
  }

  if (manualText) return manualText;
  if (pdfTexts) return pdfTexts;

  return "";
}

function renderUploadedPdfList() {
  if (!pdfFileList) return;

  if (!uploadedPdfDocuments.length) {
    pdfFileList.textContent = "";
    return;
  }

  const names = uploadedPdfDocuments.map((doc) => doc.name).join(", ");
  pdfFileList.textContent = `Feltöltve: ${names}`;
}

async function extractPdfTextFromFile(file, pdfjsLib) {
  const arrayBuffer = await file.arrayBuffer();
  const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

  let fullText = "";

  for (let pageNum = 1; pageNum <= pdf.numPages; pageNum += 1) {
    const page = await pdf.getPage(pageNum);
    const textContent = await page.getTextContent();
    const pageText = textContent.items.map((item) => item.str).join(" ");
    fullText += pageText + "\n\n";

    if (fullText.length > 18000) {
      break;
    }
  }

  return fullText.trim();
}

async function runAI(type) {
  try {
    if (output) output.textContent = "AI feldolgozás indul...";

    let payload = {};
    let historyTitle = "";
    let historyType = "";
    let processingText = "Feldolgozás elindítva...";
    let initialTags = [];
    let finalTags = [];

    if (type === "notes") {
      const combinedNotesText = getCombinedNotesText();

      payload = {
        text: combinedNotesText,
        mode: document.getElementById("output-format")?.value || "bullet-point",
      };

      if (!payload.text) {
        if (output) {
          output.textContent = "Adj meg szöveget vagy tölts fel legalább egy PDF-et az összefoglaláshoz.";
        }
        return;
      }

      historyTitle = "Jegyzet AI";
      historyType = "Jegyzet összefoglaló";
      processingText = "Jegyzet összefoglalás készül...";
      initialTags = ["Jegyzet", "Feldolgozás"];
      finalTags = ["PDF", "Jegyzet összefoglaló", "AI feldolgozás"];
    }

    if (type === "study") {
      payload = {
        subject: document.getElementById("study-subject")?.value.trim() || "",
        topic: document.getElementById("study-topic")?.value.trim() || "",
        level: document.getElementById("study-level")?.value || "altalanos-iskola",
      };

      if (!payload.subject || !payload.topic) {
        if (output) {
          output.textContent = "Add meg a tantárgyat és a témát a jegyzet generálásához.";
        }
        return;
      }

      historyTitle = "Házi feladat AI";
      historyType = "Tanulási anyag";
      processingText = "Tanulási anyag készül...";
      initialTags = ["Tanulás", "Feldolgozás"];
      finalTags = ["Tanulási jegyzet", "AI"];
    }

    if (!useCredits()) return;

    const historyId = `history-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

    await saveHistory({
      id: historyId,
      module: type,
      type: historyType,
      title: historyTitle,
      preview: processingText,
      fullOutput: processingText,
      savedTags: initialTags,
      formData: payload,
    });

    const data = await apiCall(type, payload);
    const finalText = data.text || "Nem érkezett AI válasz.";

    currentCredits = Number(data.new_credits ?? currentCredits);
    updateCreditsUI();

    setOutputContent(finalText, finalTags);

    if (type === "notes") {
      notifyCompletion("Jegyzet kész", "A jegyzet összefoglaló elkészült.");
    }

    if (type === "study") {
      notifyCompletion("Tananyag kész", "A tanulási jegyzet elkészült.");
    }

    if (type === "notes" && newsList) {
      newsList.innerHTML = "";
    }

    const items = getHistoryItems();
    const currentItem = items.find((entry) => entry.id === historyId);

    if (currentItem) {
      currentItem.preview = finalText;
      currentItem.fullOutput = finalText;
      currentItem.savedTags = finalTags;
setHistoryItems(items);
await saveHistoryToServer(currentItem);
renderHistory();
    }
  } catch (error) {
    console.error(error);
    if (output) output.textContent = "Hiba: " + error.message;
    showToast("Hiba", error.message || "A feldolgozás nem sikerült.");
  }
}

async function runNewsFlow() {
  try {
    const topic = document.getElementById("news-query")?.value.trim() || "";
    const tone = document.getElementById("summary-type")?.value || "rovid-kivonat";

    if (!topic) {
      if (output) output.textContent = "Adj meg egy keresési témát a hírkereséshez.";
      return;
    }

    if (!useCredits()) return;

    const historyId = `history-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

    await saveHistory({
      id: historyId,
      module: "news",
      type: "Hírösszefoglaló",
      title: topic,
      preview: "Hírösszefoglaló készítése folyamatban...",
      fullOutput: "Hírösszefoglaló készítése folyamatban...",
      savedTags: ["Hírkereső", "Feldolgozás"],
      formData: { topic, tone },
    });

    if (output) output.textContent = "AI összefoglaló készül...";

    if (newsList) {
      newsList.innerHTML = `
        <div class="news-item">
          <h4>${escapeHtml(topic)}</h4>
          <div class="news-meta">AI feldolgozás</div>
          <div>A témára AI összefoglaló készül.</div>
        </div>
      `;
    }

    const aiData = await apiCall("news", { topic, tone });

    const finalText = aiData.text || "Nem érkezett AI válasz.";
    const finalTags = ["Hírkereső", "AI összefoglaló", "Valódi API"];

    currentCredits = Number(aiData.new_credits ?? currentCredits);
    updateCreditsUI();

    setOutputContent(finalText, finalTags);
    notifyCompletion("Hír elkészült", `A(z) "${topic}" témához elkészült az összefoglaló.`);

    const items = getHistoryItems();
    const currentItem = items.find((entry) => entry.id === historyId);

    if (currentItem) {
      currentItem.preview = finalText;
      currentItem.fullOutput = finalText;
      currentItem.savedTags = finalTags;
      setHistoryItems(items);
await saveHistoryToServer(currentItem);
renderHistory();
    }
  } catch (error) {
    console.error(error);
    if (output) output.textContent = "Hiba: " + error.message;
    showToast("Hiba", error.message || "A hírösszefoglaló nem sikerült.");
  }
}

/* ================= TÉMA / TABS ================= */

function applyTheme(theme) {
  const isDark = theme === "dark";
  document.body.classList.toggle("dark-mode", isDark);

  const desktopThemeButton = document.getElementById("theme-toggle");
  const mobileThemeButton = document.getElementById("mobile-theme-toggle");

  if (desktopThemeButton) {
    desktopThemeButton.textContent = isDark ? "Light" : "Dark";
  }

  if (mobileThemeButton) {
    mobileThemeButton.textContent = isDark ? "Light" : "Dark";
  }

  localStorage.setItem(THEME_KEY, isDark ? "dark" : "light");
}

function toggleTheme() {
  applyTheme(document.body.classList.contains("dark-mode") ? "light" : "dark");
}

function switchTab(key) {
  tabs.forEach((tab) => {
    tab.classList.toggle("active", tab.dataset.tab === key);
  });

  document.querySelectorAll(".mobile-tool-switch").forEach((button) => {
    button.classList.toggle("active", button.dataset.mobileTab === key);
  });

  Object.entries(panels).forEach(([panelKey, panel]) => {
    if (!panel) return;
    panel.hidden = panelKey !== key;
  });

  const hideOutput = key === "chat" || key === "history";

  if (panelGrid) {
    panelGrid.classList.toggle("full-width-mode", hideOutput);
  }

  if (outputBox) {
    outputBox.hidden = hideOutput;
    outputBox.style.display = hideOutput ? "none" : "";
    outputBox.setAttribute("aria-hidden", hideOutput ? "true" : "false");
  }

  if (key === "history") {
    renderHistory();
  }
}

function animatePanelSwitch(nextTabKey) {
  if (!panelGrid) {
    switchTab(nextTabKey);
    return;
  }

  panelGrid.classList.remove("is-entering");
  panelGrid.classList.add("is-switching");

  setTimeout(() => {
    switchTab(nextTabKey);

    panelGrid.classList.remove("is-switching");
    void panelGrid.offsetWidth;
    panelGrid.classList.add("is-entering");

    setTimeout(() => {
      panelGrid.classList.remove("is-entering");
    }, 720);
  }, 220);
}

/* ================= HEADER / HERO ================= */

var sparkleParallaxState = {
  initialized: false,
  rafId: 0,
  current: 0,
  target: 0,
  reduceMotionQuery: null,
  layer: null,
};

const SPARKLE_SCROLL_FACTOR = 0.08;
const SPARKLE_LERP = 0.08;
const SPARKLE_SETTLE_EPSILON = 0.05;

function hasSparkleLayer() {
  return !!(
    document.body &&
    document.body.hasAttribute("data-index-page") &&
    document.querySelector(".sparkles-layer")
  );
}

function getSparkleLayer() {
  if (!sparkleParallaxState.layer || !document.body.contains(sparkleParallaxState.layer)) {
    sparkleParallaxState.layer = document.querySelector(".sparkles-layer");
  }

  return sparkleParallaxState.layer;
}

function writeSparkleOffset(value) {
  const sparkleLayer = getSparkleLayer();

  if (sparkleLayer) {
    sparkleLayer.style.setProperty(
      "--sparkle-scroll-offset",
      value + "px"
    );
  }

  document.documentElement.style.setProperty(
    "--sparkle-scroll-offset",
    value + "px"
  );
}

function updateSparkleTarget() {
  if (
    !hasSparkleLayer() ||
    (sparkleParallaxState.reduceMotionQuery &&
      sparkleParallaxState.reduceMotionQuery.matches)
  ) {
    sparkleParallaxState.target = 0;
    return;
  }

  var y = window.scrollY || window.pageYOffset || 0;
  sparkleParallaxState.target = y * SPARKLE_SCROLL_FACTOR;
}

function requestSparkleFrame() {
  if (sparkleParallaxState.rafId) return;

  sparkleParallaxState.rafId = (window.requestAnimationFrame || function (callback) {
    return window.setTimeout(callback, 16);
  })(runSparkleFrame);
}

function runSparkleFrame() {
  var distance;

  sparkleParallaxState.rafId = 0;

  if (
    !hasSparkleLayer() ||
    (sparkleParallaxState.reduceMotionQuery &&
      sparkleParallaxState.reduceMotionQuery.matches)
  ) {
    sparkleParallaxState.current = 0;
    sparkleParallaxState.target = 0;
    writeSparkleOffset(0);
    return;
  }

  sparkleParallaxState.current +=
    (sparkleParallaxState.target - sparkleParallaxState.current) *
    SPARKLE_LERP;

  distance = Math.abs(sparkleParallaxState.target - sparkleParallaxState.current);

  if (distance < SPARKLE_SETTLE_EPSILON) {
    sparkleParallaxState.current = sparkleParallaxState.target;
  }

  writeSparkleOffset(sparkleParallaxState.current);

  if (Math.abs(sparkleParallaxState.target - sparkleParallaxState.current) >= SPARKLE_SETTLE_EPSILON) {
    requestSparkleFrame();
  }
}

function updateSparkleOffset() {
  updateSparkleTarget();
  sparkleParallaxState.current = sparkleParallaxState.target;
  writeSparkleOffset(sparkleParallaxState.current);
}

function queueSparkleOffsetUpdate() {
  updateSparkleTarget();
  requestSparkleFrame();
}

function initSparkleParallax() {
  if (sparkleParallaxState.initialized) return;
  sparkleParallaxState.initialized = true;

  sparkleParallaxState.reduceMotionQuery =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)");

  if (
    sparkleParallaxState.reduceMotionQuery &&
    typeof sparkleParallaxState.reduceMotionQuery.addEventListener === "function"
  ) {
    sparkleParallaxState.reduceMotionQuery.addEventListener(
      "change",
      updateSparkleOffset
    );
  }

  updateSparkleOffset();
}

function updatePremiumHeaderState() {
  const header = document.querySelector("[data-site-header]");
  const interfaceSection = document.getElementById("app");
  if (!header || !interfaceSection) return;
  const COMPACT_ENTER_LINE = 132;
  const COMPACT_EXIT_LINE = 196;
  const COMPACT_MORPH_DURATION = 660;

  /* ne legyen korai mini-váltás */
  header.classList.remove("header-scrolled");
  header.classList.remove("is-scrolled");

  const wasCompact = header.classList.contains("is-compact");

  /* stabilabb trigger: hiszterezissel, hogy ne vibraljon a hataron */
  const rect = interfaceSection.getBoundingClientRect();

  const shouldCompact = wasCompact
    ? rect.top <= COMPACT_EXIT_LINE
    : rect.top <= COMPACT_ENTER_LINE;

  if (shouldCompact !== wasCompact) {
    header.classList.toggle("is-compact", shouldCompact);
  }

  if (shouldCompact && !wasCompact) {
    header.classList.remove("compact-morphing");
    void header.offsetWidth;
    header.classList.add("compact-morphing");

    window.setTimeout(() => {
      header.classList.remove("compact-morphing");
    }, COMPACT_MORPH_DURATION);
  }

  if (!shouldCompact && wasCompact) {
    header.classList.remove("compact-morphing");
  }
}

function updateActiveNavLink() {
  const sections = document.querySelectorAll("section[id]");
  const navLinks = document.querySelectorAll(".modern-nav-link");

  if (!sections.length || !navLinks.length) return;

  let current = "";

  sections.forEach((section) => {
    const sectionTop = section.offsetTop - 160;
    if (window.scrollY >= sectionTop) {
      current = section.getAttribute("id") || "";
    }
  });

  navLinks.forEach((link) => {
    const href = link.getAttribute("href") || "";
    const isActive = current && href === `#${current}`;

    link.classList.toggle("active", isActive);
    link.classList.toggle("is-current", isActive);
  });
}

function updateHeaderPointerGlow(event) {
  const header = document.querySelector("[data-site-header]");
  if (!header || !event) return;

  const rect = header.getBoundingClientRect();
  const x = ((event.clientX - rect.left) / rect.width) * 100;
  const y = ((event.clientY - rect.top) / rect.height) * 100;

  header.style.setProperty("--header-glow-x", `${x}%`);
  header.style.setProperty("--header-glow-y", `${y}%`);
}

function initUltraPremiumHeader() {
  const header = document.querySelector("[data-site-header]");
  const rotatingWord = document.getElementById("header-ai-rotating");

  if (header) {
    header.addEventListener("mousemove", updateHeaderPointerGlow);

    header.addEventListener("mouseleave", () => {
      header.style.setProperty("--header-glow-x", "50%");
      header.style.setProperty("--header-glow-y", "50%");
    });
  }

  if (rotatingWord) {
    const words = ["összefoglaló", "jegyzet", "tanulás", "chat", "workflow"];
    let wordIndex = 0;

    setInterval(() => {
      wordIndex = (wordIndex + 1) % words.length;
      rotatingWord.classList.remove("is-changing");
      void rotatingWord.offsetWidth;
      rotatingWord.textContent = words[wordIndex];
      rotatingWord.classList.add("is-changing");
    }, 2400);
  }
}

function initIndexCinematicIntro() {
  var INTRO_SEEN_KEY = "astralus:intro:seen";
  var introTimers = [];
  var hasBooted = false;

  function requestFrame(callback) {
    var raf = window.requestAnimationFrame || function (frameCallback) {
      return window.setTimeout(frameCallback, 16);
    };

    return raf(callback);
  }

  function doubleRequestAnimationFrame(callback) {
    requestFrame(function () {
      requestFrame(callback);
    });
  }

  function parseCssTimeValue(value) {
    var trimmed = (value || "").trim();

    if (!trimmed) return 0;
    if (trimmed.slice(-2) === "ms") return parseFloat(trimmed) / 1000;
    if (trimmed.slice(-1) === "s") return parseFloat(trimmed);

    var parsed = parseFloat(trimmed);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function prefersReducedMotion() {
    return (
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches
    );
  }

  function hasSeenIntro() {
    try {
      return window.sessionStorage.getItem(INTRO_SEEN_KEY) === "true";
    } catch (error) {
      return false;
    }
  }

  function markIntroSeen() {
    try {
      window.sessionStorage.setItem(INTRO_SEEN_KEY, "true");
    } catch (error) {
      /* sessionStorage can be unavailable in privacy-restricted contexts */
    }
  }

  function clearIntroTimers() {
    while (introTimers.length) {
      window.clearTimeout(introTimers.pop());
    }
  }

  function scheduleIntroStep(callback, delay) {
    var timer = window.setTimeout(callback, delay);
    introTimers.push(timer);
    return timer;
  }

  function getIntroNodes() {
    var body = document.body;

    if (!body || !body.hasAttribute("data-index-page")) return null;

    var hero = document.querySelector("[data-astralus-hero]");
    var introRoot = document.querySelector("[data-intro-root]");
    var header = document.querySelector("[data-site-header]");

    if (!hero || !introRoot || !header) return null;

    return {
      body: body,
      hero: hero,
      introRoot: introRoot,
      header: header,
      eyebrow: hero.querySelector("[data-hero-eyebrow]"),
      title: hero.querySelector("[data-hero-title]"),
      copy: hero.querySelector("[data-hero-copy]"),
      actions: hero.querySelector("[data-hero-actions]"),
      media: hero.querySelector("[data-hero-media]"),
    };
  }

  function resetIntroState(body) {
    if (!body) return;

    body.classList.remove(
      "intro-ready",
      "intro-run",
      "intro-short",
      "intro-phase-handoff",
      "intro-phase-title",
      "intro-complete",
      "intro-reduced"
    );
  }

  function readTimeline(body) {
    var styles = window.getComputedStyle(body);
    var overlayIn = parseCssTimeValue(
      styles.getPropertyValue("--intro-overlay-in")
    );
    var emblemFocus = parseCssTimeValue(
      styles.getPropertyValue("--intro-emblem-focus")
    );
    var handoff = parseCssTimeValue(styles.getPropertyValue("--intro-handoff"));
    var titleDelay = parseCssTimeValue(
      styles.getPropertyValue("--intro-title-delay")
    );
    var titleDuration = parseCssTimeValue(
      styles.getPropertyValue("--intro-title-duration")
    );
    var heroTitleLineDuration = parseCssTimeValue(
      styles.getPropertyValue("--hero-title-line-duration")
    );
    var heroTitleLineStagger = parseCssTimeValue(
      styles.getPropertyValue("--hero-title-line-stagger")
    );
    var heroCopyDelay = parseCssTimeValue(
      styles.getPropertyValue("--hero-copy-delay")
    );
    var heroActionsDelay = parseCssTimeValue(
      styles.getPropertyValue("--hero-actions-delay")
    );
    var copyDuration = parseCssTimeValue(
      styles.getPropertyValue("--intro-copy-duration")
    );
    var totalDuration = parseCssTimeValue(
      styles.getPropertyValue("--intro-total-duration")
    );

    if (!titleDuration) {
      titleDuration =
        heroTitleLineDuration + heroTitleLineStagger + heroTitleLineStagger;
    }

    if (!totalDuration) {
      totalDuration =
        overlayIn +
        emblemFocus +
        handoff +
        titleDelay +
        Math.max(
          titleDuration,
          heroCopyDelay + copyDuration,
          heroActionsDelay + copyDuration
        );
    }

    totalDuration = Math.max(
      totalDuration,
      overlayIn +
        emblemFocus +
        handoff +
        titleDelay +
        Math.max(
          titleDuration,
          heroCopyDelay + copyDuration,
          heroActionsDelay + copyDuration
        )
    );

    return {
      handoffStart: Math.round((overlayIn + emblemFocus) * 1000),
      titleStart: Math.round((overlayIn + emblemFocus + handoff + titleDelay) * 1000),
      completeAt: Math.round(totalDuration * 1000),
    };
  }

  function forceIntroComplete(nodes, options) {
    if (!nodes || !nodes.body) return;

    clearIntroTimers();
    resetIntroState(nodes.body);
    nodes.body.classList.add("loaded");

    if (options && options.reduced) {
      nodes.body.classList.add("intro-reduced");
    }

    nodes.body.classList.add("intro-complete");
    markIntroSeen();

    try {
      window.dispatchEvent(
        new CustomEvent("astralus:intro-complete", {
          detail: { reduced: !!(options && options.reduced) }
        })
      );
    } catch (error) {
      window.dispatchEvent(new Event("astralus:intro-complete"));
    }
  }

  function runIntro(nodes) {
    var body = nodes.body;
    var shortIntro = hasSeenIntro();
    var timeline;

    resetIntroState(body);
    body.classList.add("loaded", "intro-ready");

    if (shortIntro) {
      body.classList.add("intro-short");
    }

    if (prefersReducedMotion()) {
      body.classList.add("intro-reduced");
      timeline = readTimeline(body);

      doubleRequestAnimationFrame(function () {
        body.classList.add("intro-run");
      });

      scheduleIntroStep(function () {
        body.classList.add("intro-phase-title");
      }, timeline.titleStart);

      scheduleIntroStep(function () {
        forceIntroComplete(nodes, { reduced: true });
      }, timeline.completeAt);

      return;
    }

    timeline = readTimeline(body);

    doubleRequestAnimationFrame(function () {
      body.classList.add("intro-run");
    });

    scheduleIntroStep(function () {
      body.classList.add("intro-phase-handoff");
    }, timeline.handoffStart);

    scheduleIntroStep(function () {
      body.classList.add("intro-phase-title");
    }, timeline.titleStart);

    scheduleIntroStep(function () {
      forceIntroComplete(nodes);
    }, timeline.completeAt);
  }

  function bootIntro() {
    var nodes;
    var reduceMotionQuery;

    if (hasBooted) return;
    hasBooted = true;

    if (document.body) {
      document.body.classList.add("loaded");
    }

    nodes = getIntroNodes();

    if (!nodes) return;

    reduceMotionQuery = window.matchMedia
      ? window.matchMedia("(prefers-reduced-motion: reduce)")
      : null;

    if (reduceMotionQuery && typeof reduceMotionQuery.addEventListener === "function") {
      reduceMotionQuery.addEventListener("change", function (event) {
        if (event.matches) {
          forceIntroComplete(nodes, { reduced: true });
        }
      });
    }

    runIntro(nodes);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootIntro, { once: true });
    window.addEventListener("load", bootIntro, { once: true });
  } else {
    bootIntro();
  }
}

initIndexCinematicIntro();
initSparkleParallax();

/* ================= SMOOTH WHEEL SCROLL ================= */

const SMOOTH_WHEEL_SPEED = 0.95;
const SMOOTH_WHEEL_SMOOTHNESS = 0.14;
const SMOOTH_WHEEL_SETTLE_EPSILON = 0.5;
const SMOOTH_WHEEL_FRAME_MS = 1000 / 60;
const SMOOTH_WHEEL_MAX_FRAME_MULTIPLIER = 4;
const SMOOTH_WHEEL_DELTA_LINE = 1;
const SMOOTH_WHEEL_DELTA_PAGE = 2;
const SMOOTH_WHEEL_NATIVE_SELECTOR = [
  "input",
  "textarea",
  "select",
  '[contenteditable="true"]',
  ".chat-messages",
  ".confirm-overlay",
  ".confirm-modal",
  ".pricing-overlay",
  ".pricing-card",
  "[data-native-scroll]"
].join(",");

function initSmoothWheelScroll() {
  var reduceMotionQuery = window.matchMedia
    ? window.matchMedia("(prefers-reduced-motion: reduce)")
    : null;
  var finePointerQuery = window.matchMedia
    ? window.matchMedia("(hover: hover) and (pointer: fine)")
    : null;
  var hasTouch =
    "ontouchstart" in window ||
    (navigator.maxTouchPoints && navigator.maxTouchPoints > 0) ||
    (navigator.msMaxTouchPoints && navigator.msMaxTouchPoints > 0);
  var currentY = getWindowScrollY();
  var targetY = currentY;
  var animationFrameId = 0;
  var isAnimating = false;
  var lastFrameTime = 0;

  function canRunSmoothWheel() {
    return !!(
      !hasTouch &&
      (!finePointerQuery || finePointerQuery.matches) &&
      (!reduceMotionQuery || !reduceMotionQuery.matches)
    );
  }

  if (!canRunSmoothWheel()) return;

  function getMaxScrollY() {
    var doc = document.documentElement;
    var body = document.body;
    var scrollHeight = Math.max(
      doc ? doc.scrollHeight : 0,
      body ? body.scrollHeight : 0,
      doc ? doc.offsetHeight : 0,
      body ? body.offsetHeight : 0
    );

    return Math.max(0, scrollHeight - window.innerHeight);
  }

  function getWindowScrollY() {
    return window.scrollY || window.pageYOffset || 0;
  }

  function clampScrollY(value) {
    return Math.min(getMaxScrollY(), Math.max(0, value));
  }

  function syncSmoothWheelPosition() {
    currentY = clampScrollY(getWindowScrollY());
    targetY = currentY;
  }

  function normalizeWheelDelta(event) {
    if (event.deltaMode === SMOOTH_WHEEL_DELTA_LINE) {
      return event.deltaY * 18;
    }

    if (event.deltaMode === SMOOTH_WHEEL_DELTA_PAGE) {
      return event.deltaY * window.innerHeight;
    }

    return event.deltaY;
  }

  function isElementScrollable(element) {
    var style;
    var overflowY;

    if (!element || element === document.body || element === document.documentElement) {
      return false;
    }

    style = window.getComputedStyle(element);
    overflowY = style ? style.overflowY : "";

    return !!(
      (overflowY === "auto" || overflowY === "scroll") &&
      element.scrollHeight > element.clientHeight + 1
    );
  }

  function shouldUseNativeScroll(target) {
    var element =
      target && target.nodeType === Node.ELEMENT_NODE
        ? target
        : target && target.parentElement;

    if (!element) return false;

    if (element.closest(SMOOTH_WHEEL_NATIVE_SELECTOR)) {
      return true;
    }

    while (element && element !== document.body && element !== document.documentElement) {
      if (isElementScrollable(element)) {
        return true;
      }

      element = element.parentElement;
    }

    return false;
  }

  function setSmoothWheelActive(isActive) {
    document.documentElement.classList.toggle("smooth-wheel-active", isActive);
  }

  function stopSmoothWheelAnimation(syncPosition) {
    if (animationFrameId) {
      cancelAnimationFrame(animationFrameId);
      animationFrameId = 0;
    }

    isAnimating = false;
    lastFrameTime = 0;
    setSmoothWheelActive(false);

    if (syncPosition) {
      syncSmoothWheelPosition();
    }
  }

  function getFrameAdjustedSmoothness(timestamp) {
    var frameMultiplier;

    if (!lastFrameTime) {
      lastFrameTime = timestamp;
      return SMOOTH_WHEEL_SMOOTHNESS;
    }

    frameMultiplier = Math.min(
      SMOOTH_WHEEL_MAX_FRAME_MULTIPLIER,
      Math.max(1, (timestamp - lastFrameTime) / SMOOTH_WHEEL_FRAME_MS)
    );
    lastFrameTime = timestamp;

    return 1 - Math.pow(1 - SMOOTH_WHEEL_SMOOTHNESS, frameMultiplier);
  }

  function animateSmoothWheelScroll(timestamp) {
    var distance;
    var smoothness;

    animationFrameId = 0;
    timestamp = typeof timestamp === "number" ? timestamp : performance.now();

    if (!canRunSmoothWheel()) {
      stopSmoothWheelAnimation(true);
      return;
    }

    targetY = clampScrollY(targetY);
    smoothness = getFrameAdjustedSmoothness(timestamp);
    currentY += (targetY - currentY) * smoothness;
    distance = Math.abs(targetY - currentY);

    if (distance < SMOOTH_WHEEL_SETTLE_EPSILON) {
      currentY = targetY;
    }

    window.scrollTo(0, currentY);

    if (Math.abs(targetY - currentY) >= SMOOTH_WHEEL_SETTLE_EPSILON) {
      animationFrameId = requestAnimationFrame(animateSmoothWheelScroll);
      return;
    }

    stopSmoothWheelAnimation(false);
    syncSmoothWheelPosition();
  }

  function startSmoothWheelAnimation() {
    if (animationFrameId) return;

    isAnimating = true;
    lastFrameTime = 0;
    setSmoothWheelActive(true);
    animationFrameId = requestAnimationFrame(animateSmoothWheelScroll);
  }

  function handleWheel(event) {
    if (!canRunSmoothWheel() || event.ctrlKey || shouldUseNativeScroll(event.target)) {
      if (isAnimating) {
        stopSmoothWheelAnimation(true);
      }
      return;
    }

    event.preventDefault();

    if (!isAnimating) {
      syncSmoothWheelPosition();
    }

    targetY = clampScrollY(targetY + normalizeWheelDelta(event) * SMOOTH_WHEEL_SPEED);
    startSmoothWheelAnimation();
  }

  function handleNativeScroll() {
    var scrollY = getWindowScrollY();

    if (!isAnimating) {
      currentY = clampScrollY(scrollY);
      targetY = currentY;
      return;
    }

    if (Math.abs(scrollY - currentY) > 2) {
      stopSmoothWheelAnimation(true);
    }
  }

  function handleUserIntervention() {
    if (isAnimating) {
      stopSmoothWheelAnimation(true);
      return;
    }

    syncSmoothWheelPosition();
  }

  window.addEventListener("wheel", handleWheel, { passive: false });
  window.addEventListener("scroll", handleNativeScroll, { passive: true });
  window.addEventListener("resize", handleUserIntervention, { passive: true });
  window.addEventListener("blur", handleUserIntervention, { passive: true });
  document.addEventListener("keydown", handleUserIntervention, { passive: true });
  document.addEventListener("mousedown", handleUserIntervention, { passive: true });
  document.addEventListener("pointerdown", handleUserIntervention, { passive: true });

  if (
    reduceMotionQuery &&
    typeof reduceMotionQuery.addEventListener === "function"
  ) {
    reduceMotionQuery.addEventListener("change", function (event) {
      if (event.matches) {
        stopSmoothWheelAnimation(true);
      }
    });
  }
}

/* ================= HERO TITLE WIDTH NORMALIZER V2 ================= */

var HERO_TITLE_WIDTH_DEFAULTS = {
  maxScale: 1,
  minScale: 0.72,
  resizeDebounceMs: 80,
  followupDelays: [60, 180, 520],
  sidePadding: function () {
    if (window.innerWidth <= 520) return 10;
    if (window.innerWidth <= 900) return 14;
    return 20;
  }
};

var heroTitleWidthNormalizerState = {
  initialized: false,
  resizeRaf: 0,
  resizeTimer: 0,
  timers: [],
  resizeObserver: null,
  titleMutationObserver: null,
  bodyMutationObserver: null,
  config: null,
  title: null
};

function getHeroTitleWidthConfig(options) {
  var config = Object.assign({}, HERO_TITLE_WIDTH_DEFAULTS, options || {});
  var maxScale = Number(config.maxScale);
  var minScale = Number(config.minScale);
  var resizeDebounceMs = Number(config.resizeDebounceMs);

  config.maxScale =
    Number.isFinite(maxScale) && maxScale > 0
      ? Math.min(maxScale, 1)
      : HERO_TITLE_WIDTH_DEFAULTS.maxScale;

  config.minScale =
    Number.isFinite(minScale) && minScale > 0 && minScale <= 1
      ? minScale
      : HERO_TITLE_WIDTH_DEFAULTS.minScale;

  config.resizeDebounceMs =
    Number.isFinite(resizeDebounceMs) && resizeDebounceMs >= 0
      ? resizeDebounceMs
      : HERO_TITLE_WIDTH_DEFAULTS.resizeDebounceMs;

  config.followupDelays = Array.isArray(config.followupDelays)
    ? config.followupDelays
        .map(function (delay) {
          return Number(delay);
        })
        .filter(function (delay) {
          return Number.isFinite(delay) && delay >= 0;
        })
    : HERO_TITLE_WIDTH_DEFAULTS.followupDelays.slice();

  return config;
}

function isHeroTitleV2Enabled() {
  return !!(
    document.body &&
    document.body.hasAttribute("data-index-page") &&
    document.body.getAttribute("data-hero-title-v2") === "on"
  );
}

function findHeroTitleElement() {
  return document.querySelector("[data-hero-title].cinematic-title");
}

function getHeroTitleElement() {
  if (!isHeroTitleV2Enabled()) return null;
  return findHeroTitleElement();
}

function getHeroTitleLineEntries(title) {
  var lines;

  if (!title) return [];

  lines = Array.prototype.slice.call(title.querySelectorAll(".hero-title-line"));

  return lines
    .map(function (line) {
      var inner =
        line.querySelector(".hero-title-line-inner") ||
        line.querySelector(".hero-title-inner");

      if (!inner) return null;

      return {
        line: line,
        inner: inner
      };
    })
    .filter(Boolean);
}

function resolveHeroTitleSidePadding(config, title) {
  var rawValue =
    typeof config.sidePadding === "function"
      ? config.sidePadding(title)
      : config.sidePadding;
  var numericValue = Number(rawValue);

  if (!Number.isFinite(numericValue) || numericValue < 0) {
    return 24;
  }

  return numericValue;
}

function resetHeroTitleLineScale(entry) {
  if (!entry || !entry.inner) return;

  entry.inner.style.setProperty("--hero-title-auto-fit-x", "1");
  entry.inner.style.setProperty("--hero-title-auto-shift-x", "0px");
  entry.line.style.removeProperty("--hero-title-natural-width");
}

function resetHeroTitleWidthState(title) {
  var entries = getHeroTitleLineEntries(title);

  entries.forEach(resetHeroTitleLineScale);

  if (!title) return;

  title.classList.remove(
    "hero-title-width-normalized",
    "hero-title-scale-danger",
    "is-measuring-hero-title"
  );
  title.style.removeProperty("--hero-title-reference-width");
  title.style.removeProperty("--hero-title-target-width");
  title.style.removeProperty("--hero-title-safe-inline");
  title.style.removeProperty("--hero-title-fit-max-scale");
  title.style.removeProperty("--hero-title-fit-min-scale");
}

function withHeroTitleMeasureMode(title, callback) {
  if (!title || typeof callback !== "function") return null;

  title.classList.add("is-measuring-hero-title");

  try {
    return callback();
  } finally {
    title.classList.remove("is-measuring-hero-title");
  }
}

function readHeroTitleNaturalWidths(title, entries) {
  return (
    withHeroTitleMeasureMode(title, function () {
      return entries.map(function (entry) {
        var rectWidth;
        var scrollWidth;
        var naturalWidth;

        resetHeroTitleLineScale(entry);

        rectWidth = entry.inner.getBoundingClientRect().width || 0;
        scrollWidth = entry.inner.scrollWidth || 0;
        naturalWidth = Math.max(rectWidth, scrollWidth);

        return {
          entry: entry,
          naturalWidth: naturalWidth
        };
      });
    }) || []
  );
}

function getHeroTitleReferenceMeasurement(measurements) {
  var longestMeasurement = null;

  measurements.forEach(function (measurement) {
    if (!measurement || !measurement.naturalWidth) return;

    if (
      !longestMeasurement ||
      measurement.naturalWidth > longestMeasurement.naturalWidth
    ) {
      longestMeasurement = measurement;
    }
  });

  return longestMeasurement;
}

function applyHeroTitleScale(entry, naturalWidth, targetWidth, config, title) {
  var scale = 1;

  if (!entry || !entry.inner || !naturalWidth || !targetWidth) {
    resetHeroTitleLineScale(entry);
    return;
  }

  if (naturalWidth > targetWidth) {
    scale = targetWidth / naturalWidth;
  } else {
    scale = 1;
  }

  if (!Number.isFinite(scale) || scale <= 0) {
    scale = 1;
  }

  scale = Math.min(scale, config.maxScale, 1);

  if (scale < config.minScale && title) {
    title.classList.add("hero-title-scale-danger");
  }

  entry.inner.style.setProperty("--hero-title-auto-fit-x", scale.toFixed(5));
  entry.inner.style.setProperty("--hero-title-auto-shift-x", "0px");

  entry.line.style.setProperty(
    "--hero-title-natural-width",
    naturalWidth.toFixed(2) + "px"
  );
}

function normalizeHeroTitleWidth(options) {
  var config = getHeroTitleWidthConfig(
    options || heroTitleWidthNormalizerState.config || HERO_TITLE_WIDTH_DEFAULTS
  );
  var title = getHeroTitleElement();
  var entries;
  var measurements;
  var referenceMeasurement;
  var sidePadding;
  var titleWidth;
  var availableWidth;
  var targetWidth;

  if (!title) {
    resetHeroTitleWidthState(heroTitleWidthNormalizerState.title || findHeroTitleElement());
    return;
  }

  heroTitleWidthNormalizerState.title = title;
  entries = getHeroTitleLineEntries(title);

  if (!entries.length) return;

  title.classList.remove("hero-title-scale-danger");
  sidePadding = resolveHeroTitleSidePadding(config, title);

  title.style.setProperty("--hero-title-fit-max-scale", String(config.maxScale));
  title.style.setProperty("--hero-title-fit-min-scale", String(config.minScale));
  title.style.setProperty("--hero-title-safe-inline", sidePadding + "px");

  measurements = readHeroTitleNaturalWidths(title, entries).filter(function (measurement) {
    return measurement && measurement.naturalWidth > 0;
  });

  if (!measurements.length) return;

  referenceMeasurement = getHeroTitleReferenceMeasurement(measurements);
  if (!referenceMeasurement || !referenceMeasurement.naturalWidth) return;

  titleWidth = title.getBoundingClientRect().width || title.clientWidth || 0;
  availableWidth = Math.max(0, titleWidth - sidePadding * 2);
  targetWidth = Math.min(
    referenceMeasurement.naturalWidth,
    availableWidth || referenceMeasurement.naturalWidth
  );

  if (!targetWidth || targetWidth <= 0) {
    resetHeroTitleWidthState(title);
    return;
  }

  measurements.forEach(function (measurement) {
    applyHeroTitleScale(
      measurement.entry,
      measurement.naturalWidth,
      targetWidth,
      config,
      title
    );
  });

  title.style.setProperty(
    "--hero-title-reference-width",
    referenceMeasurement.naturalWidth.toFixed(2) + "px"
  );
  title.style.setProperty("--hero-title-target-width", targetWidth.toFixed(2) + "px");
  title.classList.add("hero-title-width-normalized");
}

function runHeroTitleWidthNormalization() {
  if (heroTitleWidthNormalizerState.resizeRaf) {
    window.cancelAnimationFrame(heroTitleWidthNormalizerState.resizeRaf);
  }

  heroTitleWidthNormalizerState.resizeRaf = window.requestAnimationFrame(function () {
    heroTitleWidthNormalizerState.resizeRaf = 0;
    normalizeHeroTitleWidth(heroTitleWidthNormalizerState.config);
  });
}

function requestHeroTitleWidthNormalization() {
  var config =
    heroTitleWidthNormalizerState.config ||
    getHeroTitleWidthConfig(HERO_TITLE_WIDTH_DEFAULTS);

  if (heroTitleWidthNormalizerState.resizeTimer) {
    window.clearTimeout(heroTitleWidthNormalizerState.resizeTimer);
  }

  heroTitleWidthNormalizerState.resizeTimer = window.setTimeout(function () {
    heroTitleWidthNormalizerState.resizeTimer = 0;
    runHeroTitleWidthNormalization();
  }, config.resizeDebounceMs);
}

function scheduleHeroTitleWidthNormalization(delay) {
  var timer = window.setTimeout(function () {
    runHeroTitleWidthNormalization();
  }, delay);

  heroTitleWidthNormalizerState.timers.push(timer);
  return timer;
}

function scheduleHeroTitleWidthFollowups(config) {
  config.followupDelays.forEach(scheduleHeroTitleWidthNormalization);
}

function initHeroTitleWidthNormalization(options) {
  var title;
  var state = heroTitleWidthNormalizerState;

  state.config = getHeroTitleWidthConfig(options);
  title = findHeroTitleElement();
  state.title = title;

  if (state.initialized) {
    requestHeroTitleWidthNormalization();
    scheduleHeroTitleWidthFollowups(state.config);
    return;
  }

  state.initialized = true;

  window.addEventListener("resize", requestHeroTitleWidthNormalization, { passive: true });
  window.addEventListener("orientationchange", requestHeroTitleWidthNormalization, { passive: true });
  window.addEventListener("astralus:intro-complete", requestHeroTitleWidthNormalization);

  window.addEventListener("load", function () {
    requestHeroTitleWidthNormalization();
    scheduleHeroTitleWidthFollowups(state.config);
  });

  if (window.visualViewport) {
    window.visualViewport.addEventListener("resize", requestHeroTitleWidthNormalization, {
      passive: true
    });
  }

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready
      .then(function () {
        requestHeroTitleWidthNormalization();
        scheduleHeroTitleWidthFollowups(state.config);
      })
      .catch(requestHeroTitleWidthNormalization);
  }

  if (title && "ResizeObserver" in window) {
    state.resizeObserver = new ResizeObserver(requestHeroTitleWidthNormalization);
    state.resizeObserver.observe(title);

    if (title.parentElement) {
      state.resizeObserver.observe(title.parentElement);
    }
  }

  if ("MutationObserver" in window) {
    if (title) {
      state.titleMutationObserver = new MutationObserver(
        requestHeroTitleWidthNormalization
      );
      state.titleMutationObserver.observe(title, {
        childList: true,
        subtree: true,
        characterData: true
      });
    }

    if (document.body) {
      state.bodyMutationObserver = new MutationObserver(function () {
        state.title = findHeroTitleElement();
        requestHeroTitleWidthNormalization();
      });
      state.bodyMutationObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ["class", "data-hero-title-v2", "data-hero-weight"]
      });
    }
  }

  runHeroTitleWidthNormalization();
  scheduleHeroTitleWidthFollowups(state.config);
}

window.HERO_TITLE_WIDTH_DEFAULTS = HERO_TITLE_WIDTH_DEFAULTS;
window.normalizeHeroTitleWidth = normalizeHeroTitleWidth;
window.initHeroTitleWidthNormalization = initHeroTitleWidthNormalization;

// TEMP FIX: kikapcsolva, mert a hero-title sorokat torzítja / rosszul méri.
// initHeroTitleWidthNormalization(HERO_TITLE_WIDTH_DEFAULTS);

/* ================= DOM READY ================= */

document.addEventListener("DOMContentLoaded", function () {
  document.body.classList.add("loaded");
  requestNotificationPermissionIfNeeded();
  initSmoothWheelScroll();

  function updateScrollProgressBar() {
    const progressBar = document.getElementById("scroll-progress-bar");
    if (!progressBar) return;

    const scrollTop =
      window.pageYOffset ||
      document.documentElement.scrollTop ||
      document.body.scrollTop ||
      0;

    const doc = document.documentElement;
    const scrollHeight = doc.scrollHeight - window.innerHeight;

    if (scrollHeight <= 0) {
      progressBar.style.width = "0%";
      return;
    }

    const progress = Math.min(100, Math.max(0, (scrollTop / scrollHeight) * 100));
    progressBar.style.width = `${progress}%`;
  }

  document.querySelectorAll(".textarea").forEach((el) => {
    const resize = () => autoResizeTextarea(el);
    el.addEventListener("input", resize);
    el.addEventListener("change", resize);
    el.addEventListener("paste", () => setTimeout(resize, 0));
    el.addEventListener("cut", () => setTimeout(resize, 0));
    el.addEventListener("keyup", resize);
    window.addEventListener("resize", resize);
    resize();
  });

updateCreditsUI();
renderHistory();
applyTheme(localStorage.getItem(THEME_KEY) || "light");
updateClearChatButtonVisibility();
  updateSparkleOffset();
  updateScrollProgressBar();
  updatePremiumHeaderState();
  updateActiveNavLink();
  initUltraPremiumHeader();

  const initialActiveTab =
    document.querySelector(".tool-tab.active")?.dataset.tab ||
    document.querySelector(".mobile-tool-switch.active")?.dataset.mobileTab ||
    "news";

  switchTab(initialActiveTab);

  const runNewsBtn = document.getElementById("run-news-btn");
  const runNotesBtn = document.getElementById("run-notes-btn");
  const runStudyBtn = document.getElementById("run-study-btn");
  const sendChatBtn = document.getElementById("send-chat-btn");
  const clearChatBtn = document.getElementById("clear-chat-btn");

  if (runNewsBtn) runNewsBtn.addEventListener("click", runNewsFlow);
  if (runNotesBtn) runNotesBtn.addEventListener("click", () => runAI("notes"));
  if (runStudyBtn) runStudyBtn.addEventListener("click", () => runAI("study"));
  if (sendChatBtn) sendChatBtn.addEventListener("click", sendChatMessage);
  if (clearChatBtn) clearChatBtn.addEventListener("click", openChatDeleteConfirm);

  tabs.forEach((tab) => {
    tab.addEventListener("click", function () {
      const nextTabKey = this.dataset.tab;
      const isAlreadyActive = this.classList.contains("active");
      if (isAlreadyActive) return;
      animatePanelSwitch(nextTabKey);
    });
  });

  const mobileDrawerViews = document.getElementById("mobile-drawer-views");
  const mobileToolsEntryBtn = document.getElementById("mobile-tools-entry-btn");
  const mobileToolsBackBtn = document.getElementById("mobile-tools-back-btn");
  const mobileDrawerMainView = document.getElementById("mobile-drawer-view-main");
  const mobileDrawerToolsView = document.getElementById("mobile-drawer-view-tools");

  function openMobileToolsView() {
    if (!mobileDrawerViews || !mobileDrawerMainView || !mobileDrawerToolsView) return;
    mobileDrawerViews.classList.add("is-tools-view");
    mobileDrawerMainView.classList.remove("is-active");
    mobileDrawerToolsView.classList.add("is-active");
  }

  function closeMobileToolsView() {
    if (!mobileDrawerViews || !mobileDrawerMainView || !mobileDrawerToolsView) return;
    mobileDrawerViews.classList.remove("is-tools-view");
    mobileDrawerToolsView.classList.remove("is-active");
    mobileDrawerMainView.classList.add("is-active");
  }

  if (mobileToolsEntryBtn) {
    mobileToolsEntryBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      openMobileToolsView();
    });
  }

  if (mobileToolsBackBtn) {
    mobileToolsBackBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      closeMobileToolsView();
    });
  }

  document.querySelectorAll(".mobile-tool-switch").forEach((button) => {
    button.addEventListener("click", function () {
      const nextTabKey = this.dataset.mobileTab;
      if (!nextTabKey) return;

      const matchingDesktopTab = document.querySelector(`.tool-tab[data-tab="${nextTabKey}"]`);
      const isAlreadyActive =
        this.classList.contains("active") ||
        (matchingDesktopTab && matchingDesktopTab.classList.contains("active"));

      if (!isAlreadyActive) {
        animatePanelSwitch(nextTabKey);
      }

      const drawer = document.getElementById("mobile-drawer");
      const overlay = document.getElementById("mobile-drawer-overlay");

      document.body.classList.remove("mobile-menu-open");
      drawer?.classList.remove("is-open");
      overlay?.classList.remove("is-open");

      closeMobileToolsView();

      setTimeout(() => {
        if (overlay) overlay.hidden = true;
        if (drawer) drawer.hidden = true;
      }, 400);

      const appSection = document.getElementById("app");
      if (appSection) {
        setTimeout(() => {
          appSection.scrollIntoView({ behavior: "smooth", block: "start" });
        }, 120);
      }
    });
  });

  if (themeToggleButton) {
    themeToggleButton.addEventListener("click", toggleTheme);
  }

  const mobileThemeButton = document.getElementById("mobile-theme-toggle");
  if (mobileThemeButton) {
    mobileThemeButton.addEventListener("click", toggleTheme);
  }

  if (clearHistoryButton) clearHistoryButton.addEventListener("click", openDeleteConfirm);
  if (confirmCancel) confirmCancel.addEventListener("click", closeDeleteConfirm);

  if (confirmDelete) {
    confirmDelete.addEventListener("click", function () {
      if (pendingHistoryDeleteIndex !== null) {
        deleteHistoryItem(pendingHistoryDeleteIndex);
        closeDeleteConfirm();
        return;
      }
      clearHistory();
    });
  }

  if (confirmOverlay) {
    confirmOverlay.addEventListener("click", function (e) {
      if (e.target === confirmOverlay) closeDeleteConfirm();
    });
  }

  if (chatConfirmCancel) chatConfirmCancel.addEventListener("click", closeChatDeleteConfirm);
  if (chatConfirmDelete) chatConfirmDelete.addEventListener("click", clearChatImmediately);

  if (chatConfirmOverlay) {
    chatConfirmOverlay.addEventListener("click", function (e) {
      if (e.target === chatConfirmOverlay) closeChatDeleteConfirm();
    });
  }

  if (historyList) {
    historyList.addEventListener("click", function (e) {
      const openBtn = e.target.closest(".history-open-btn");
      const deleteBtn = e.target.closest(".history-delete-btn");

      if (openBtn) {
        const index = Number(openBtn.dataset.historyIndex);
        if (Number.isNaN(index)) return;
        restoreHistoryItem(index);
        return;
      }

      if (deleteBtn) {
        const index = Number(deleteBtn.dataset.historyIndex);
        if (Number.isNaN(index)) return;
        openDeleteConfirm(index);
      }
    });
  }

  if (chatInput) {
    chatInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendChatMessage();
      }
    });
  }

  if (pdfFileInput) {
    pdfFileInput.addEventListener("change", async function (e) {
      const files = Array.from(e.target.files || []);
      if (!files.length) return;

      const uploadText = document.querySelector('label[for="pdf-file"] .upload-text');
      if (uploadText) {
        if (files.length === 1) {
          uploadText.textContent = files[0].name;
        } else {
          uploadText.textContent = `${files.length} PDF kiválasztva`;
        }
      }

      showToast("Fájlok kiválasztva", "A PDF-ek beolvasása folyamatban van.");

      try {
        const pdfjsLib = await ensurePdfJs();

        if (!pdfjsLib) {
          throw new Error("A PDF olvasó betöltése nem sikerült.");
        }

        uploadedPdfDocuments = [];

        for (const file of files) {
          const text = await extractPdfTextFromFile(file, pdfjsLib);
          uploadedPdfDocuments.push({
            name: file.name,
            text,
          });
        }

        renderUploadedPdfList();

        if (output) {
          output.textContent = `${files.length} PDF sikeresen beolvasva. A szöveg nem jelent meg a bemeneti mezőben, de az AI összefoglalóhoz fel lesz használva.`;
        }

        showToast("Sikeres fájl-feltöltés", `${files.length} PDF sikeresen betöltve.`);
      } catch (error) {
        console.error("PDF feldolgozási hiba:", error);
        uploadedPdfDocuments = [];
        renderUploadedPdfList();

        if (output) {
          output.textContent = "Nem sikerült beolvasni a PDF-et: " + error.message;
        }

        showToast("PDF feldolgozási hiba", "A PDF feldolgozása nem sikerült.");
      }
    });
  }

  window.addEventListener(
    "scroll",
    function () {
      queueSparkleOffsetUpdate();
      updateScrollProgressBar();
      updatePremiumHeaderState();
      updateActiveNavLink();
    },
    { passive: true }
  );

  window.addEventListener("resize", function () {
    queueSparkleOffsetUpdate();
    updateScrollProgressBar();
    updatePremiumHeaderState();
    updateActiveNavLink();
  });

  document.querySelectorAll(".menu-btn").forEach((btn) => {
    btn.addEventListener("mousemove", (e) => {
      const rect = btn.getBoundingClientRect();
      const x = e.clientX - rect.left - rect.width / 2;
      const y = e.clientY - rect.top - rect.height / 2;
      btn.style.transform = `translate(${x * 0.08}px, ${y * 0.08}px)`;
    });

    btn.addEventListener("mouseleave", () => {
      btn.style.transform = "translate(0,0)";
    });
  });

  window.addEventListener("load", function () {
    updateSparkleOffset();
    updatePremiumHeaderState();
  });

  window.addEventListener("hashchange", function () {
    queueSparkleOffsetUpdate();
    updatePremiumHeaderState();
  });

  requestAnimationFrame(function () {
    updateSparkleOffset();
    updatePremiumHeaderState();
  });


(async function initHistoryState() {
  try {
    await loadHistoryFromServer(true);
    renderHistory();
  } catch (error) {
    console.error("Előzmények betöltési hiba:", error);
    setHistoryItems([]);
    renderHistory();
  }
})();

});

