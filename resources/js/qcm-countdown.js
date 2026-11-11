/**
 * Compte à rebours d'un passage d'examen (PRD QCM UC-12, 3a).
 *
 * Le temps restant vient du serveur à chaque rendu ; le navigateur ne fait
 * que l'afficher. À zéro, il demande au serveur de terminer le passage.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('qcmCountdown', (remainingSeconds) => ({
        end: Date.now() + remainingSeconds * 1000,
        remaining: remainingSeconds,
        timer: null,
        expired: false,

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            clearInterval(this.timer);
        },

        tick() {
            this.remaining = Math.max(0, Math.round((this.end - Date.now()) / 1000));

            if (this.remaining === 0 && !this.expired) {
                this.expired = true;
                clearInterval(this.timer);
                this.$wire.timeUp();
            }
        },

        get display() {
            const hours = Math.floor(this.remaining / 3600);
            const minutes = Math.floor((this.remaining % 3600) / 60);
            const seconds = String(this.remaining % 60).padStart(2, '0');

            return hours > 0
                ? `${hours} h ${String(minutes).padStart(2, '0')} min ${seconds} s`
                : `${minutes} min ${seconds} s`;
        },

        get warning() {
            if (this.remaining <= 60) {
                return 'Moins d\'une minute : le passage sera terminé automatiquement à la fin du compte à rebours.';
            }

            if (this.remaining <= 300) {
                return 'Moins de 5 minutes restantes.';
            }

            return '';
        },
    }));
});
