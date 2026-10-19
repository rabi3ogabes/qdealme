import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

import { theme } from './components/theme';
import { searchPalette } from './components/searchPalette';
import { loadMore } from './components/loadMore';
import { flyerViewer } from './components/flyerViewer';
import { toggleAction } from './components/toggleAction';
import { toast } from './components/toast';
import { sheet } from './components/sheet';
import { logoStrip } from './components/logoStrip';

// Register self-hosted fonts with Vite so Blade can preload them with Vite::asset().
import.meta.glob(['../fonts/**']);

Alpine.plugin(focus);

Alpine.data('theme', theme);
Alpine.data('searchPalette', searchPalette);
Alpine.data('loadMore', loadMore);
Alpine.data('flyerViewer', flyerViewer);
Alpine.data('toggleAction', toggleAction);
Alpine.data('toast', toast);
Alpine.data('sheet', sheet);
Alpine.data('logoStrip', logoStrip);

window.Alpine = Alpine;
Alpine.start();
