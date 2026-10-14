/**
 * Directory: Statamic 6 Control Panel entry point. The Inertia identifier must match
 * `Inertia::render('directory::Index')`; the widget name must match VueComponent::render().
 */
import Index from './pages/Index.vue';
import DirectoryWidget from './components/DirectoryWidget.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('directory::Index', Index);
    Statamic.$components.register('directory-widget', DirectoryWidget);
});
