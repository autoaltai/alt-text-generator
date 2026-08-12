import showAutoAltConfirmation from './confirmation-dialog.js';

function lang(key, fallback) {
  return (window.TYPO3?.lang && window.TYPO3.lang[key]) || fallback;
}

class SettingsPage {
  constructor(form) {
    this.form = form;
    this.initLengthControl();
    this.initPrefixSuffixCounters();
    this.initSeoGuidance();
    this.initErrorLog();
    this.initExtensionChips();
    this.initNotifyToggle();
  }

  initLengthControl() {
    const control = this.form.querySelector('[data-role="length-control"]');
    if (!control) {
      return;
    }

    const minNumber = control.querySelector('[data-role="min-number"]');
    const maxNumber = control.querySelector('[data-role="max-number"]');
    const minRange = control.querySelector('[data-role="min-range"]');
    const maxRange = control.querySelector('[data-role="max-range"]');
    const badge = control.querySelector('[data-role="seo-status-badge"]');

    const updateRangeProgress = (rangeField) => {
      if (!rangeField) {
        return;
      }
      const minimum = Number(rangeField.min) || 0;
      const maximum = Number(rangeField.max) || 100;
      const value = Number(rangeField.value) || minimum;
      const progress = maximum > minimum ? ((value - minimum) / (maximum - minimum)) * 100 : 0;
      rangeField.style.setProperty('--autoalt-range-progress', `${Math.min(100, Math.max(0, progress))}%`);
    };

    const sync = (numberField, rangeField) => {
      if (!numberField || !rangeField) {
        return;
      }
      rangeField.value = numberField.value;
      updateRangeProgress(rangeField);
    };

    minNumber?.addEventListener('input', () => sync(minNumber, minRange));
    maxNumber?.addEventListener('input', () => {
      sync(maxNumber, maxRange);
      this.updateSeoStatusBadge(badge, maxNumber.value);
    });
    minRange?.addEventListener('input', () => {
      minNumber.value = minRange.value;
      updateRangeProgress(minRange);
    });
    maxRange?.addEventListener('input', () => {
      maxNumber.value = maxRange.value;
      updateRangeProgress(maxRange);
      this.updateSeoStatusBadge(badge, maxNumber.value);
    });

    updateRangeProgress(minRange);
    updateRangeProgress(maxRange);
    if (maxNumber) {
      this.updateSeoStatusBadge(badge, maxNumber.value);
    }
  }

  updateSeoStatusBadge(badge, value) {
    if (!badge) {
      return;
    }

    const numericValue = Number(value) || 0;
    let state = 'optimal';
    let fallback = 'Optimal';
    if (numericValue > 150) {
      state = 'long';
      fallback = 'Long';
    } else if (numericValue < 100) {
      state = 'short';
      fallback = 'Short';
    }

    badge.textContent = lang('settings.length.seoStatus.' + state, fallback);
    badge.className = 'autoalt-badge autoalt-badge--' + state;
  }

  initPrefixSuffixCounters() {
    const prefixInput = this.form.querySelector('[data-role="prefix-input"]');
    const suffixInput = this.form.querySelector('[data-role="suffix-input"]');
    const prefixCounter = this.form.querySelector('[data-role="prefix-counter"]');
    const suffixCounter = this.form.querySelector('[data-role="suffix-counter"]');
    const prefixPreview = this.form.querySelector('[data-role="prefix-preview"]');
    const suffixPreview = this.form.querySelector('[data-role="suffix-preview"]');

    const updatePreview = (input, preview) => {
      if (!input || !preview) {
        return;
      }
      preview.textContent = input.value;
      preview.hidden = input.value === '';
    };

    const updatePrefix = () => {
      if (prefixCounter) {
        prefixCounter.textContent = prefixInput.value.length + ' / 60';
      }
      updatePreview(prefixInput, prefixPreview);
    };

    const updateSuffix = () => {
      if (suffixCounter) {
        suffixCounter.textContent = suffixInput.value.length + ' / 60';
      }
      updatePreview(suffixInput, suffixPreview);
    };

    prefixInput?.addEventListener('input', updatePrefix);
    suffixInput?.addEventListener('input', updateSuffix);

    if (prefixInput) {
      updatePrefix();
    }
    if (suffixInput) {
      updateSuffix();
    }
  }

