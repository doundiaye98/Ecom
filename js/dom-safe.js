/**
 * Pure Essence Vita — Safe HTML helpers for innerHTML templates
 */
(function (global) {
  function escapeHtml(text) {
    if (text == null) return "";
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function escapeAttr(text) {
    return escapeHtml(text);
  }

  global.escapeHtml = escapeHtml;
  global.escapeAttr = escapeAttr;
})(typeof window !== "undefined" ? window : globalThis);
