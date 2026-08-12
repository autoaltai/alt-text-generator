import Notification from '@typo3/backend/notification.js';
import showAutoAltConfirmation from './confirmation-dialog.js';

function lang(key, fallback) {
  return (window.TYPO3?.lang && window.TYPO3.lang[key]) || fallback;
}

class BulkGeneration {
  constructor(container) {
    this.container = container;
    this.previewUrl = container.dataset.previewUrl;
    this.processUrl = container.dataset.processUrl;
    this.generateLabel = container.dataset.generateLabel || 'Generate alt text for %1$d images';

    this.seoKeywordsInput = container.querySelector('[data-role="seo-keywords-input"]');
    this.negativeKeywordsInput = container.querySelector('[data-role="negative-keywords-input"]');
    this.seoKeywordsError = container.querySelector('[data-role="seo-keywords-error"]');
    this.negativeKeywordsError = container.querySelector('[data-role="negative-keywords-error"]');
    this.keywordLimits = {
      maxKeywords: 6,
      maxKeywordLength: 30,
      maxTotalLength: 180,
    };
    this.fieldActionControls = Array.from(container.querySelectorAll('[data-role="field-action"]'));
    this.fieldActions = this.getFieldActions();
    this.checkboxes = {
      overwriteExisting: container.querySelector('[data-role="option-overwriteExisting"]'),
      skipProcessed: container.querySelector('[data-role="option-skipProcessed"]'),
      onlyShortAltText: container.querySelector('[data-role="option-onlyShortAltText"]'),
    };
    this.generateButton = container.querySelector('[data-role="generate-button"]');

    this.progressPanel = container.querySelector('[data-role="progress-panel"]');
    this.stopButton = container.querySelector('[data-role="stop"]');
    this.bar = container.querySelector('[data-role="bar"]');
    this.percentage = container.querySelector('[data-role="percentage"]');
    this.meta = container.querySelector('[data-role="meta"]');
    this.message = container.querySelector('[data-role="message"]');
    this.liveLogRows = container.querySelector('[data-role="live-log-rows"]');
    this.liveLogEmpty = container.querySelector('[data-role="live-log-empty"]');
    this.liveLogCount = container.querySelector('[data-role="live-log-count"]');

    this.running = false;
    this.stopRequested = false;
    this.currentCount = 0;
    this.totalAtStart = 0;
    this.completedSoFar = 0;
    this.failedSoFar = 0;

    Object.values(this.checkboxes).forEach((checkbox) => {
      checkbox?.addEventListener('change', () => this.refreshPreview());
    });
    [this.seoKeywordsInput, this.negativeKeywordsInput].forEach((input) => {
      input?.addEventListener('input', () => this.validateKeywordInputs());
    });
    this.fieldActionControls.forEach((control) => {
      control.querySelectorAll('[data-action]').forEach((button) => {
        button.addEventListener('click', () => this.setFieldAction(control, button.dataset.action));
      });
    });
    this.generateButton?.addEventListener('click', () => this.confirmStart());
    this.stopButton?.addEventListener('click', () => this.stop());

    this.validateKeywordInputs();
    this.refreshPreview();
  }

  async refreshPreview() {
    if (!this.previewUrl || !this.generateButton || this.running) {
      return;
    }

    const url = new URL(this.previewUrl, window.location.origin);
    url.searchParams.set('overwriteExisting', this.checkboxes.overwriteExisting?.checked ? '1' : '0');
    url.searchParams.set('skipProcessed', this.checkboxes.skipProcessed?.checked ? '1' : '0');
    url.searchParams.set('onlyShortAltText', this.checkboxes.onlyShortAltText?.checked ? '1' : '0');

    try {
      const response = await fetch(url, { method: 'GET', credentials: 'same-origin' });
      const data = await response.json();
      this.currentCount = data.success ? Number(data.count) || 0 : 0;
      this.applyButtonCount(this.currentCount);
    } catch {
      // Silently ignore - the button keeps its previously rendered count.
    }
  }

  applyButtonCount(count) {
    if (!this.generateButton) {
      return;
    }
    this.generateButton.textContent = this.generateLabel.replace('%1$d', count);
    this.generateButton.disabled = count <= 0 || this.running || !this.keywordsAreValid;
  }

