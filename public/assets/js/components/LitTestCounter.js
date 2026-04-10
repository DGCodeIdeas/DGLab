import{LitElement,html,css}from 'lit';export class LitTestCounter extends LitElement{static properties={count:{type:Number}};constructor(){super();const saved=localStorage.getItem('lit-test-count');this.count=saved?parseInt(saved):0}
increment(){this.count++;localStorage.setItem('lit-test-count',this.count)}
render(){return html`
            <div class="card p-3 mt-3">
                <h5>Test 2: Reactive State Counter</h5>
                <p>Current count: <strong>${this.count}</strong></p>
                <button class="btn btn-primary btn-sm" @click="${this.increment}">Increment</button>
                <p class="small text-muted mt-2">Value persists in localStorage.</p>
            </div>
        `}}
customElements.define('lit-test-counter',LitTestCounter)