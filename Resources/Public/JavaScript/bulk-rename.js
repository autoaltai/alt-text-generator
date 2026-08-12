import DocumentService from '@typo3/core/document-service.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import showAutoAltConfirmation from './confirmation-dialog.js';

function lang(key, fallback) {
  return (window.TYPO3?.lang && window.TYPO3.lang[key]) || fallback;
}

class BulkRename {
  constructor(container) {
    this.container = container;
    this.urls = { manual: container.dataset.manualUrl, ai: container.dataset.aiUrl, undo: container.dataset.undoUrl };
    this.selectedButton = container.querySelector('[data-role="rename-selected"]');
    this.visibleButton = container.querySelector('[data-role="rename-visible"]');
    this.selectAll = container.querySelector('[data-role="select-all"]');
    this.progress = container.querySelector('[data-role="progress"]');
    this.running = false;
    this.disabledState = new WeakMap();

    this.container.addEventListener('click', (event) => this.onClick(event));
    this.container.addEventListener('input', (event) => this.onInput(event));
    this.container.addEventListener('change', (event) => this.onChange(event));
    this.updateSelection();
  }

  onChange(event) {
    if (event.target.matches('[data-role="select-all"]')) {
      this.rowCheckboxes().forEach((checkbox) => { checkbox.checked = event.target.checked; });
      this.updateSelection();
    } else if (event.target.matches('[data-role="row-select"]')) {
      this.updateSelection();
    }
  }

  onInput(event) {
    if (!event.target.matches('[data-role="manual-input"]')) return;
    const row = event.target.closest('tr');
    const current = row?.querySelector('[data-role="current-filename"]')?.textContent || '';
    const extension = current.includes('.') ? current.split('.').pop().toLowerCase() : '';
    let stem = event.target.value.trim().replace(/\.[^.]+$/, '').toLowerCase();
    stem = stem.normalize('NFKD').replace(/[\s_]+/g, '-').replace(/[^\p{L}\p{N}-]+/gu, '-').replace(/-+/g, '-').replace(/^[.\s-]+|[.\s-]+$/g, '');
    const preview = row?.querySelector('[data-role="normalized-preview"]');
    if (preview) preview.textContent = stem ? stem + (extension ? '.' + extension : '') : lang('rename.manual.invalid', 'Invalid filename');
  }

  async onClick(event) {
    const actionElement = event.target.closest('[data-action], [data-role="rename-selected"], [data-role="rename-visible"]');
    if (!actionElement || this.running) return;
    const row = actionElement.closest('tr');

    if (actionElement.dataset.action === 'manual') {
      row.querySelector('[data-role="manual-editor"]').hidden = false;
      row.querySelector('[data-role="manual-input"]').focus();
      return;
    }
    if (actionElement.dataset.action === 'manual-cancel') {
      row.querySelector('[data-role="manual-editor"]').hidden = true;
      return;
    }
    if (actionElement.dataset.action === 'manual-confirm') {
      await this.renameOne('manual', Number(row.dataset.fileUid), row.querySelector('[data-role="manual-input"]').value);
      return;
    }
    if (actionElement.dataset.action === 'ai') {
      const filename = row.querySelector('[data-role="current-filename"]')?.textContent?.trim() || '';
      this.confirm({
        title: lang('rename.confirm.ai.title', 'Rename this image with AI?'),
        note: lang('rename.confirm.ai.note', 'This action may use 1 credit. You can undo the rename from Rename History.'),
        actionLabel: lang('rename.confirm.ai.action', 'Rename with AI'),
        icon: 'actions-wand-sparkles',
        subject: filename,
        callback: () => this.renameOne('ai', Number(row.dataset.fileUid)),
      });
      return;
    }
    if (actionElement.dataset.action === 'undo') {
      const filename = row.querySelector('[data-role="current-filename"]')?.textContent?.trim() || '';
      this.confirm({
        title: lang('rename.confirm.undo.title', 'Undo this rename?'),
        message: lang('rename.confirm.undo.message', 'The immediately previous filename will be restored and TYPO3 file references will remain connected.'),
        note: lang('rename.confirm.undo.note', 'This action does not use an API credit.'),
        actionLabel: lang('rename.confirm.undo.action', 'Undo rename'),
        icon: 'actions-edit-undo',
        variant: 'undo',
        subject: filename,
        callback: () => this.renameOne('undo', Number(row.dataset.fileUid)),
      });
      return;
    }
    if (actionElement.dataset.role === 'rename-selected') {
      const ids = this.rowCheckboxes().filter((checkbox) => checkbox.checked).map((checkbox) => Number(checkbox.value));
      this.confirmBulk(ids);
      return;
    }
    if (actionElement.dataset.role === 'rename-visible') {
      const ids = [...this.container.querySelectorAll('tr[data-eligible="1"]')].map((item) => Number(item.dataset.fileUid));
      this.confirmBulk(ids);
    }
  }

