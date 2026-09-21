import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

// The keyword chart is decorative (aria-hidden; the table next to it holds the
// data). Loading Recharts on first use is slow enough to make unrelated tests
// time out on a cold cache, so tests render a lightweight stand-in.
vi.mock('../components/report/details/KeywordChart', () => ({
    default: () => null,
}));

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