  confirmStart() {
    if (this.running || this.currentCount <= 0) {
      return;
    }
    if (!this.validateKeywordInputs()) {
      const invalidInput = [this.seoKeywordsInput, this.negativeKeywordsInput]
        .find((input) => input?.classList.contains('is-invalid'));
      invalidInput?.focus();
      Notification.warning('AutoAlt.ai', lang('bulk.keywordValidation.fixErrors', 'Fix the keyword validation errors before starting generation.'));
      return;
    }

    const fieldsToGenerate = [
      ['altTextAction', lang('bulk.fieldActions.altText', 'Alt Text')],
      ['titleAction', lang('bulk.fieldActions.titleField', 'Title')],
      ['descriptionAction', lang('bulk.fieldActions.description', 'Description')],
    ]
      .filter(([field]) => this.fieldActions[field] === 'generate')
      .map(([, label]) => label);
    const message = fieldsToGenerate.length > 0
      ? lang('bulk.confirm.message.generateFields', 'AutoAlt.ai will generate: %1$s.')
        .replace('%1$s', fieldsToGenerate.join(', '))
      : lang('bulk.confirm.message.noGeneratedFields', 'No fields are set to Generate. Keep and Clear actions will be applied without creating generation history.');
    const note = this.checkboxes.overwriteExisting?.checked
      ? lang('bulk.confirm.note.overwrite', 'Existing metadata may be overwritten. Stop finishes the current image, and completed changes are kept.')
      : lang('bulk.confirm.note.default', 'Stop finishes the current image, and completed changes are kept.');

    showAutoAltConfirmation({
      title: lang('bulk.confirm.title', 'Start bulk generation?'),
      subjectLabel: lang('bulk.confirm.subjectLabel', 'Images'),
      subject: lang('bulk.confirm.subject', '%1$d images').replace('%1$d', String(this.currentCount)),
      message,
      note,
      confirmLabel: lang('bulk.confirm.confirm', 'Start generation'),
      cancelLabel: lang('bulk.confirm.cancel', 'Cancel'),
      icon: 'actions-wand-sparkles',
      onConfirm: () => this.start(),
    });
  }

  start() {
    this.activeConfig = {
      overwriteExisting: this.checkboxes.overwriteExisting?.checked ? '1' : '0',
      skipProcessed: this.checkboxes.skipProcessed?.checked ? '1' : '0',
      onlyShortAltText: this.checkboxes.onlyShortAltText?.checked ? '1' : '0',
      seoKeywords: this.seoKeywordsInput?.value || '',
      negativeKeywords: this.negativeKeywordsInput?.value || '',
      ...this.fieldActions,
    };
    // The server scans sys_file.uid DESC and returns the next cursor after
    // each batch, keeping long bulk runs from sending a growing UID list.
    this.afterFileUid = 0;

    this.running = true;
    this.stopRequested = false;
    this.totalAtStart = this.currentCount;
    this.completedSoFar = 0;
    this.failedSoFar = 0;

    this.setInputsDisabled(true);
    this.stopButton?.removeAttribute('disabled');
    if (this.stopButton) {
      this.stopButton.textContent = lang('bulk.stop.button', 'Stop generation');
    }
    if (this.progressPanel) {
      this.progressPanel.hidden = false;
    }
    if (this.liveLogRows) {
      this.liveLogRows.innerHTML = '';
    }
    if (this.liveLogEmpty) {
      this.liveLogEmpty.hidden = false;
    }
    this.setBar(0);
    this.setMeta(0, this.totalAtStart);
    this.setMessage(lang('js.processing', 'Processing images…'), 'info');

    this.runBatchLoop();
  }

  stop() {
    if (!this.running || this.stopRequested) {
      return;
    }

    this.stopRequested = true;
    this.stopButton?.setAttribute('disabled', 'disabled');
    if (this.stopButton) {
      this.stopButton.textContent = lang('bulk.stop.stopping', 'Stopping…');
    }
    this.setMessage(lang('js.stopping', 'Stopping after the current batch…'), 'info');
  }

  setInputsDisabled(disabled) {
    this.generateButton.disabled = disabled || !this.keywordsAreValid;
    this.seoKeywordsInput?.toggleAttribute('disabled', disabled);
    this.negativeKeywordsInput?.toggleAttribute('disabled', disabled);
    Object.values(this.checkboxes).forEach((checkbox) => {
      checkbox?.toggleAttribute('disabled', disabled);
    });
    this.fieldActionControls.forEach((control) => {
      control.querySelectorAll('button').forEach((button) => button.toggleAttribute('disabled', disabled));
    });
  }

