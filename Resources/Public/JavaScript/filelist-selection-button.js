import DocumentService from '@typo3/core/document-service.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import Icons from '@typo3/backend/icons.js';
import { MultiRecordSelectionSelectors } from '@typo3/backend/multi-record-selection.js';
import showAutoAltConfirmation from './confirmation-dialog.js';
import '@typo3/backend/element/spinner-element.js';
import '@typo3/backend/element/progress-bar-element.js';

function lang(key, fallback) {
  return (window.TYPO3?.lang && window.TYPO3.lang[key]) || fallback;
}

const ACTIONS_SELECTOR = '.t3js-multi-record-selection-actions';
const ACTION_NAME = 'generateAltText';
const busyState = new WeakMap();
let currentActionsWrapper = null;

function setButtonBusy(button, isBusy) {
  button.disabled = isBusy;
  button.setAttribute('aria-disabled', isBusy ? 'true' : 'false');
  button.style.pointerEvents = isBusy ? 'none' : '';
  button.style.opacity = isBusy ? '.75' : '';

  const iconElement = button.querySelector('.icon');
  if (!iconElement && !busyState.has(button)) {
    return;
  }

  if (isBusy) {
    const spinner = document.createElement('typo3-backend-spinner');
    spinner.setAttribute('size', 'small');
    iconElement.replaceWith(spinner);
    busyState.set(button, { icon: iconElement, spinner });
  } else {
    const state = busyState.get(button);
    if (state) {
      state.spinner.replaceWith(state.icon);
      busyState.delete(button);
    }
  }
}

function collectSelection(checkboxes) {
  const fileUids = [];
  const folderIdentifiers = [];

  checkboxes.forEach((checkbox) => {
    const element = checkbox.closest(MultiRecordSelectionSelectors.elementSelector);
    if (!element) {
      return;
    }

    if (element.dataset.filelistType === 'file') {
      const uid = Number(element.dataset.filelistUid || 0);
      if (uid > 0) {
        fileUids.push(uid);
      }
    } else if (element.dataset.filelistType === 'folder' && element.dataset.filelistIdentifier) {
      folderIdentifiers.push(element.dataset.filelistIdentifier);
    }
  });

  return { fileUids, folderIdentifiers };
}

function chunkArray(items, size) {
  const chunks = [];
  for (let i = 0; i < items.length; i += size) {
    chunks.push(items.slice(i, i + size));
  }
  return chunks;
}

function startProgress(progressBar) {
  if (typeof progressBar.start === 'function') {
    progressBar.start();
  }
}

async function finishProgress(progressBar) {
  if (typeof progressBar.done === 'function') {
    await progressBar.done();
  } else {
    progressBar.remove();
  }
}

async function injectButton(actionsWrapper) {
  if (actionsWrapper.querySelector('[data-multi-record-selection-action="' + ACTION_NAME + '"]')) {
    return;
  }

  const icon = await Icons.getIcon('actions-wand-sparkles', Icons.sizes.small);

  const column = document.createElement('div');
  column.className = 'col';

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'btn btn-default btn-sm';
  button.dataset.multiRecordSelectionAction = ACTION_NAME;
  button.style.backgroundColor = '#b30000';
  button.style.borderColor = '#b30000';
  button.style.color = '#fff';

  const label = lang('filelist.selection.button', 'Generate Alt Text - AutoAlt.ai');
  const span = document.createElement('span');
  span.title = label;
  span.appendChild(document.createRange().createContextualFragment(icon));
  span.appendChild(document.createTextNode(' ' + label));
  button.appendChild(span);

  column.appendChild(button);
  actionsWrapper.appendChild(column);
}

