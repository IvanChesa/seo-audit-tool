import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

afterEach(() => {
    cleanup();
});

// jsdom gaps: modal <dialog>, scrolling and ResizeObserver (used by Recharts).
function showModal() {
    this.open = true;
}

function close() {
    this.open = false;
}

if (typeof HTMLDialogElement !== 'undefined' && !HTMLDialogElement.prototype.showModal) {
    HTMLDialogElement.prototype.showModal = showModal;
    HTMLDialogElement.prototype.close = close;
}

window.scrollTo = () => {};

if (typeof window.ResizeObserver === 'undefined') {
    window.ResizeObserver = class ResizeObserver {
        observe() {}
        unobserve() {}
        disconnect() {}
    };
}
