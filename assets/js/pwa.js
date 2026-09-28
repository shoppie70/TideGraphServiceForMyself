/**
 * シオヨミ PWA — Service Worker 登録
 */
(function () {
  if (!('serviceWorker' in navigator)) return;

  var baseEl = document.querySelector('base');
  var baseHref = baseEl && baseEl.href ? baseEl.href : new URL('.', window.location.href).href;

  window.addEventListener('load', function () {
    var swUrl = new URL('sw.js', baseHref).href;
    var scope = new URL('./', baseHref).href;
    navigator.serviceWorker.register(swUrl, { scope: scope }).catch(function (err) {
      console.debug('[shioyomi-pwa] SW register failed', err);
    });
  });
})();
