/**
 * All client-side behaviour for the app.
 *
 * The previous version pulled Tailwind, Lucide, Chart.js and html2pdf from four
 * separate CDNs — roughly 3 MB of JavaScript, none of it version-pinned, all of
 * it a hard dependency on an outside network. Charts are now server-rendered
 * SVG and printing is a stylesheet, which leaves only these interactions.
 */

const t = (key, fallback) => document.body.dataset[key] ?? fallback;

/* -------------------------------------------------------------------------
 * Upload: file name feedback, drag and drop, submit state
 * ---------------------------------------------------------------------- */

function initUpload() {
    const input = document.querySelector('[data-file-input]');
    const zone = document.querySelector('[data-dropzone]');
    const label = document.querySelector('[data-file-label]');
    const form = document.querySelector('[data-upload-form]');

    if (!input || !zone) return;

    const describe = (file) => {
        if (!file) return;
        const megabytes = (file.size / 1024 / 1024).toFixed(2);
        label.textContent = `${file.name} — ${megabytes} MB`;
        label.classList.add('font-bold');
    };

    input.addEventListener('change', () => describe(input.files?.[0]));

    ['dragenter', 'dragover'].forEach((event) =>
        zone.addEventListener(event, (e) => {
            e.preventDefault();
            zone.classList.add('is-active');
        })
    );

    ['dragleave', 'drop'].forEach((event) =>
        zone.addEventListener(event, (e) => {
            e.preventDefault();
            zone.classList.remove('is-active');
        })
    );

    zone.addEventListener('drop', (e) => {
        const file = e.dataTransfer?.files?.[0];
        if (!file) return;

        // DataTransfer is the only way to programmatically populate a file input.
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        describe(file);
    });

    form?.addEventListener('submit', () => {
        const button = form.querySelector('[data-submit]');
        if (!button) return;
        button.disabled = true;
        button.textContent = t('labelSubmitting', 'Analysing…');
    });
}

/* -------------------------------------------------------------------------
 * Protein dialog
 *
 * <dialog> gives focus trapping, Escape handling and inertness for free, so
 * none of that has to be reimplemented — and it stays accessible.
 * ---------------------------------------------------------------------- */

function initProteinDialog() {
    const dialog = document.querySelector('[data-protein-dialog]');
    if (!dialog) return;

    const title = dialog.querySelector('[data-protein-title]');
    const body = dialog.querySelector('[data-protein-sequence]');
    const copyButton = dialog.querySelector('[data-copy]');

    document.querySelectorAll('[data-protein-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            title.textContent = trigger.dataset.recordId ?? '';
            body.textContent = trigger.dataset.protein || t('labelNoProtein', '—');
            dialog.showModal();
        });
    });

    dialog.querySelectorAll('[data-close]').forEach((button) =>
        button.addEventListener('click', () => dialog.close())
    );

    // Clicking the backdrop closes the dialog; clicking the panel does not.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    copyButton?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(body.textContent ?? '');
            const original = copyButton.textContent;
            copyButton.textContent = t('labelCopied', 'Copied');
            setTimeout(() => {
                copyButton.textContent = original;
            }, 1600);
        } catch {
            // Clipboard access can be denied; the text is selectable regardless.
        }
    });
}

/* ---------------------------------------------------------------------- */

/* -------------------------------------------------------------------------
 * Compiler: live character count, example insertion, submit state
 * ---------------------------------------------------------------------- */

function initCompiler() {
    const form = document.querySelector('[data-compile-form]');
    const input = document.querySelector('[data-compile-input]');
    if (!input) return;

    const counter = document.querySelector('[data-char-count]');
    const max = Number(input.getAttribute('maxlength')) || 2000;

    const render = () => {
        if (counter) counter.textContent = `${input.value.length} / ${max}`;
    };

    input.addEventListener('input', render);
    render();

    document.querySelectorAll('[data-example]').forEach((button) => {
        button.addEventListener('click', () => {
            // Fill rather than submit: the example is a starting point to edit,
            // not a shortcut past reading it.
            input.value = button.dataset.text ?? '';
            input.focus();
            render();
        });
    });

    form?.addEventListener('submit', () => {
        const button = form.querySelector('[data-submit]');
        if (!button) return;
        button.disabled = true;
        button.textContent = t('labelCompiling', 'Compiling…');
    });
}

/* -------------------------------------------------------------------------
 * Simulator: live slider readouts, submit state
 *
 * Enhancement only. The sliders are real range inputs with server-rendered
 * values beside them, so with JavaScript off the form still submits and still
 * says what it will run — it just stops updating the number as you drag.
 * ---------------------------------------------------------------------- */

function initSimulator() {
    const form = document.querySelector('[data-simulate-form]')
        ?? document.querySelector('[data-memory-form]');
    if (!form) return;

    const busyLabel = form.matches('[data-memory-form]')
        ? t('labelModelling', 'Modelling…')
        : t('labelSimulating', 'Simulating…');

    form.querySelectorAll('[data-slider]').forEach((slider) => {
        const output = form.querySelector(`[data-slider-output="${slider.name}"]`);
        if (!output) return;

        const scale = Number(slider.dataset.scale) || 1;
        const suffix = slider.dataset.suffix ?? '';

        const render = () => {
            output.textContent = `${Math.round(Number(slider.value) * scale)}${suffix}`;
        };

        slider.addEventListener('input', render);
        render();
    });

    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-submit]');
        if (!button) return;
        button.disabled = true;
        button.textContent = busyLabel;
    });
}

/* ---------------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', () => {
    initUpload();
    initProteinDialog();
    initCompiler();
    initSimulator();
});
