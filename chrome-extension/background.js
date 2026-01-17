const BACKEND_URL = "http://localhost:8000/api/tickets/from-email";
const CLIENT_KEY = "change-me";

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!message || message.action !== "create-ticket") {
    return;
  }

  handleCreateTicket(message.payload)
    .then((result) => sendResponse(result))
    .catch((error) => {
      sendResponse({ ok: false, error: error.message || "Request failed." });
    });

  return true;
});

async function handleCreateTicket(payload) {
  const response = await fetch(BACKEND_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
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
      (data && data.error) || `Backend error (${response.status}).`;
    throw new Error(message);
  }

  if (!data || !data.issueId || !data.url) {
    throw new Error("Backend response missing issue data.");
  }

  return { ok: true, issueId: data.issueId, url: data.url };
}