  validateKeywordInputs() {
    const seoInput = (this.seoKeywordsInput?.value || '').trim();
    const negativeInput = (this.negativeKeywordsInput?.value || '').trim();
    const seoKeywords = this.parseKeywords(seoInput);
    const negativeKeywords = this.parseKeywords(negativeInput);
    const errors = { seo: '', negative: '' };

    if (seoInput.length > this.keywordLimits.maxTotalLength) {
      errors.seo = lang('bulk.keywordValidation.seoTotal', 'SEO keywords must not exceed %1$d characters.')
        .replace('%1$d', String(this.keywordLimits.maxTotalLength));
    } else if (negativeInput.length > this.keywordLimits.maxTotalLength) {
      errors.negative = lang('bulk.keywordValidation.negativeTotal', 'Negative keywords must not exceed %1$d characters.')
        .replace('%1$d', String(this.keywordLimits.maxTotalLength));
    } else if (seoKeywords.length > this.keywordLimits.maxKeywords) {
      errors.seo = lang('bulk.keywordValidation.seoCount', 'SEO keywords can contain a maximum of %1$d keywords.')
        .replace('%1$d', String(this.keywordLimits.maxKeywords));
    } else if (negativeKeywords.length > this.keywordLimits.maxKeywords) {
      errors.negative = lang('bulk.keywordValidation.negativeCount', 'Negative keywords can contain a maximum of %1$d keywords.')
        .replace('%1$d', String(this.keywordLimits.maxKeywords));
    } else if (seoKeywords.some((keyword) => keyword.length > this.keywordLimits.maxKeywordLength)) {
      errors.seo = lang('bulk.keywordValidation.seoKeywordLength', 'Each SEO keyword must be at most %1$d characters.')
        .replace('%1$d', String(this.keywordLimits.maxKeywordLength));
    } else if (negativeKeywords.some((keyword) => keyword.length > this.keywordLimits.maxKeywordLength)) {
      errors.negative = lang('bulk.keywordValidation.negativeKeywordLength', 'Each negative keyword must be at most %1$d characters.')
        .replace('%1$d', String(this.keywordLimits.maxKeywordLength));
    } else if (this.hasDuplicates(seoKeywords)) {
      errors.seo = lang('bulk.keywordValidation.seoDuplicates', 'SEO keywords contain duplicate values.');
    } else if (this.hasDuplicates(negativeKeywords)) {
      errors.negative = lang('bulk.keywordValidation.negativeDuplicates', 'Negative keywords contain duplicate values.');
    } else {
      const negativeSet = new Set(negativeKeywords.map((keyword) => keyword.toLowerCase()));
      const conflicts = seoKeywords
        .map((keyword) => keyword.toLowerCase())
        .filter((keyword) => negativeSet.has(keyword));
      if (conflicts.length > 0) {
        errors.negative = lang('bulk.keywordValidation.conflict', 'Negative keywords must not match SEO keywords: %1$s.')
          .replace('%1$s', conflicts.join(', '));
      }
    }

    this.setKeywordFieldError(this.seoKeywordsInput, this.seoKeywordsError, errors.seo);
    this.setKeywordFieldError(this.negativeKeywordsInput, this.negativeKeywordsError, errors.negative);
    this.keywordsAreValid = errors.seo === '' && errors.negative === '';
    this.applyButtonCount(this.currentCount);

    return this.keywordsAreValid;
  }

  parseKeywords(input) {
    return input.split(',').map((keyword) => keyword.trim()).filter((keyword) => keyword !== '');
  }

  hasDuplicates(keywords) {
    const normalized = keywords.map((keyword) => keyword.toLowerCase());
    return new Set(normalized).size !== normalized.length;
  }

  setKeywordFieldError(input, errorElement, message) {
    if (!input || !errorElement) {
      return;
    }

    const invalid = message !== '';
    input.classList.toggle('is-invalid', invalid);
    input.setAttribute('aria-invalid', invalid ? 'true' : 'false');
    input.setCustomValidity(message);
    errorElement.textContent = message;
    errorElement.hidden = !invalid;
  }

  getFieldActions() {
    return this.fieldActionControls.reduce((actions, control) => {
      const field = control.dataset.field;
      const activeButton = control.querySelector('[data-action].is-active');
      if (field) {
        actions[field] = activeButton?.dataset.action || 'keep';
      }
      return actions;
    }, {});
  }

