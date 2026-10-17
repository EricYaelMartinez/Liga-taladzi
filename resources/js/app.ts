import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import type { DefineComponent } from 'vue';

const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue', { eager: true });

createInertiaApp({
    title: (title) => title ? `${title} - ProLiga` : 'ProLiga',
    resolve: (name) => {
        return pages[`./pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: '#2E7D32' },
});
