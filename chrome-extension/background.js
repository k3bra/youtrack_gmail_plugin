const BACKEND_BASE_URL = "http://localhost:8000/api/tickets";
const CLIENT_KEY = "79522295879337155423933099952363QA";

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!message) {
    return;
  }

  let handler = null;
  if (message.action === "create-ticket") {
    handler = handleCreateTicket;
  } else if (message.action === "preview-ticket") {
    handler = handlePreviewTicket;
  } else if (message.action === "get-priorities") {
    handler = handleGetPriorities;
  } else if (message.action === "get-sprint-options") {
    handler = handleGetSprintOptions;
  } else if (message.action === "fetch-image") {
    handler = handleFetchImage;
  } else if (message.action === "upload-attachment-chunk") {
    handler = handleUploadAttachmentChunk;
  } else if (message.action === "finalize-attachments") {
    handler = handleFinalizeAttachments;
  } else {
    return;
  }

  handler(message.payload)
    .then((result) => sendResponse(result))
    .catch((error) => {
      sendResponse({ ok: false, error: error.message || "Request failed." });
    });

  return true;
});

async function handleCreateTicket(payload) {
  const data = await postJson(`${BACKEND_BASE_URL}/from-email`, payload);

  if (!data || !data.issueId || !data.url) {
    throw new Error("Backend response missing issue data.");
  }

  return {
    ok: true,
    issueId: data.issueId,
    url: data.url,
    warning: data.warning || null,
    replyMessage: data.replyMessage || null
  };
}

async function handlePreviewTicket(payload) {
  const data = await postJson(`${BACKEND_BASE_URL}/preview`, payload);

  if (!data || !data.summary || !data.description) {
    throw new Error("Backend response missing draft data.");
  }

  return {
    ok: true,
    summary: data.summary,
    description: data.description,
    labels: Array.isArray(data.labels) ? data.labels : []
  };
}

async function handleGetPriorities() {
  const data = await requestJson("GET", `${BACKEND_BASE_URL}/priorities`);

  if (!data || !Array.isArray(data.priorities)) {
    throw new Error("Backend response missing priorities.");
  }

  return { ok: true, priorities: data.priorities };
}

async function handleGetSprintOptions() {
  const data = await requestJson("GET", `${BACKEND_BASE_URL}/sprint-options`);

  return {
    ok: true,
    current: data ? data.current : null,
    proposal: data ? data.proposal : null
  };
}

// Only Gmail and Google's image hosts; the content script asks for these when it can't fetch cross-origin.
const IMAGE_HOSTS = [/^https:\/\/mail\.google\.com\//, /^https:\/\/[a-z0-9-]+\.googleusercontent\.com\//];

async function handleFetchImage(payload) {
  const url = payload && payload.url;
  if (typeof url !== "string" || !IMAGE_HOSTS.some((pattern) => pattern.test(url))) {
    throw new Error("Image host not allowed.");
  }

  const response = await fetch(url, { credentials: "include" });
  if (!response.ok) {
    throw new Error(`Image download failed (${response.status}).`);
  }

  const buffer = await response.arrayBuffer();
  return {
    ok: true,
    type: response.headers.get("Content-Type") || "",
    data: arrayBufferToBase64(buffer)
  };
}

async function handleUploadAttachmentChunk(payload) {
  const { issueId, ...chunk } = payload;
  const data = await postJson(
    `${BACKEND_BASE_URL}/${encodeURIComponent(issueId)}/attachments/chunks`,
    chunk
  );

  return { ok: true, done: Boolean(data && data.done), name: data ? data.name : null };
}

async function handleFinalizeAttachments(payload) {
  await postJson(
    `${BACKEND_BASE_URL}/${encodeURIComponent(payload.issueId)}/attachments/finalize`,
    { names: payload.names }
  );

  return { ok: true };
}

function arrayBufferToBase64(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = "";
  for (let i = 0; i < bytes.length; i += 0x8000) {
    binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
  }
  return btoa(binary);
}

function postJson(url, payload) {
  return requestJson("POST", url, payload);
}

async function requestJson(method, url, payload) {
  const options = {
    method,
    headers: {
      Accept: "application/json",
      "X-Client-Key": CLIENT_KEY
    }
  };
  if (payload !== undefined) {
    options.headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(payload);
  }

  const response = await fetch(url, options);

  const text = await response.text();
  let data = null;

  if (text) {
    try {
      data = JSON.parse(text);
    } catch (error) {
      throw new Error("Backend returned invalid JSON.");
    }
  }

  if (!response.ok) {
    const message =
      (data && (data.error || data.message)) ||
      `Backend error (${response.status}).`;
    throw new Error(message);
  }

  return data;
}
