import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['count'];
    static values = { url: String };

    async like() {
        const response = await fetch(this.urlValue, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) return;

        const data = await response.json();
        this.countTarget.textContent = data.likes;
        this.element.classList.add('liked');
    }
}
