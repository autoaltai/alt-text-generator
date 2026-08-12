import Modal, { Sizes, Styles } from '@typo3/backend/modal.js';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import { html } from 'lit';

function ensureStyles(ownerDocument) {
  if (ownerDocument.querySelector('link[data-autoalt-confirmation-styles]')) return;

  const stylesheetUrl = new URL('../Css/confirmation-modal.css', import.meta.url);
  stylesheetUrl.searchParams.set('v', '3');
  const stylesheet = ownerDocument.createElement('link');
  stylesheet.rel = 'stylesheet';
  stylesheet.href = stylesheetUrl.href;
  stylesheet.dataset.autoaltConfirmationStyles = 'true';
  ownerDocument.head.append(stylesheet);
}

export default function showAutoAltConfirmation({
  title,
  note,
  confirmLabel,
  onConfirm,
  message = '',
  subject = '',
  subjectLabel = '',
  cancelLabel = 'Cancel',
  icon = 'actions-wand-sparkles',
  variant = 'ai',
}) {
  const modal = Modal.advanced({
    title,
    content: html`
      <div class="autoalt-confirmation autoalt-confirmation--${variant}">
        <div class="autoalt-confirmation__icon" aria-hidden="true">
          <typo3-backend-icon identifier=${icon} size="medium"></typo3-backend-icon>
        </div>
        <div class="autoalt-confirmation__content">
          ${subject ? html`
            <div class="autoalt-confirmation__subject">
              ${subjectLabel ? html`<span>${subjectLabel}</span>` : ''}
              <strong>${subject}</strong>
            </div>
          ` : ''}
          ${message ? html`<p class="autoalt-confirmation__message">${message}</p>` : ''}
        </div>
        <div class="autoalt-confirmation__note">
          <typo3-backend-icon identifier="actions-info" size="small"></typo3-backend-icon>
          <span>${note}</span>
        </div>
      </div>
    `,
    severity: SeverityEnum.notice,
    style: Styles.light,
    size: Sizes.small,
    staticBackdrop: true,
    additionalCssClasses: ['autoalt-confirm-modal', `autoalt-confirm-modal--${variant}`],
    buttons: [
      {
        text: cancelLabel,
        active: true,
        btnClass: 'btn-default',
        name: 'cancel',
        trigger: (event, currentModal) => currentModal.hideModal(),
      },
      {
        text: confirmLabel,
        btnClass: 'btn-danger',
        icon,
        name: 'confirm',
        trigger: (event, currentModal) => {
          currentModal.hideModal();
          onConfirm();
        },
      },
    ],
  });

  ensureStyles(modal.ownerDocument);
  return modal;
}