  initSeoGuidance() {
    const section = this.form.querySelector('[data-role="seo-guidance"]');
    if (!section) {
      return;
    }

    const seoKeywordsInput = section.querySelector('[data-role="seo-keywords-input"]');
    const negativeKeywordsInput = section.querySelector('[data-role="negative-keywords-input"]');
    const seoKeywordsCounter = section.querySelector('[data-role="seo-keywords-counter"]');
    const negativeKeywordsCounter = section.querySelector('[data-role="negative-keywords-counter"]');
    const seoKeywordsError = section.querySelector('[data-role="seo-keywords-error"]');
    const negativeKeywordsError = section.querySelector('[data-role="negative-keywords-error"]');
    const submitButtons = Array.from(this.form.querySelectorAll('button[type="submit"][name="settingsAction"]'));
    const customPromptInput = section.querySelector('[data-role="custom-prompt-input"]');
    const customPromptCounter = section.querySelector('[data-role="custom-prompt-counter"]');
    const customPromptError = section.querySelector('[data-role="custom-prompt-error"]');
    let keywordsAreValid = true;
    let customPromptIsValid = true;

    const updateSubmitButtons = () => {
      if (seoKeywordsInput?.disabled || negativeKeywordsInput?.disabled || customPromptInput?.disabled) {
        return;
      }
      submitButtons.forEach((button) => { button.disabled = !keywordsAreValid || !customPromptIsValid; });
    };

    const parseKeywords = (value) => value
      .split(',')
      .map((keyword) => keyword.trim())
      .filter((keyword) => keyword !== '');

    const updateKeywordCounter = (input, counter) => {
      if (!input || !counter) {
        return;
      }
      const count = parseKeywords(input.value).length;
      counter.textContent = lang('settings.seo.keywordCount', '%1$d / 6 keywords').replace('%1$d', String(count));
      counter.classList.toggle('is-over-limit', count > 6);
    };

    const setKeywordError = (input, errorElement, message) => {
      if (!input || !errorElement) {
        return;
      }
      const invalid = message !== '';
      input.classList.toggle('is-invalid', invalid);
      input.setAttribute('aria-invalid', invalid ? 'true' : 'false');
      input.setCustomValidity(message);
      errorElement.textContent = message;
      errorElement.hidden = !invalid;
    };

    const validateKeywords = () => {
      const seoInput = (seoKeywordsInput?.value || '').trim();
      const negativeInput = (negativeKeywordsInput?.value || '').trim();
      const seoKeywords = parseKeywords(seoInput);
      const negativeKeywords = parseKeywords(negativeInput);
      const errors = { seo: '', negative: '' };

      if (seoInput.length > 180) {
        errors.seo = lang('bulk.keywordValidation.seoTotal', 'SEO keywords must not exceed %1$d characters.').replace('%1$d', '180');
      } else if (negativeInput.length > 180) {
        errors.negative = lang('bulk.keywordValidation.negativeTotal', 'Negative keywords must not exceed %1$d characters.').replace('%1$d', '180');
      } else if (seoKeywords.length > 6) {
        errors.seo = lang('bulk.keywordValidation.seoCount', 'SEO keywords can contain a maximum of %1$d keywords.').replace('%1$d', '6');
      } else if (negativeKeywords.length > 6) {
        errors.negative = lang('bulk.keywordValidation.negativeCount', 'Negative keywords can contain a maximum of %1$d keywords.').replace('%1$d', '6');
      } else if (seoKeywords.some((keyword) => keyword.length > 30)) {
        errors.seo = lang('bulk.keywordValidation.seoKeywordLength', 'Each SEO keyword must be at most %1$d characters.').replace('%1$d', '30');
      } else if (negativeKeywords.some((keyword) => keyword.length > 30)) {
        errors.negative = lang('bulk.keywordValidation.negativeKeywordLength', 'Each negative keyword must be at most %1$d characters.').replace('%1$d', '30');
      } else if (new Set(seoKeywords.map((keyword) => keyword.toLowerCase())).size !== seoKeywords.length) {
        errors.seo = lang('bulk.keywordValidation.seoDuplicates', 'SEO keywords contain duplicate values.');
      } else if (new Set(negativeKeywords.map((keyword) => keyword.toLowerCase())).size !== negativeKeywords.length) {
        errors.negative = lang('bulk.keywordValidation.negativeDuplicates', 'Negative keywords contain duplicate values.');
      } else {
        const negativeSet = new Set(negativeKeywords.map((keyword) => keyword.toLowerCase()));
        const conflicts = seoKeywords.map((keyword) => keyword.toLowerCase()).filter((keyword) => negativeSet.has(keyword));
        if (conflicts.length > 0) {
          errors.negative = lang('bulk.keywordValidation.conflict', 'Negative keywords must not match SEO keywords: %1$s.')
            .replace('%1$s', conflicts.join(', '));
        }
      }

      setKeywordError(seoKeywordsInput, seoKeywordsError, errors.seo);
      setKeywordError(negativeKeywordsInput, negativeKeywordsError, errors.negative);
      keywordsAreValid = errors.seo === '' && errors.negative === '';
      updateSubmitButtons();
    };

    const updatePromptCounter = () => {
      if (!customPromptInput || !customPromptCounter) {
        return;
      }
      const maximum = Number(customPromptInput.maxLength) || 500;
      const count = customPromptInput.value.length;
      const error = count > maximum
        ? lang('settings.validation.customPromptTooLong', 'Custom instructions must not exceed %1$d characters.')
          .replace('%1$d', String(maximum))
        : '';
      customPromptIsValid = error === '';
      customPromptInput.classList.toggle('is-invalid', !customPromptIsValid);
      customPromptInput.setAttribute('aria-invalid', customPromptIsValid ? 'false' : 'true');
      customPromptInput.setCustomValidity(error);
      if (customPromptError) {
        customPromptError.textContent = error;
        customPromptError.hidden = customPromptIsValid;
      }
      customPromptCounter.textContent = lang('settings.seo.customPromptCharacterCount', '%1$d / %2$d characters')
        .replace('%1$d', String(count))
        .replace('%2$d', String(maximum));
      updateSubmitButtons();
    };

    const updateKeywords = () => {
      updateKeywordCounter(seoKeywordsInput, seoKeywordsCounter);
      updateKeywordCounter(negativeKeywordsInput, negativeKeywordsCounter);
      validateKeywords();
    };

    seoKeywordsInput?.addEventListener('input', updateKeywords);
    negativeKeywordsInput?.addEventListener('input', updateKeywords);
    customPromptInput?.addEventListener('input', updatePromptCounter);

    updateKeywords();
    updatePromptCounter();
  }

