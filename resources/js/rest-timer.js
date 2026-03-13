export class RestTimer {
    constructor(element, options = {}) {
        this.element = element;
        this.defaultSeconds = Number(options.defaultSeconds ?? 90);
        this.onComplete = typeof options.onComplete === 'function' ? options.onComplete : () => {};
        this.onTick = typeof options.onTick === 'function' ? options.onTick : () => {};

        this.card = element.querySelector('#ws-rest-card');
        this.timeNode = element.querySelector('#ws-rest-time');
        this.progressNode = element.querySelector('#ws-rest-progress');
        this.minusBtn = element.querySelector('#ws-rest-minus');
        this.plusBtn = element.querySelector('#ws-rest-plus');
        this.skipBtn = element.querySelector('#ws-rest-skip');

        this.radius = 52;
        this.circumference = 2 * Math.PI * this.radius;
        this.progressNode.style.strokeDasharray = `${this.circumference} ${this.circumference}`;

        this.duration = this.defaultSeconds;
        this.remainingMs = this.defaultSeconds * 1000;
        this.timerId = null;
        this.endsAt = null;

        this.minusBtn?.addEventListener('click', () => this.adjust(-15));
        this.plusBtn?.addEventListener('click', () => this.adjust(15));
        this.skipBtn?.addEventListener('click', () => this.stop(true));

        this.render();
    }

    open(seconds = this.defaultSeconds) {
        this.duration = Math.max(1, Number(seconds));
        this.remainingMs = this.duration * 1000;
        this.endsAt = Date.now() + this.remainingMs;

        this.element.hidden = false;
        this.render();
        this.start();
    }

    start() {
        this.clearTicker();
        this.timerId = window.setInterval(() => {
            this.remainingMs = Math.max(0, this.endsAt - Date.now());
            this.render();
            this.onTick(this.remainingMs);

            if (this.remainingMs <= 0) {
                this.flashCard();
                this.stop(false);
                this.onComplete();
            }
        }, 100);
    }

    adjust(deltaSeconds) {
        const deltaMs = Number(deltaSeconds) * 1000;
        this.remainingMs = Math.max(0, this.remainingMs + deltaMs);
        this.duration = Math.max(1, Math.ceil(this.remainingMs / 1000));
        this.endsAt = Date.now() + this.remainingMs;
        this.render();
    }

    stop(skipped) {
        this.clearTicker();
        this.element.hidden = true;
        if (skipped) {
            this.onComplete();
        }
    }

    clearTicker() {
        if (this.timerId !== null) {
            window.clearInterval(this.timerId);
            this.timerId = null;
        }
    }

    flashCard() {
        if (!this.card) {
            return;
        }

        this.card.classList.add('is-complete-pulse');
        window.setTimeout(() => this.card.classList.remove('is-complete-pulse'), 450);
    }

    render() {
        const totalMs = Math.max(1000, this.duration * 1000);
        const remaining = Math.max(0, this.remainingMs);
        const ratio = remaining / totalMs;

        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

        if (this.timeNode) {
            this.timeNode.textContent = formatted;
        }

        if (this.progressNode) {
            this.progressNode.style.strokeDashoffset = `${(1 - ratio) * this.circumference}`;
        }
    }
}
