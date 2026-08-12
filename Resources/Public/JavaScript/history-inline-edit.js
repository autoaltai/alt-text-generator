import Notification from '@typo3/backend/notification.js';

function lang(key, fallback) {
  return (window.TYPO3?.lang && window.TYPO3.lang[key]) || fallback;
}

const FIELD_CONFIG = {
  altText: {
    allowEmpty: false,
    emptyMessage: ['history.edit.empty', 'Alt text cannot be empty.'],
    updatedMessage: ['history.edit.updated', 'Alt text updated.'],
  },
  title: {
    allowEmpty: true,
    updatedMessage: ['history.edit.title.updated', 'Title updated.'],
  },
  description: {
    allowEmpty: true,
    updatedMessage: ['history.edit.description.updated', 'Description updated.'],
  },
};

class HistoryInlineEdit {
  constructor(container) {
    container.querySelectorAll('[data-role="editable-field"]').forEach((row) => this.initRow(row));
  }

  initRow(row) {
    const uid = row.dataset.historyUid;
    const field = row.dataset.field || 'altText';
    const updateUrl = row.dataset.updateUrl;
    const config = FIELD_CONFIG[field] || FIELD_CONFIG.altText;
    const viewMode = row.querySelector('[data-role="view-mode"]');
    const viewText = row.querySelector('[data-role="view-text"]');
    const editTrigger = row.querySelector('[data-role="edit-trigger"]');
    const editMode = row.querySelector('[data-role="edit-mode"]');
    const textarea = row.querySelector('[data-role="edit-textarea"]');
    const message = row.querySelector('[data-role="edit-message"]');
    const cancelButton = row.querySelector('[data-role="cancel-btn"]');
    const saveButton = row.querySelector('[data-role="save-btn"]');

    if (!editTrigger || !editMode || !textarea || !updateUrl) {
      return;
    }

    editTrigger.addEventListener('click', () => {
      textarea.value = viewText.textContent.trim();
      this.setMessage(message, '', '');
      viewMode.hidden = true;
      editMode.hidden = false;
      textarea.focus();
    });

    cancelButton?.addEventListener('click', () => {
      editMode.hidden = true;
      viewMode.hidden = false;
      this.setMessage(message, '', '');
    });

    saveButton?.addEventListener('click', () => this.save({
      uid, field, updateUrl, config, textarea, message, editMode, viewMode, viewText, saveButton, cancelButton,
    }));
  }

  async save({ uid, field, updateUrl, config, textarea, message, editMode, viewMode, viewText, saveButton, cancelButton }) {
    const value = textarea.value.trim();
    if (value === '' && !config.allowEmpty) {
      this.setMessage(message, lang(...config.emptyMessage), 'danger');
      return;
    }

    saveButton.disabled = true;
    cancelButton.disabled = true;
    this.setMessage(message, lang('history.edit.saving', 'Saving…'), 'info');

    try {
      const response = await fetch(updateUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ uid: uid, [field]: value }).toString(),
      });
      const data = await response.json();

      if (!data.success) {
        const fallback = lang('history.edit.failed', 'Could not save. Please try again.');
        this.setMessage(message, data.message || fallback, 'danger');
        saveButton.disabled = false;
        cancelButton.disabled = false;
        return;
      }

      viewText.textContent = data[field];
      editMode.hidden = true;
      viewMode.hidden = false;
      saveButton.disabled = false;
      cancelButton.disabled = false;
      Notification.success('AutoAlt.ai', lang(...config.updatedMessage));
    } catch (error) {
      const fallback = lang('history.edit.failed', 'Could not save. Please try again.');
      this.setMessage(message, error?.message || fallback, 'danger');
      saveButton.disabled = false;
      cancelButton.disabled = false;
    }
  }

  setMessage(element, text, state) {
    if (!element) {
      return;
    }
    if (text === '') {
      element.hidden = true;
      return;
    }
    element.textContent = text;
    element.className = 'autoalt-history-editable__message autoalt-history-editable__message--' + state;
    element.hidden = false;
  }
}

function showRetryNotification() {
  const holder = document.querySelector('[data-role="retry-notification"]');
  if (!holder) {
    return;
  }

  const state = holder.dataset.state;
  const message = holder.dataset.message;
  const notify = { success: Notification.success, warning: Notification.warning, danger: Notification.error }[state] || Notification.info;
  notify.call(Notification, 'AutoAlt.ai', message);

  const url = new URL(window.location.href);
  url.searchParams.delete('retried');
  url.searchParams.delete('permissionDenied');
  window.history.replaceState({}, document.title, url.toString());
}

document.querySelectorAll('[data-role="history-table"]').forEach((container) => new HistoryInlineEdit(container));
showRetryNotification();

export default HistoryInlineEdit;
