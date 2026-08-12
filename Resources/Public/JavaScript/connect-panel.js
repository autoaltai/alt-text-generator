import Notification from '@typo3/backend/notification.js';
import showAutoAltConfirmation from './confirmation-dialog.js';

function lang(key, fallback) {
  return (window.TYPO3?.lang && window.TYPO3.lang[key]) || fallback;
}

const OTP_LENGTH = 6;
const OTP_DURATION_SECONDS = 300;

class ConnectPanel {
  constructor(container) {
    this.container = container;
    this.connectUrl = container.dataset.connectUrl;
    this.clearUrl = container.dataset.clearUrl;
    this.sendOtpUrl = container.dataset.sendOtpUrl;
    this.verifyOtpUrl = container.dataset.verifyOtpUrl;

    this.apiKeyInput = container.querySelector('[data-role="connect-api-key-input"]');
    this.toggleVisibilityButton = container.querySelector('[data-role="connect-toggle-visibility"]');
    this.toggleVisibilityLabel = container.querySelector('[data-role="connect-toggle-visibility-label"]');
    this.showIcon = container.querySelector('[data-role="connect-show-icon"]');
    this.hideIcon = container.querySelector('[data-role="connect-hide-icon"]');
    this.saveButton = container.querySelector('[data-role="connect-save-button"]');
    this.clearButton = container.querySelector('[data-role="connect-clear-button"]');
    this.connectMessage = container.querySelector('[data-role="connect-message"]');
    this.creditLine = container.querySelector('[data-role="connect-credit-line"]');
    this.creditText = container.querySelector('[data-role="connect-credit-text"]');
    this.connectionStatus = container.querySelector('[data-role="connect-status"]');
    this.summaryStatus = document.querySelector('[data-role="connect-summary-status"]');
    this.summaryStatusText = document.querySelector('[data-role="connect-summary-text"]');

    this.registerCard = container.querySelector('[data-role="connect-register-card"]');
    this.emailStep = container.querySelector('[data-role="connect-email-step"]');
    this.emailInput = container.querySelector('[data-role="connect-email-input"]');
    this.emailMessage = container.querySelector('[data-role="connect-email-message"]');
    this.sendOtpButton = container.querySelector('[data-role="connect-send-otp-button"]');
    this.otpStep = container.querySelector('[data-role="connect-otp-step"]');
    this.otpDigits = Array.from(container.querySelectorAll('[data-role="connect-otp-digit"]'));
    this.otpMessage = container.querySelector('[data-role="connect-otp-message"]');
    this.otpTimerLabel = container.querySelector('[data-role="connect-otp-timer"]');
    this.resendButton = container.querySelector('[data-role="connect-resend-button"]');
    this.verifyOtpButton = container.querySelector('[data-role="connect-verify-otp-button"]');

    this.otpTimerInterval = null;
    this.otpSecondsLeft = 0;
    this.otpEmail = '';

    this.initApiKeyCard();
    this.initRegisterCard();
  }

  initApiKeyCard() {
    this.toggleVisibilityButton?.addEventListener('click', () => {
      if (!this.apiKeyInput) {
        return;
      }
      const isPassword = this.apiKeyInput.type === 'password';
      this.apiKeyInput.type = isPassword ? 'text' : 'password';
      this.updateVisibilityToggle(isPassword);
    });

    this.saveButton?.addEventListener('click', () => this.connectApiKey());
    this.clearButton?.addEventListener('click', () => this.confirmClearApiKey());
  }

  async connectApiKey() {
    const apiKey = (this.apiKeyInput?.value || '').trim();
    if (apiKey === '') {
      this.setMessage(this.connectMessage, lang('connect.error.emptyKey', 'Enter an API key before connecting.'), 'danger');
      return;
    }

    this.setButtonBusy(this.saveButton, true, lang('connect.action.connecting', 'Connecting…'));
    this.setMessage(this.connectMessage, '', '');

    let data;
    try {
      data = await this.postJson(this.connectUrl, { apiKey });
    } catch (error) {
      this.setButtonBusy(this.saveButton, false, lang('connect.action.connectAndSave', 'Connect & Save'));
      this.setMessage(this.connectMessage, this.extractErrorMessage(error), 'danger');
      return;
    }

    if (!data.success) {
      this.setButtonBusy(this.saveButton, false, lang('connect.action.connectAndSave', 'Connect & Save'));
      this.setMessage(this.connectMessage, data.message || lang('connect.error.invalidKey', 'This API key could not be verified. Check the key and try again.'), 'danger');
      return;
    }

    this.onConnected(data);
  }

