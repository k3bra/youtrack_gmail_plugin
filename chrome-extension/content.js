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
  // Used until the backend returns the project's actual Priority values.
  const DEFAULT_PRIORITIES = ["Highest", "High", "Medium"];
  // Images smaller than this on either side are treated as logos, icons or tracking pixels.
  const MIN_IMAGE_SIDE = 100;
  const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
  const MAX_TOTAL_IMAGE_BYTES = 25 * 1024 * 1024;
  // Must not exceed StoreTicketAttachmentChunkAction::CHUNK_BYTES on the backend.
  const UPLOAD_CHUNK_BYTES = 1024 * 1024;
  // Reply all is flagged when the thread includes anyone outside these domains (subdomains count as internal).
  const INTERNAL_DOMAINS = ["hijiffy.com"];
  const SIGNATURE_SELECTOR =
    '.gmail_signature, [data-smartmail="gmail_signature"], [id*="Signature"], [class*="signature"]';
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
    modal.style.maxHeight = "85vh";
    modal.style.overflow = "hidden";
    modal.style.display = "flex";
    modal.style.flexDirection = "column";

    const header = document.createElement("div");
    header.style.display = "flex";
    header.style.alignItems = "center";
    header.style.justifyContent = "space-between";
    header.style.gap = "12px";
    header.style.background = "#f8f9fa";
    header.style.padding = "10px 12px";
    header.style.borderBottom = "1px solid #e0e0e0";
    header.style.flexShrink = "0";

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
    let draftLabels = [];
    let draftEmail = null;

    const modeWrap = document.createElement("div");
    modeWrap.style.display = "inline-flex";
    modeWrap.style.border = "1px solid #dadce0";
    modeWrap.style.borderRadius = "8px";
    modeWrap.style.overflow = "hidden";
    modeWrap.style.alignSelf = "flex-start";

    const manualButton = document.createElement("button");
    manualButton.type = "button";
    manualButton.textContent = "✍️ Ticket";
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
    descriptionInput.rows = 14;
    descriptionInput.style.padding = "8px";
    descriptionInput.style.border = "1px solid #dadce0";
    descriptionInput.style.borderRadius = "6px";
    descriptionInput.style.fontSize = "13px";
    descriptionInput.style.resize = "vertical";

    const priorityRow = document.createElement("label");
    priorityRow.style.display = "flex";
    priorityRow.style.alignItems = "center";
    priorityRow.style.gap = "8px";
    priorityRow.style.fontSize = "12px";
    priorityRow.style.color = "#3c4043";
    priorityRow.textContent = "Priority";

    const prioritySelect = document.createElement("select");
    prioritySelect.style.padding = "6px 8px";
    prioritySelect.style.border = "1px solid #dadce0";
    prioritySelect.style.borderRadius = "6px";
    prioritySelect.style.fontSize = "13px";
    prioritySelect.style.background = "#fff";
    priorityRow.appendChild(prioritySelect);
    setPriorityOptions(prioritySelect, DEFAULT_PRIORITIES);
    loadPriorities(prioritySelect);

    const sprintRow = document.createElement("label");
    sprintRow.style.display = "none";
    sprintRow.style.alignItems = "center";
    sprintRow.style.gap = "8px";
    sprintRow.style.fontSize = "12px";
    sprintRow.style.color = "#3c4043";
    sprintRow.textContent = "Sprint";

    const sprintSelect = document.createElement("select");
    sprintSelect.style.padding = "6px 8px";
    sprintSelect.style.border = "1px solid #dadce0";
    sprintSelect.style.borderRadius = "6px";
    sprintSelect.style.fontSize = "13px";
    sprintSelect.style.background = "#fff";
    sprintRow.appendChild(sprintSelect);
    loadSprintOptions(sprintRow, sprintSelect);

    const optionsRow = document.createElement("div");
    optionsRow.style.display = "flex";
    optionsRow.style.flexWrap = "wrap";
    optionsRow.style.gap = "16px";
    optionsRow.appendChild(priorityRow);
    optionsRow.appendChild(sprintRow);

    const imagesSection = document.createElement("div");
    imagesSection.style.display = "none";
    imagesSection.style.flexDirection = "column";
    imagesSection.style.gap = "6px";

    const imagesHeader = document.createElement("div");
    imagesHeader.style.fontSize = "12px";
    imagesHeader.style.color = "#3c4043";

    const imagesGrid = document.createElement("div");
    imagesGrid.style.display = "flex";
    imagesGrid.style.flexWrap = "wrap";
    imagesGrid.style.gap = "8px";

    const imagesNote = document.createElement("div");
    imagesNote.style.fontSize = "11px";
    imagesNote.style.color = "#5f6368";

    imagesSection.appendChild(imagesHeader);
    imagesSection.appendChild(imagesGrid);
    imagesSection.appendChild(imagesNote);

    let imageItems = [];

    const imagePreview = document.createElement("img");
    imagePreview.alt = "";
    imagePreview.style.position = "fixed";
    imagePreview.style.display = "none";
    imagePreview.style.zIndex = "10002";
    imagePreview.style.pointerEvents = "none";
    imagePreview.style.background = "#fff";
    imagePreview.style.border = "1px solid #dadce0";
    imagePreview.style.borderRadius = "8px";
    imagePreview.style.boxShadow = "0 6px 18px rgba(60, 64, 67, 0.3)";
    imagePreview.style.objectFit = "contain";

    manualSection.appendChild(summaryInput);
    manualSection.appendChild(optionsRow);
    manualSection.appendChild(descriptionInput);
    manualSection.appendChild(imagesSection);

    const aiSection = document.createElement("div");
    aiSection.style.display = "none";
    aiSection.style.flexDirection = "column";
    aiSection.style.gap = "8px";

    const email = extractEmail();
    const aiHelper = document.createElement("div");
    aiHelper.textContent =
      "Edit the email content, then generate a draft. You can review and edit it before the ticket is created.";
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

    const replyPanel = document.createElement("div");
    replyPanel.style.display = "none";
    replyPanel.style.flexDirection = "column";
    replyPanel.style.gap = "8px";
    replyPanel.style.borderTop = "1px solid #e0e0e0";
    replyPanel.style.paddingTop = "12px";

    const replyLabel = document.createElement("div");
    replyLabel.textContent = "Reply to the thread";
    replyLabel.style.fontSize = "12px";
    replyLabel.style.fontWeight = "600";
    replyLabel.style.color = "#3c4043";

    const replyInput = document.createElement("textarea");
    replyInput.rows = 9;
    replyInput.style.padding = "8px";
    replyInput.style.border = "1px solid #dadce0";
    replyInput.style.borderRadius = "6px";
    replyInput.style.fontSize = "13px";
    replyInput.style.resize = "vertical";

    const replyActions = document.createElement("div");
    replyActions.style.display = "flex";
    replyActions.style.gap = "8px";

    const replyAllButton = document.createElement("button");
    replyAllButton.type = "button";
    replyAllButton.textContent = "Reply all with message";
    replyAllButton.style.border = "1px solid #1a73e8";
    replyAllButton.style.background = "#1a73e8";
    replyAllButton.style.color = "#fff";
    replyAllButton.style.padding = "6px 12px";
    replyAllButton.style.borderRadius = "6px";
    replyAllButton.style.cursor = "pointer";
    replyAllButton.style.fontSize = "12px";

    const copyButton = document.createElement("button");
    copyButton.type = "button";
    copyButton.textContent = "Copy";
    copyButton.style.border = "1px solid #dadce0";
    copyButton.style.background = "#fff";
    copyButton.style.color = "#3c4043";
    copyButton.style.padding = "6px 12px";
    copyButton.style.borderRadius = "6px";
    copyButton.style.cursor = "pointer";
    copyButton.style.fontSize = "12px";

    const replyStatus = document.createElement("span");
    replyStatus.style.fontSize = "12px";
    replyStatus.style.alignSelf = "center";

    replyActions.appendChild(replyAllButton);
    replyActions.appendChild(copyButton);
    replyActions.appendChild(replyStatus);
    replyPanel.appendChild(replyLabel);
    const replyWarning = document.createElement("div");
    replyWarning.style.display = "none";
    replyWarning.style.fontSize = "12px";
    replyWarning.style.lineHeight = "1.4";
    replyWarning.style.color = "#7a4100";
    replyWarning.style.background = "#fef7e0";
    replyWarning.style.border = "1px solid #f9ab00";
    replyWarning.style.borderRadius = "6px";
    replyWarning.style.padding = "8px 10px";

    replyPanel.appendChild(replyInput);
    replyPanel.appendChild(replyWarning);
    replyPanel.appendChild(replyActions);

    const actions = document.createElement("div");
    actions.style.display = "flex";
    actions.style.justifyContent = "flex-end";
    actions.style.gap = "8px";
    actions.style.padding = "12px 16px";
    actions.style.borderTop = "1px solid #e0e0e0";
    actions.style.background = "#fff";
    actions.style.flexShrink = "0";

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

    const uploadStatus = document.createElement("div");
    uploadStatus.style.fontSize = "12px";
    uploadStatus.style.color = "#5f6368";

    // Header and actions stay pinned; only the body scrolls. Body rows must not shrink,
    // otherwise the browser squashes them (e.g. the mode toggle) instead of scrolling.
    const body = document.createElement("div");
    body.style.flex = "1 1 auto";
    body.style.minHeight = "0";
    body.style.overflowY = "auto";
    body.style.padding = "16px";
    body.style.display = "flex";
    body.style.flexDirection = "column";
    body.style.gap = "14px";
    [modeWrap, manualSection, aiSection, status, uploadStatus, replyPanel].forEach((section) => {
      section.style.flexShrink = "0";
      body.appendChild(section);
    });

    modal.appendChild(header);
    modal.appendChild(body);
    modal.appendChild(actions);
    overlay.appendChild(modal);
    overlay.appendChild(imagePreview);
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
      imageItems.forEach((item) => URL.revokeObjectURL(item.previewUrl));
      if (overlay.parentElement) {
        overlay.parentElement.removeChild(overlay);
      }
      if (minimizedBar.parentElement) {
        minimizedBar.parentElement.removeChild(minimizedBar);
      }
      modalState = null;
      setFloatingVisible(true);
    }

    function setLoading(isLoading, loadingText) {
      submitButton.disabled = isLoading;
      cancelButton.disabled = isLoading;
      minimizeButton.disabled = isLoading;
      manualButton.disabled = isLoading;
      aiButton.disabled = isLoading;
      summaryInput.disabled = isLoading;
      descriptionInput.disabled = isLoading;
      prioritySelect.disabled = isLoading;
      sprintSelect.disabled = isLoading;
      imageItems.forEach((item) => {
        item.checkbox.disabled = isLoading;
      });
      aiBodyInput.disabled = isLoading;
      submitButton.style.opacity = isLoading ? "0.7" : "1";
      cancelButton.style.opacity = isLoading ? "0.7" : "1";
      minimizeButton.style.opacity = isLoading ? "0.7" : "1";
      submitButton.textContent = isLoading ? loadingText : submitLabel();
      submitButton.style.cursor = isLoading ? "not-allowed" : "pointer";
      cancelButton.style.cursor = isLoading ? "not-allowed" : "pointer";
      minimizeButton.style.cursor = isLoading ? "not-allowed" : "pointer";
    }

    function setStatusMessage(message, type) {
      status.textContent = message;
      status.style.color = type === "error" ? "#d93025" : "#5f6368";
    }

    function setStatusSuccess(issueId, url, warning) {
      status.textContent = "";
      status.style.color = "#5f6368";
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
      if (warning) {
        const warningText = document.createElement("div");
        warningText.textContent = warning;
        warningText.style.color = "#b06000";
        warningText.style.marginTop = "4px";
        status.appendChild(warningText);
      }
    }

    function showReplyPanel(message) {
      replyInput.value = message;
      replyStatus.textContent = "";
      updateReplyAllWarning();
      replyPanel.style.display = "flex";
      replyPanel.scrollIntoView({ block: "nearest" });
    }

    function updateReplyAllWarning() {
      const external = findExternalParticipants();

      if (external.length === 0) {
        replyWarning.style.display = "none";
        replyAllButton.textContent = "Reply all with message";
        replyAllButton.style.background = "#1a73e8";
        replyAllButton.style.borderColor = "#1a73e8";
        return;
      }

      const listed = external.slice(0, 5).join(", ");
      const more = external.length > 5 ? ` and ${external.length - 5} more` : "";
      replyWarning.textContent =
        `⚠️ This thread includes people outside ${INTERNAL_DOMAINS.join(", ")}: ${listed}${more}. ` +
        "Reply all would send them this message, including the internal YouTrack link. Check the recipients before sending.";
      replyWarning.style.display = "block";
      replyAllButton.textContent = "⚠️ Reply all (includes external)";
      replyAllButton.style.background = "#b06000";
      replyAllButton.style.borderColor = "#b06000";
    }

    function setReplyStatus(message, type) {
      replyStatus.textContent = message;
      replyStatus.style.color = type === "error" ? "#d93025" : "#188038";
    }

    copyButton.addEventListener("click", async () => {
      const copied = await copyText(replyInput.value);
      setReplyStatus(
        copied ? "Copied." : "Copy failed. Select the text and copy it manually.",
        copied ? "info" : "error"
      );
    });

    replyAllButton.addEventListener("click", async () => {
      replyAllButton.disabled = true;
      setReplyStatus("Opening Reply all...", "info");
      const result = await insertIntoReplyAll(replyInput.value);
      replyAllButton.disabled = false;

      if (result.ok) {
        // Leave the draft for the user to review and send themselves.
        closeModal();
        return;
      }
      setReplyStatus(`${result.error} Use Copy and paste it instead.`, "error");
    });

    function submitLabel() {
      return currentMode === "manual" ? "Create Ticket" : "✨ Generate draft";
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
      submitButton.textContent = submitLabel();
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

      if (currentMode === "manual") {
        createTicket();
      } else {
        generateDraft();
      }
    });

    function createTicket() {
      const summary = summaryInput.value.trim();
      const description = descriptionInput.value.trim();
      if (!summary || !description) {
        setStatusMessage("Summary and description are required.", "error");
        return;
      }

      const payload = { type, mode: "manual", summary, description };
      if (prioritySelect.value) {
        payload.priority = prioritySelect.value;
      }
      const sender = findSender();
      if (sender && sender.name) {
        payload.senderName = sender.name;
      }

      const sprintOption = sprintSelect.selectedOptions[0];
      if (sprintOption && sprintOption.value) {
        payload.sprint = sprintOption.value;
        payload.sprintNumber = Number(sprintOption.dataset.number);
      }
      if (draftLabels.length > 0) {
        payload.labels = draftLabels;
      }
      if (draftEmail) {
        payload.email = draftEmail;
      }

      const selectedImages = imageItems.filter((item) => item.checkbox.checked);
      const selectedBytes = selectedImages.reduce((sum, item) => sum + item.blob.size, 0);
      if (selectedBytes > MAX_TOTAL_IMAGE_BYTES) {
        setStatusMessage(
          `Selected images are ${formatBytes(selectedBytes)}; the limit is ${formatBytes(MAX_TOTAL_IMAGE_BYTES)}. Untick some images.`,
          "error"
        );
        return;
      }

      setLoading(true, "Creating...");
      setStatusMessage("Creating ticket...", "info");

      sendToBackground("create-ticket", payload, (response) => {
        setStatusSuccess(response.issueId, response.url, response.warning);
        // The ticket exists now; prevent creating a duplicate.
        submitButton.disabled = true;
        submitButton.textContent = "Created";
        submitButton.style.opacity = "0.7";
        imageItems.forEach((item) => {
          item.checkbox.disabled = true;
        });
        if (response.replyMessage) {
          showReplyPanel(response.replyMessage);
        }
        if (selectedImages.length > 0) {
          uploadImages(response.issueId, selectedImages);
        }
      });
    }

    async function uploadImages(issueId, items) {
      const uploaded = [];
      const failed = [];

      for (let i = 0; i < items.length; i++) {
        uploadStatus.style.color = "#5f6368";
        uploadStatus.textContent = `Uploading images ${i + 1}/${items.length}...`;
        try {
          uploaded.push(await uploadImage(issueId, items[i]));
        } catch (error) {
          failed.push(`${items[i].name} (${error.message})`);
        }
      }

      let finalizeError = null;
      if (uploaded.length > 0) {
        const response = await sendMessageAsync("finalize-attachments", {
          issueId,
          names: uploaded
        });
        if (!response.ok) {
          finalizeError = response.error;
        }
      }

      const messages = [];
      if (uploaded.length > 0) {
        messages.push(`${uploaded.length} image${uploaded.length === 1 ? "" : "s"} attached.`);
      }
      if (failed.length > 0) {
        messages.push(`Not attached: ${failed.join("; ")}.`);
      }
      if (finalizeError) {
        messages.push(`Images were attached but not embedded in the description: ${finalizeError}`);
      }
      uploadStatus.textContent = messages.join(" ");
      uploadStatus.style.color = failed.length > 0 || finalizeError ? "#b06000" : "#188038";
    }

    async function uploadImage(issueId, item) {
      const buffer = await item.blob.arrayBuffer();
      const total = Math.max(1, Math.ceil(buffer.byteLength / UPLOAD_CHUNK_BYTES));
      const uploadId = crypto.randomUUID();
      let name = item.name;

      for (let index = 0; index < total; index++) {
        const chunk = buffer.slice(index * UPLOAD_CHUNK_BYTES, (index + 1) * UPLOAD_CHUNK_BYTES);
        const response = await sendMessageAsync("upload-attachment-chunk", {
          issueId,
          uploadId,
          name: item.name,
          index,
          total,
          data: arrayBufferToBase64(chunk)
        });
        if (!response.ok) {
          throw new Error(response.error || "upload failed");
        }
        if (response.done && response.name) {
          name = response.name;
        }
      }

      return name;
    }

    async function prepareImages() {
      const ownState = modalState;
      const sources = collectImageSources();
      if (sources.length === 0) {
        return;
      }

      imagesSection.style.display = "flex";
      imagesHeader.textContent = "Looking for images in the email...";

      let tooLarge = 0;
      let unreadable = 0;
      const seenHashes = new Set();
      const usedNames = new Set();
      const items = [];

      for (const source of sources) {
        let blob;
        try {
          blob = await downloadImage(source.url);
        } catch (error) {
          unreadable++;
          continue;
        }

        if (!blob.type.startsWith("image/")) {
          continue;
        }
        if (blob.size > MAX_IMAGE_BYTES) {
          tooLarge++;
          continue;
        }

        let dimensions;
        try {
          dimensions = await imageDimensions(blob);
        } catch (error) {
          unreadable++;
          continue;
        }
        if (dimensions.width < MIN_IMAGE_SIDE || dimensions.height < MIN_IMAGE_SIDE) {
          continue;
        }

        const hash = await hashBlob(blob);
        if (seenHashes.has(hash)) {
          continue;
        }
        seenHashes.add(hash);

        items.push({
          blob,
          name: uniqueImageName(source.name, blob.type, items.length + 1, usedNames),
          previewUrl: URL.createObjectURL(blob)
        });
      }

      if (modalState !== ownState) {
        items.forEach((item) => URL.revokeObjectURL(item.previewUrl));
        return;
      }

      imageItems = items.map((item) => ({ ...item, checkbox: renderImageItem(item) }));

      if (imageItems.length === 0 && tooLarge === 0 && unreadable === 0) {
        imagesSection.style.display = "none";
        return;
      }

      imagesHeader.textContent =
        imageItems.length > 0
          ? `Attach images (${imageItems.length})`
          : "No images to attach";

      const notes = [];
      if (tooLarge > 0) {
        notes.push(`${tooLarge} image${tooLarge === 1 ? " is" : "s are"} over ${formatBytes(MAX_IMAGE_BYTES)} and won't be attached.`);
      }
      if (unreadable > 0) {
        notes.push(`${unreadable} image${unreadable === 1 ? "" : "s"} couldn't be loaded.`);
      }
      imagesNote.textContent = notes.join(" ");
    }

    function renderImageItem(item) {
      const tile = document.createElement("div");
      tile.style.display = "flex";
      tile.style.flexDirection = "column";
      tile.style.alignItems = "center";
      tile.style.gap = "2px";
      tile.style.cursor = "pointer";
      tile.title = `${item.name} — click to include or exclude`;

      const frame = document.createElement("div");
      frame.style.position = "relative";
      frame.style.borderRadius = "6px";
      frame.style.outlineOffset = "1px";

      const img = document.createElement("img");
      img.src = item.previewUrl;
      img.alt = item.name;
      img.style.display = "block";
      img.style.height = "72px";
      img.style.width = "110px";
      img.style.objectFit = "cover";
      img.style.border = "1px solid #dadce0";
      img.style.borderRadius = "6px";
      img.style.transition = "opacity 0.15s";

      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.checked = true;
      checkbox.setAttribute("aria-label", `Attach ${item.name}`);
      checkbox.style.position = "absolute";
      checkbox.style.top = "4px";
      checkbox.style.left = "4px";
      checkbox.style.margin = "0";
      checkbox.style.width = "16px";
      checkbox.style.height = "16px";
      checkbox.style.cursor = "pointer";

      const size = document.createElement("span");
      size.textContent = formatBytes(item.blob.size);
      size.style.fontSize = "11px";
      size.style.color = "#5f6368";

      const syncState = () => {
        img.style.opacity = checkbox.checked ? "1" : "0.35";
        frame.style.outline = checkbox.checked ? "2px solid #1a73e8" : "none";
      };

      tile.addEventListener("click", (event) => {
        if (event.target === checkbox || checkbox.disabled) {
          return;
        }
        checkbox.checked = !checkbox.checked;
        syncState();
      });
      checkbox.addEventListener("change", syncState);

      img.addEventListener("mouseenter", () => showImagePreview(item.previewUrl, frame));
      img.addEventListener("mouseleave", hideImagePreview);

      frame.appendChild(img);
      frame.appendChild(checkbox);
      tile.appendChild(frame);
      tile.appendChild(size);
      imagesGrid.appendChild(tile);
      syncState();

      return checkbox;
    }

    function showImagePreview(url, anchor) {
      imagePreview.src = url;
      imagePreview.style.display = "block";

      const rect = anchor.getBoundingClientRect();
      const width = Math.min(480, window.innerWidth - 32);
      const height = Math.min(360, window.innerHeight - 32);
      imagePreview.style.maxWidth = `${width}px`;
      imagePreview.style.maxHeight = `${height}px`;

      // Prefer showing the preview above the thumbnail, otherwise below; keep it on screen.
      const left = Math.min(Math.max(16, rect.left), window.innerWidth - width - 16);
      const top = rect.top - height - 8 >= 16 ? rect.top - height - 8 : Math.min(rect.bottom + 8, window.innerHeight - height - 16);
      imagePreview.style.left = `${left}px`;
      imagePreview.style.top = `${Math.max(16, top)}px`;
    }

    function hideImagePreview() {
      imagePreview.style.display = "none";
      imagePreview.removeAttribute("src");
    }

    function generateDraft() {
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

      const email = {
        subject: latestEmail.subject,
        from: latestEmail.from,
        body,
        threadUrl: latestEmail.threadUrl
      };

      setLoading(true, "Generating...");
      setStatusMessage("Generating draft with AI...", "info");

      sendToBackground("preview-ticket", { type, email }, (response) => {
        summaryInput.value = response.summary;
        descriptionInput.value = response.description;
        draftLabels = response.labels || [];
        draftEmail = email;
        setMode("manual");
        setStatusMessage(
          "Draft ready. Review and edit it, then click Create Ticket.",
          "info"
        );
      });
    }

    function sendToBackground(action, payload, onSuccess) {
      chrome.runtime.sendMessage({ action, payload }, (response) => {
        setLoading(false);

        if (chrome.runtime.lastError) {
          setStatusMessage(chrome.runtime.lastError.message, "error");
          return;
        }

        if (!response) {
          setStatusMessage("No response from background.", "error");
          return;
        }

        if (!response.ok) {
          setStatusMessage(response.error || "Request failed.", "error");
          return;
        }

        onSuccess(response);
      });
    }

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
    prepareImages();
  }

  function setPriorityOptions(select, priorities) {
    const selected = select.value;
    select.textContent = "";

    const none = document.createElement("option");
    none.value = "";
    none.textContent = "None";
    select.appendChild(none);

    priorities.forEach((priority) => {
      const option = document.createElement("option");
      option.value = priority;
      option.textContent = priority;
      select.appendChild(option);
    });

    select.value = priorities.includes(selected) ? selected : "";
  }

  // Shown only once the backend confirms the current sprint and/or latest proposal tag.
  function loadSprintOptions(row, select) {
    chrome.runtime.sendMessage({ action: "get-sprint-options" }, (response) => {
      if (chrome.runtime.lastError || !response || !response.ok) {
        return;
      }

      const options = [];
      if (response.current) {
        options.push({
          value: "current",
          number: response.current.number,
          label: `Current (${response.current.number})`,
          title: `Adds to ${response.current.name} and tags added-sprint${response.current.number}`
        });
      }
      if (response.proposal) {
        options.push({
          value: "proposal",
          number: response.proposal.number,
          label: `Proposal (${response.proposal.number})`,
          title: `Tags ${response.proposal.name}`
        });
      }
      if (options.length === 0) {
        return;
      }

      select.textContent = "";
      const none = document.createElement("option");
      none.value = "";
      none.textContent = "None";
      select.appendChild(none);

      options.forEach((item) => {
        const option = document.createElement("option");
        option.value = item.value;
        option.textContent = item.label;
        option.title = item.title;
        option.dataset.number = String(item.number);
        select.appendChild(option);
      });

      row.style.display = "flex";
    });
  }

  function loadPriorities(select) {
    chrome.runtime.sendMessage({ action: "get-priorities" }, (response) => {
      if (chrome.runtime.lastError || !response || !response.ok) {
        return;
      }
      setPriorityOptions(select, response.priorities);
    });
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
    const sender = findSender();
    const bodyText = getBodyText();

    const subject = subjectEl ? subjectEl.textContent.trim() : "";
    const from = sender ? sender.name || sender.email : "";
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

  // Inline images in the open messages plus image attachments, skipping signature blocks.
  function collectImageSources() {
    const sources = [];
    const seen = new Set();
    const add = (url, name) => {
      if (!url || seen.has(url)) {
        return;
      }
      seen.add(url);
      sources.push({ url, name });
    };

    const bodies = Array.from(document.querySelectorAll("div.a3s")).filter((node) =>
      isVisible(node)
    );
    bodies.forEach((body) => {
      body.querySelectorAll("img").forEach((img) => {
        if (img.closest(SIGNATURE_SELECTOR)) {
          return;
        }
        // Cheap pre-filter for icons already rendered in the page; real size is checked after download.
        if (
          img.complete &&
          img.naturalWidth > 0 &&
          (img.naturalWidth < MIN_IMAGE_SIDE || img.naturalHeight < MIN_IMAGE_SIDE)
        ) {
          return;
        }
        add(img.currentSrc || img.src, img.getAttribute("alt") || "");
      });
    });

    const messages = Array.from(document.querySelectorAll("div[data-message-id]")).filter(
      (node) => isVisible(node)
    );
    messages.forEach((message) => {
      message.querySelectorAll("[download_url]").forEach((node) => {
        // Format: "<mime type>:<file name>:<url>"
        const value = node.getAttribute("download_url") || "";
        const first = value.indexOf(":");
        const second = value.indexOf(":", first + 1);
        if (first < 0 || second < 0) {
          return;
        }
        const mimeType = value.slice(0, first);
        if (!mimeType.startsWith("image/")) {
          return;
        }
        add(value.slice(second + 1), value.slice(first + 1, second));
      });
    });

    return sources;
  }

  async function downloadImage(url) {
    try {
      const response = await fetch(url, { credentials: "include" });
      if (response.ok) {
        return await response.blob();
      }
    } catch (error) {
      // Cross-origin images (e.g. googleusercontent.com) are fetched by the background worker instead.
    }

    const response = await sendMessageAsync("fetch-image", { url });
    if (!response.ok) {
      throw new Error(response.error || "download failed");
    }
    return base64ToBlob(response.data, response.type);
  }

  async function imageDimensions(blob) {
    const bitmap = await createImageBitmap(blob);
    const dimensions = { width: bitmap.width, height: bitmap.height };
    bitmap.close();
    return dimensions;
  }

  async function hashBlob(blob) {
    const digest = await crypto.subtle.digest("SHA-256", await blob.arrayBuffer());
    return Array.from(new Uint8Array(digest))
      .map((byte) => byte.toString(16).padStart(2, "0"))
      .join("");
  }

  function uniqueImageName(rawName, mimeType, position, usedNames) {
    const extension = (mimeType.split("/")[1] || "png").replace("jpeg", "jpg").replace(/[^a-z0-9]/g, "");
    let base = (rawName || "").replace(/\.[A-Za-z0-9]+$/, "").replace(/[^A-Za-z0-9_-]+/g, "_");
    base = base.replace(/^_+|_+$/g, "").slice(0, 60) || `image-${position}`;

    let name = `${base}.${extension}`;
    let counter = 2;
    while (usedNames.has(name)) {
      name = `${base}-${counter}.${extension}`;
      counter++;
    }
    usedNames.add(name);
    return name;
  }

  function formatBytes(bytes) {
    if (bytes < 1024 * 1024) {
      return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  function arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = "";
    for (let i = 0; i < bytes.length; i += 0x8000) {
      binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    }
    return btoa(binary);
  }

  function base64ToBlob(base64, type) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return new Blob([bytes], { type: type || "" });
  }

  function sendMessageAsync(action, payload) {
    return new Promise((resolve) => {
      chrome.runtime.sendMessage({ action, payload }, (response) => {
        if (chrome.runtime.lastError) {
          resolve({ ok: false, error: chrome.runtime.lastError.message });
          return;
        }
        resolve(response || { ok: false, error: "No response from background." });
      });
    });
  }

  // Email addresses shown in the open messages' headers (from, to, cc) that are not internal.
  function findExternalParticipants() {
    const messages = Array.from(document.querySelectorAll("div[data-message-id]")).filter(
      (node) => isVisible(node)
    );
    const scopes = messages.length > 0 ? messages : [document];
    const external = new Set();

    scopes.forEach((scope) => {
      scope.querySelectorAll("[email]").forEach((node) => {
        const email = (node.getAttribute("email") || "").trim().toLowerCase();
        if (email.includes("@") && !isInternalEmail(email)) {
          external.add(email);
        }
      });
    });

    return Array.from(external);
  }

  function isInternalEmail(email) {
    const domain = email.split("@").pop();
    return INTERNAL_DOMAINS.some((internal) => domain === internal || domain.endsWith(`.${internal}`));
  }

  // Sender of the newest open message in the thread (the one being replied to),
  // falling back to the first sender on the page.
  function findSender() {
    const messages = Array.from(
      document.querySelectorAll("div[data-message-id]")
    ).filter((node) => isVisible(node));
    const scopes = messages.length > 0 ? [messages[messages.length - 1], document] : [document];

    for (const scope of scopes) {
      const el =
        scope.querySelector("span.gD[email]") ||
        scope.querySelector("span.gD") ||
        scope.querySelector("span[email]");
      if (el) {
        const name = (el.getAttribute("name") || el.textContent || "").trim();
        const email = (el.getAttribute("email") || "").trim();
        if (name || email) {
          return { name, email };
        }
      }
    }

    return null;
  }

  async function copyText(text) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (error) {
      const helper = document.createElement("textarea");
      helper.value = text;
      helper.style.position = "fixed";
      helper.style.opacity = "0";
      document.body.appendChild(helper);
      helper.select();
      const copied = document.execCommand("copy");
      helper.remove();
      return copied;
    }
  }

  function findReplyAllButton() {
    const messages = Array.from(
      document.querySelectorAll("div[data-message-id]")
    ).filter((node) => isVisible(node));
    const lastMessage = messages[messages.length - 1];

    const candidates = [
      // Thread footer "Reply all" button.
      ...document.querySelectorAll("span.ams.bkI"),
      ...(lastMessage
        ? lastMessage.querySelectorAll('[aria-label="Reply all"], [data-tooltip="Reply all"]')
        : []),
      ...document.querySelectorAll('[role="button"][aria-label="Reply all"]')
    ];

    return candidates.find((node) => isVisible(node)) || null;
  }

  function findReplyBodies() {
    return Array.from(
      document.querySelectorAll('div[contenteditable="true"][role="textbox"]')
    ).filter((node) => isVisible(node));
  }

  async function insertIntoReplyAll(text) {
    const button = findReplyAllButton();
    if (!button) {
      return { ok: false, error: "Couldn't find Gmail's Reply all button." };
    }

    const existing = new Set(findReplyBodies());
    button.click();

    const body = await waitFor(() => {
      const bodies = findReplyBodies();
      return bodies.find((node) => !existing.has(node)) || bodies[bodies.length - 1] || null;
    }, 5000);

    if (!body) {
      return { ok: false, error: "Reply all opened, but the reply box wasn't found." };
    }

    body.focus();
    const selection = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(body);
    range.collapse(true);
    selection.removeAllRanges();
    selection.addRange(range);

    const inserted = document.execCommand("insertText", false, `${text}\n\n`);
    if (!inserted) {
      return { ok: false, error: "Couldn't type into the reply box." };
    }

    return { ok: true };
  }

  function waitFor(check, timeoutMs) {
    return new Promise((resolve) => {
      const startedAt = Date.now();
      const tick = () => {
        const result = check();
        if (result || Date.now() - startedAt >= timeoutMs) {
          resolve(result || null);
          return;
        }
        setTimeout(tick, 150);
      };
      tick();
    });
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
