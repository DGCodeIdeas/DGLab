import{LitElement,html,css}from 'lit';export class LitTestFetch extends LitElement{static properties={data:{type:Object},status:{type:String}};constructor(){super();this.data=null;this.status='loading'}
async firstUpdated(){try{const response=await fetch('/api/sample');if(!response.ok)throw new Error('Offline or Error');this.data=await response.json();this.status='success';localStorage.setItem('lit-test-cache',JSON.stringify(this.data))}catch(e){console.warn('Fetch failed, using cache',e);const cached=localStorage.getItem('lit-test-cache');if(cached){this.data=JSON.parse(cached);this.status='cached'}else{this.status='error'}}}
render(){return html`
            <div class="card p-3 mt-3">
                <h5>Test 3: API Fetch & Fallback</h5>
                ${this.status === 'loading' ? html`<p>Loading data...</p>` : ''}
                ${this.status === 'success' ? html`<p class="text-success">Data loaded from API:${this.data.message}</p>` : ''}
                ${this.status === 'cached' ? html`<p class="text-warning">Running offline!Using cached data:${this.data.message}</p>` : ''}
                ${this.status === 'error' ? html`<p class="text-danger">Failed to load data(no cache available).</p>` : ''}
            </div>
        `}}
customElements.define('lit-test-fetch',LitTestFetch)