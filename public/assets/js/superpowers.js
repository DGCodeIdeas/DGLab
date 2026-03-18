/**
 * Superpowers JS Runtime
 * Handles event delegation and reactive bridge communication.
 */
document.addEventListener('DOMContentLoaded', () => {
    const registry = new Map();

    function initReactiveElements(root = document) {
        const elements = root.querySelectorAll('[s-on\\:click]');
        elements.forEach(el => {
            if (registry.has(el)) return;

            el.addEventListener('click', async (e) => {
                e.preventDefault();
                const action = el.getAttribute('s-on:click');
                const boundary = el.closest('[s-data]');

                if (!boundary) {
                    console.error('Reactive action outside of boundary:', action);
                    return;
                }

                const state = boundary.getAttribute('s-data');
                const id = boundary.getAttribute('s-id');
                // View name needs to be tracked. For Phase 7, we'll assume it's stored on the boundary.
                const view = boundary.getAttribute('s-view') || window.location.pathname;

                try {
                    const response = await fetch('/_superpowers/action', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('input[name="_token"]')?.value
                        },
                        body: JSON.stringify({ action, state, view })
                    });

                    if (response.ok) {
                        const result = await response.json();
                        // Replace the boundary content with new HTML
                        const temp = document.createElement('div');
                        temp.innerHTML = result.html;
                        const newBoundary = temp.firstElementChild;

                        if (newBoundary) {
                            boundary.replaceWith(newBoundary);
                            initReactiveElements(newBoundary);
                        }
                    }
                } catch (err) {
                    console.error('Superpowers Bridge Error:', err);
                }
            });

            registry.set(el, true);
        });
    }

    initReactiveElements();
});