  onConnected(data) {
    const connectedLabel = lang('connect.action.connected', 'Connected');
    this.setButtonBusy(this.saveButton, false, connectedLabel);
    const message = data.message || lang('connect.apiKeyConnected', 'API key connected successfully.');
    this.setMessage(this.connectMessage, message, 'success');
    Notification.success('AutoAlt.ai', message);

    this.clearButton?.removeAttribute('hidden');
    if (this.registerCard) {
      this.registerCard.hidden = true;
    }
    this.container.classList.remove('autoalt-connect--setup');
    this.container.classList.add('autoalt-connect--connected');
    this.connectionStatus?.removeAttribute('hidden');
    if (this.summaryStatus) {
      this.summaryStatus.classList.remove('autoalt-settings-summary__status--warning', 'autoalt-settings-summary__status--danger');
      this.summaryStatus.classList.add('autoalt-settings-summary__status--success');
    }
    if (this.summaryStatusText) {
      this.summaryStatusText.textContent = connectedLabel;
    }
    if (this.apiKeyInput) {
      this.apiKeyInput.value = data.apiKey || this.apiKeyInput.value;
      this.apiKeyInput.type = 'password';
    }
    if (this.toggleVisibilityButton) {
      this.updateVisibilityToggle(false);
    }
    this.updateCreditLine(data.credit);
  }

  updateVisibilityToggle(isVisible) {
    const label = isVisible
      ? lang('connect.action.hide', 'Hide')
      : lang('connect.action.show', 'Show');

    if (this.toggleVisibilityButton) {
      this.toggleVisibilityButton.setAttribute('aria-label', label);
      this.toggleVisibilityButton.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
      this.toggleVisibilityButton.setAttribute('title', label);
    }
    if (this.toggleVisibilityLabel) {
      this.toggleVisibilityLabel.textContent = label;
    }
    if (this.showIcon) {
      this.showIcon.hidden = isVisible;
    }
    if (this.hideIcon) {
      this.hideIcon.hidden = !isVisible;
    }
  }

  updateCreditLine(credit) {
    if (!credit || !credit.hasCreditData || !this.creditLine) {
      return;
    }

    const usageText = lang('connect.credit.usage', 'Your Credit Current Balance is %1$s / %2$s.')
      .replace('%1$s', credit.available)
      .replace('%2$s', credit.total);
    if (this.creditText) {
      this.creditText.textContent = usageText;
    }
    this.creditLine.hidden = false;
  }

  confirmClearApiKey() {
    showAutoAltConfirmation({
      title: lang('connect.clearConfirm.title', 'Clear the API key?'),
      subjectLabel: lang('connect.clearConfirm.subjectLabel', 'Connection'),
      subject: lang('connect.clearConfirm.subject', 'AutoAlt.ai API key'),
      note: lang('connect.clearConfirm', 'You will need to reconnect to AutoAlt.ai before generating alt text again.'),
      confirmLabel: lang('connect.action.clearApiKey', 'Clear API Key'),
      cancelLabel: lang('filelist.selection.confirm.cancel', 'Cancel'),
      icon: 'actions-key',
      variant: 'danger',
      onConfirm: () => this.clearApiKey(),
    });
  }

  async clearApiKey() {
    let data;
    try {
      data = await this.postJson(this.clearUrl, {});
    } catch (error) {
      Notification.error('AutoAlt.ai', this.extractErrorMessage(error));
      return;
    }

    if (!data.success) {
      Notification.error('AutoAlt.ai', data.message || lang('connect.error.otpVerifyFailed', 'The verification code could not be confirmed. Please try again.'));
      return;
    }

    Notification.success('AutoAlt.ai', data.message || lang('connect.apiKeyCleared', 'API key has been cleared.'));
    window.location.reload();
  }

  initRegisterCard() {
    this.sendOtpButton?.addEventListener('click', () => this.sendOtp());
    this.resendButton?.addEventListener('click', () => this.sendOtp());
    this.verifyOtpButton?.addEventListener('click', () => this.verifyOtp());

    this.otpDigits.forEach((input, index) => {
      input.addEventListener('input', () => this.onOtpInput(index));
      input.addEventListener('keydown', (event) => this.onOtpKeydown(index, event));
      input.addEventListener('paste', (event) => this.onOtpPaste(event));
    });
  }

  isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  async sendOtp() {
    const email = (this.emailInput?.value || '').trim();
    if (!this.isValidEmail(email)) {
      this.setMessage(this.emailMessage, lang('connect.error.invalidEmail', 'Please enter a valid email address.'), 'danger');
      return;
    }

    this.otpEmail = email;
    this.setButtonBusy(this.sendOtpButton, true, lang('connect.action.sending', 'Sending…'));
    this.setMessage(this.emailMessage, '', '');
    this.setMessage(this.otpMessage, '', '');

    let data;
    try {
      data = await this.postJson(this.sendOtpUrl, { email });
    } catch (error) {
      this.setButtonBusy(this.sendOtpButton, false, lang('connect.action.sendCode', 'Send Verification Code'));
      this.setMessage(this.emailMessage, this.extractErrorMessage(error), 'danger');
      return;
    }

    this.setButtonBusy(this.sendOtpButton, false, lang('connect.action.sendCode', 'Send Verification Code'));

    if (!data.success) {
      this.setMessage(this.emailMessage, data.message || lang('connect.error.invalidEmail', 'Please enter a valid email address.'), 'danger');
      return;
    }

    Notification.success('AutoAlt.ai', data.message || lang('connect.otpSent', 'OTP sent to your email.'));

    if (this.emailStep) {
      this.emailStep.hidden = true;
    }
    if (this.otpStep) {
      this.otpStep.hidden = false;
    }
    this.otpDigits.forEach((input) => {
      input.value = '';
    });
    this.otpDigits[0]?.focus();
    this.startOtpTimer();
  }

