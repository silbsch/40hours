function initFormHandler() {
  //const form = document.querySelector('form');
  this.document.querySelectorAll('form').forEach(form => {
    if (!form) return;

    // Button beim Zurück-Navigieren zurücksetzen
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = false;
          btn.setAttribute('aria-busy', 'false');
          submitting = false;
        }
        window.location.reload();
      }
    });

    let submitting = false;

    form.addEventListener('submit', (event) => {
      // Wenn wir schon "freigegeben" haben, NICHT nochmal abfangen
      if (submitting) return;

      // HTML5-Validierung
      if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        event.preventDefault();

        // Meldungen anzeigen, falls verfügbar
        if (typeof form.reportValidity === 'function') {
          form.reportValidity();
        }
        return;
      }

      // 2) Wichtig für iOS Touch: erst verhindern, UI setzen, dann "später" absenden
      event.preventDefault();
      submitting = true;
      
      const btn = event.submitter || form.querySelector('button[type="submit"]');

      if (btn) {
        btn.blur(); // iOS: pressed-look loswerden
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        
        // Reflow erzwingen (hilft Safari manchmal beim Repaint)
        void btn.offsetWidth;
      }

      // 3) Im nächsten Frame wirklich senden
      const doSubmit = () => {
        // requestSubmit: best, aber nicht überall vorhanden
        if (typeof form.requestSubmit === 'function') {
          // Wenn btn fehlt, requestSubmit() ohne Argument
          btn ? form.requestSubmit(btn) : form.requestSubmit();
        } else {
          // Fallback: form.submit() (Validierung haben wir oben schon gemacht)
          form.submit();
        }
      };

      if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(doSubmit);
      } else {
        setTimeout(doSubmit, 0); // uralt-Fallback
      }

    });
  });
}