document.addEventListener('DOMContentLoaded', function () {
  const planRadios = Array.from(document.querySelectorAll('input[data-plan-radio]'));
  if (!planRadios.length) {
    return;
  }

  const submitButton = document.querySelector('[data-plan-submit]');
  const summaryName = document.querySelector('[data-plan-summary-name]');
  const summaryPrice = document.querySelector('[data-plan-summary-price]');
  const summaryMeta = document.querySelector('[data-plan-summary-meta]');
  const summaryNote = document.querySelector('[data-plan-note]');

  const resolveSubmitLabel = function (checkoutMode) {
    if (!submitButton) {
      return '';
    }

    if (checkoutMode === 'stripe') {
      return submitButton.getAttribute('data-plan-submit-stripe') || 'Continuar con pago';
    }

    if (checkoutMode === 'sales' || checkoutMode === 'pending') {
      return submitButton.getAttribute('data-plan-submit-pending') || 'Plan no disponible';
    }

    return submitButton.getAttribute('data-plan-submit-free') || 'Crear cuenta';
  };

  const resolveCardAction = function (checkoutMode) {
    if (checkoutMode === 'free') {
      return submitButton
        ? (submitButton.getAttribute('data-plan-submit-free') || 'Crear cuenta')
        : 'Crear cuenta';
    }

    if (checkoutMode === 'stripe') {
      return submitButton
        ? (submitButton.getAttribute('data-plan-submit-stripe') || 'Continuar con pago')
        : 'Continuar con pago';
    }

    return 'Plan no disponible';
  };

  const syncSelectionState = function () {
    planRadios.forEach(function (radio) {
      const card = radio.closest('[data-plan-card]');
      if (!card) {
        return;
      }

      const checkoutMode = card.getAttribute('data-plan-checkout') || 'free';
      const action = card.querySelector('[data-plan-card-action]');
      card.classList.toggle('is-selected', radio.checked);

      if (action) {
        action.textContent = radio.checked
          ? 'Plan seleccionado'
          : resolveCardAction(checkoutMode);
      }
    });
  };

  const updateSummary = function (radio) {
    const card = radio.closest('[data-plan-card]');
    if (!card) {
      return;
    }

    const checkoutMode = card.getAttribute('data-plan-checkout') || 'free';

    if (summaryName) {
      summaryName.textContent = card.getAttribute('data-plan-name') || 'Plan';
    }

    if (summaryPrice) {
      summaryPrice.textContent = card.getAttribute('data-plan-price-label') || '$0.00';
    }

    if (summaryMeta) {
      summaryMeta.textContent = card.getAttribute('data-plan-summary-meta') || '';
    }

    if (summaryNote) {
      summaryNote.textContent = card.getAttribute('data-plan-note') || '';
      summaryNote.classList.toggle('auth-inline-note--warning', checkoutMode === 'pending' || checkoutMode === 'sales');
    }

    if (submitButton) {
      submitButton.disabled = checkoutMode === 'pending' || checkoutMode === 'sales';
      submitButton.textContent = resolveSubmitLabel(checkoutMode);
    }
  };

  planRadios.forEach(function (radio) {
    radio.addEventListener('change', function () {
      syncSelectionState();
      updateSummary(radio);
    });
  });

  const selectedRadio = planRadios.find(function (radio) {
    return radio.checked;
  }) || planRadios[0];

  if (selectedRadio) {
    selectedRadio.checked = true;
    syncSelectionState();
    updateSummary(selectedRadio);
  }
});