// AjaxRequest rejects with the raw AjaxResponse (not an Error) for any
// non-2xx status, so error.message is always undefined there; the actual
// server-provided reason has to be read from its JSON body instead.
async function extractErrorMessage(error) {
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

async function resolveSelection(fileUids, folderIdentifiers) {
  const url = TYPO3.settings.ajaxUrls.alt_text_generator_selection_resolve;
  const response = await new AjaxRequest(url).post({ fileUids, folderIdentifiers });

  return response.resolve();
}

async function processChunk(chunk) {
  const url = TYPO3.settings.ajaxUrls.alt_text_generator_selection_process;
  const response = await new AjaxRequest(url).post({ fileUids: chunk, folderIdentifiers: [] });

  return response.resolve();
}

async function confirmAndRun(event) {
  const button = event.target;
  const { fileUids, folderIdentifiers } = collectSelection(event.detail.checkboxes);

  if (fileUids.length === 0 && folderIdentifiers.length === 0) {
    Notification.warning(
      'AutoAlt.ai',
      lang('filelist.selection.noImages', 'Select at least one image or folder. Other file types are skipped.')
    );
    return;
  }

  // Resolving (expanding folders) can take a moment for large folders, so
  // give feedback and prevent double-clicks before the confirmation dialog.
  setButtonBusy(button, true);

  let resolved;
  try {
    resolved = await resolveSelection(fileUids, folderIdentifiers);
  } catch (error) {
    setButtonBusy(button, false);
    const message = await extractErrorMessage(error);
    Notification.error('AutoAlt.ai', message || lang('filelist.selection.resolveFailed', 'Could not resolve the selected images.'));
    return;
  }

  setButtonBusy(button, false);

  if (!resolved.success) {
    Notification.error('AutoAlt.ai', resolved.message || lang('filelist.selection.resolveFailed', 'Could not resolve the selected images.'));
    return;
  }

  if (resolved.total === 0) {
    Notification.warning('AutoAlt.ai', lang('filelist.selection.noImages', 'Select at least one image or folder. Other file types are skipped.'));
    return;
  }

  let note = lang(
    'filelist.selection.confirm.note',
    'Existing alt text will be overwritten. Keep this browser tab open until processing finishes.'
  );

  if (resolved.truncated) {
    note += ' ' + lang(
      'filelist.selection.confirm.truncatedNote',
      'Only the first %d matching images will be processed - your selection contains more. Run this again afterwards for the rest.'
    ).replace('%d', String(resolved.total));
  }

  showAutoAltConfirmation({
    title: lang('filelist.selection.confirm.title', 'Generate alt text for selected images?'),
    subjectLabel: lang('filelist.selection.confirm.subjectLabel', 'Images'),
    subject: lang('filelist.selection.confirm.subject', '%1$d selected').replace('%1$d', String(resolved.total)),
    note,
    confirmLabel: lang('filelist.selection.confirm.ok', 'Generate alt text'),
    cancelLabel: lang('filelist.selection.confirm.cancel', 'Cancel'),
    icon: 'actions-wand-sparkles',
    onConfirm: () => runChunkedGeneration(resolved.fileUids, resolved.batchSize, button),
  });
}

async function runChunkedGeneration(fileUids, batchSize, button) {
  const chunks = chunkArray(fileUids, Math.max(1, batchSize));
  const total = fileUids.length;

  setButtonBusy(button, true);

  const progressBar = document.createElement('typo3-backend-progress-bar');
  progressBar.max = total;
  (currentActionsWrapper || button.parentElement)?.appendChild(progressBar);
  startProgress(progressBar);

  // Warn on navigation away, since this is a client-driven loop with no
  // server-side job to resume - closing the tab mid-run abandons the rest.
  const beforeUnloadHandler = (unloadEvent) => {
    unloadEvent.preventDefault();
    unloadEvent.returnValue = lang(
      'filelist.selection.beforeunload',
      'AutoAlt.ai is still generating alt text. Anything not yet processed will be skipped if you leave now.'
    );
  };
  window.addEventListener('beforeunload', beforeUnloadHandler);

  let completed = 0;
  let failed = 0;
  let processedCount = 0;
  let stoppedEarly = null;

  for (const chunk of chunks) {
    progressBar.label = lang('filelist.selection.progress.label', '%1$d of %2$d processed')
      .replace('%1$d', String(processedCount))
      .replace('%2$d', String(total));

    let data;
    try {
      data = await processChunk(chunk);
    } catch (error) {
      stoppedEarly = (await extractErrorMessage(error)) || lang('filelist.selection.failed', 'Alt text generation failed.');
      break;
    }

    if (!data.success) {
      stoppedEarly = data.message || lang('filelist.selection.failed', 'Alt text generation failed.');
      break;
    }

    completed += data.result.completed;
    failed += data.result.failed;
    processedCount += chunk.length;
    progressBar.value = processedCount;

    if (data.result.creditExhausted) {
      stoppedEarly = data.result.message || lang('filelist.selection.creditExhausted', 'You have run out of AutoAlt.ai credits. Add more credits to your account to continue.');
      break;
    }
  }

  window.removeEventListener('beforeunload', beforeUnloadHandler);
  await finishProgress(progressBar);
  setButtonBusy(button, false);

  if (stoppedEarly) {
    Notification.error(
      'AutoAlt.ai',
      lang('filelist.selection.stoppedEarly', 'Stopped after %1$d of %2$d images: %3$s')
        .replace('%1$d', String(processedCount))
        .replace('%2$d', String(total))
        .replace('%3$s', stoppedEarly)
    );
  } else {
    const summary = lang('filelist.selection.done', '%1$d generated, %2$d failed.')
      .replace('%1$d', String(completed))
      .replace('%2$d', String(failed));

    if (failed > 0 && completed === 0) {
      Notification.error('AutoAlt.ai', summary);
    } else if (failed > 0) {
      Notification.warning('AutoAlt.ai', summary);
    } else {
      Notification.success('AutoAlt.ai', summary);
    }
  }

  window.location.reload();
}

DocumentService.ready().then(() => {
  const actionsWrapper = document.querySelector(ACTIONS_SELECTOR);
  if (!actionsWrapper) {
    return;
  }

  currentActionsWrapper = actionsWrapper;
  injectButton(actionsWrapper);
  document.addEventListener('multiRecordSelection:action:' + ACTION_NAME, confirmAndRun);
});
