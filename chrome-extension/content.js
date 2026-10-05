(() => {
  const WRAPPER_ID = "yt-ticket-wrapper";
  const CONTAINER_ID = "yt-ticket-buttons";
  const STATUS_ID = "yt-ticket-status";
  const MODAL_ID = "yt-ticket-modal";
  const OVERLAY_ID = "yt-ticket-overlay";
  const MINIMIZED_ID = "yt-ticket-minimized";
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
      if (modalState.isMinimized && modalState.refreshMinimized) {
        modalState.refreshMinimized();
      }
      setFloatingVisible(false);
    } else {
      setFloatingVisible(true);
    }
  }

  // Terminal look in YouTrack colours: blue = actions/selection, pink = prompts/headings, purple = flags/tags.
  const STYLE_ID = "ytx-styles";
  const FONT_LINK_ID = "ytx-font";
  const STYLES = `
.ytx, .ytx * { box-sizing: border-box; }
.ytx { color-scheme: dark; font-family: "JetBrains Mono", "SF Mono", Menlo, Consolas, ui-monospace, monospace; font-size: 13px; line-height: 1.55; color: #D7D9DE; -webkit-font-smoothing: antialiased; text-align: left; }
.ytx button, .ytx input, .ytx textarea { font-family: inherit; font-size: inherit; line-height: inherit; color: inherit; margin: 0; }
.ytx button { cursor: pointer; background: transparent; border: 1px solid #45474F; border-radius: 0; padding: 2px 10px; color: #2EA8FF; }
.ytx button:disabled { cursor: not-allowed; opacity: 0.5; }
.ytx button:focus-visible, .ytx input:focus-visible, .ytx textarea:focus-visible { outline: 2px solid #2EA8FF; outline-offset: 1px; }
.ytx a { color: #2EA8FF; }
.ytx a:hover { color: #8FD0FF; }
.ytx .ytx-dim { color: #6A6D75; }
.ytx .ytx-muted { color: #9A9EA6; }
.ytx .ytx-prompt { color: #FF4F9A; }
.ytx .ytx-flag { color: #A796FF; }
.ytx .ytx-badge { display: block; flex: 0 0 auto; width: 26px; height: 19px; }

.ytx-overlay { position: fixed; inset: 0; z-index: 10001; display: flex; align-items: center; justify-content: center; background: rgba(5, 6, 8, 0.6); }
.ytx-window { width: min(94vw, 680px); max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; background: #16171B; border: 1px solid #34363D; box-shadow: 0 18px 48px rgba(0, 0, 0, 0.5); }
.ytx-titlebar { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 14px; background: #1E1F24; border-bottom: 1px solid #34363D; color: #9A9EA6; }
.ytx-titlebar-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
.ytx .ytx-titlebar button { border-color: #34363D; }
.ytx-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 16px; padding: 18px 22px; }
.ytx-body > * { flex-shrink: 0; }
.ytx-cmd { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.ytx .ytx-chip { border-color: #45474F; }
.ytx .ytx-chip[aria-pressed="true"] { background: #2EA8FF; border-color: #2EA8FF; color: #0B0D10; font-weight: 700; }
.ytx-tabs { display: flex; border-bottom: 1px solid #34363D; }
.ytx .ytx-tab { border: none; border-bottom: 2px solid transparent; padding: 6px 14px; color: #9A9EA6; }
.ytx .ytx-tab[aria-selected="true"] { color: #F4F5F7; border-bottom-color: #FF4F9A; }
.ytx-section { display: flex; flex-direction: column; gap: 14px; }
.ytx-field { display: flex; flex-direction: column; gap: 6px; }
.ytx-inputline { display: flex; align-items: baseline; gap: 8px; padding-bottom: 6px; border-bottom: 1px dashed #45474F; }
.ytx .ytx-inputline input { flex: 1 1 auto; min-width: 0; padding: 0; background: transparent; border: none; outline: none; color: #F4F5F7; }
.ytx-grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.ytx .ytx-fieldset { min-width: 0; margin: 0; padding: 8px 12px 10px; display: flex; flex-direction: column; gap: 2px; border: 1px solid #34363D; }
.ytx .ytx-fieldset legend { padding: 0 6px; color: #A796FF; }
.ytx-radio { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.ytx .ytx-radio input { margin: 0; accent-color: #2EA8FF; }
.ytx .ytx-radio input:checked + span { color: #F4F5F7; }
.ytx .ytx-code { width: 100%; padding: 12px 14px; background: #1B1C21; border: 1px solid #34363D; color: #D7D9DE; outline: none; resize: vertical; tab-size: 2; }
.ytx .ytx-code::placeholder, .ytx .ytx-inputline input::placeholder { color: #6A6D75; }
.ytx-files { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.ytx-file { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 8px; border: 1px solid #45474F; cursor: pointer; transition: opacity 0.15s; }
.ytx-file[data-checked="true"] { border-color: #2EA8FF; }
.ytx-file[data-checked="false"] { opacity: 0.45; }
.ytx .ytx-file input { flex: 0 0 auto; margin: 0; accent-color: #2EA8FF; }
.ytx .ytx-file img { display: block; flex: 0 0 auto; width: 72px; height: 48px; object-fit: cover; border: 1px solid #45474F; }
.ytx-file-meta { display: flex; flex-direction: column; min-width: 0; }
.ytx-file-name { color: #F4F5F7; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ytx-log { display: flex; flex-direction: column; gap: 4px; }
.ytx-log:empty { display: none; }
.ytx-tag { display: inline-block; margin-right: 8px; padding: 0 6px; font-weight: 700; color: #0B0D10; }
.ytx-tag-ok { background: #2EA8FF; }
.ytx-tag-err { background: #FF6B6B; }
.ytx-tag-warn { background: #FFB454; }
.ytx-tag-info { background: #45474F; color: #F4F5F7; }
.ytx-warnbox { padding: 8px 10px; border: 1px solid #FFB454; background: #2A2218; color: #FFD9A8; }
.ytx-source { display: flex; gap: 8px; min-width: 0; color: #9A9EA6; }
.ytx-source-text { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #D7D9DE; }
.ytx .ytx-warnbox .ytx-btn { min-height: 32px; padding: 4px 10px; }
.ytx-reply { display: flex; flex-direction: column; gap: 8px; padding-top: 14px; border-top: 1px dashed #45474F; }
.ytx-row { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.ytx .ytx-btn { min-height: 40px; padding: 8px 14px; }
.ytx .ytx-btn-primary { background: #2EA8FF; border-color: #2EA8FF; color: #0B0D10; font-weight: 700; }
.ytx .ytx-btn-warn { background: #FFB454; border-color: #FFB454; color: #0B0D10; font-weight: 700; }
.ytx-footer { flex-shrink: 0; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 22px; background: #1E1F24; border-top: 1px solid #34363D; }
.ytx-cursor { display: inline-block; width: 0.6em; background: #2EA8FF; animation: ytx-blink 1s steps(1) infinite; }
@keyframes ytx-blink { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }
.ytx-preview { position: fixed; display: none; z-index: 10002; pointer-events: none; object-fit: contain; background: #16171B; border: 1px solid #2EA8FF; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5); }
.ytx-launcher { position: fixed; bottom: 24px; left: 24px; z-index: 9999; width: 240px; display: flex; flex-direction: column; gap: 8px; padding: 8px 10px 10px; background: #16171B; border: 1px solid #34363D; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35); }
.ytx-launcher-handle { display: flex; align-items: center; gap: 10px; color: #9A9EA6; font-size: 12px; cursor: move; user-select: none; }
.ytx-launcher-actions { display: flex; gap: 6px; }
.ytx .ytx-launcher-actions button { flex: 1 1 0; padding: 6px 8px; }
.ytx .ytx-launcher-actions button:hover { background: #2EA8FF; border-color: #2EA8FF; color: #0B0D10; }
.ytx-mini { position: fixed; left: 24px; bottom: 24px; z-index: 10001; width: 260px; display: none; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; background: #16171B; border: 1px solid #34363D; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35); cursor: pointer; }
@media (max-width: 560px) { .ytx-grid2, .ytx-files { grid-template-columns: minmax(0, 1fr); } .ytx-footer > .ytx-dim { display: none; } .ytx-footer { justify-content: flex-end; } }
`;

  function ensureStyles() {
    if (!document.getElementById(FONT_LINK_ID)) {
      // If Gmail's CSP blocks Google Fonts, the stack falls back to the system monospace font.
      const font = document.createElement("link");
      font.id = FONT_LINK_ID;
      font.rel = "stylesheet";
      font.href = "https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap";
      document.head.appendChild(font);
    }
    if (!document.getElementById(STYLE_ID)) {
      const style = document.createElement("style");
      style.id = STYLE_ID;
      style.textContent = STYLES;
      document.head.appendChild(style);
    }
  }

  // Small DOM builder: h("div", "class", { text, title, attrs: {...}, ...props }, [children]).
  function h(tag, className, props = {}, children = []) {
    const node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    Object.entries(props).forEach(([key, value]) => {
      if (key === "text") {
        node.textContent = value;
      } else if (key === "attrs") {
        Object.entries(value).forEach(([name, attrValue]) => node.setAttribute(name, attrValue));
      } else {
        node[key] = value;
      }
    });
    children.forEach((child) => {
      if (child === null || child === undefined || child === false) {
        return;
      }
      node.appendChild(typeof child === "string" ? document.createTextNode(child) : child);
    });
    return node;
  }

  function setTextIfChanged(node, text) {
    if (node.textContent !== text) {
      node.textContent = text;
    }
  }

  // HiJiffy arcs-and-dots mark, pink with a purple offset echo (same as backend/public/logo.svg).
  // Built with createElementNS because Gmail enforces Trusted Types on innerHTML.
  const LOGO_PATHS = [
    "m27.9496 30.9707c-8.5516 0-15.51-6.9214-15.51-15.4307 0-1.7178 1.3993-3.1094 3.1257-3.1094 1.7263 0 3.1256 1.3916 3.1256 3.1094 0 5.0793 4.1534 9.2118 9.2587 9.2118 5.1054 0 9.2588-4.1325 9.2588-9.2118 0-1.7178 1.3993-3.1094 3.1256-3.1094 1.7264 0 3.1257 1.3916 3.1257 3.1094 0 8.5086-6.9577 15.4307-15.5101 15.4307z",
    "m40.3581 9.34733c1.7134 0 3.1023-1.3855 3.1023-3.0946s-1.3889-3.09459-3.1023-3.09459c-1.7133 0-3.1023 1.38549-3.1023 3.09459s1.389 3.0946 3.1023 3.0946z",
    "m15.51 0c8.5517 0 15.5101 6.92136 15.5101 15.4306 0 1.7178-1.3993 3.1094-3.1257 3.1094-1.7263 0-3.1256-1.3916-3.1256-3.1094 0-5.0793-4.1534-9.21176-9.2588-9.21176-5.1053 0-9.2587 4.13246-9.2587 9.21176 0 1.7178-1.39932 3.1094-3.12565 3.1094s-3.12565-1.3923-3.12565-3.1094c0-8.50924 6.95768-15.4306 15.51-15.4306z",
    "m3.10229 27.8125c1.71335 0 3.1023-1.3855 3.1023-3.0946s-1.38895-3.0946-3.1023-3.0946-3.10229 1.3855-3.10229 3.0946 1.38894 3.0946 3.10229 3.0946z",
  ];

  function badge() {
    const ns = "http://www.w3.org/2000/svg";
    const svg = document.createElementNS(ns, "svg");
    svg.setAttribute("class", "ytx-badge");
    svg.setAttribute("viewBox", "0 0 48 35");
    svg.setAttribute("aria-hidden", "true");
    [["#6B57FF", "translate(3.5 3.5)"], ["#FF318C", null]].forEach(([fill, transform]) => {
      const group = document.createElementNS(ns, "g");
      group.setAttribute("fill", fill);
      if (transform) group.setAttribute("transform", transform);
      LOGO_PATHS.forEach((d) => {
        const path = document.createElementNS(ns, "path");
        path.setAttribute("d", d);
        group.appendChild(path);
      });
      svg.appendChild(group);
    });
    return svg;
  }

  function ensureButtons() {
    if (document.getElementById(WRAPPER_ID)) {
      return;
    }
    ensureStyles();

    const dragHandle = h("div", "ytx-launcher-handle", { title: "Drag to move" }, [
      badge(),
      h("span", "", { text: "yt-ticket" }),
      h("span", "ytx-dim", { text: "~/gmail" })
    ]);

    const container = h("div", "ytx-launcher-actions", { id: CONTAINER_ID }, [
      launcherButton("task"),
      launcherButton("spike")
    ]);

    const wrapper = h("div", "ytx ytx-launcher", { id: WRAPPER_ID }, [
      dragHandle,
      container,
      h("div", "ytx-muted", { id: STATUS_ID })
    ]);

    document.body.appendChild(wrapper);
    enableDragging(wrapper, dragHandle);
  }

  function launcherButton(type) {
    const button = h("button", "", {
      type: "button",
      text: `+ ${type}`,
      title: `Create a ${type} from this email`
    });
    button.addEventListener("click", () => openModal(type));
    return button;
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

  // A fieldset of radio buttons with the same small API the form needs (value, number, disabled).
  function createRadioGroup(name, legendText) {
    const list = h("div", "");
    const fieldset = h("fieldset", "ytx-fieldset", {}, [h("legend", "", { text: legendText }), list]);
    let inputs = [];

    return {
      fieldset,
      setOptions(options) {
        const previous = this.value;
        list.textContent = "";
        inputs = options.map((option) => {
          const input = h("input", "", { type: "radio", name, value: option.value });
          if (option.number !== undefined) {
            input.dataset.number = String(option.number);
          }
          list.appendChild(
            h("label", "ytx-radio", { title: option.title || "" }, [
              input,
              h("span", "", {}, [option.label, option.hint ? h("span", "ytx-flag", { text: ` ${option.hint}` }) : null])
            ])
          );
          return input;
        });
        const selected = inputs.find((input) => input.value === previous) || inputs[0];
        if (selected) {
          selected.checked = true;
        }
      },
      get value() {
        const checked = inputs.find((input) => input.checked);
        return checked ? checked.value : "";
      },
      get number() {
        const checked = inputs.find((input) => input.checked);
        return checked && checked.dataset.number ? Number(checked.dataset.number) : null;
      },
      set disabled(isDisabled) {
        inputs.forEach((input) => {
          input.disabled = isDisabled;
        });
      }
    };
  }

  function openModal(initialType) {
    if (modalState) {
      if (modalState.isMinimized && modalState.restore) {
        modalState.restore();
      }
      return;
    }

    ensureStyles();
    modalState = { isMinimized: false };
    setFloatingVisible(false);

    let type = initialType;
    let currentMode = "manual";
    // Everything the ticket needs from the email is read once, here, so it can't mix two emails.
    const snapshot = captureEmailSnapshot();
    let touched = false;
    let created = false;
    let draftLabels = [];
    let draftEmail = null;
    let imageItems = [];
    const groupId = Date.now();

    // Title bar
    const minimizeButton = h("button", "", {
      type: "button",
      text: "[_]",
      title: "Minimize",
      attrs: { "aria-label": "Minimize" }
    });
    const titleText = h("span", "");
    const titlebar = h("div", "ytx-titlebar", {}, [
      h("div", "ytx-titlebar-left", {}, [badge(), titleText]),
      minimizeButton
    ]);

    // $ yt create --type=task|spike
    const taskChip = h("button", "ytx-chip", { type: "button", text: "task" });
    const spikeChip = h("button", "ytx-chip", { type: "button", text: "spike" });
    const sourceLine = h("div", "ytx-source", {}, [
      h("span", "ytx-flag", { text: "source:" }),
      h("span", "ytx-source-text", {
        text: snapshot.subject
          ? `${snapshot.subject}${snapshot.senderName ? ` · ${snapshot.senderName}` : ""}`
          : "(no email open)",
        title: snapshot.subject
      })
    ]);

    const mismatchText = h("div", "");
    const loadEmailButton = h("button", "ytx-btn ytx-btn-warn", { type: "button", text: "load this email" });
    const keepDraftButton = h("button", "ytx-btn", { type: "button", text: "keep draft" });
    const mismatchBanner = h("div", "ytx-warnbox", {}, [
      mismatchText,
      h("div", "ytx-row", {}, [loadEmailButton, keepDraftButton])
    ]);
    mismatchBanner.style.display = "none";
    mismatchBanner.style.flexDirection = "column";
    mismatchBanner.style.gap = "8px";

    const commandLine = h("div", "ytx-cmd", {}, [
      h("span", "ytx-prompt", { text: "$" }),
      h("span", "", { text: "yt create" }),
      h("span", "ytx-flag", { text: "--type=" }),
      taskChip,
      spikeChip
    ]);

    // Tabs: the ticket fields, or the source email used to generate a draft
    const manualButton = h("button", "ytx-tab", { type: "button", text: "ticket.md", attrs: { role: "tab" } });
    const aiButton = h("button", "ytx-tab", { type: "button", text: "source.eml + ai", attrs: { role: "tab" } });
    const tabs = h("div", "ytx-tabs", { attrs: { role: "tablist" } }, [manualButton, aiButton]);

    // Ticket fields
    const summaryInput = h("input", "", {
      type: "text",
      id: `ytx-summary-${groupId}`,
      placeholder: "one line that says what's wrong"
    });
    const summaryField = h("div", "ytx-field", {}, [
      h("label", "ytx-muted", { text: "# summary", htmlFor: summaryInput.id }),
      h("div", "ytx-inputline", {}, [h("span", "ytx-prompt", { text: ">" }), summaryInput])
    ]);

    const priorityGroup = createRadioGroup(`ytx-priority-${groupId}`, "--priority");
    setPriorityOptions(priorityGroup, DEFAULT_PRIORITIES);
    loadPriorities(priorityGroup);

    const sprintGroup = createRadioGroup(`ytx-sprint-${groupId}`, "--sprint");
    sprintGroup.fieldset.style.display = "none";
    loadSprintOptions(sprintGroup);

    const descriptionInput = h("textarea", "ytx-code", {
      id: `ytx-description-${groupId}`,
      rows: 14,
      placeholder: "## Context\n...",
      spellcheck: false
    });
    const descriptionField = h("div", "ytx-field", {}, [
      h("label", "ytx-muted", { text: "# description.md", htmlFor: descriptionInput.id }),
      descriptionInput
    ]);

    const imagesHeader = h("div", "ytx-muted");
    const imagesGrid = h("div", "ytx-files");
    const imagesNote = h("div", "ytx-dim");
    const imagesSection = h("div", "ytx-field", {}, [imagesHeader, imagesGrid, imagesNote]);
    imagesSection.style.display = "none";

    const imagePreview = h("img", "ytx-preview", { alt: "" });

    const manualSection = h("div", "ytx-section", {}, [
      summaryField,
      h("div", "ytx-grid2", {}, [priorityGroup.fieldset, sprintGroup.fieldset]),
      descriptionField,
      imagesSection
    ]);

    // Source email + AI
    const aiBodyInput = h("textarea", "ytx-code", {
      id: `ytx-source-${groupId}`,
      rows: 14,
      value: snapshot.body,
      spellcheck: false
    });
    const aiSection = h("div", "ytx-field", {}, [
      h("label", "ytx-muted", {
        text: "# source.eml (trim it if you like, then generate a draft you can review)",
        htmlFor: aiBodyInput.id
      }),
      aiBodyInput
    ]);

    // Log lines
    const status = h("div", "ytx-log", { attrs: { "aria-live": "polite" } });
    const uploadStatus = h("div", "ytx-log", { attrs: { "aria-live": "polite" } });

    // Reply to the thread
    const replyInput = h("textarea", "ytx-code", { id: `ytx-reply-${groupId}`, rows: 9 });
    const replyWarning = h("div", "ytx-warnbox");
    replyWarning.style.display = "none";
    const replyAllButton = h("button", "ytx-btn ytx-btn-primary", { type: "button", text: "reply all" });
    const copyButton = h("button", "ytx-btn", { type: "button", text: "copy" });
    const replyStatus = h("span", "ytx-muted", { attrs: { "aria-live": "polite" } });
    const replyPanel = h("div", "ytx-reply", {}, [
      h("label", "ytx-muted", { text: "# reply.txt (paste into the thread)", htmlFor: replyInput.id }),
      replyInput,
      replyWarning,
      h("div", "ytx-row", {}, [replyAllButton, copyButton, replyStatus])
    ]);
    replyPanel.style.display = "none";

    // Footer
    const cancelButton = h("button", "ytx-btn", { type: "button", text: "[esc] abort" });
    const submitButton = h("button", "ytx-btn ytx-btn-primary", { type: "button" });
    const footer = h("div", "ytx-footer", {}, [
      h("span", "ytx-dim", { text: "no tickets were harmed yet" }),
      h("div", "ytx-row", {}, [cancelButton, submitButton])
    ]);

    const body = h("div", "ytx-body", {}, [
      mismatchBanner,
      sourceLine,
      commandLine,
      tabs,
      manualSection,
      aiSection,
      status,
      uploadStatus,
      replyPanel
    ]);
    const modal = h("div", "ytx-window", {
      id: MODAL_ID,
      attrs: { role: "dialog", "aria-modal": "true", "aria-label": "Create YouTrack ticket" }
    }, [titlebar, body, footer]);
    const overlay = h("div", "ytx ytx-overlay", { id: OVERLAY_ID }, [modal, imagePreview]);
    document.body.appendChild(overlay);

    // Minimized bar
    const minimizedText = h("span", "");
    const minimizedAction = h("button", "", {
      type: "button",
      text: "[open]",
      title: "Open",
      attrs: { "aria-label": "Open" }
    });
    const minimizedBar = h("div", "ytx ytx-mini", { id: MINIMIZED_ID }, [
      h("div", "ytx-titlebar-left", {}, [badge(), minimizedText]),
      minimizedAction
    ]);
    document.body.appendChild(minimizedBar);

    const onKeyDown = (event) => {
      if (modalState && modalState.isMinimized) {
        return;
      }
      if (event.key === "Escape") {
        closeModal();
        return;
      }
      // Cmd/Ctrl+Enter runs the main action, but only while typing inside this form.
      if (event.key === "Enter" && (event.metaKey || event.ctrlKey) && overlay.contains(event.target)) {
        event.preventDefault();
        event.stopPropagation();
        submitButton.click();
      }
    };

    function closeModal() {
      document.removeEventListener("keydown", onKeyDown, true);
      imageItems.forEach((item) => URL.revokeObjectURL(item.previewUrl));
      overlay.remove();
      minimizedBar.remove();
      modalState = null;
      setFloatingVisible(true);
    }

    function setLoading(isLoading, loadingText) {
      [submitButton, cancelButton, minimizeButton, manualButton, aiButton, taskChip, spikeChip].forEach((button) => {
        button.disabled = isLoading;
      });
      summaryInput.disabled = isLoading;
      descriptionInput.disabled = isLoading;
      aiBodyInput.disabled = isLoading;
      priorityGroup.disabled = isLoading;
      sprintGroup.disabled = isLoading;
      imageItems.forEach((item) => {
        item.checkbox.disabled = isLoading;
      });
      submitButton.textContent = isLoading ? loadingText : submitLabel();
    }

    function logLine(target, kind, parts) {
      const labels = { ok: "OK", err: "ERR", warn: "WARN", info: ".." };
      target.appendChild(
        h("div", "", {}, [h("span", `ytx-tag ytx-tag-${kind}`, { text: labels[kind] }), ...parts])
      );
    }

    function setStatusMessage(message, kind) {
      status.textContent = "";
      logLine(status, kind === "error" ? "err" : "info", [
        message,
        kind === "error" ? null : h("span", "ytx-cursor", { text: " ", attrs: { "aria-hidden": "true" } })
      ]);
    }

    function setStatusSuccess(issueId, url, warning) {
      status.textContent = "";
      logLine(status, "ok", [
        `created ${issueId} → `,
        h("a", "", { href: url, text: "open in youtrack", target: "_blank", rel: "noopener noreferrer" })
      ]);
      if (warning) {
        logLine(status, "warn", [warning]);
      }
    }

    function setUploadStatus(kind, message) {
      uploadStatus.textContent = "";
      logLine(uploadStatus, kind, [message]);
    }

    function showReplyPanel(message) {
      replyInput.value = message;
      replyStatus.textContent = "";
      updateReplyAllWarning();
      replyPanel.style.display = "flex";
      replyPanel.scrollIntoView({ block: "nearest" });
    }

    function updateReplyAllWarning() {
      const external = snapshot.external;

      if (external.length === 0) {
        replyWarning.style.display = "none";
        replyAllButton.textContent = "reply all";
        replyAllButton.className = "ytx-btn ytx-btn-primary";
        return;
      }

      const listed = external.slice(0, 5).join(", ");
      const more = external.length > 5 ? ` and ${external.length - 5} more` : "";
      replyWarning.textContent =
        `[WARN] This thread includes people outside ${INTERNAL_DOMAINS.join(", ")}: ${listed}${more}. ` +
        "Reply all would send them this message, including the internal YouTrack link. Check the recipients before sending.";
      replyWarning.style.display = "block";
      replyAllButton.textContent = "reply all (includes external!)";
      replyAllButton.className = "ytx-btn ytx-btn-warn";
    }

    function setReplyStatus(message, kind) {
      replyStatus.textContent = message;
      replyStatus.style.color = kind === "error" ? "#FF6B6B" : "#2EA8FF";
    }

    copyButton.addEventListener("click", async () => {
      const copied = await copyText(replyInput.value);
      setReplyStatus(
        copied ? "copied." : "copy failed. select the text and copy it manually.",
        copied ? "info" : "error"
      );
    });

    replyAllButton.addEventListener("click", async () => {
      replyAllButton.disabled = true;
      setReplyStatus("opening reply all...", "info");
      const result = await replyAllOnSourceEmail(replyInput.value);
      replyAllButton.disabled = false;

      if (result.ok) {
        // Leave the draft for the user to review and send themselves.
        closeModal();
        return;
      }
      setReplyStatus(`${result.error} Use copy and paste it instead.`, "error");
    });

    function submitLabel() {
      return currentMode === "manual" ? "[⌘⏎] create ticket" : "[⌘⏎] generate draft";
    }

    function setMode(mode) {
      currentMode = mode;
      const isManual = currentMode === "manual";
      manualSection.style.display = isManual ? "flex" : "none";
      aiSection.style.display = isManual ? "none" : "flex";
      manualButton.setAttribute("aria-selected", isManual ? "true" : "false");
      aiButton.setAttribute("aria-selected", isManual ? "false" : "true");
      submitButton.textContent = submitLabel();
      updateTitles();
    }

    function setType(nextType) {
      type = nextType;
      taskChip.setAttribute("aria-pressed", type === "task" ? "true" : "false");
      spikeChip.setAttribute("aria-pressed", type === "spike" ? "true" : "false");
      updateTitles();
    }

    function updateTitles() {
      setTextIfChanged(titleText, `yt-ticket — new ${type} — ${currentMode === "manual" ? "ticket.md" : "source.eml"}`);
      const otherEmail = isOnDifferentEmail();
      // Only write when the text changes: this runs from the page's MutationObserver, and
      // writing unchanged text would trigger the observer again in an endless loop.
      setTextIfChanged(minimizedText, `yt-ticket · ${type} · ${created ? "created" : "draft"}${otherEmail ? " · other email" : ""}`);
      const title = otherEmail ? `This form is for: ${snapshot.subject}` : "";
      if (minimizedBar.title !== title) {
        minimizedBar.title = title;
      }
    }

    function isOnDifferentEmail() {
      const key = currentEmailKey();
      return key !== null && key !== snapshot.key;
    }

    function startOverOnCurrentEmail() {
      const nextType = type;
      closeModal();
      openModal(nextType);
    }

    function checkSourceEmail() {
      if (!isOnDifferentEmail()) {
        mismatchBanner.style.display = "none";
        return true;
      }
      if (!touched && !created) {
        // Nothing to lose: rebuild the form from the email that is open now.
        startOverOnCurrentEmail();
        return false;
      }

      const current = extractEmail();
      mismatchText.textContent =
        `[WARN] you're now on a different email: "${current ? current.subject : "unknown"}". ` +
        `This ${created ? "ticket" : "draft"} is from "${snapshot.subject}".`;
      loadEmailButton.textContent = created ? "new ticket for this email" : "load this email";
      keepDraftButton.textContent = created ? "keep" : "keep draft";
      mismatchBanner.style.display = "flex";
      body.scrollTop = 0;
      return true;
    }

    async function replyAllOnSourceEmail(text) {
      if (currentEmailKey() !== snapshot.key) {
        // Reply all must go to the email the ticket came from, so navigate back to it first.
        setReplyStatus("going back to the source email...", "info");
        window.location.href = snapshot.threadUrl;
        const back = await waitFor(() => currentEmailKey() === snapshot.key, 6000);
        if (!back) {
          return { ok: false, error: `Couldn't reopen "${snapshot.subject}".` };
        }
      }
      return insertIntoReplyAll(text);
    }

    loadEmailButton.addEventListener("click", () => startOverOnCurrentEmail());
    keepDraftButton.addEventListener("click", () => {
      mismatchBanner.style.display = "none";
    });
    [summaryInput, descriptionInput, aiBodyInput].forEach((input) => {
      input.addEventListener("input", () => {
        touched = true;
      });
    });

    function minimizeModal() {
      modalState.isMinimized = true;
      overlay.style.display = "none";
      minimizedBar.style.display = "flex";
      updateTitles();
      setFloatingVisible(false);
    }

    function restoreModal() {
      if (!checkSourceEmail()) {
        return;
      }
      modalState.isMinimized = false;
      overlay.style.display = "flex";
      minimizedBar.style.display = "none";
      setFloatingVisible(false);
    }

    manualButton.addEventListener("click", () => setMode("manual"));
    aiButton.addEventListener("click", () => setMode("ai"));
    taskChip.addEventListener("click", () => setType("task"));
    spikeChip.addEventListener("click", () => setType("spike"));

    minimizeButton.addEventListener("click", () => {
      if (!minimizeButton.disabled) {
        minimizeModal();
      }
    });

    minimizedBar.addEventListener("click", () => restoreModal());
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
        setStatusMessage("summary and description are required.", "error");
        return;
      }

      const payload = { type, mode: "manual", summary, description };
      if (priorityGroup.value) {
        payload.priority = priorityGroup.value;
      }
      if (snapshot.senderName) {
        payload.senderName = snapshot.senderName;
      }
      if (sprintGroup.value) {
        payload.sprint = sprintGroup.value;
        payload.sprintNumber = sprintGroup.number;
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
          `selected images are ${formatBytes(selectedBytes)}; the limit is ${formatBytes(MAX_TOTAL_IMAGE_BYTES)}. untick some images.`,
          "error"
        );
        return;
      }

      setLoading(true, "creating...");
      setStatusMessage("creating ticket in youtrack...", "info");

      sendToBackground("create-ticket", payload, (response) => {
        setStatusSuccess(response.issueId, response.url, response.warning);
        created = true;
        // The ticket exists now; prevent creating a duplicate.
        submitButton.disabled = true;
        submitButton.textContent = "[✓] created";
        taskChip.disabled = true;
        spikeChip.disabled = true;
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
        setUploadStatus("info", `uploading attachments ${i + 1}/${items.length}...`);
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

      uploadStatus.textContent = "";
      if (uploaded.length > 0) {
        logLine(uploadStatus, "ok", [`${uploaded.length} image${uploaded.length === 1 ? "" : "s"} attached.`]);
      }
      if (failed.length > 0) {
        logLine(uploadStatus, "err", [`not attached: ${failed.join("; ")}.`]);
      }
      if (finalizeError) {
        logLine(uploadStatus, "warn", [`attached but not embedded in the description: ${finalizeError}`]);
      }
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
      const sources = snapshot.imageSources;
      if (sources.length === 0) {
        return;
      }

      imagesSection.style.display = "flex";
      imagesHeader.textContent = "$ scanning email for images...";

      let tooLarge = 0;
      let unreadable = 0;
      let skipped = 0;
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
          skipped++;
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
          skipped++;
          continue;
        }

        const hash = await hashBlob(blob);
        if (seenHashes.has(hash)) {
          skipped++;
          continue;
        }
        seenHashes.add(hash);

        items.push({
          blob,
          width: dimensions.width,
          height: dimensions.height,
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

      imagesHeader.textContent = "";
      imagesHeader.appendChild(h("span", "ytx-prompt", { text: "$ " }));
      imagesHeader.appendChild(document.createTextNode("ls -lh ./attachments "));
      imagesHeader.appendChild(
        h("span", "ytx-dim", {
          text: `# ${imageItems.length} found${skipped > 0 ? ` · ${skipped} skipped (logos, icons, duplicates)` : ""}`
        })
      );

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
      const checkbox = h("input", "", {
        type: "checkbox",
        checked: true,
        attrs: { "aria-label": `Attach ${item.name}` }
      });
      const img = h("img", "", { src: item.previewUrl, alt: item.name });
      const tile = h("label", "ytx-file", { title: `${item.name} — click to include or exclude` }, [
        checkbox,
        img,
        h("span", "ytx-file-meta", {}, [
          h("span", "ytx-file-name", { text: item.name }),
          h("span", "ytx-muted", { text: `${formatBytes(item.blob.size)} · ${item.width}x${item.height}` })
        ])
      ]);

      const syncState = () => {
        tile.dataset.checked = checkbox.checked ? "true" : "false";
      };
      checkbox.addEventListener("change", syncState);
      img.addEventListener("mouseenter", () => showImagePreview(item.previewUrl, tile));
      img.addEventListener("mouseleave", hideImagePreview);

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
      const body = aiBodyInput.value.trim();
      if (!snapshot.subject) {
        setStatusMessage("unable to read the email.", "error");
        return;
      }
      if (!body) {
        setStatusMessage("source email is empty.", "error");
        return;
      }

      const email = {
        subject: snapshot.subject,
        from: snapshot.from,
        body,
        threadUrl: snapshot.threadUrl
      };

      const startedAt = Date.now();
      setLoading(true, "generating...");
      setStatusMessage("asking the AI for a draft...", "info");

      sendToBackground("preview-ticket", { type, email }, (response) => {
        summaryInput.value = response.summary;
        descriptionInput.value = response.description;
        draftLabels = response.labels || [];
        draftEmail = email;
        touched = true;
        setMode("manual");
        status.textContent = "";
        logLine(status, "ok", [
          `draft generated in ${((Date.now() - startedAt) / 1000).toFixed(1)}s · review it, then ship it `,
          h("span", "ytx-cursor", { text: " ", attrs: { "aria-hidden": "true" } })
        ]);
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
          setStatusMessage("no response from background.", "error");
          return;
        }

        if (!response.ok) {
          setStatusMessage(response.error || "request failed.", "error");
          return;
        }

        onSuccess(response);
      });
    }

    Object.assign(modalState, {
      overlay,
      modal,
      minimizedBar,
      isMinimized: false,
      restore: restoreModal,
      minimize: minimizeModal,
      refreshMinimized: updateTitles,
      close: closeModal
    });

    setType(type);
    setMode(currentMode);
    // Capture phase so Cmd/Ctrl+Enter reaches us before Gmail's own shortcuts.
    document.addEventListener("keydown", onKeyDown, true);
    summaryInput.focus();
    prepareImages();
  }

  function setPriorityOptions(group, priorities) {
    group.setOptions([
      { value: "", label: "none" },
      ...priorities.map((priority) => ({ value: priority, label: priority.toLowerCase() }))
    ]);
  }

  // Shown only once the backend confirms the current sprint and/or latest proposal tag.
  function loadSprintOptions(group) {
    chrome.runtime.sendMessage({ action: "get-sprint-options" }, (response) => {
      if (chrome.runtime.lastError || !response || !response.ok) {
        return;
      }

      const options = [];
      if (response.current) {
        options.push({
          value: "current",
          number: response.current.number,
          label: `current:${response.current.number}`,
          hint: `+added-sprint${response.current.number}`,
          title: `Adds to ${response.current.name} and tags added-sprint${response.current.number}`
        });
      }
      if (response.proposal) {
        options.push({
          value: "proposal",
          number: response.proposal.number,
          label: `proposal:${response.proposal.number}`,
          hint: `+${response.proposal.name}`,
          title: `Tags ${response.proposal.name}`
        });
      }
      if (options.length === 0) {
        return;
      }

      group.setOptions([{ value: "", label: "none" }, ...options]);
      group.fieldset.style.display = "flex";
    });
  }

  function loadPriorities(group) {
    chrome.runtime.sendMessage({ action: "get-priorities" }, (response) => {
      if (chrome.runtime.lastError || !response || !response.ok) {
        return;
      }
      setPriorityOptions(group, response.priorities);
    });
  }

  // Identifies the open email by Gmail's thread id (last segment of the URL hash) plus its subject.
  function currentEmailKey() {
    const subjectEl = findSubjectElement();
    const subject = subjectEl ? subjectEl.textContent.trim() : "";
    if (!subject || !findActiveEmailContainer()) {
      return null;
    }
    const hash = (window.location.hash || "").replace(/^#/, "").split("?")[0];
    const segments = hash.split("/").filter(Boolean);
    const threadId = segments.length > 1 ? segments[segments.length - 1] : "";
    return `${threadId}|${subject}`;
  }

  function captureEmailSnapshot() {
    const email = extractEmail();
    const sender = findSender();
    return {
      key: currentEmailKey(),
      subject: email ? email.subject : "",
      from: email ? email.from : "",
      senderName: sender && sender.name ? sender.name : "",
      body: email ? email.body : "",
      threadUrl: email ? email.threadUrl : window.location.href,
      external: findExternalParticipants(),
      imageSources: collectImageSources()
    };
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
