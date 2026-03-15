(function () {
  var trigger = document.querySelector('[data-contact-trigger]');
  var modal = document.querySelector('[data-contact-modal]');
  if (!trigger || !modal) return;

  var dialog = modal.querySelector('.se-contact-modal__dialog');
  var closeEls = modal.querySelectorAll('[data-contact-close]');
  var form = modal.querySelector('[data-contact-form]');
  var statusEl = modal.querySelector('[data-contact-status]');
  var successEl = modal.querySelector('[data-contact-success]');
  var playIntroBtn = modal.querySelector('[data-contact-play-intro]');
  var audioStatus = modal.querySelector('[data-contact-audio-status]');
  var firstInput = modal.querySelector('#se-contact-name');
  var lastFocused = null;

  function setStatus(message, isError) {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.classList.toggle('is-error', !!isError);
  }

  function openModal() {
    lastFocused = document.activeElement;
    modal.hidden = false;
    document.body.classList.add('se-modal-open');
    window.setTimeout(function () {
      if (firstInput) firstInput.focus();
    }, 10);
    document.addEventListener('keydown', onKeydown);
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('se-modal-open');
    document.removeEventListener('keydown', onKeydown);
    if (lastFocused && typeof lastFocused.focus === 'function') {
      lastFocused.focus();
    } else {
      trigger.focus();
    }
  }

  function trapFocus(e) {
    if (e.key !== 'Tab') return;
    var focusables = dialog.querySelectorAll('button, [href], input, textarea, select, [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;
    var first = focusables[0];
    var last = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  function onKeydown(e) {
    if (e.key === 'Escape') {
      closeModal();
      return;
    }
    trapFocus(e);
  }

  async function submitForm(event) {
    event.preventDefault();
    setStatus('Sending message...', false);

    var endpoint = form.getAttribute('data-endpoint') || '/wp-admin/admin-ajax.php';
    var formData = new FormData(form);

    try {
      var response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      });

      var data = await response.json();
      if (!response.ok || !data || !data.success) {
        var errorMessage = (data && data.data && data.data.message) ? data.data.message : 'Could not send message right now.';
        throw new Error(errorMessage);
      }

      setStatus(data.data && data.data.message ? data.data.message : 'Message sent.', false);
      form.hidden = true;
      if (successEl) successEl.hidden = false;
    } catch (error) {
      setStatus(error.message || 'Could not send message right now.', true);
    }
  }

  function playIntro() {
    if (!('speechSynthesis' in window)) {
      if (audioStatus) {
        audioStatus.textContent = 'Voice intro not supported in this browser, but the form still works great.';
      }
      return;
    }

    window.speechSynthesis.cancel();
    var utterance = new SpeechSynthesisUtterance('Yo, what\'s up? Write a message and Suzy will get back to you.');
    utterance.rate = 1;
    utterance.pitch = 1;
    utterance.onstart = function () {
      if (audioStatus) audioStatus.textContent = 'Playing intro voice...';
    };
    utterance.onend = function () {
      if (audioStatus) audioStatus.textContent = 'Intro complete. Drop your message below.';
    };
    utterance.onerror = function () {
      if (audioStatus) audioStatus.textContent = 'Could not play intro voice on this device.';
    };
    window.speechSynthesis.speak(utterance);
  }

  trigger.addEventListener('click', openModal);
  closeEls.forEach(function (el) {
    el.addEventListener('click', closeModal);
  });

  if (form) {
    form.addEventListener('submit', submitForm);
  }

  if (playIntroBtn) {
    playIntroBtn.addEventListener('click', playIntro);
  }
})();
