import DocumentService from '@typo3/core/document-service.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import FormEngine from '@typo3/backend/form-engine.js';
import FormEngineValidation from '@typo3/backend/form-engine-validation.js';
import Notification from '@typo3/backend/notification.js';
import '@typo3/backend/element/spinner-element.js';

function lang(key, fallback) {
  return (window.TYPO3?.lang && window.TYPO3.lang[key]) || fallback;
}

class SingleGenerateControl {
  constructor(id) {
    this.controlElement = null;
    this.targetField = null;
    this.titleField = null;
    this.descriptionField = null;
    this.fileUid = 0;
    this.busy = false;

    DocumentService.ready().then(() => {
      this.controlElement = document.getElementById(id);
      if (!this.controlElement) {
        return;
      }

      this.addLabel();

      const itemName = this.controlElement.dataset.itemName;
      this.targetField = document.querySelector(
        '[data-formengine-input-name="' + itemName + '"]'
      );
      this.titleField = this.findSiblingField(itemName, 'title');
      this.descriptionField = this.findSiblingField(itemName, 'description');
      this.fileUid = Number(this.controlElement.dataset.fileUid || 0);

      if (!this.targetField || this.fileUid <= 0) {
        this.controlElement.setAttribute('disabled', 'disabled');
        this.controlElement.classList.add('disabled');
        return;
      }

      this.controlElement.addEventListener('click', (event) => this.generate(event));
    });
  }

  findSiblingField(itemName, fieldName) {
    const siblingName = itemName.replace(/\[alternative\]$/, '[' + fieldName + ']');
    if (siblingName === itemName) {
      return null;
    }

    return document.querySelector('[data-formengine-input-name="' + siblingName + '"]');
  }

  fillField(field, value) {
    if (!field || !value) {
      return;
    }

    field.value = value;
    field.dispatchEvent(new Event('change'));
    FormEngineValidation.validateField(field);
    FormEngine.markFieldAsChanged(field);
  }

  addLabel() {
    if (this.controlElement.querySelector('[data-role="autoalt-generate-label"]')) {
      return;
    }

    const iconElement = this.controlElement.querySelector('[title]');
    const labelText = iconElement?.getAttribute('title')
      || this.controlElement.getAttribute('title')
      || lang('single.button', 'Generate Alt Text - AutoAlt.ai');

    this.controlElement.style.alignItems = 'center';
    this.controlElement.style.display = 'inline-flex';
    this.controlElement.style.gap = '.35rem';
    this.controlElement.style.whiteSpace = 'nowrap';
    this.controlElement.style.width = 'auto';
    this.controlElement.style.backgroundColor = '#b30000';
    this.controlElement.style.borderColor = '#b30000';
    this.controlElement.style.color = '#fff';

    const label = document.createElement('span');
    label.setAttribute('data-role', 'autoalt-generate-label');
    label.textContent = labelText;
    this.controlElement.appendChild(label);

    this.iconElement = iconElement;

    // Core renders the field control button beside the input via CSS grid
    // (.form-wizards-wrap). Move our button's grid cell onto its own row,
    // below the input, now that it carries a text label and needs more room.
    const asideWrap = this.controlElement.closest('.form-wizards-item-aside');
    if (asideWrap) {
      asideWrap.style.gridColumn = '1 / -1';
      asideWrap.style.justifySelf = 'start';
      asideWrap.style.marginTop = '.35rem';
    }
  }

  setBusy(isBusy) {
    this.controlElement.classList.toggle('disabled', isBusy);
    this.controlElement.setAttribute('aria-disabled', isBusy ? 'true' : 'false');
    this.controlElement.style.pointerEvents = isBusy ? 'none' : '';
    this.controlElement.style.opacity = isBusy ? '.75' : '';

    if (!this.iconElement) {
      return;
    }

    if (isBusy) {
      if (!this.spinnerElement) {
        this.spinnerElement = document.createElement('typo3-backend-spinner');
        this.spinnerElement.setAttribute('size', 'small');
      }
      this.iconElement.replaceWith(this.spinnerElement);
    } else if (this.spinnerElement?.isConnected) {
      this.spinnerElement.replaceWith(this.iconElement);
    }
  }

  async generate(event) {
    event.preventDefault();
    if (this.busy) {
      return;
    }

    this.busy = true;
    this.setBusy(true);

    try {
      const url = TYPO3.settings.ajaxUrls.alt_text_generator_single_generate;
      const response = await new AjaxRequest(url).post({ fileUid: this.fileUid });
      const data = await response.resolve();

      if (data.success) {
        this.fillField(this.targetField, data.altText);
        this.fillField(this.titleField, data.title);
        this.fillField(this.descriptionField, data.description);
        Notification.success('AutoAlt.ai', lang('single.success', 'Alt text and translations generated.'));
      } else {
        Notification.error('AutoAlt.ai', data.message || lang('single.failed', 'Alt text generation failed.'));
      }
    } catch (error) {
      const message = await this.extractErrorMessage(error);
      Notification.error('AutoAlt.ai', message || lang('single.failed', 'Alt text generation failed.'));
    } finally {
      this.busy = false;
      this.setBusy(false);
    }
  }

  // AjaxRequest rejects with the raw AjaxResponse (not an Error) for any
  // non-2xx status, so error.message is always undefined there; the actual
  // server-provided reason has to be read from its JSON body instead.
  async extractErrorMessage(error) {
    if (error && typeof error.resolve === 'function') {
      try {
        const body = await error.resolve();
        if (body && typeof body === 'object' && body.message) {
          return body.message;
        }
      } catch {
        // Body wasn't JSON or couldn't be read - fall through.
      }
    }

    return error?.message;
  }
}

export default SingleGenerateControl;
