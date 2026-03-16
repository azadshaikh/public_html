/**
 * Menu Editor - Toast Notifications
 */

import { escapeHtml } from './utils.js';

export class Toast {
    constructor(containerSelector = '.toast-container') {
        this.container = document.querySelector(containerSelector);
    }

    show(type, message) {
        if (!this.container || typeof bootstrap === 'undefined') {
            console.log(`[${type}] ${message}`);
            return;
        }

        const toastId = `toast-${Date.now()}`;
        const config = this.getConfig(type);

        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white ${config.bgClass} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        <i class="${config.icon} me-2"></i>
                        ${escapeHtml(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        this.container.insertAdjacentHTML('beforeend', toastHTML);

        const toastEl = document.getElementById(toastId);
        const bsToast = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: type === 'error' ? 6000 : 4000,
        });

        bsToast.show();

        toastEl.addEventListener('hidden.bs.toast', () => {
            bsToast.dispose();
            toastEl.remove();
        });
    }

    getConfig(type) {
        const configs = {
            success: { icon: 'ri-checkbox-circle-line', bgClass: 'bg-success' },
            error: { icon: 'ri-error-warning-fill', bgClass: 'bg-danger' },
            warning: { icon: 'ri-alert-line', bgClass: 'bg-warning' },
            info: { icon: 'ri-information-line', bgClass: 'bg-info' },
        };
        return configs[type] || configs.info;
    }

    success(message) {
        this.show('success', message);
    }

    error(message) {
        this.show('error', message);
    }

    warning(message) {
        this.show('warning', message);
    }

    info(message) {
        this.show('info', message);
    }
}
