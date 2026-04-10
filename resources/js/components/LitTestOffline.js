import { LitElement, html, css } from 'lit';

export class LitTestOffline extends LitElement {
    static properties = {
        notes: { type: Array }
    };

    constructor() {
        super();
        const saved = localStorage.getItem('lit-test-notes');
        this.notes = saved ? JSON.parse(saved) : [];
    }

    addNote() {
        const note = prompt('Enter a note:');
        if (note) {
            this.notes = [...this.notes, { id: Date.now(), text: note }];
            localStorage.setItem('lit-test-notes', JSON.stringify(this.notes));
        }
    }

    render() {
        return html`
            <div class="card p-3 mt-3 border-info">
                <h5>Test 4: Pure Offline Mode</h5>
                <button class="btn btn-info btn-sm text-white" @click="${this.addNote}">Add Offline Note</button>
                <ul class="mt-2">
                    ${this.notes.map(n => html`<li>${n.text}</li>`)}
                </ul>
            </div>
        `;
    }
}
customElements.define('lit-test-offline', LitTestOffline);
