function sendHeight() {
  //const height = document.documentElement.scrollHeight;
  const height = document.documentElement.offsetHeight;
  window.parent.postMessage({ type: 'iframeHeight', height: height },'*');
}

window.addEventListener('load', sendHeight);

// bei Resize (z.B. responsive, Fonts, Bilder)
window.addEventListener('resize', sendHeight);