  setFieldAction(control, action) {
    if (this.running || !['generate', 'keep', 'clear'].includes(action)) {
      return;
    }
    control.querySelectorAll('[data-action]').forEach((button) => {
      const active = button.dataset.action === action;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    this.fieldActions = this.getFieldActions();
  }

  async runBatchLoop() {
    while (!this.stopRequested) {
      const params = new URLSearchParams(this.activeConfig);
      if (this.afterFileUid > 0) {
        params.set('afterFileUid', String(this.afterFileUid));
      }

      let data;
      try {
        const response = await fetch(this.processUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: params.toString(),
        });
        data = await response.json();
      } catch (error) {
        const fallback = lang('js.requestFailed', 'Batch processing request failed.');
        this.setMessage(fallback, 'warning');
        Notification.error('AutoAlt.ai', error?.message || fallback);
        break;
      }

      if (!data.success) {
        const fallback = lang('js.processingFailed', 'Batch processing failed.');
        this.setMessage(data.message || fallback, 'danger');
        Notification.error('AutoAlt.ai', data.message || fallback);
        break;
      }

      const result = data.result;
      this.completedSoFar += result.completed;
      this.failedSoFar += result.failed;
      this.appendResultItems(result.items);
      this.afterFileUid = Number(result.nextCursor) || this.afterFileUid;

      const finished = this.completedSoFar + this.failedSoFar;
      const total = Math.max(this.totalAtStart, finished);
      this.setBar(total > 0 ? Math.round((finished / total) * 100) : 100);
      this.setMeta(finished, total);

      if (result.creditExhausted) {
        const message = result.message || lang('js.creditExhausted', 'You have run out of AutoAlt.ai credits. Add more credits to your account to continue.');
        this.setMessage(message, 'danger');
        Notification.error('AutoAlt.ai', message);
        break;
      }

      if (result.processed === 0 || result.remaining <= 0) {
        const fallback = result.remaining > 0
          ? lang('js.noneAvailable', 'No eligible images were available in this batch.')
          : lang('js.complete', 'Bulk generation is complete.');
        this.setMessage(result.message || fallback, this.failedSoFar > 0 ? 'warning' : 'success');
        break;
      }
    }

    if (this.stopRequested) {
      this.setMessage(lang('bulk.stop.stopped', 'Generation stopped. Completed images remain saved.'), 'info');
    }

    this.running = false;
    this.stopButton?.setAttribute('disabled', 'disabled');
    if (this.stopButton) {
      this.stopButton.textContent = lang('bulk.stop.button', 'Stop generation');
    }
    this.setInputsDisabled(false);
    this.refreshPreview();
  }

  appendResultItems(items) {
    if (!this.liveLogRows || !Array.isArray(items) || items.length === 0) {
      return;
    }

    if (this.liveLogEmpty) {
      this.liveLogEmpty.hidden = true;
    }

    items.forEach((item) => {
      const row = document.createElement('tr');

      const imageCell = document.createElement('td');
      if (item.publicUrl) {
        const image = document.createElement('img');
        image.src = item.publicUrl;
        image.alt = '';
        image.width = 40;
        image.height = 40;
        image.style.objectFit = 'cover';
        image.style.borderRadius = '4px';
        imageCell.appendChild(image);
      }
      const fileNameSpan = document.createElement('span');
      fileNameSpan.className = 'autoalt-history-table__identifier';
      fileNameSpan.textContent = item.fileName;
      imageCell.appendChild(document.createElement('br'));
      imageCell.appendChild(fileNameSpan);

      const resultCell = document.createElement('td');
      resultCell.className = 'autoalt-history-table__result';
      resultCell.textContent = item.message;
      if (!item.success) {
        resultCell.classList.add('autoalt-history-table__error');
      }

      const statusCell = document.createElement('td');
      const statusPill = document.createElement('span');
      statusPill.className = 'badge ' + (item.success ? 'badge-success' : 'badge-danger');
      statusPill.textContent = item.success
        ? lang('dashboard.bulkQueue.liveLog.success', 'Success')
        : lang('dashboard.bulkQueue.liveLog.failed', 'Failed');
      statusCell.appendChild(statusPill);

      row.appendChild(imageCell);
      row.appendChild(resultCell);
      row.appendChild(statusCell);
      this.liveLogRows.prepend(row);
    });
  }

  setBar(percentage) {
    const value = Math.max(0, Math.min(100, Number(percentage) || 0));
    if (this.bar) {
      this.bar.style.width = value + '%';
    }
    if (this.percentage) {
      this.percentage.textContent = value + '%';
    }
  }

  setMeta(finished, total) {
    if (this.meta) {
      this.meta.textContent = finished + ' of ' + total;
    }
    if (this.liveLogCount) {
      this.liveLogCount.textContent = finished + ' / ' + total;
    }
  }

  setMessage(message, state) {
    if (!this.message) {
      return;
    }
    this.message.textContent = message;
    this.message.className = 'autoalt-progress__message autoalt-progress__message--' + state;
    this.message.hidden = false;
  }
}

document.querySelectorAll('[data-autoalt-bulk-generation]').forEach((container) => new BulkGeneration(container));

export default BulkGeneration;
