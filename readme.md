<style>
.lkg-iframe {
    margin-left: -1.5rem;
    width:100%;
  }
@media only screen and (100vw <= 100%) {
  .lkg-iframe {
    margin-left: -2.5rem;
  }
}
</style>

<script>
window.addEventListener('message', function (event) {
  // event.origin muss dem Wert von FORTY_HOURS_APPLICATION_HOST aus der .env-Datei entsprechen
  if (event.origin !== 'https://meine-lkg.de') { return; }
  if (event.data?.type === 'iframeHeight') {
    const iframe = document.querySelector('iframe');
    iframe.style.height = event.data.height + 'px';
    iframe.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }
});
</script>
<div class="raw-html-embed" style="margin-left:-1.5rem;">
    <!-- src muss dem Wert von FORTY_HOURS_APPLICATION_HOST aus der .env-Datei entsprechen -->
    <iframe src="https://meine-lkg.de/index.php" height="1490" frameborder="0" marginheight="0" marginwidth="0" style="border:0; width:clamp(575px, 100vw, 100%); max-width:100%; display:block;"></iframe>
</div>