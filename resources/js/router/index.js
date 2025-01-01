import { createRouter, createWebHistory } from 'vue-router';

// Import your components
import AppRender from '../components/main/AppRender.vue';

// Define routes
const routes = [
    {
        path: '/home',
        name: 'home',
        component: AppRender,
    },
];

// Create and export the router
const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes,
});

export default router;
