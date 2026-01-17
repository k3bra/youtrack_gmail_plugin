(() => {
  console.log("[YT EXT] content.js loaded");
  const WRAPPER_ID = "yt-ticket-wrapper";
  const CONTAINER_ID = "yt-ticket-buttons";
  const STATUS_ID = "yt-ticket-status";
  let currentThreadUrl = null;
  let currentMessageId = null;

  const observer = new MutationObserver(() => {
    console.log("[YT EXT] mutation observer fired");
    updateInjection();
  });

  function start() {
    if (!document.body) {
      return;
    }
    observer.observe(document.body, { childList: true, subtree: true });
    updateInjection();
  }

  function updateInjection() {
    const subjectEl = findSubjectElement();
    const bodyEl = findBodyElement();
    console.log("[YT EXT] attempting to find Gmail toolbar");
    const emailContainer = findActiveEmailContainer();
    console.log("[YT EXT] toolbar found:", !!emailContainer);
    if (emailContainer) {
      console.log("[YT EXT] email container found", emailContainer);
    } else {
      console.log("[YT EXT] email container found: null");
    }
    const threadUrl = window.location.href;
    const messageId = emailContainer
      ? emailContainer.getAttribute("data-message-id")
      : null;

    if (!subjectEl || !bodyEl || !emailContainer) {
      removeWrapper();
      currentThreadUrl = null;
      currentMessageId = null;
      return;
    }

    if (currentThreadUrl !== threadUrl || currentMessageId !== messageId) {
      removeWrapper();
      currentThreadUrl = threadUrl;
      currentMessageId = messageId;
    }

    ensureButtons();
  }

  function ensureButtons() {
    if (document.getElementById(WRAPPER_ID)) {
      return;
    }

    const wrapper = document.createElement("div");
    wrapper.id = WRAPPER_ID;
    wrapper.className = "yt-ext-actions";
    wrapper.style.display = "inline-flex";
    wrapper.style.alignItems = "center";
    wrapper.style.gap = "6px";
    wrapper.style.padding = "8px";
    wrapper.style.background = "#fff";
    wrapper.style.border = "1px solid #dadce0";
    wrapper.style.borderRadius = "8px";
    wrapper.style.boxShadow = "0 2px 6px rgba(60, 64, 67, 0.15)";
    wrapper.style.position = "fixed";
    wrapper.style.top = "16px";
    wrapper.style.right = "16px";
    wrapper.style.zIndex = "9999";

    const container = document.createElement("div");
    container.id = CONTAINER_ID;
    container.style.display = "inline-flex";
    container.style.gap = "4px";
    container.style.alignItems = "center";

    const taskButton = createButton("Create Task", "task");
    const spikeButton = createButton("Create Spike", "spike");
    container.appendChild(taskButton);
    container.appendChild(spikeButton);

    const status = document.createElement("div");
    status.id = STATUS_ID;
    status.style.fontSize = "12px";
    status.style.color = "#5f6368";
    status.style.marginLeft = "6px";

    wrapper.appendChild(container);
    wrapper.appendChild(status);

    document.body.appendChild(wrapper);
    console.log("[YT EXT] buttons injected");
    console.log("[YT EXT] buttons injected next to Gmail actions");
    console.log("[YT EXT] buttons injected into action row");
    console.log("[YT EXT] floating buttons injected");
  }

  function removeWrapper() {
    const wrapper = document.getElementById(WRAPPER_ID);
    if (wrapper) {
      wrapper.remove();
      console.log("[YT EXT] floating buttons removed");
    }
  }

  function createButton(label, type) {
    const button = document.createElement("button");
    button.type = "button";
    button.textContent = label;
    button.className = "T-I J-J5-Ji";
    button.style.height = "28px";
    button.style.lineHeight = "28px";
    button.style.padding = "0 8px";
    button.style.fontSize = "12px";
    button.style.minWidth = "auto";

    button.addEventListener("click", () => handleClick(type));
    return button;
  }

  function handleClick(type) {
    setStatus("Creating ticket...", "info");
    setButtonsDisabled(true);

    const email = extractEmail();
    if (!email) {
      setStatus("Unable to read email content.", "error");
      setButtonsDisabled(false);
      return;
    }

    chrome.runtime.sendMessage(
      { action: "create-ticket", payload: { type, email } },
      (response) => {
        if (chrome.runtime.lastError) {
          setStatus(chrome.runtime.lastError.message, "error");
          setButtonsDisabled(false);
          return;
        }

        if (!response) {
          setStatus("No response from background.", "error");
          setButtonsDisabled(false);
          return;
        }

        if (response.ok) {
          setStatusSuccess(response.issueId, response.url);
          return;
        }

        setStatus(response.error || "Request failed.", "error");
        setButtonsDisabled(false);
      }
    );
  }

  function setButtonsDisabled(disabled) {
    const container = document.getElementById(CONTAINER_ID);
    if (!container) {
      return;
    }
    const buttons = container.querySelectorAll("button");
    buttons.forEach((button) => {
      button.disabled = disabled;
      button.style.opacity = disabled ? "0.6" : "1";
      button.style.cursor = disabled ? "not-allowed" : "pointer";
    });
  }

  function setStatus(message, type) {
    const status = document.getElementById(STATUS_ID);
    if (!status) {
      return;
    }
    status.textContent = message;
    status.style.color = type === "error" ? "#d93025" : "#5f6368";
  }

  function setStatusSuccess(issueId, url) {
    const status = document.getElementById(STATUS_ID);
    if (!status) {
      return;
    }
    status.textContent = "";

    const text = document.createElement("span");
    text.textContent = `Ticket created: ${issueId} `;

    const link = document.createElement("a");
    link.href = url;
    link.textContent = "Open in YouTrack";
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.style.color = "#1a73e8";

    status.appendChild(text);
    status.appendChild(link);
  }

  function extractEmail() {
    const subjectEl = findSubjectElement();
    const fromEl = findFromElement();
    const bodyText = getBodyText();

    const subject = subjectEl ? subjectEl.textContent.trim() : "";
    const from = fromEl ? fromEl.textContent.trim() : "";
    const body = bodyText || "";
    const threadUrl = window.location.href;

    if (!subject || !from || !body) {
      return null;
    }

    return { subject, from, body, threadUrl };
  }

  function findSubjectElement() {
    return (
      document.querySelector("h2.hP") ||
      document.querySelector("h2[data-legacy-thread-id]") ||
      document.querySelector("h2[data-thread-id]") ||
      document.querySelector('div[role="main"] h2')
    );
  }

  function findFromElement() {
    return (
      document.querySelector("span.gD") ||
      document.querySelector("span.go") ||
      document.querySelector("span[email]")
    );
  }

  function findBodyElement() {
    const candidates = Array.from(document.querySelectorAll("div.a3s"));
    return candidates.find((node) => isVisible(node)) || null;
  }

  function findActiveEmailContainer() {
    const candidates = Array.from(
      document.querySelectorAll("div[data-message-id]")
    );
    return candidates.find((node) => isVisible(node)) || null;
  }


  function getBodyText() {
    const candidates = Array.from(document.querySelectorAll("div.a3s"));
    let best = "";

    candidates.forEach((node) => {
      if (!isVisible(node)) {
        return;
      }
      const text = (node.innerText || "").trim();
      if (text.length > best.length) {
        best = text;
      }
    });

    return best;
  }

  function isVisible(node) {
    if (!node) {
      return false;
    }
    return node.getClientRects().length > 0;
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