  startOtpTimer() {
    window.clearInterval(this.otpTimerInterval);
    this.otpSecondsLeft = OTP_DURATION_SECONDS;
    this.resendButton?.setAttribute('hidden', 'hidden');
    this.updateOtpTimerLabel();

    this.otpTimerInterval = window.setInterval(() => {
      this.otpSecondsLeft -= 1;
      if (this.otpSecondsLeft <= 0) {
        window.clearInterval(this.otpTimerInterval);
        this.onOtpExpired();
        return;
      }
      this.updateOtpTimerLabel();
    }, 1000);
  }

  updateOtpTimerLabel() {
    if (!this.otpTimerLabel) {
      return;
    }
    const minutes = Math.floor(this.otpSecondsLeft / 60);
    const seconds = String(this.otpSecondsLeft % 60).padStart(2, '0');
    this.otpTimerLabel.textContent = lang('connect.otp.expiresIn', 'OTP expires in') + ' ' + minutes + ':' + seconds;
  }

  onOtpExpired() {
    if (this.otpTimerLabel) {
      this.otpTimerLabel.textContent = lang('connect.otp.expired', 'The verification code has expired. Please request a new one.');
    }
    this.resendButton?.removeAttribute('hidden');
  }

  onOtpInput(index) {
    const input = this.otpDigits[index];
    input.value = input.value.replace(/\D/g, '').slice(0, 1);
    if (input.value !== '' && index < this.otpDigits.length - 1) {
      this.otpDigits[index + 1]?.focus();
    }
    this.maybeAutoSubmitOtp();
  }

  onOtpKeydown(index, event) {
    if (event.key === 'Backspace' && this.otpDigits[index].value === '' && index > 0) {
      this.otpDigits[index - 1]?.focus();
    }
  }

  onOtpPaste(event) {
    const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, OTP_LENGTH);
    if (pasted === '') {
      return;
    }
    event.preventDefault();
    pasted.split('').forEach((digit, index) => {
      if (this.otpDigits[index]) {
        this.otpDigits[index].value = digit;
      }
    });
    const lastIndex = Math.min(pasted.length, OTP_LENGTH) - 1;
    this.otpDigits[lastIndex]?.focus();
    this.maybeAutoSubmitOtp();
  }

  maybeAutoSubmitOtp() {
    const otp = this.otpDigits.map((input) => input.value).join('');
    if (otp.length === OTP_LENGTH) {
      this.verifyOtp();
    }
  }

  async verifyOtp() {
    if (this.otpSecondsLeft <= 0) {
      this.setMessage(this.otpMessage, lang('connect.otp.expired', 'The verification code has expired. Please request a new one.'), 'danger');
      return;
    }

    const otp = this.otpDigits.map((input) => input.value).join('');
    if (otp.length !== OTP_LENGTH) {
      this.setMessage(this.otpMessage, lang('connect.error.otpFormat', 'The verification code must be 6 digits.'), 'danger');
      return;
    }

    this.setButtonBusy(this.verifyOtpButton, true, lang('connect.action.verifying', 'Verifying…'));
    this.setMessage(this.otpMessage, '', '');

    let data;
    try {
      data = await this.postJson(this.verifyOtpUrl, { email: this.otpEmail, otp });
    } catch (error) {
      this.setButtonBusy(this.verifyOtpButton, false, lang('connect.action.verifyOtp', 'Verify OTP'));
      this.setMessage(this.otpMessage, this.extractErrorMessage(error), 'danger');
      return;
    }

    if (!data.success) {
      this.setButtonBusy(this.verifyOtpButton, false, lang('connect.action.verifyOtp', 'Verify OTP'));
      this.setMessage(this.otpMessage, data.message || lang('connect.error.otpVerifyFailed', 'The verification code could not be confirmed. Please try again.'), 'danger');
      return;
    }

    window.clearInterval(this.otpTimerInterval);
    this.onConnected(data);
  }

  setButtonBusy(button, isBusy, label) {
    if (!button) {
      return;
    }
    button.disabled = isBusy;
    if (label) {
      button.textContent = label;
    }
  }

  setMessage(element, text, state) {
    if (!element) {
      return;
    }
    if (!text) {
      element.hidden = true;
      element.textContent = '';
      return;
    }
    element.textContent = text;
    element.className = 'autoalt-connect-card__message autoalt-connect-card__message--' + state;
    element.hidden = false;
  }

  async postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(payload).toString(),
    });

    return response.json();
  }

  extractErrorMessage(error) {
    return error?.message || lang('connect.error.otpVerifyFailed', 'The verification code could not be confirmed. Please try again.');
  }
}

document.querySelectorAll('[data-role="connect-panel"]').forEach((container) => new ConnectPanel(container));

export default ConnectPanel;
