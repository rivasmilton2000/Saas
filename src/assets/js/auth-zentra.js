document.addEventListener('DOMContentLoaded', function () {
  const planRadios = Array.from(document.querySelectorAll('input[data-plan-radio]'));
  if (!planRadios.length) {
    return;
  }

  const submitButton = document.querySelector('[data-plan-submit]');
  const summaryName = document.querySelector('[data-plan-summary-name]');
  const summaryPrice = document.querySelector('[data-plan-summary-price]');
  const summaryDescription = document.querySelector('[data-plan-summary-description]');
  const summaryCompanies = document.querySelector('[data-plan-summary-companies]');
  const summaryUsers = document.querySelector('[data-plan-summary-users]');
  const summaryDocs = document.querySelector('[data-plan-summary-docs]');
  const summaryBenefits = document.querySelector('[data-plan-summary-benefits]');
  const summaryNote = document.querySelector('[data-plan-note]');

  const syncSelectionState = function () {
    planRadios.forEach(function (radio) {
      const card = radio.closest('[data-plan-card]');
      if (!card) {
        return;
      }

      card.classList.toggle('is-selected', radio.checked);
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

    if (summaryDescription) {
      summaryDescription.textContent = card.getAttribute('data-plan-description') || '';
    }

    if (summaryCompanies) {
      summaryCompanies.textContent = card.getAttribute('data-plan-companies') || 'Escalable';
    }

    if (summaryUsers) {
      summaryUsers.textContent = card.getAttribute('data-plan-users') || 'Escalable';
    }

    if (summaryDocs) {
      summaryDocs.textContent = card.getAttribute('data-plan-docs') || 'Sin tope fijo';
    }

    if (summaryBenefits) {
      summaryBenefits.textContent = card.getAttribute('data-plan-benefits') || '';
    }

    if (summaryNote) {
      summaryNote.textContent = card.getAttribute('data-plan-note') || '';
      summaryNote.classList.toggle('auth-inline-note--warning', checkoutMode === 'pending');
    }

    if (submitButton) {
      submitButton.disabled = checkoutMode === 'pending';
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