  initErrorLog() {
    const clearButton = this.form.querySelector('[data-role="clear-error-logs"]');
    if (!clearButton) {
      return;
    }

    clearButton.addEventListener('click', (event) => {
      event.preventDefault();
      showAutoAltConfirmation({
        title: lang('settings.errorLogs.clearConfirm.title', 'Clear all error logs?'),
        subjectLabel: lang('settings.errorLogs.clearConfirm.subjectLabel', 'Logs'),
        subject: lang('settings.errorLogs.clearConfirm.subject', 'All recent error entries'),
        message: lang('settings.errorLogs.clearConfirm.message', 'This removes the stored API and processing issues from the Error Logs panel.'),
        note: lang('settings.errorLogs.clearConfirm.note', 'This action cannot be undone.'),
        confirmLabel: lang('settings.errorLogs.clear', 'Clear logs'),
        cancelLabel: lang('settings.action.cancel', 'Cancel'),
        icon: 'actions-delete',
        variant: 'danger',
        onConfirm: () => this.form.requestSubmit(clearButton),
      });
    });
  }

  initExtensionChips() {
    const group = this.form.querySelector('[data-role="extension-chips"]');
    const input = this.form.querySelector('[data-role="extension-input"]');
    if (!group || !input) {
      return;
    }

    const currentExtensions = () => input.value
      .split(',')
      .map((value) => value.trim().toLowerCase())
      .filter((value) => value !== '');

    const refreshChipStates = () => {
      const selected = currentExtensions();
      group.querySelectorAll('[data-extension]').forEach((chip) => {
        chip.classList.toggle('is-active', selected.includes(chip.dataset.extension));
      });
    };

    group.querySelectorAll('[data-extension]').forEach((chip) => {
      chip.addEventListener('click', () => {
        const selected = currentExtensions();
        const extension = chip.dataset.extension;
        const index = selected.indexOf(extension);
        if (index === -1) {
          selected.push(extension);
        } else {
          selected.splice(index, 1);
        }
        input.value = selected.join(',');
        refreshChipStates();
      });
    });

    input.addEventListener('input', refreshChipStates);
    refreshChipStates();
  }

  initNotifyToggle() {
    const toggle = this.form.querySelector('[data-role="notify-toggle"]');
    const emailField = this.form.querySelector('[data-role="notify-email-field"]');
    if (!toggle || !emailField) {
      return;
    }

    const refresh = () => {
      emailField.hidden = !toggle.checked;
    };

    toggle.addEventListener('change', refresh);
    refresh();
  }
}

document.querySelectorAll('[data-autoalt-settings-page]').forEach((form) => new SettingsPage(form));

export default SettingsPage;
