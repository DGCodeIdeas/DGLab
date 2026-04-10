import { LitElement, html, css } from 'lit';
import { repeat } from 'lit/directives/repeat';
import { classMap } from 'lit/directives/class-map';
import { until } from 'lit/directives/until';

export class LitTestList extends LitElement {
    static styles = css`
        .item { padding: 5px; margin: 2px; border: 1px solid #eee; cursor: pointer; }
        .active { background-color: #e7f3ff; border-color: #0d6efd; font-weight: bold; }
    `;

    static properties = {
        items: { type: Array },
        activeId: { type: Number }
    };

    constructor() {
        super();
        this.items = [
            { id: 1, text: 'Item One' },
            { id: 2, text: 'Item Two' },
            { id: 3, text: 'Item Three' }
        ];
        this.activeId = null;
    }

    toggle(id) {
        this.activeId = this.activeId === id ? null : id;
    }

    render() {
        const delayedMessage = new Promise((resolve) => {
            setTimeout(() => resolve(html`<em>Directives loaded successfully!</em>`), 500);
        });

        return html`
            <div class="card p-3">
                <h5>Test 1: Directive-Heavy List</h5>
                <p>${until(delayedMessage, html`<span>Loading...</span>`)}</p>
                <div>
                    ${repeat(this.items, (i) => i.id, (i) => html`
                        <div class="${classMap({ item: true, active: this.activeId === i.id })}"
                             @click="${() => this.toggle(i.id)}">
                            ${i.text}
                        </div>
                    `)}
                </div>
            </div>
        `;
    }
}
customElements.define('lit-test-list', LitTestList);
