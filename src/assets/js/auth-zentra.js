document.addEventListener('DOMContentLoaded', function () {
  const googleButtons = document.querySelectorAll('[data-auth-google]');
  googleButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      const scope = button.getAttribute('data-auth-google') || 'default';
      const note = document.querySelector('[data-google-note="' + scope + '"]');
      if (note) {
        note.classList.toggle('is-visible');
      }
    });
  });

  const planRadios = Array.from(document.querySelectorAll('input[data-plan-radio]'));
  const submitButton = document.querySelector('[data-plan-submit]');
  const submitLabel = document.querySelector('[data-plan-submit-label]');
  const submitNote = document.querySelector('[data-plan-note]');
  const summaryName = document.querySelector('[data-plan-summary-name]');
  const summaryPrice = document.querySelector('[data-plan-summary-price]');
  const summaryPeriod = document.querySelector('[data-plan-summary-period]');
  const summaryDescription = document.querySelector('[data-plan-summary-description]');
  const summaryCompanies = document.querySelector('[data-plan-summary-companies]');
  const summaryUsers = document.querySelector('[data-plan-summary-users]');
  const summaryDocs = document.querySelector('[data-plan-summary-docs]');
  const summaryBenefits = document.querySelector('[data-plan-benefits-list]');

  if (!planRadios.length) {
    return;
  }

  const syncSelectionState = function () {
    planRadios.forEach(function (radio) {
      const card = radio.closest('[data-plan-card]');
      if (!card) {
        return;
      }

      card.classList.toggle('is-selected', radio.checked);
    });
  };

  const renderBenefits = function (encodedBenefits) {
    if (!summaryBenefits) {
      return;
    }

    summaryBenefits.innerHTML = '';

    let benefits = [];
    try {
      benefits = JSON.parse(encodedBenefits || '[]');
    } catch (error) {
      benefits = [];
    }

    benefits.forEach(function (item) {
      const li = document.createElement('li');
      li.textContent = item;
      summaryBenefits.appendChild(li);
    });
  };

  const updateSummary = function (radio) {
    const card = radio.closest('[data-plan-card]');
    if (!card) {
      return;
    }

    const checkoutMode = card.getAttribute('data-plan-checkout') || 'free';
    const planName = card.getAttribute('data-plan-name') || 'Plan';
    const planPrice = card.getAttribute('data-plan-price') || '$0.00';
    const planPeriod = card.getAttribute('data-plan-period') || '';
    const planDescription = card.getAttribute('data-plan-description') || '';
    const companies = card.getAttribute('data-plan-companies') || 'Escalable';
    const users = card.getAttribute('data-plan-users') || 'Escalable';
    const docs = card.getAttribute('data-plan-docs') || 'Escalable';
    const benefits = card.getAttribute('data-plan-benefits') || '[]';

    if (summaryName) {
      summaryName.textContent = planName;
    }

    if (summaryPrice) {
      summaryPrice.textContent = planPrice;
    }

    if (summaryPeriod) {
      summaryPeriod.textContent = planPeriod;
    }

    if (summaryDescription) {
      summaryDescription.textContent = planDescription;
    }

    if (summaryCompanies) {
      summaryCompanies.textContent = companies;
    }

    if (summaryUsers) {
      summaryUsers.textContent = users;
    }

    if (summaryDocs) {
      summaryDocs.textContent = docs;
    }

    renderBenefits(benefits);

    if (!submitButton || !submitLabel || !submitNote) {
      return;
    }

    submitButton.disabled = false;
    submitNote.classList.remove('is-warning');

    if (checkoutMode === 'free') {
      submitLabel.textContent = 'Crear cuenta gratis';
      submitNote.textContent = 'La cuenta se crea al instante y luego podras cambiar de plan cuando quieras.';
      return;
    }

    if (checkoutMode === 'stripe') {
      submitLabel.textContent = 'Continuar a Stripe';
      submitNote.textContent = 'Te llevaremos a Stripe Checkout para cobrar la membresia y activar los beneficios correctos.';
      return;
    }

    submitButton.disabled = true;
    submitLabel.textContent = 'Stripe pendiente';
    submitNote.textContent = 'Este plan aun necesita su Price ID de Stripe en el entorno antes de poder cobrarse.';
    submitNote.classList.add('is-warning');
  };

  planRadios.forEach(function (radio) {
    radio.addEventListener('change', function () {
      syncSelectionState();
      updateSummary(radio);
    });
  });

  let selectedRadio = planRadios.find(function (radio) {
    return radio.checked;
  });

  if (!selectedRadio) {
    selectedRadio = planRadios[0];
    if (selectedRadio) {
      selectedRadio.checked = true;
    }
  }

  if (selectedRadio) {
    syncSelectionState();
    updateSummary(selectedRadio);
  }
});
