import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

createInertiaApp({
    // Título fijo: el <Head title> de cada página ya no llega a la pestaña.
    title: () => 'SGJ',
    pages: './Pages',
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
