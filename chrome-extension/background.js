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

  return { ok: true, issueId: data.issueId, url: data.url };
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

async function postJson(url, payload) {
  const response = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-Client-Key": CLIENT_KEY
    },
    body: JSON.stringify(payload)
  });

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
