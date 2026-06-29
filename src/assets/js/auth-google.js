document.addEventListener('DOMContentLoaded', function () {
  const googleRoot = document.querySelector('[data-google-auth-root]');
  if (!googleRoot) {
    return;
  }

  const clientId = googleRoot.getAttribute('data-google-client-id') || '';
  const callbackUrl = googleRoot.getAttribute('data-google-callback') || '';
  const context = googleRoot.getAttribute('data-google-context') || 'login';
  const nonce = googleRoot.getAttribute('data-google-nonce') || '';
  const slot = googleRoot.querySelector('[data-google-slot]');
  const feedback = document.querySelector('[data-auth-feedback]');

  if (!clientId || !callbackUrl || !slot || !window.google || !google.accounts || !google.accounts.id) {
    return;
  }

  const setFeedback = function (message) {
    if (!feedback) {
      window.alert(message);
      return;
    }

    feedback.textContent = message;
    feedback.hidden = false;
  };

  const currentRegisterPayload = function () {
    const selectedPlan = document.querySelector('input[data-plan-radio]:checked');
    const username = document.querySelector('input[name="username"]');
    const country = document.querySelector('select[name="pais"]');
    const terms = document.querySelector('input[name="terms"]');

    return {
      id_plan: selectedPlan ? Number(selectedPlan.value || 0) : 0,
      username: username ? username.value.trim() : '',
      pais: country ? country.value : '',
      terms_accepted: !!(terms && terms.checked)
    };
  };

  const handleCredential = function (response) {
    if (!response || !response.credential) {
      setFeedback('No recibimos una respuesta válida de Google. Intenta de nuevo.');
      return;
    }

    const payload = {
      credential: response.credential,
      context: context
    };

    if (context === 'register') {
      Object.assign(payload, currentRegisterPayload());
    }

    fetch(callbackUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    })
      .then(function (result) {
        return result.json().then(function (data) {
          if (!result.ok || !data.ok) {
            throw new Error(data.message || 'No se pudo completar el acceso con Google.');
          }

          return data;
        });
      })
      .then(function (data) {
        if (data.redirect_url) {
          window.location.href = data.redirect_url;
          return;
        }

        window.location.reload();
      })
      .catch(function (error) {
        setFeedback(error.message || 'No se pudo completar el acceso con Google.');
      });
  };

  google.accounts.id.initialize({
    client_id: clientId,
    callback: handleCredential,
    nonce: nonce,
    auto_select: false,
    cancel_on_tap_outside: true
  });

  google.accounts.id.renderButton(slot, {
    type: 'standard',
    theme: 'outline',
    text: context === 'register' ? 'signup_with' : 'continue_with',
    shape: 'pill',
    size: 'large',
    width: Math.min(360, Math.max(260, slot.clientWidth || 320)),
    logo_alignment: 'left'
  });
});
