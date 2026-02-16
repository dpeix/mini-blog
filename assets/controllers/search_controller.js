import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'card'];

    filter() {
        const query = this.inputTarget.value.toLowerCase().trim();

        this.cardTargets.forEach((card) => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            card.classList.toggle('hidden', query !== '' && !title.includes(query));
        });
    }
}