  confirmBulk(ids) {
    if (!ids.length) return;
    const count = String(ids.length);
    this.confirm({
      title: lang('rename.confirm.bulk.title', 'Rename these images with AI?'),
      message: lang('rename.confirm.bulk.message', '%1$d images will be renamed one at a time with SEO-friendly filenames.').replace('%1$d', count),
      note: lang('rename.confirm.bulk.note', 'Keep this tab open until processing finishes. Up to %1$d credits may be used.').replace('%1$d', count),
      actionLabel: lang('rename.confirm.bulk.action', 'Rename %1$d images').replace('%1$d', count),
      icon: 'actions-wand-sparkles',
      callback: () => this.processBulk(ids),
    });
  }

  confirm({ title, message = '', note, actionLabel, callback, icon = 'actions-wand-sparkles', variant = 'ai', subject = '' }) {
    showAutoAltConfirmation({
      title,
      message,
      note,
      subject,
      subjectLabel: lang('rename.confirm.imageLabel', 'Image'),
      confirmLabel: actionLabel,
      cancelLabel: lang('rename.action.cancel', 'Cancel'),
      icon,
      variant,
      onConfirm: callback,
    });
  }

  async request(action, fileUid, filename = '') {
    const response = await new AjaxRequest(this.urls[action]).post({ fileUid, filename });
    return await response.resolve();
  }

  async renameOne(action, fileUid, filename = '') {
    this.setRunning(true);
    try {
      const data = await this.request(action, fileUid, filename);
      if (!data.success) throw new Error(data.message || lang('rename.message.failed', 'The image could not be renamed.'));
      Notification.success('AutoAlt.ai', data.message);
      window.location.reload();
    } catch (error) {
      Notification.error('AutoAlt.ai', await this.errorMessage(error));
      this.setRunning(false);
    }
  }

  async processBulk(ids) {
    this.setRunning(true);
    this.progress.hidden = false;
    let successful = 0;
    let failed = 0;
    let skipped = 0;
    let processed = 0;

    for (const fileUid of ids) {
      try {
        const data = await this.request('ai', fileUid);
        if (data.success) successful += 1;
        else if (data.skipped) skipped += 1;
        else failed += 1;
        if (data.creditExhausted) {
          Notification.warning('AutoAlt.ai', data.message);
          processed += 1;
          this.updateProgress(processed, ids.length, successful, failed, skipped);
          break;
        }
      } catch (error) {
        failed += 1;
        const message = await this.errorMessage(error);
        if (/credit|balance|quota/i.test(message)) {
          Notification.warning('AutoAlt.ai', message);
          processed += 1;
          this.updateProgress(processed, ids.length, successful, failed, skipped);
          break;
        }
      }
      processed += 1;
      this.updateProgress(processed, ids.length, successful, failed, skipped);
    }

    const summary = lang('rename.bulk.done', '%1$d successful, %2$d failed, %3$d skipped.')
      .replace('%1$d', String(successful)).replace('%2$d', String(failed)).replace('%3$d', String(skipped));
    if (failed) Notification.warning('AutoAlt.ai', summary);
    else Notification.success('AutoAlt.ai', summary);
    window.setTimeout(() => window.location.reload(), 700);
  }

  updateProgress(processed, total, successful, failed, skipped) {
    const percent = total ? Math.round(processed / total * 100) : 100;
    this.container.querySelector('[data-role="progress-label"]').textContent = lang('rename.progress.label', '%1$d of %2$d processed').replace('%1$d', String(processed)).replace('%2$d', String(total));
    this.container.querySelector('[data-role="progress-percent"]').textContent = percent + '%';
    this.container.querySelector('[data-role="progress-bar"]').style.width = percent + '%';
    this.container.querySelector('[data-role="progress-summary"]').textContent = lang('rename.progress.summary', '%1$d successful · %2$d failed · %3$d skipped').replace('%1$d', String(successful)).replace('%2$d', String(failed)).replace('%3$d', String(skipped));
  }

  async errorMessage(error) {
    try {
      const data = await error.response?.json();
      if (data?.message) return data.message;
    } catch {}
    return error?.message || lang('rename.message.failed', 'The image could not be renamed.');
  }

  rowCheckboxes() {
    return [...this.container.querySelectorAll('[data-role="row-select"]:not(:disabled)')];
  }

  updateSelection() {
    const count = this.rowCheckboxes().filter((checkbox) => checkbox.checked).length;
    if (this.selectedButton) {
      this.selectedButton.disabled = count === 0;
      this.selectedButton.textContent = lang('rename.bulk.selected', 'AI Rename Selected (%1$d)').replace('%1$d', String(count));
    }
  }

  setRunning(running) {
    this.running = running;
    this.container.querySelectorAll('button, input[type="checkbox"]').forEach((element) => {
      if (running) {
        this.disabledState.set(element, element.disabled);
        element.disabled = true;
      } else {
        element.disabled = this.disabledState.get(element) || false;
      }
    });
    if (!running) this.updateSelection();
  }
}

DocumentService.ready().then(() => {
  document.querySelectorAll('[data-autoalt-bulk-rename]').forEach((container) => new BulkRename(container));
});
