(() => {
  const WRAPPER_ID = "yt-ticket-wrapper";
  const CONTAINER_ID = "yt-ticket-buttons";
  const STATUS_ID = "yt-ticket-status";
  const MODAL_ID = "yt-ticket-modal";
  const OVERLAY_ID = "yt-ticket-overlay";
  const MINIMIZED_ID = "yt-ticket-minimized";
  const PANEL_WIDTH = "240px";
  const PANEL_RADIUS = "10px";
  const PANEL_BORDER = "1px solid #dadce0";
  const PANEL_SHADOW = "0 2px 6px rgba(60, 64, 67, 0.15)";
  let currentThreadUrl = null;
  let currentMessageId = null;
  let modalState = null;

  const observer = new MutationObserver(() => {
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
    const emailContainer = findActiveEmailContainer();
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
    if (modalState) {
      setFloatingVisible(false);
    } else {
      setFloatingVisible(true);
    }
  }

  function ensureButtons() {
    if (document.getElementById(WRAPPER_ID)) {
      return;
    }

    const wrapper = document.createElement("div");
    wrapper.id = WRAPPER_ID;
    wrapper.className = "yt-ext-actions";
    wrapper.style.display = "flex";
    wrapper.style.flexDirection = "column";
    wrapper.style.alignItems = "stretch";
    wrapper.style.gap = "6px";
    wrapper.style.padding = "6px 8px 8px";
    wrapper.style.background = "#fff";
    wrapper.style.border = PANEL_BORDER;
    wrapper.style.borderRadius = PANEL_RADIUS;
    wrapper.style.boxShadow = PANEL_SHADOW;
    wrapper.style.position = "fixed";
    wrapper.style.bottom = "24px";
    wrapper.style.left = "24px";
    wrapper.style.zIndex = "9999";
    wrapper.style.width = PANEL_WIDTH;

    const dragHandle = document.createElement("div");
    dragHandle.style.width = "100%";
    dragHandle.style.height = "10px";
    dragHandle.style.background = "#f1f3f4";
    dragHandle.style.borderRadius = "6px 6px 4px 4px";
    dragHandle.style.flexShrink = "0";
    dragHandle.style.cursor = "move";

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

    wrapper.appendChild(dragHandle);
    wrapper.appendChild(container);
    wrapper.appendChild(status);

    document.body.appendChild(wrapper);
    enableDragging(wrapper, dragHandle);
  }

  function removeWrapper() {
    const wrapper = document.getElementById(WRAPPER_ID);
    if (wrapper) {
      wrapper.remove();
    }
  }

  function setFloatingVisible(visible) {
    const wrapper = document.getElementById(WRAPPER_ID);
    if (!wrapper) {
      return;
    }
    wrapper.style.display = visible ? "flex" : "none";
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
    openModal(type);
  }

  function openModal(type) {
    if (modalState) {
      if (modalState.isMinimized && modalState.restore) {
        modalState.restore();
      }
      return;
    }

    modalState = { isMinimized: false };
    setFloatingVisible(false);

    const overlay = document.createElement("div");
    overlay.id = OVERLAY_ID;
    overlay.style.position = "fixed";
    overlay.style.inset = "0";
    overlay.style.background = "rgba(0, 0, 0, 0.35)";
    overlay.style.zIndex = "10001";
    overlay.style.display = "flex";
    overlay.style.alignItems = "center";
    overlay.style.justifyContent = "center";

    const modal = document.createElement("div");
    modal.id = MODAL_ID;
    modal.style.background = "#fff";
    modal.style.border = PANEL_BORDER;
    modal.style.borderRadius = PANEL_RADIUS;
    modal.style.boxShadow = "0 6px 18px rgba(60, 64, 67, 0.2)";
    modal.style.width = "min(92vw, 520px)";
    modal.style.maxHeight = "80vh";
    modal.style.overflow = "auto";
    modal.style.padding = "16px";
    modal.style.display = "flex";
    modal.style.flexDirection = "column";
    modal.style.gap = "14px";

    const header = document.createElement("div");
    header.style.display = "flex";
    header.style.alignItems = "center";
    header.style.justifyContent = "space-between";
    header.style.gap = "12px";
    header.style.background = "#f8f9fa";
    header.style.margin = "-16px -16px 8px";
    header.style.padding = "10px 12px";
    header.style.borderBottom = "1px solid #e0e0e0";
    header.style.borderRadius = `${PANEL_RADIUS} ${PANEL_RADIUS} 0 0`;

    const title = document.createElement("div");
    title.textContent = type === "task" ? "Create Task" : "Create Spike";
    title.style.fontSize = "16px";
    title.style.fontWeight = "600";
    title.style.color = "#202124";

    const minimizeButton = document.createElement("button");
    minimizeButton.type = "button";
    minimizeButton.textContent = "-";
    minimizeButton.title = "Minimize";
    minimizeButton.setAttribute("aria-label", "Minimize");
    minimizeButton.style.border = "1px solid #dadce0";
    minimizeButton.style.background = "#fff";
    minimizeButton.style.color = "#3c4043";
    minimizeButton.style.width = "28px";
    minimizeButton.style.height = "28px";
    minimizeButton.style.borderRadius = "6px";
    minimizeButton.style.cursor = "pointer";
    minimizeButton.style.fontSize = "16px";
    minimizeButton.style.lineHeight = "26px";
    minimizeButton.style.textAlign = "center";

    header.appendChild(title);
    header.appendChild(minimizeButton);

    let currentMode = "manual";

    const modeWrap = document.createElement("div");
    modeWrap.style.display = "inline-flex";
    modeWrap.style.border = "1px solid #dadce0";
    modeWrap.style.borderRadius = "8px";
    modeWrap.style.overflow = "hidden";

    const manualButton = document.createElement("button");
    manualButton.type = "button";
    manualButton.textContent = "✍️ Manual";
    manualButton.style.border = "none";
    manualButton.style.borderRight = "1px solid #dadce0";
    manualButton.style.padding = "6px 10px";
    manualButton.style.fontSize = "12px";
    manualButton.style.cursor = "pointer";

    const aiButton = document.createElement("button");
    aiButton.type = "button";
    aiButton.textContent = "🤖 From email";
    aiButton.style.border = "none";
    aiButton.style.padding = "6px 10px";
    aiButton.style.fontSize = "12px";
    aiButton.style.cursor = "pointer";

    modeWrap.appendChild(manualButton);
    modeWrap.appendChild(aiButton);

    const manualSection = document.createElement("div");
    manualSection.style.display = "flex";
    manualSection.style.flexDirection = "column";
    manualSection.style.gap = "8px";

    const summaryInput = document.createElement("input");
    summaryInput.type = "text";
    summaryInput.placeholder = "Summary";
    summaryInput.style.padding = "8px";
    summaryInput.style.border = "1px solid #dadce0";
    summaryInput.style.borderRadius = "6px";
    summaryInput.style.fontSize = "13px";

    const descriptionInput = document.createElement("textarea");
    descriptionInput.placeholder = "Description";
    descriptionInput.rows = 6;
    descriptionInput.style.padding = "8px";
    descriptionInput.style.border = "1px solid #dadce0";
    descriptionInput.style.borderRadius = "6px";
    descriptionInput.style.fontSize = "13px";
    descriptionInput.style.resize = "vertical";

    manualSection.appendChild(summaryInput);
    manualSection.appendChild(descriptionInput);

    const aiSection = document.createElement("div");
    aiSection.style.display = "none";
    aiSection.style.flexDirection = "column";
    aiSection.style.gap = "8px";

    const email = extractEmail();
    const aiHelper = document.createElement("div");
    aiHelper.textContent = "Edit the email content before generating.";
    aiHelper.style.fontSize = "12px";
    aiHelper.style.color = "#5f6368";

    const aiBodyInput = document.createElement("textarea");
    aiBodyInput.rows = 10;
    aiBodyInput.value = email ? email.body : "";
    aiBodyInput.style.padding = "8px";
    aiBodyInput.style.border = "1px solid #dadce0";
    aiBodyInput.style.borderRadius = "6px";
    aiBodyInput.style.fontSize = "13px";
    aiBodyInput.style.resize = "vertical";

    aiSection.appendChild(aiHelper);
    aiSection.appendChild(aiBodyInput);

    const status = document.createElement("div");
    status.style.fontSize = "12px";
    status.style.color = "#5f6368";

    const actions = document.createElement("div");
    actions.style.display = "flex";
    actions.style.justifyContent = "flex-end";
    actions.style.gap = "8px";

    const cancelButton = document.createElement("button");
    cancelButton.type = "button";
    cancelButton.textContent = "Cancel";
    cancelButton.style.border = "1px solid #dadce0";
    cancelButton.style.background = "#fff";
    cancelButton.style.color = "#3c4043";
    cancelButton.style.padding = "6px 12px";
    cancelButton.style.borderRadius = "6px";
    cancelButton.style.cursor = "pointer";
    cancelButton.style.fontSize = "12px";

    const submitButton = document.createElement("button");
    submitButton.type = "button";
    submitButton.textContent = "Create Ticket";
    submitButton.style.border = "1px solid #1a73e8";
    submitButton.style.background = "#1a73e8";
    submitButton.style.color = "#fff";
    submitButton.style.padding = "6px 12px";
    submitButton.style.borderRadius = "6px";
    submitButton.style.cursor = "pointer";
    submitButton.style.fontSize = "12px";

    actions.appendChild(cancelButton);
    actions.appendChild(submitButton);

    modal.appendChild(header);
    modal.appendChild(modeWrap);
    modal.appendChild(manualSection);
    modal.appendChild(aiSection);
    modal.appendChild(status);
    modal.appendChild(actions);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    const minimizedBar = document.createElement("div");
    minimizedBar.id = MINIMIZED_ID;
    minimizedBar.style.position = "fixed";
    minimizedBar.style.left = "24px";
    minimizedBar.style.bottom = "24px";
    minimizedBar.style.background = "#f8f9fa";
    minimizedBar.style.border = PANEL_BORDER;
    minimizedBar.style.borderRadius = PANEL_RADIUS;
    minimizedBar.style.boxShadow = PANEL_SHADOW;
    minimizedBar.style.padding = "4px 8px";
    minimizedBar.style.width = PANEL_WIDTH;
    minimizedBar.style.display = "none";
    minimizedBar.style.alignItems = "center";
    minimizedBar.style.gap = "8px";
    minimizedBar.style.justifyContent = "space-between";
    minimizedBar.style.cursor = "pointer";
    minimizedBar.style.zIndex = "10001";

    const minimizedLeft = document.createElement("div");
    minimizedLeft.style.display = "inline-flex";
    minimizedLeft.style.alignItems = "center";
    minimizedLeft.style.gap = "6px";

    const minimizedIcon = document.createElement("span");
    minimizedIcon.textContent = "📝";
    minimizedIcon.style.fontSize = "13px";

    const minimizedText = document.createElement("span");
    minimizedText.style.fontSize = "12px";
    minimizedText.style.color = "#3c4043";

    minimizedLeft.appendChild(minimizedIcon);
    minimizedLeft.appendChild(minimizedText);

    const minimizedAction = document.createElement("button");
    minimizedAction.type = "button";
    minimizedAction.textContent = "⤢";
    minimizedAction.title = "Open";
    minimizedAction.setAttribute("aria-label", "Open");
    minimizedAction.style.border = "none";
    minimizedAction.style.background = "transparent";
    minimizedAction.style.color = "#1a73e8";
    minimizedAction.style.cursor = "pointer";
    minimizedAction.style.fontSize = "14px";

    minimizedBar.appendChild(minimizedLeft);
    minimizedBar.appendChild(minimizedAction);
    document.body.appendChild(minimizedBar);

    const onKeyDown = (event) => {
      if (event.key === "Escape") {
        closeModal();
      }
    };

    function closeModal() {
      document.removeEventListener("keydown", onKeyDown);
      if (overlay.parentElement) {
        overlay.parentElement.removeChild(overlay);
      }
      if (minimizedBar.parentElement) {
        minimizedBar.parentElement.removeChild(minimizedBar);
      }
      modalState = null;
      setFloatingVisible(true);
    }

    function setLoading(isLoading) {
      submitButton.disabled = isLoading;
      cancelButton.disabled = isLoading;
      minimizeButton.disabled = isLoading;
      manualButton.disabled = isLoading;
      aiButton.disabled = isLoading;
      summaryInput.disabled = isLoading;
      descriptionInput.disabled = isLoading;
      aiBodyInput.disabled = isLoading;
      submitButton.style.opacity = isLoading ? "0.7" : "1";
      cancelButton.style.opacity = isLoading ? "0.7" : "1";
      minimizeButton.style.opacity = isLoading ? "0.7" : "1";
      submitButton.textContent = isLoading ? "Creating..." : "Create Ticket";
      submitButton.style.cursor = isLoading ? "not-allowed" : "pointer";
      cancelButton.style.cursor = isLoading ? "not-allowed" : "pointer";
      minimizeButton.style.cursor = isLoading ? "not-allowed" : "pointer";
    }

    function setStatusMessage(message, type) {
      status.textContent = message;
      status.style.color = type === "error" ? "#d93025" : "#5f6368";
    }

    function setStatusSuccess(issueId, url) {
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

    function setMode(mode) {
      currentMode = mode;
      const isManual = currentMode === "manual";
      manualSection.style.display = isManual ? "flex" : "none";
      aiSection.style.display = isManual ? "none" : "flex";
      manualButton.style.background = isManual ? "#e8f0fe" : "#fff";
      manualButton.style.color = isManual ? "#1a73e8" : "#3c4043";
      aiButton.style.background = isManual ? "#fff" : "#e8f0fe";
      aiButton.style.color = isManual ? "#3c4043" : "#1a73e8";
      manualButton.setAttribute("aria-pressed", isManual ? "true" : "false");
      aiButton.setAttribute("aria-pressed", isManual ? "false" : "true");
      updateMinimizedText();
    }

    function updateMinimizedText() {
      const typeLabel = type === "task" ? "Task" : "Spike";
      minimizedText.textContent = `${typeLabel} • Draft`;
    }

    function minimizeModal() {
      modalState.isMinimized = true;
      overlay.style.display = "none";
      minimizedBar.style.display = "flex";
      updateMinimizedText();
      setFloatingVisible(false);
    }

    function restoreModal() {
      modalState.isMinimized = false;
      overlay.style.display = "flex";
      minimizedBar.style.display = "none";
      setFloatingVisible(false);
    }

    manualButton.addEventListener("click", () => setMode("manual"));
    aiButton.addEventListener("click", () => setMode("ai"));

    minimizeButton.addEventListener("click", () => {
      if (minimizeButton.disabled) {
        return;
      }
      minimizeModal();
    });

    minimizedBar.addEventListener("click", () => {
      restoreModal();
    });

    minimizedAction.addEventListener("click", (event) => {
      event.stopPropagation();
      restoreModal();
    });

    cancelButton.addEventListener("click", () => {
      if (!cancelButton.disabled) {
        closeModal();
      }
    });

    submitButton.addEventListener("click", () => {
      if (submitButton.disabled) {
        return;
      }

      const mode = currentMode;
      let payload = null;

      if (mode === "manual") {
        const summary = summaryInput.value.trim();
        const description = descriptionInput.value.trim();
        if (!summary || !description) {
          setStatusMessage("Summary and description are required.", "error");
          return;
        }
        payload = { type, mode, summary, description };
      } else {
        const latestEmail = extractEmail();
        const body = aiBodyInput.value.trim();
        if (!latestEmail) {
          setStatusMessage("Unable to read email content.", "error");
          return;
        }
        if (!body) {
          setStatusMessage("Email body is required.", "error");
          return;
        }
        payload = {
          type,
          mode,
          email: {
            subject: latestEmail.subject,
            from: latestEmail.from,
            body,
            threadUrl: latestEmail.threadUrl
          }
        };
      }

      setLoading(true);
      setStatusMessage("Creating ticket...", "info");

      chrome.runtime.sendMessage(
        { action: "create-ticket", payload },
        (response) => {
          if (chrome.runtime.lastError) {
            setStatusMessage(chrome.runtime.lastError.message, "error");
            setLoading(false);
            return;
          }

          if (!response) {
            setStatusMessage("No response from background.", "error");
            setLoading(false);
            return;
          }

          if (response.ok) {
            setStatusSuccess(response.issueId, response.url);
            setLoading(false);
            return;
          }

          setStatusMessage(response.error || "Request failed.", "error");
          setLoading(false);
        }
      );
    });

    Object.assign(modalState, {
      type,
      overlay,
      modal,
      minimizedBar,
      manualButton,
      aiButton,
      summaryInput,
      descriptionInput,
      aiBodyInput,
      status,
      isMinimized: false,
      restore: restoreModal,
      minimize: minimizeModal,
      close: closeModal
    });

    setMode(currentMode);
    document.addEventListener("keydown", onKeyDown);
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

  function enableDragging(wrapper, dragHandle) {
    let isDragging = false;
    let offsetX = 0;
    let offsetY = 0;

    dragHandle.addEventListener("mousedown", (event) => {
      event.preventDefault();
      isDragging = true;
      offsetX = event.clientX - wrapper.getBoundingClientRect().left;
      offsetY = event.clientY - wrapper.getBoundingClientRect().top;
      wrapper.style.bottom = "auto";
      wrapper.style.right = "auto";
    });

    document.addEventListener("mousemove", (event) => {
      if (!isDragging) {
        return;
      }
      const left = event.clientX - offsetX;
      const top = event.clientY - offsetY;
      wrapper.style.left = `${Math.max(0, left)}px`;
      wrapper.style.top = `${Math.max(0, top)}px`;
    });

    document.addEventListener("mouseup", () => {
      if (!isDragging) {
        return;
      }
      isDragging = false;
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
