/**
 * Живые счётчики панели.
 *
 * Страницу шлюза держат открытой весь день, и статичная разметка врёт: телефон
 * мог отвалиться десять минут назад. Компонент раз в несколько секунд забирает
 * свежие цифры и подменяет только их — без перезагрузки, без потери прокрутки
 * и открытых модалок.
 */
export default function gatewayPulse(url, intervalSeconds = 15) {
    return {
        data: null,
        updatedAt: null,
        failing: false,

        init() {
            this.refresh();

            const timer = setInterval(() => {
                // Во вкладке, на которую не смотрят, опрос бессмысленен:
                // браузер всё равно душит таймеры фоновых вкладок.
                if (document.visibilityState === 'visible') {
                    this.refresh();
                }
            }, intervalSeconds * 1000);

            // Вернулись на вкладку — показываем актуальное сразу, а не через
            // интервал.
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') this.refresh();
            });

            this.$el.addEventListener('alpine:destroyed', () => clearInterval(timer));
        },

        async refresh() {
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) throw new Error(response.status);

                this.data = await response.json();
                this.updatedAt = new Date();
                this.failing = false;
            } catch (e) {
                // Сервер мог упасть — это само по себе новость, и «живой»
                // индикатор должен погаснуть, а не показывать старое как новое.
                this.failing = true;
            }
        },

        device(id) {
            return this.data?.devices?.find((d) => d.id === id) ?? null;
        },

        get ago() {
            if (!this.updatedAt) return '';

            const seconds = Math.round((Date.now() - this.updatedAt) / 1000);

            if (seconds < 5) return 'только что';
            if (seconds < 60) return `${seconds} сек назад`;

            return `${Math.floor(seconds / 60)} мин назад`;
        },
    };
}
